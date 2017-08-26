<?php
/**
 * Created by Cui Yi
 * 2017/8/19
 */

namespace Api;

use Graph\Graph;
use Graph\MBook;
use Graph\MCard;
use Graph\MUser;

class Card extends ApiBase {

	// --------- 自己的读书卡片相关的接口,需要登录

	public function insert($content, $title = '', $picUrl = '', $bookIsbn = '') {
		\Visitor::instance()->checkAuth();

		$userId = \Visitor::instance()->getUserId();

		$content = Graph::escape($content);
		$title = Graph::escape($title);

		$query = new MCard();
		$query->userId = $userId;
		$query->title = $title;
		$query->content = $content;
		$query->picUrl = $picUrl;
		$query->bookIsbn = $bookIsbn;
		$query->createTime = strtotime('now');
		$query->status = CARD_STATUS_NORMAL;

		$insertId = $query->insert();
		return $insertId;
	}

	public function modify($cardId, $content, $title = '', $picUrl = '') {
		\Visitor::instance()->checkAuth();

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
		if (count($picUrl) > 0) {
			$card->picUrl = $picUrl;
		}
		$card->update();

		return 'ok';
	}

	public function delete($cardId) {
		\Visitor::instance()->checkAuth();

		$userId = \Visitor::instance()->getUserId();

		$query = new MCard();
		$query->status = CARD_STATUS_DELETED;
		$query->modify("_id = {$cardId} and user_id = {$userId}", '');
		return 'ok';
	}

	public function getMyCards() {
		\Visitor::instance()->checkAuth();

		$userId = \Visitor::instance()->getUserId();

		$query = new MCard();
		$query->userId = $userId;

		$cardList = $query->query("status = '0'", 'ORDER BY create_time DESC');

		return array_map(function($card) {
			/** @var MBook $book */
			$book = Graph::findBook($card->bookIsbn);

			/** @var MCard $card */
			return [
				'id'         => $card->id,
				'title'      => $card->title,
				'content'    => mb_substr($card->content, 0, 48, 'utf-8'),
				'picUrl'     => getListThumbnailUrl($card->picUrl),
				'bookTitle'  => $book->title,
				'createTime' => $card->createTime,
			];
		}, $cardList);
	}

	// --------- 别人的读书卡片相关的接口,不需要登录

	public function getCardById($cardId) {
		$query = new MCard();
		$query->id = $cardId;

		/** @var MCard $card */
		$card = $query->findOne();
		if ($card === false) {
			return '';
		} else {
			/** @var MUser $user */
			$user = Graph::findUserById($card->userId);

			/** @var MBook $book */
			$book = Graph::findBook($card->bookIsbn);
			$bookData = $book === false ? null : [
				'isbn'      => $book->isbn,
				'title'     => $book->title,
				'author'    => self::parseAuthor($book->author),
				'cover'     => $book->cover,
				'publisher' => $book->publisher,
			];
			return [
				'id'         => $card->id,
				'user'        => [
					'id'       => $user->id,
					'nickname' => $user->nickname,
					'avatar'   => $user->avatar,
				],
				'title'      => $card->title,
				'content'    => $card->content,
				'picUrl'     => getOriginalImgUrl($card->picUrl),
				'book'       => $bookData,
				'createTime' => $card->createTime,
				'isMe'       => \Visitor::instance()->isMe($card->userId),
			];
		}
	}

	public function getUserCards($userId) {
		$query = new MCard();
		$query->userId = $userId;

		$cardList = $query->query("status = '0'", 'ORDER BY create_time DESC');

		return array_map(function($card) {
			/** @var MBook $book */
			$book = Graph::findBook($card->bookIsbn);

			/** @var MCard $card */
			return [
				'id'         => $card->id,
				'title'      => $card->title,
				'content'    => mb_substr($card->content, 0, 48, 'utf-8'),
				'picUrl'     => getListThumbnailUrl($card->picUrl),
				'bookTitle'  => $book->title,
				'createTime' => $card->createTime,
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
			];
		}, $cardList);

		return array_filter($resultList, function($item) {
			return $item !== false;
		});
	}

	/*
	 * 第一版出去最简单的卡片流:读书卡片和最新图书混排的流
	 * $cursor 时间戳
	 * $isUp 下拉或者上拉刷新
	 */
	public function getDiscoverPageData($cursor, $isTop) {
		$cursor = intval($cursor);
		if ($cursor < 0) {
			$cursor = 0;
		}

		if ($isTop) {
			// 表示下拉刷新,数据要放在顶部,直接取最新的数据发下去
			$condition = "status = '0'";
		} else {
			$condition = "status = '0' and create_time < {$cursor}";
		}

		// 先取50条数据出来
		$query = new MCard();
		$cardList = $query->query($condition,
								  'ORDER BY create_time DESC LIMIT 0,50');
		
		// 去重逻辑:1.同一个用户不能超过五条
		$filteredList = [];
		$userMap = [];
		foreach ($cardList as $card) {
			/** @var MCard $card */
			$userId = $card->userId;
			if (!isset($userMap[$userId])) {
				$userMap[$userId] = 1;
			} else if ($userMap[$userId] < 5) {
				$userMap[$userId] = $userMap[$userId] + 1;
			} else {
				continue;
			}
			// 一次最多返回15条,取50条去重应该很大概率返回的是15条数据
			if (count($filteredList) >= 15) {
				break;
			}
			$filteredList[] = $card;
		}

		$resultList =  array_map(function($card) {
			/** @var MUser $user */
			$user = Graph::findUserById($card->userId);
			if ($user === false) {
				return false;
			}

			/** @var MCard $card */
			return [
				'type' => 'card',
				'data' => [
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
				],
			];
		}, $filteredList);

		$resultList = array_filter($resultList, function($item) {
			return $item !== false;
		});

		$topCursor = self::getCursor($resultList, true);
		$bottomCursor = self::getCursor($resultList, false);

		return [
			'list'         => $resultList,
			'topCursor'    => $topCursor,
			'bottomCursor' => $bottomCursor,
			'showPost'     => true,
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
}
