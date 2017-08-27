<?php
/**
 * Created by Cui Yi
 * 2017/8/19
 */

namespace Api;

use Graph\Graph;
use Graph\MBook;
use Graph\MCard;
use Graph\MCardPulp;
use Graph\MUser;
use Graph\MUserBook;

class Card extends ApiBase {

	// --------- 自己的读书卡片相关的接口,需要登录

	public function insert($content, $title, $picUrl, $bookIsbn = '') {
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
		$query->userId = $userId;
		$query->title = $title;
		$query->content = $content;

		// 先简单防一下
		if ($query->findOne() !== false) {
			throw new Exception(Exception::RESOURCE_IS_PULP, '你已经发送过类似读书卡片了~');
		}

		$query->picUrl = $picUrl;
		$query->bookIsbn = $bookIsbn;
		$query->createTime = strtotime('now');
		$query->status = CARD_STATUS_NORMAL;

		$insertId = $query->insert();
		return $insertId;
	}

	public function modify($cardId, $content, $title, $picUrl, $picModified) {
		\Visitor::instance()->checkAuth();

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

			$userId = \Visitor::instance()->getUserId();
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

		// 拉最新图书

		$bookCursor = intval($bookCursor);
		if ($bookCursor < 0) {
			$bookCursor = 0;
		}

		// 老数据一律不要了
		if ($isTop) {
			$bookCondition = 'create_time > 0';
		} else {
			$bookCondition = "create_time > 0 and create_time < {$bookCursor}";
		}

		// 先取20条数据出来
		$queryBook = new MUserBook();
		$bookList = $queryBook->query($bookCondition,
								  'ORDER BY create_time DESC LIMIT 0,20');

		// 去重逻辑:1.同一个用户不能超过2条
		$filteredBookList = [];
		$bookUserMap = [];
		foreach ($bookList as $book) {
			/** @var MUserBook $book */
			$userId = $book->userId;
			if (!isset($bookUserMap[$userId])) {
				$bookUserMap[$userId] = 1;
			} else if ($bookUserMap[$userId] < 2) {
				$bookUserMap[$userId] = $bookUserMap[$userId] + 1;
			} else {
				continue;
			}
			// 一次最多返回5条,取20条去重应该很大概率返回的是5条数据
			if (count($filteredBookList) >= 5) {
				break;
			}
			$filteredBookList[] = $book;
		}

		$bookResultList =  array_map(function($book) {
			/** @var MUserBook $book */
			/** @var MBook $bookData */
			$bookData = Graph::findBook($book->isbn);
			if ($bookData === false) {
				return false;
			}

			/** @var MUser $user */
			$user = Graph::findUserById($book->userId);
			if ($user === false) {
				return false;
			}

			return [
				'type' => 'book',
				'data' => [
					'isbn'         => $bookData->isbn,
					'user'       => [
						'id'       => $user->id,
						'nickname' => $user->nickname,
						'avatar'   => $user->avatar,
					],
					'title'      => $bookData->title,
					'author'     => self::parseAuthor($bookData->author),
					'cover'      => $bookData->cover,
					'publisher'  => $bookData->publisher,
					'summary'    => $bookData->summary,
					'createTime' => $book->createTime,
				],
			];
		}, $filteredBookList);

		$bookResultList = array_filter($bookResultList, function($item) {
			return $item !== false;
		});

		$bookTopCursor = self::getCursor($bookResultList, true);
		$bookBottomCursor = self::getCursor($bookResultList, false);

		$finalList = array_merge($resultList, $bookResultList);
		usort($finalList, function($a, $b) {
			return $a['data']['createTime'] < $b['data']['createTime'] ? 1 : -1;
		});

		return [
			'list'             => $finalList,
			'topCursor'        => $topCursor,
			'bottomCursor'     => $bottomCursor,
			'bookTopCursor'    => $bookTopCursor,
			'bookBottomCursor' => $bookBottomCursor,
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
}
