<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

namespace Api;

use Graph\Graph;
use Graph\MBook;
use Graph\MBorrowRequest;
use Graph\MUser;
use Graph\MUserBook;

class Book extends ApiBase {
	public function search($key, $count = 20, $page = 0) {
		$url = "https://api.douban.com/v2/book/search?"
			. http_build_query([
				'q'     => $key,
				'start' => $count * $page,
				'count' => $count
			]);
		$response = file_get_contents($url);

		$json = json_decode($response);
		$books = $json->books;
		if ($books !== null) {
			$formatBooks = [];
			
			foreach ($books as $book) {
				$added = false;
				$user = \Visitor::instance()->getUser();
				if ($user !== null) {
					$added = $user->isBookAdded($book->id);
				}

				$formatBooks[] = [
					'isbn'      => $book->id,
					'title'     => $book->title,
					'author'    => $book->author,
					'url'       => $book->alt,
					'cover'     => $book->image,
					'publisher' => $book->publisher,
					'added'     => $added
				];
			}
			return $formatBooks;
		} else {
			return [];
		}
	}

	public function getBookByIsbn($isbn) {
		$url = "https://api.douban.com/v2/book/isbn/{$isbn}";
		$response = file_get_contents($url);

		$book = json_decode($response);
		if ($book !== null && !empty($book->id)) {
			$added = false;
			$user = \Visitor::instance()->getUser();
			if ($user !== null) {
				$added = $user->isBookAdded($book->id);
			}

			$formatBooks[] = [
				'isbn'      => $book->id,
				'title'     => $book->title,
				'author'    => $book->author,
				'url'       => $book->alt,
				'cover'     => $book->image,
				'publisher' => $book->publisher,
				'added'     => $added
			];

			return $formatBooks;
		} else {
			return [];
		}
	}

	public function getBorrowPageData($userId) {
		/** @var MUser $user */
		$user = Graph::findUserById($userId);
		if ($user === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '用户不存在');
		}

		$borrowBooks = array_map(function($userBook) {
			/** @var MUserBook $userBook */
			$book = new MBook();
			$book->isbn = $userBook->isbn;
			/** @var MBook $bookOne */
			$bookOne = $book->findOne();
			if ($bookOne === false) {
				return false;
			}
			return [
				'isbn'      => $bookOne->isbn,
				'title'     => $bookOne->title,
				'author'    => json_decode($bookOne->author),
				'cover'     => $bookOne->cover,
				'publisher' => $bookOne->publisher,
				'leftCount' => $userBook->leftCount,
			];
		}, $user->getBorrowBooks());

		$borrowBooks = array_filter($borrowBooks, function($book) {
			return $book !== false;
		});

		return [
			'nickname' => $user->nickname,
			'avatar'   => $user->avatar,
			'books'    => $borrowBooks,
		];
	}

	public function borrow($to, $isbn) {
		\Visitor::instance()->checkAuth();

		$selfId = \Visitor::instance()->getUserId();
		if ($to === $selfId) {
			throw new Exception(Exception::BAD_REQUEST , '不可以借自己的书哦~');
		}

		/** @var MUserBook $userBook */
		$userBook = Graph::findUserBook($isbn, $to);

		// 判断图书是否存在,是否是闲置图书,是否还有库存
		// TODO 后续可能要根据需求做成,不存在的图书也可以借阅
		// TODO 场景是图书馆里的书没有完全录入,只要用户可以从书架上找到这本书,就可以借阅
		// TODO 当然这个是特殊场景下的特殊需求
		if ($userBook === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, 'TA的书房里没有这本书');
		}

		if ($userBook->canBeBorrowed === BOOK_CANNOT_BE_BORROWED) {
			throw new Exception(Exception::BAD_REQUEST, '这本书似乎不是闲置图书哦');
		}

		if ($userBook->leftCount === 0) {
			throw new Exception(Exception::BAD_REQUEST, '这本书似乎已经被借出去了');
		}

		if (Graph::borrow($selfId, $to, $isbn)) {
			return 'ok';
		}

		throw new Exception(Exception::INTERNAL_ERROR, '服务器繁忙，请稍后再试');
	}

	public function accept($id, $from, $isbn) {
		\Visitor::instance()->checkAuth();

		$selfId = \Visitor::instance()->getUserId();

		/** @var MBorrowRequest $borrowRequest */
		$borrowRequest = Graph::getBorrowRequest($id, $from, $selfId, $isbn);
		if ($borrowRequest === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '借阅请求不存在');
		}

		// 不可能发生,简单防一下
		if ($from === $selfId) {
			throw new Exception(Exception::BAD_REQUEST , '不可以处理自己的请求哦~');
		}

		// 书不存在或者库存小于1,可能是用户删除了图书或者把书借给了其他人
		// 直接把状态设置为 BORROW_STATUS_DECLINED
		/** @var MUserBook $userBook */
		$userBook = Graph::findUserBook($isbn, $selfId);
		if ($userBook === false || $userBook->leftCount <= 0) {
			$borrowRequest->status = BORROW_STATUS_DECLINED;
			$borrowRequest->update();
			throw new Exception(Exception::INTERNAL_ERROR, '你的书被删除或者借出去了，不可以完成这个操作~');
		}

		$borrowRequest->status = BORROW_STATUS_ACCEPTED;
		
		if ($borrowRequest->update() > 0) {
			// 书借出去了,库存减一
			Graph::minusUserBookCountByOne($isbn, $selfId);
		}

		return 'ok';
	}

	public function markBookReturn($id, $from, $isbn) {
		\Visitor::instance()->checkAuth();

		$selfId = \Visitor::instance()->getUserId();

		// 不可能发生,简单防一下
		if ($from === $selfId) {
			throw new Exception(Exception::BAD_REQUEST , '不可以处理自己的请求哦~');
		}

		/** @var MBorrowRequest $borrowRequest */
		$borrowRequest = Graph::getBorrowRequest($id, $from, $selfId, $isbn);

		if ($borrowRequest === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '借阅请求不存在');
		}

		$borrowRequest->status = BORROW_STATUS_RETURNED;
		$borrowRequest->update();

		return 'ok';
	}

	public function declineReturn($id, $from, $isbn) {
		\Visitor::instance()->checkAuth();

		$selfId = \Visitor::instance()->getUserId();

		// 不可能发生,简单防一下
		if ($from === $selfId) {
			throw new Exception(Exception::BAD_REQUEST , '不可以处理自己的请求哦~');
		}

		/** @var MBorrowRequest $borrowRequest */
		$borrowRequest = Graph::getBorrowRequest($id, $from, $selfId, $isbn);

		if ($borrowRequest === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '借阅请求不存在');
		}

		$borrowRequest->status = BORROW_STATUS_ACCEPTED; // 回到同意借阅状态
		$borrowRequest->update();

		return 'ok';
	}

	public function getMyBorrowRequests($flag = 0) {
		\Visitor::instance()->checkAuth();

		$status = intval($flag);
		if ($status < BORROW_STATUS_NORMAL || $status > BORROW_STATUS_RETURNED) {
			throw new Exception(Exception::BAD_REQUEST, '无法获取数据');
		}

		$selfId = \Visitor::instance()->getUserId();
		$dataList = Graph::getBorrowRequests($selfId, $status);

		if ($dataList === false) {
			return [];
		}

		$dataList = array_map(function($data) {
			/** @var MBorrowRequest $data */
			/** @var MUser $user */
			$user = Graph::findUserById($data->from);
			if ($user === false) {
				return false;
			}
			/** @var MBook $book */
			$book = Graph::findBook($data->bookIsbn);
			if ($book === false) {
				return false;
			}
			return [
				'id'        => $data->id,
				'user'      => [
					'id'       => $user->id,
					'nickname' => $user->nickname,
					'avatar'   => $user->avatar
				],
				'book'      => [
					'isbn'      => $book->isbn,
					'title'     => $book->title,
					'author'    => self::parseAuthor($book->author),
					'cover'     => $book->cover,
					'publisher' => $book->publisher,
				],
				'timestamp' => $data->createTime,
				'status'    => $data->status,
			];
		}, $dataList);

		$dataList = array_filter($dataList, function($item) {
			return $item !== false;
		});

		return $dataList;
	}

	public function getMyOutBorrowRequests($flag = 0) {
		\Visitor::instance()->checkAuth();

		$status = intval($flag);
		if ($status < BORROW_STATUS_NORMAL || $status > BORROW_STATUS_RETURNED) {
			throw new Exception(Exception::BAD_REQUEST, '无法获取数据');
		}

		$selfId = \Visitor::instance()->getUserId();
		$dataList = Graph::getOutBorrowRequests($selfId, $status);

		if ($dataList === false) {
			return [];
		}

		$dataList = array_map(function($data) {
			/** @var MBorrowRequest $data */
			/** @var MUser $user */
			$user = Graph::findUserById($data->to);
			if ($user === false) {
				return false;
			}
			/** @var MBook $book */
			$book = Graph::findBook($data->bookIsbn);
			if ($book === false) {
				return false;
			}
			return [
				'id'        => $data->id,
				'user'      => [
					'id'       => $user->id,
					'nickname' => $user->nickname,
					'avatar'   => $user->avatar
				],
				'book'      => [
					'isbn'      => $book->isbn,
					'title'     => $book->title,
					'author'    => self::parseAuthor($book->author),
					'cover'     => $book->cover,
					'publisher' => $book->publisher,
				],
				'timestamp' => $data->createTime,
				'status'    => $data->status,
			];
		}, $dataList);

		$dataList = array_filter($dataList, function($item) {
			return $item !== false;
		});

		return $dataList;
	}

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
