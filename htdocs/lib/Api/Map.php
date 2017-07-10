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

	// 需要进行搜索的附近距离，默认5公里
	const NEAR_DISTANCE = 5;

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
	 * 根据经纬度获取周围的5公里以内的点
	 */
	public function getMarkersNearBy($lat, $lng, $nearDistance = self::NEAR_DISTANCE) {

		$latOffset = $nearDistance / self::LAT_DISTANCE;
		$lngOffset = $nearDistance / self::LNG_DISTANCE;

		$minLat = $lat - $latOffset;
		$maxLat = $lat + $latOffset;
		$minLng = $lng - $lngOffset;
		$maxLng = $lng + $lngOffset;

		$userAddress = new MUserAddress();
		$query = 'latitude > ' . $minLat . ' and latitude < ' . $maxLat;
		$query = $query . ' and longitude > ' . $minLng . ' and longitude < ' . $maxLng;
		return array_map(function($address) {
			$user = Graph::findUserById($addres->userId);
			return [
				'id' 				=> $address->userId,
				'title'			=> $user->nickname,
				'latitude' 	=> $address->latitude,
				'longitude'	=> $address->longitude,
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
				$user = Graph::findUserById($userId);
				$address = Graph::findUserAddress($userId);
				$result[] = [
					'id' 				=> $userId,
					'nickname' 	=> $user->nickname,
					'avatar' 		=> $user->avatar,
					'address'		=> [
						'latitude'  => $address->latitude,
						'longitude' => $address->longitude,
						'name'      => $address->name,
						'detail'    => $address->detail
					]
				];
			}
		}
		return $result;
	}
}
