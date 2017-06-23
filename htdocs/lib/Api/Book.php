<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

namespace Api;

class Book extends ApiBase {
	public function search($key) {
		$url = "https://api.douban.com/v2/book/search?q={$key}";
		$response = file_get_contents($url);

		$json = json_decode($response);
		$books = $json->books;
		if ($books !== null) {
			$formatBooks = [];
			
			foreach ($books as $book) {
				$added = false;
				$user = \Visitor::instance()->getUser();
				if ($user !== null) {
					$added = $user->isBookAdded($book->isbn13);
				}

				$formatBooks[] = [
					'isbn'      => $book->isbn13,
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
				$added = $user->isBookAdded($book->isbn13);
			}

			$formatBooks[] = [
				'isbn'      => $book->isbn13,
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
