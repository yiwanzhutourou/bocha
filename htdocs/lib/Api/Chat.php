<?php
/**
 * Created by Cui Yi
 * 2017/8/2
 */

namespace Api;

use Graph\Graph;
use Graph\MChat;
use Graph\MChatMessage;
use Graph\MUser;

// TODO 删除/更新未读状态/chat页分页

class Chat extends ApiBase {

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

		// 这里暂时不做分页了
		$chatUsers = $query->query('', 'ORDER BY timestamp DESC');

		if ($chatUsers !== false) {
			$chatList = array_map(
				function ($chat) {
					/** @var MChat $chat */
					$selfId = $chat->user1;
					$otherId = $chat->user2;
					$isSend = ($selfId === $chat->msgSender);
					/** @var MUser $otherUser */
					$otherUser = Graph::findUserById($otherId);

					switch ($chat->msgType) {
						case MSG_TYPE_TEXT:
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
						'user'      => [
							'id'       => $otherUser->id,
							'nickname' => $otherUser->nickname,
							'avatar'   => $otherUser->avatar,
						],
						'message'   => $message,
						'timeStamp' => $chat->timestamp
					];
				},
				$chatUsers
			);
			return $chatList;
		} else {
			return [];
		}
	}

	public function start($otherId) {
		$this->checkAuth();

		// check user exist
		/** @var MUser $otherUser */
		$otherUser = Graph::findUserById($otherId);
		if ($otherUser === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND , '用户不存在~');
		}

		$self = \Visitor::instance()->getUser();
		$selfId = $self->id;

		$query = new MChatMessage();
		$queryString = "((user_1 = {$selfId} and user_2 = {$otherId})"
			. " or (user_1 = {$otherId} and user_2 = {$selfId}))";
		$list = $query->query($queryString, 'ORDER BY timestamp ASC'); // LIMIT 0,5

		if ($list !== false) {
			$messages = array_map(function($message) {
				return $this->createMessage($message);
			}, $list);
		} else {
			$messages = [];
		}
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
			default:
				return [
					'type' => 'unknown',
					'from' => $message->user1,
					'to'   => $message->user2,
				];
		}
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