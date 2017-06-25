<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

namespace Api;

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
		$url = "https://api.douban.com/v2/book/{$isbn}";
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
}
