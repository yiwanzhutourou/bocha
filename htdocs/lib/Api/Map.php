<?php
/**
 * Created by Cui Yi
 * 2017/6/1
 */

namespace Api;

use Graph\Graph;
use Graph\MUser;
use Graph\MUserAddress;

class Map extends ApiBase {

	// 每一度纬度和经度对应的距离（公里）
	const LAT_DISTANCE = 111.7;
	const LNG_DISTANCE = 85.567;

	// 需要进行搜索的附近距离，默认值会根据数据多少来调整
	const NEAR_DISTANCE = 10;

	public function getMarkers() {
		$userAddress = new MUserAddress();
		return array_map(function ($address) {
			return [
				'id'        => $address->userId,
				'latitude'  => $address->latitude,
				'longitude' => $address->longitude,
			];
		}, $userAddress->find());
	}

	/**
	 * 根据经纬度获取周围的一定公里以内的点
	 */
	public function getMarkersNearBy($lat, $lng, $distance = self::NEAR_DISTANCE) {

		$latOffset = $distance / self::LAT_DISTANCE;
		$lngOffset = $distance / self::LNG_DISTANCE;

		$minLat = $lat - $latOffset;
		$maxLat = $lat + $latOffset;
		$minLng = $lng - $lngOffset;
		$maxLng = $lng + $lngOffset;

		$userAddress = new MUserAddress();
		$query = 'latitude > ' . $minLat . ' and latitude < ' . $maxLat;
		$query = $query . ' and longitude > ' . $minLng . ' and longitude < ' . $maxLng;
		return array_map(function($address) {
			/** @var MUser $user */
			$user = Graph::findUserById($address->userId);
			return [
				'id'        => $address->userId,
				'title'     => $user->nickname,
				'latitude'  => $address->latitude,
				'longitude' => $address->longitude,
			];
		}, $userAddress->query($query));
	}

	/**
	 * 获取一组、或者一个用户ID的相关信息
	 */
	public function getUserAddresses($userIds) {
		$userIdArray = split(',', $userIds);
		$result = [];
		foreach ($userIdArray as $userId) {
			$uid = trim($userId);
			if ($uid && count($uid) > 0) {
				/** @var MUser $user */
				$user = Graph::findUserById($userId);
				/** @var MUserAddress $address */
				$address = Graph::findUserAddress($userId);
				$bookCount = $user->getBookListCount();
				$result[] = [
					'id' 				=> $userId,
					'nickname' 	=> $user->nickname,
					'avatar' 		=> $user->avatar,
					'address'		=> [
						'latitude'  => $address->latitude,
						'longitude' => $address->longitude,
						'name'      => $address->name,
						'detail'    => $address->detail,
						'city'      => json_decode($address->city)
					],
					'bookCount' => $bookCount
				];
			}
		}
		return $result;
	}
}
