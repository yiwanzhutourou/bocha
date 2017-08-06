<?php
/**
 * Created by Cui Yi
 * 2017/7/8
 */

namespace Api;

use Graph\Graph;
use Graph\MBook;
use Graph\MUser;
use Graph\MUserBook;

class Search extends ApiBase {
	public function books($keyword, $longitude = 0, $latitude = 0,
						  $count = 100, $page = 0) {
		$keyword = Graph::escape($keyword);
		if (empty($keyword)) {
			return [];
		}
		$offset = $page * $count;
		$query = new MBook();
		$books = $query->query(
			"title LIKE '%{$keyword}%'"
			. "OR author LIKE '%{$keyword}%'"
			. "OR publisher LIKE '%{$keyword}%'"
			. "LIMIT {$offset},{$count}"
		);

		// 这一段array_map...
		$result = array_map(function($book) use ($longitude, $latitude) {
			$userBook = new MUserBook();
			$userBook->isbn = $book->isbn;
			$users = array_map(function($userBook) use ($longitude, $latitude) {
				$userId = $userBook->userId;
				/** @var MUser $user */
				$user = Graph::findUserById($userId);
				$addresses = array_map(function($address) use ($longitude, $latitude) {

					$distance = getDistance($latitude, $longitude,
											$address->latitude, $address->longitude);
					return [
						'distance'  => $distance,
						'distanceText'  => getDistanceString($distance),
						'latitude'  => $address->latitude,
						'longitude' => $address->longitude,
						'name'      => $address->name,
						'detail'    => $address->detail
					];
				}, $user->getAddressList());
				usort($addresses, function($a, $b) {
					return ($a['distance'] > $b['distance']) ? 1 : -1;
				});
				return [
					'id'          => $user->id,
					'nickname'    => $user->nickname,
					'avatar'      => $user->avatar,
					'address' => array_values($addresses)[0]
				];
			}, $userBook->find());
			// filter:距离大于10公里的不返回
//			$users = array_filter($users, function($user) {
//				return !empty($user['address']) && $user['address']['distance'] < 10;
//			});
			return [
				'book'  => [
					'isbn'      => $book->isbn,
					'title'     => $book->title,
					'author'    => self::parseAuthor($book->author),
					'cover'     => $book->cover,
					'publisher' => $book->publisher,
				],
				'users' => $users
			];
		}, $books);

		// filter:没有user的不返回
		$result = array_filter($result, function($item) {
			return !empty($item['users']);
		});

		// sort: 按照有这本书的书房数量排序
		usort($result, function($a, $b) {
			return (count($a['users']) < count($b['users'])) ? 1 : -1;
		});

		return $result;
	}

	public function users($keyword, $longitude = 0, $latitude = 0,
							   $count = 100, $page = 0) {
		$keyword = Graph::escape($keyword);
		if (empty($keyword)) {
			return [];
		}
		$offset = $page * $count;
		$query = new MUser();
		$users = $query->query(
			"nickname LIKE '%{$keyword}%'"
			. " LIMIT {$offset},{$count}"
		);
		$result = array_map(function($user) use ($longitude, $latitude) {
			/** @var MUser $user */
			$addresses = array_map(function($address) use ($longitude, $latitude) {

				$distance = getDistance($latitude, $longitude,
										$address->latitude, $address->longitude);
				return [
					'distance'  => $distance,
					'distanceText'  => getDistanceString($distance),
					'latitude'  => $address->latitude,
					'longitude' => $address->longitude,
					'name'      => $address->name,
					'detail'    => $address->detail,
					'city'      => json_decode($address->city)
				];
			}, $user->getAddressList());
			usort($addresses, function($a, $b) {
				return ($a['distance'] > $b['distance']) ? 1 : -1;
			});
			$bookCount = $user->getBookListCount();
			return [
				'id'          => $user->id,
				'nickname'    => $user->nickname,
				'avatar'      => $user->avatar,
				'address' => array_values($addresses)[0],
				'bookCount' => $bookCount
			];
		}, $users);
		return $result;
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