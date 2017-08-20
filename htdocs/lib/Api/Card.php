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

	public function modify($cardId, $content, $title = '', $picUrl = '', $bookIsbn = '') {
		\Visitor::instance()->checkAuth();

		$userId = \Visitor::instance()->getUserId();

		$content = Graph::escape($content);
		$title = Graph::escape($title);

		// TODO
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

	public function getCardsLine() {
		// TODO 卡片流的实现,第一期做一个最简单的出来
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
}
