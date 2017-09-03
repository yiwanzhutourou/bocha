<?php
/**
 * Created by Cui Yi
 * 2017/8/2
 */

namespace Api;

use Graph\Graph;
use Graph\MBook;
use Graph\MBorrowHistory;
use Graph\MChat;
use Graph\MChatMessage;
use Graph\MUser;

class Chat extends ApiBase {

	/*
	 * 删除与另一个用户之间的所有消息,目前暂不支持某一条单独删除
	 * start()和getChatList()两个接口会下发获取到数据的时间戳,
	 * 删除时把这个时间戳作为参数传到服务端,服务端会删除这个时间戳之前的消息,
	 * 这样能保证删除的都是用户已经拉取过的数据,而不会误删新发的而用户还没有看到的消息
	 */
	public function delete($otherId, $timestamp) {
		$this->checkAuth();

		if (intval($otherId) !== BOCHA_SYSTEM_USER_ID) {
			// check user exist
			/** @var MUser $otherUser */
			$otherUser = Graph::findUserById($otherId);
			if ($otherUser === false) {
				throw new Exception(Exception::RESOURCE_NOT_FOUND , '用户不存在~');
			}
		}

		$selfId = \Visitor::instance()->getUser()->id;
		Graph::deleteChatMessage($selfId, $otherId, $timestamp);
		return 'ok';
	}

	public function borrowBook($toUser, $isbn, $message) {
		$this->checkAuth();

		// check user exist
		/** @var MUser $user */
		$user = Graph::findUserById($toUser);
		if ($user === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND , '用户不存在~');
		}

		$selfId = \Visitor::instance()->getUser()->id;
		if ($toUser === $selfId) {
			throw new Exception(Exception::BAD_REQUEST , '不可以借自己的书哦~');
		}

		// check book exist
		/** @var MBook $book */
		$book = Graph::findBook($isbn);
		if ($book === false) {
			throw new Exception(Exception::WEIXIN_AUTH_FAILED, '无法获取图书信息');
		}

		$history = new MBorrowHistory();
		$history->from = $selfId;
		$history->to = $toUser;
		/** @var MBorrowHistory $one */
		$list = $history->find();
		if (!empty($list)) {
			$one = current($list);
		}
//		if (isset($one) && $one->date === date('Y-m-d')) {
//			throw new Exception(Exception::REQUEST_TOO_MUCH, '你今天已经在他的书房里借阅了一本书~');
//		}

		// 这个当时为什么只存了个日期字符串,算了将错就错吧
		$date = date('Y-m-d');

		$history->bookIsbn = $book->isbn;
		$history->bookTitle = $book->title;
		$history->bookCover = $book->cover;
		$history->date = date('Y-m-d');
		$history->formId = '';
		$history->requestStatus = 0;
		$history->insert();

		// 插一条消息到聊天记录
		$requestExtra = [
			'isbn'  => $book->isbn,
			'title' => $book->title,
			'cover' => $book->cover,
			'date'  => $date,
		];
		Graph::sendRequest($selfId, $toUser, json_stringify($requestExtra));

		// 最新的接口借书会带一条消息,直接当做发送了一条普通消息
		if (!empty($message)) {
			Graph::sendMessage($selfId, $toUser, $message);
		}

		// 发通知短信
		/** @var MUser $sendSmsUser */
		$sendSmsUser = Graph::findUserById($toUser);
		if ($sendSmsUser !== false && !empty($sendSmsUser->mobile)) {
			sendBorrowBookSms(
				$sendSmsUser->mobile, \Visitor::instance()->getUser()->nickname, $book->title);
		}

		return 'ok';
	}

	public function sendMessage($otherId, $message) {
		$this->checkAuth();

		// check user exist
		/** @var MUser $otherUser */
		$otherUser = Graph::findUserById($otherId);
		if ($otherUser === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND , '用户不存在~');
		}

		$selfId = \Visitor::instance()->getUser()->id;
		Graph::sendMessage($selfId, $otherId, $message);

		return 'ok';
	}

	public function sendContact($otherId) {
		$this->checkAuth();

		// check user exist
		/** @var MUser $otherUser */
		$otherUser = Graph::findUserById($otherId);
		if ($otherUser === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND , '用户不存在~');
		}

		$selfUser = \Visitor::instance()->getUser();
		$selfId = $selfUser->id;
		
		$hasContact = false;
		$contactJson = $selfUser->contact;
		$contact = json_decode($contactJson);
		if (isset($contact->name) && isset($contact->contact)) {
			if (in_array($contact->name, ['微信', 'QQ', '邮箱'])
				&& !empty($contact->contact)) {
				$hasContact = true;
			}
		}

		if (!$hasContact) {
			return 'no';
		}
		
		Graph::sendContact($selfId, $otherId, $contactJson);
		return $contactJson;
	}

	public function getChatList() {
		$this->checkAuth();

		$self = \Visitor::instance()->getUser();
		$query = new MChat();
		$query->user1 = $self->id;
		$query->status = MSG_STATUS_NORMAL; // 删除的消息不下发

		// 这里暂时不做分页了
		$chatUsers = $query->query("status = '0'", 'ORDER BY timestamp DESC');

		if ($chatUsers !== false) {
			$chatList = array_map(
				function ($chat) {
					/** @var MChat $chat */
					$selfId = $chat->user1;
					$otherId = $chat->user2;
					$isSend = ($selfId === $chat->msgSender);
					/** @var MUser $otherUser */
					if (intval($otherId) === BOCHA_SYSTEM_USER_ID) {
						$otherUser = $this->createSystemUser();
					} else {
						$otherUser = Graph::findUserById($otherId);
					}
					if ($otherUser === false) {
						return false;
					}

					switch ($chat->msgType) {
						case MSG_TYPE_TEXT:
						case MSG_TYPE_SYSTEM:
							$message = $chat->msgContent;
							break;
						case MSG_TYPE_BORROW:
							$extra = json_decode($chat->extra);
							if ($isSend) {
								$message = "你想要借阅{$otherUser->nickname}的《{$extra->title}》";
							} else {
								$message = "{$otherUser->nickname}想要借阅你的《{$extra->title}》";
							}
							break;
						case MSG_TYPE_CONTACT:
							$extra = json_decode($chat->extra);
							if ($isSend) {
								$message = "你向{$otherUser->nickname}发送了{$extra->name}";
							} else {
								$message = "{$otherUser->nickname}向你发送了{$extra->name}";
							}
							break;
						default:
							$message = '';
					}
					return [
						'user'        => [
							'id'       => $otherUser->id,
							'nickname' => $otherUser->nickname,
							'avatar'   => $otherUser->avatar,
						],
						'message'     => $message,
						'timeStamp'   => $chat->timestamp,
						'unreadCount' => $chat->unreadCount,
					];
				},
				$chatUsers
			);

			$resultList = array_filter($chatList, function($item) {
				return $item !== false;
			});

			for ($i = count($resultList) - 1; $i >= 0; $i--) {
				$userId = array_values($resultList)[$i]['user']['id'];
				if (intval($userId)
						   === BOCHA_SYSTEM_USER_ID) {
					$tmp = $resultList[$i];
					unset($resultList[$i]);
					array_unshift($resultList, $tmp);
					break;
				}
			}

			return [
				'messages'  => $resultList,
				'timestamp' => strtotime('now')
			];
		} else {
			return [
				'timestamp' => 0,
				'messages'  => []
			];
		}
	}

	public function getNew($otherId, $timestamp) {
		$this->checkAuth();

		if (intval($otherId) !== BOCHA_SYSTEM_USER_ID) {
			// check user exist
			/** @var MUser $otherUser */
			$otherUser = Graph::findUserById($otherId);
			if ($otherUser === false) {
				throw new Exception(Exception::RESOURCE_NOT_FOUND , '用户不存在~');
			}
		}

		$self = \Visitor::instance()->getUser();
		$selfId = $self->id;

		$query = new MChatMessage();
		$queryString = "(((user_1 = {$selfId} and user_2 = {$otherId} and status_1 = 0)"
					   . " or (user_1 = {$otherId} and user_2 = {$selfId} and status_2 = 0))"
					   . "and (timestamp > {$timestamp}))";
		$list = $query->query($queryString,
							  "ORDER BY timestamp DESC");

		$messages = [];
		if ($list !== false) {
			$values = array_values($list);
			for ($i = count($list) - 1; $i >= 0; $i--) {
				$messages[] = $this->createMessage($values[$i]);
			}
		}

		return $messages;
	}

	public function start($otherId, $count = 15, $page = 0) {
		$this->checkAuth();

		// check user exist
		/** @var MUser $otherUser */
		if (intval($otherId) === BOCHA_SYSTEM_USER_ID) {
			$otherUser = $this->createSystemUser();
		} else {
			$otherUser = Graph::findUserById($otherId);
		}
		if ($otherUser === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND , '用户不存在~');
		}

		$self = \Visitor::instance()->getUser();
		$selfId = $self->id;

		$offset = $page * $count;

		$query = new MChatMessage();
		$queryString = "((user_1 = {$selfId} and user_2 = {$otherId} and status_1 = 0)"
			. " or (user_1 = {$otherId} and user_2 = {$selfId} and status_2 = 0))";
		$list = $query->query($queryString,
							  "ORDER BY timestamp DESC LIMIT {$offset},{$count}");

		$messages = [];
		if ($list !== false) {
			$values = array_values($list);
			for ($i = count($list) - 1; $i >= 0; $i--) {
				$messages[] = $this->createMessage($values[$i]);
			}
		}

		if (intval($page) === 0 && intval($otherId) !== BOCHA_SYSTEM_USER_ID) {
			// 所有聊天开始默认推一条hint
			$messages[] = $this->createFakeMessage();
		}

		// clear unread count
		Graph::clearUnread($selfId, $otherId);

		return [
			'self'  => [
				'id'       => $self->id,
				'nickname' => $self->nickname,
				'avatar'   => $self->avatar,
			],
			'other' => [
				'id'       => $otherUser->id,
				'nickname' => $otherUser->nickname,
				'avatar'   => $otherUser->avatar,
			],
			'messages'  => $messages,
			'timestamp' => strtotime('now')
		];
	}

	private function createFakeMessage() {
		return [
			'type'      => 'fake_hint',
			'from'      => '',
			'to'        => '',
			'content'   => '提示：有读书房的留言并不是及时聊天，你可以尝试点击刷新来获取更新的消息',
			'timeStamp' => '',
		];
	}

	private function createMessage($message) {
		/** @var MChatMessage $message */
		switch ($message->msgType) {
			case MSG_TYPE_TEXT:
				return [
					'type'      => 'message',
					'from'      => $message->user1,
					'to'        => $message->user2,
					'content'   => $message->msgContent,
					'timeStamp' => $message->timestamp,
				];
			case MSG_TYPE_BORROW:
				$extra = json_decode($message->extra);
				return [
					'type'      => 'request',
					'from'      => $message->user1,
					'to'        => $message->user2,
					'timeStamp' => $message->timestamp,
					'extra'     => [
						'isbn'  => $extra->isbn,
						'title' => $extra->title,
						'cover' => $extra->cover,
						'date' => $extra->date,
					],
				];
			case MSG_TYPE_CONTACT:
				$extra = json_decode($message->extra);
				return [
					'type'      => 'contact',
					'from'      => $message->user1,
					'to'        => $message->user2,
					'timeStamp' => $message->timestamp,
					'extra'     => [
						'name'    => $extra->name,
						'contact' => $extra->contact,
					],
				];
			case MSG_TYPE_SYSTEM:
				$extra = json_decode($message->extra);
				return [
					'type'      => 'system',
					'from'      => $message->user1,
					'to'        => $message->user2,
					'content'   => $message->msgContent,
					'timeStamp' => $message->timestamp,
					'extra'     => [
						'router' => $extra->router,
						'extra'  => $extra->extra,
					],
				];
			default:
				return [
					'type' => 'unknown',
					'from' => $message->user1,
					'to'   => $message->user2,
				];
		}
	}

	private function createSystemUser() {
		$systemUser = new MUser();
		$systemUser->id = BOCHA_SYSTEM_USER_ID;
		$systemUser->nickname = '有读书房';
		$systemUser->avatar = 'http://othb16dht.bkt.clouddn.com/Fm3qYpsmNFGRDbWeTOQDRDfiJz9l?imageView2/1/w/640/h/640/format/jpg/q/75|imageslim';
		return $systemUser;
	}

	private function checkAuth($skipMobile = false) {
		if (!\Visitor::instance()->isLogin())
			throw new Exception(Exception::AUTH_FAILED, '未登录');
		if (!$skipMobile) {
			if (!\Visitor::instance()->hasMobile())
				throw new Exception(Exception::AUTH_FAILED_NO_MOBILE, '未绑定手机号');
		}
	}
}