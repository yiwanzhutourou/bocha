<?php
/**
 * Created by Cui Yi
 * 2017/8/19
 */

namespace Api;

use Graph\Graph;
use Graph\MBook;
use Graph\MCard;
use Graph\MCardApproval;
use Graph\MCardPulp;
use Graph\MDiscoverFlow;
use Graph\MUser;
use Graph\MUserBook;

define ('ACTIVITY_CARD_ID', 1);
define ('ACTIVITY_CARD_TITLE', '');
define ('ACTIVITY_CARD_CONTENT', "参与方式：在有读书房小程序内发布读书卡片\n\n流程：在有读书房小程序内注册，填写个人书房信息，添加图书，发布读书卡片，转发邀请朋友们点赞，有读书房将在 10月9日 通过消息收集你的邮寄信息\n\n规则：10月1-8日 发布的有效卡片！（有效卡片：原创内容，关联图书，上传图片）点赞数前 50 的朋友们，我们将精选一本“只给你”的书；点赞数最高者，可获 Kindle 一部（所有解释权归有读书房所有）");
define('ACTIVITY_CARD_PIC', 'http://othb16dht.bkt.clouddn.com/WechatIMG3886.jpeg');
define('ACTIVITY_CARD_BANNER_PIC', 'http://othb16dht.bkt.clouddn.com/zhongqiu.jpeg');

class Card extends ApiBase {

	// --------- 自己的读书卡片相关的接口,需要登录

	public function insert($content, $title, $picUrl, $bookIsbn = '') {
		\Visitor::instance()->checkAuth();

		if (!empty($bookIsbn)) {
			// check book in Douban
			$url = "https://api.douban.com/v2/book/{$bookIsbn}";
			$response = file_get_contents($url);

			$doubanBook = json_decode($response);
			if ($doubanBook === null || empty($doubanBook->id)) {
				throw new Exception(Exception::RESOURCE_NOT_FOUND, '无法获取图书信息');
			}

			$book = new MBook();
			$book->updateBook($doubanBook);
		}

		$userId = \Visitor::instance()->getUserId();

		if (!empty($picUrl)) {
			// 鉴黄
			$url = $picUrl . '?pulp';
			$response = file_get_contents($url);
			$pulp = json_decode($response);
			// 没解出数据认为是正常的
			if (empty($pulp)) {
				$picIsNormal = true;
			} else if ($pulp->code === 0
					   && $pulp->pulp->label === 2) {
				$picIsNormal = true;
			} else {
				$picIsNormal = false;
			}

			$query = new MCardPulp();
			$query->userId = $userId;
			$query->title = $title;
			$query->content = $content;
			$query->picUrl = $picUrl;
			$query->createTime = strtotime('now');
			$query->pulpRate = empty($pulp) ? -1 : $pulp->pulp->rate;
			$query->pulpLabel = empty($pulp) ? -1 : $pulp->pulp->label;
			$query->pulpReview = empty($pulp) ? 'empty' : $pulp->pulp->review;
			$query->insert();

			if ($picIsNormal === false) {
				throw new Exception(Exception::RESOURCE_IS_PULP, '你的图片不符合规范，不可以在有读书房使用');
			}
		}

		$content = Graph::escape($content);
		$title = Graph::escape($title);

		$query = new MCard();
		$query->userId = $userId;
		$query->title = $title;
		$query->content = $content;
		$query->status = CARD_STATUS_NORMAL;

		// 先简单防一下
		if ($query->findOne() !== false) {
			throw new Exception(Exception::RESOURCE_IS_PULP, '你已经发送过类似读书卡片了~');
		}

		$query->picUrl = $picUrl;
		$query->bookIsbn = $bookIsbn;
		$query->createTime = strtotime('now');
		$query->readCount = 0;

		$insertId = $query->insert();

		if ($insertId > 0) {
			$query->id = $insertId;
			// 将卡片加入发现流的待审核状态
			Graph::addNewCardToDiscoverFlow($query);
		}

		return $insertId;
	}

	public function modify($cardId, $content, $title, $picUrl, $picModified) {
		\Visitor::instance()->checkAuth();

		$userId = \Visitor::instance()->getUserId();
		if (!empty($picUrl)) {
			// 鉴黄
			$url = $picUrl . '?pulp';
			$response = file_get_contents($url);
			$pulp = json_decode($response);
			// 没解出数据认为是正常的
			if (empty($pulp)) {
				$picIsNormal = true;
			} else if ($pulp->code === 0
				&& $pulp->pulp->label === 2) {
				$picIsNormal = true;
			} else {
				$picIsNormal = false;
			}

			$query = new MCardPulp();
			$query->userId = $userId;
			$query->title = $title;
			$query->content = $content;
			$query->picUrl = $picUrl;
			$query->createTime = strtotime('now');
			$query->pulpRate = empty($pulp) ? -1 : $pulp->pulp->rate;
			$query->pulpLabel = empty($pulp) ? -1 : $pulp->pulp->label;
			$query->pulpReview = empty($pulp) ? 'empty' : $pulp->pulp->review;
			$query->insert();

			if ($picIsNormal === false) {
				throw new Exception(Exception::RESOURCE_IS_PULP, '你的图片可能涉及色情，不可以在有读书房发布');
			}
		}

		$content = Graph::escape($content);
		$title = Graph::escape($title);

		$query = new MCard();
		$query->id = $cardId;

		/** @var MCard $card */
		$card = $query->findOne();
		if ($card === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '卡片不存在');
		}

		$card->title = $title;
		$card->content = $content;
		if ($picModified) {
			$card->picUrl = $picUrl;
		}
		$card->update();

		// 将卡片重新加入发现流的待审核状态
		Graph::resetCardStatusInDiscoverFlow($cardId, $userId);

		return 'ok';
	}

	public function delete($cardId) {
		\Visitor::instance()->checkAuth();

		$userId = \Visitor::instance()->getUserId();

		$query = new MCard();
		$query->status = CARD_STATUS_DELETED;
		$query->modify("_id = {$cardId} and user_id = {$userId}", '');

		// 将卡片从发现流中删除
		Graph::removeCardFromDiscoverFlow($cardId, $userId);

		return 'ok';
	}

	public function approve($cardId) {
		\Visitor::instance()->checkAuth();

		/** @var MCard $card */
		$card = Graph::getCardById($cardId);

		if ($card === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '读书卡片不存在');
		}

		$user = \Visitor::instance()->getUser();
		$userId = $user->id;

		$query = new MCardApproval();
		$query->cardId = $cardId;
		$query->userId = $userId;

		/** @var MCardApproval $approval */
		$approval = $query->findOne();

		// 已经点过赞的就让他去吧
		if ($approval === false) {
			$query->userAvatar = $user->avatar;
			$query->createTime = strtotime('now');
			$query->insert();

			$extra = [
				'router' => 'card',
				'extra'  => $cardId,
			];

			if (intval($userId) !== intval($card->userId)) {
				// 给被点赞的同志发一条系统消息
				Graph::sendSystemMessage(BOCHA_SYSTEM_USER_ID, $card->userId,
										 "书友 {$user->nickname} 给你的读书卡片 {$card->title} 点了一个赞~",
										 json_stringify($extra));
			}
		}

		return [
			'result' => 'ok',
			'id'     => $user->id,
			'avatar' => $user->avatar,
		];
	}

	public function unapprove($cardId) {
		\Visitor::instance()->checkAuth();

		$query = new MCard();
		$query->id = $cardId;
		/** @var MCard $card */
		$card = $query->findOne();

		if ($card === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '读书卡片不存在');
		}

		$user = \Visitor::instance()->getUser();
		$userId = $user->id;

		$query = new MCardApproval();
		$query->cardId = $cardId;
		$query->userId = $userId;

		/** @var MCardApproval $approval */
		$approval = $query->findOne();

		// 已经点过赞的就让他去吧
		if ($approval !== false) {
			$approval->delete();
		}

		return [
			'result' => 'ok',
			'id'     => $user->id,
			'avatar' => $user->avatar,
		];
	}

	public function getMyCards() {
		\Visitor::instance()->checkAuth();

		$userId = \Visitor::instance()->getUserId();

		$query = new MCard();
		$query->userId = $userId;

		$cardList = $query->query("status = '0'", 'ORDER BY create_time DESC');

		return array_map(function($card) {
			/** @var MCard $card */
			return [
				'id'         => $card->id,
				'title'      => $card->title,
				'content'    => mb_substr($card->content, 0, 48, 'utf-8'),
				'picUrl'     => getListThumbnailUrl($card->picUrl),
				'createTime' => $card->createTime,
				'readCount'     => intval($card->readCount),
				'approvalCount' => Graph::getCardApprovalCount($card->id),
			];
		}, $cardList);
	}

	// --------- 别人的读书卡片相关的接口,不需要登录

	public function getCardById($cardId) {

		// 活动 card
		if (intval($cardId) === ACTIVITY_CARD_ID) {
			return $this->createActivityDetail();
		}

		$query = new MCard();
		$query->id = $cardId;

		/** @var MCard $card */
		$card = $query->findOne();
		if ($card === false || $card->status === CARD_STATUS_DELETED) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '读书卡片已被删除');
		} else {
			/** @var MUser $cardUser */
			$cardUser = Graph::findUserById($card->userId);

			$user = \Visitor::instance()->getUser();
			if ($user != null) {
				$hasApproved = Graph::hasApproved($cardId, $user->id);
			} else {
				$hasApproved = false;
			}

			/** @var MBook $book */
			$book = Graph::findBook($card->bookIsbn);
			$bookData = $book === false ? null : [
				'isbn'      => $book->isbn,
				'title'     => $book->title,
				'author'    => self::parseAuthor($book->author),
				'cover'     => $book->cover,
				'publisher' => $book->publisher,
			];

			// approval list
			$approvalList = array_map(function($approval) {
				/** @var MCardApproval $approval */
				return [
					'id'     => $approval->userId,
					'avatar' => $approval->userAvatar,
				];
			}, Graph::getCardApprovals($cardId));
			$approvalCount = Graph::getCardApprovalCount($cardId);

			// 增加一次浏览
			$query->update('read_count = read_count + 1');

			return [
				'id'            => $card->id,
				'user'          => [
					'id'       => $cardUser->id,
					'nickname' => $cardUser->nickname,
					'avatar'   => $cardUser->avatar,
				],
				'title'         => $card->title,
				'content'       => $card->content,
				'picUrl'        => getOriginalImgUrl($card->picUrl),
				'book'          => $bookData,
				'createTime'    => $card->createTime,
				'isMe'          => \Visitor::instance()->isMe($card->userId),
				'hasApproved'   => $hasApproved,
				'approvalList'  => $approvalList,
				'approvalCount' => $approvalCount,
				'readCount'     => intval($card->readCount),
			];
		}
	}

	public function getUserCards($userId) {
		$query = new MCard();
		$query->userId = $userId;

		$cardList = $query->query("status = '0'", 'ORDER BY create_time DESC');

		return array_map(function($card) {
			/** @var MCard $card */
			return [
				'id'         => $card->id,
				'title'      => $card->title,
				'content'    => mb_substr($card->content, 0, 48, 'utf-8'),
				'picUrl'     => getListThumbnailUrl($card->picUrl),
				'createTime' => $card->createTime,
				'readCount'     => intval($card->readCount),
				'approvalCount' => Graph::getCardApprovalCount($card->id),
			];
		}, $cardList);
	}

	public function getBookCards($isbn, $page = 0, $count = 5) {

		$offset = $page * $count;
		$query = new MCard();
		$cardList = $query->query("status = '0' and book_isbn = '{$isbn}'",
				"ORDER BY create_time DESC LIMIT {$offset},{$count}");

		$resultList = array_map(function($card) {
			/** @var MUser $user */
			$user = Graph::findUserById($card->userId);
			if ($user === false) {
				return false;
			}

			/** @var MCard $card */
			return [
				'id'         => $card->id,
				'user'       => [
					'id'       => $user->id,
					'nickname' => $user->nickname,
					'avatar'   => $user->avatar,
				],
				'title'      => $card->title,
				'content'    => mb_substr($card->content, 0, 48, 'utf-8'),
				'picUrl'     => getListThumbnailUrl($card->picUrl),
				'createTime' => $card->createTime,
				'readCount'     => intval($card->readCount),
				'approvalCount' => Graph::getCardApprovalCount($card->id),
			];
		}, $cardList);

		return array_filter($resultList, function($item) {
			return $item !== false;
		});
	}

	/*
	 * 第一版出去最简单的卡片流:读书卡片和最新图书混排的流
	 * $cursor 卡片列表的时间戳
	 * $bookCursor 书列表的时间戳
	 * $isUp 下拉或者上拉刷新
	 */
	public function getDiscoverPageData($cursor, $bookCursor, $isTop) {
		$cursor = intval($cursor);
		if ($cursor < 0) {
			$cursor = 0;
		}

		if ($isTop) {
			// 表示下拉刷新,数据要放在顶部,直接取最新的数据发下去
			$condition = "status = '1'"; // 状态 1 表示审核通过的内容
		} else {
			$condition = "status = '1' and create_time < {$cursor}";
		}

		// 一次请求发 20 条数据
		$query = new MDiscoverFlow();
		$discoverList = $query->query($condition,
								  'ORDER BY create_time DESC LIMIT 0,20');

		$resultList = [];
		foreach ($discoverList as $item) {
			/** @var MDiscoverFlow $item */
			/** @var MUser $user */
			$user = Graph::findUserById($item->userId);
			if ($item->type === 'card') {
				/** @var MCard $card */
				$card = Graph::findCardById($item->contentId);
				if ($card !== false && $card->status == CARD_STATUS_NORMAL) {
					$resultList[] = [
						'type' => 'card',
						'data' => [
							'id'            => $card->id,
							'user'          => [
								'id'       => $user->id,
								'nickname' => $user->nickname,
								'avatar'   => $user->avatar,
							],
							'title'         => $card->title,
							'content'       => mb_substr($card->content, 0, 48, 'utf-8'),
							'picUrl'        => getListThumbnailUrl($card->picUrl),
							'createTime'    => $card->createTime,
							'readCount'     => intval($card->readCount),
							'approvalCount' => Graph::getCardApprovalCount($card->id),
						],
					];
				}
			} else if ($item->type === 'book') {
				/** @var MUserBook $userBook */
				$userBook = Graph::findUserBook($item->contentId, $item->userId);
				if ($userBook !== false) {
					/** @var MBook $book */
					$book = Graph::findBook($item->contentId);
					$resultList[] = [
						'type' => 'book',
						'data' => [
							'isbn'         => $book->isbn,
							'user'       => [
								'id'       => $user->id,
								'nickname' => $user->nickname,
								'avatar'   => $user->avatar,
							],
							'title'      => $book->title,
							'author'     => self::parseAuthor($book->author),
							'cover'      => $book->cover,
							'publisher'  => $book->publisher,
							'summary'    => $book->summary,
							'createTime' => $userBook->createTime,
						],
					];
				}
			}
		}

		$topCursor = self::getCursor($resultList, true);
		$bottomCursor = self::getCursor($resultList, false);

		$banners = [];
		if ($isTop) {
			// 活动置顶
			$banners[] =  $this->createNewBookItem('27068817');
			$banners[] = $this->createCardItem('54');
			//$banners[] =  $this->createActivityItem();
			$banners[] =  $this->createNewBookItem('27069925');
		}

		return [
			'banner'           => $banners,
			'list'             => $resultList,
			'topCursor'        => $topCursor,
			'bottomCursor'     => $bottomCursor,
			'bookTopCursor'    => -1, // 结构改了，暂时没有用了
			'bookBottomCursor' => -1,
			'showPost'         => true,
		];
	}

	// --------- utils

	private static function parseAuthor($authorString) {
		if (empty($authorString)) {
			return '';
		}

		$authors = json_decode($authorString);
		$result = '';
		foreach ($authors as $author) {
			$result .= ($author . ' ');
		}
		if (strlen($result) > 0) {
			$result = substr($result, 0, strlen($result) - 1);
		}
		return $result;
	}

	private static function getCursor($list, $isTop) {
		if (count($list) > 0) {
			$index = $isTop ? 0 : (count($list) - 1);
			$item = array_values($list)[$index];
			return $item['data']['createTime'];
		}
		return -1;
	}

	private function createActivityItem() {
		/** @var MCard $card */
		$card = Graph::findCardById(ACTIVITY_CARD_ID);
		return [
			'type' => 'card',
			'data' => [
				'id'            => ACTIVITY_CARD_ID,
				'title'         => ACTIVITY_CARD_TITLE,
				'picUrl'        => ACTIVITY_CARD_BANNER_PIC,
			],
		];
	}

	private function createCardItem($id) {
		/** @var MCard $card */
		$card = Graph::findCardById($id);
		return [
			'type' => 'card',
			'data' => [
				'id'            => $id,
				'title'         => $card->title,
				'picUrl'        => $card->picUrl,
			],
		];
	}

	private function createNewBookItem($isbn) {

		$url = "https://api.douban.com/v2/book/{$isbn}";
		$response = file_get_contents($url);
		$doubanBook = json_decode($response);
		if ($doubanBook === null || empty($doubanBook->id)) {
			return false;
		}

		/** @var MBook $book */
		$book = Graph::findBook($isbn);
		
		if ($book === false) {
			$book = new MBook();
			$book->updateBook($doubanBook);
		}
		$bookPic = $doubanBook->images->large;
		return [
			'type' => 'book',
			'data' => [
				'id'            => $book->isbn,
				'title'         => "新书推荐: {$book->title}",
				'picUrl'        => $bookPic,
			],
		];
	}

	private function createActivityDetail() {
		/** @var MCard $card */
		$card = Graph::findCardById(ACTIVITY_CARD_ID);

		if ($card === false) {
			return [];
		}

		$user = \Visitor::instance()->getUser();
		if ($user != null) {
			$hasApproved = Graph::hasApproved(ACTIVITY_CARD_ID, $user->id);
		} else {
			$hasApproved = false;
		}

		// approval list
		$approvalList = array_map(function($approval) {
			/** @var MCardApproval $approval */
			return [
				'id'     => $approval->userId,
				'avatar' => $approval->userAvatar,
			];
		}, Graph::getCardApprovals(ACTIVITY_CARD_ID));
		$approvalCount = Graph::getCardApprovalCount(ACTIVITY_CARD_ID);

		// 增加一次浏览
		$card->update('read_count = read_count + 1');

		return [
			'id'            => ACTIVITY_CARD_ID,
			'user'          => [
				'id'       => BOCHA_ACTIVITY_USER_ID,
				'nickname' => '有读书房',
				'avatar'   => 'http://othb16dht.bkt.clouddn.com/Fm3qYpsmNFGRDbWeTOQDRDfiJz9l?imageView2/1/w/640/h/640/format/jpg/q/75|imageslim',
			],
			'title'         => ACTIVITY_CARD_TITLE,
			'content'       => ACTIVITY_CARD_CONTENT,
			'picUrl'        => getOriginalImgUrl(ACTIVITY_CARD_PIC),
			'book'          => null,
			'createTime'    => strtotime('2017-10-1'),
			'isMe'          => false,
			'hasApproved'   => $hasApproved,
			'approvalList'  => $approvalList,
			'approvalCount' => $approvalCount,
			'readCount'     => intval($card->readCount),
		];
	}
}
