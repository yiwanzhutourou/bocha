<?php
/**
 * Created by Cui Yi
 * 2017/6/1
 */

namespace Api;

use Graph\MUser;
use Graph\MUserAddress;

class Map extends ApiBase {

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
}
