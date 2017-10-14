<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

/**
 * @param $data
 * @return string
 */
function json_stringify($data) {
	$result = @json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	if (json_last_error() === JSON_ERROR_NONE) {
		return str_replace('</script>', '<\/script>', $result);
	}

	return 'null';
}

/**
 * 获取距离字符串
 * @param $lat1
 * @param $lng1
 * @param $lat2
 * @param $lng2
 * @return string
 */
function getDistanceString($distance) {
	if ($distance < 0.1) {
		return "小于 100 米";
	} else if ($distance < 1.0) {
		$distanceInMeter = intval($distance * 1000);
		return "{$distanceInMeter} 米";
	} else if ($distance < 30.0) {
		$distanceInKm = intval($distance);
		return "{$distanceInKm} 千米";
	} else {
		return "大于 30 千米";
	}
}

/**
 * 根据两点间的经纬度计算距离
 * @param $lat1
 * @param $lng1
 * @param $lat2
 * @param $lng2
 * @return float distance in kilometer with 1 precision like 0.1 (km)
 */
function getDistance($lat1, $lng1, $lat2, $lng2) {
	$earthRadius = 6367; // approximate radius of earth in kilometers
	/*
	  Convert these degrees to radians
	  to work with the formula
	*/

	$lat1 = ($lat1 * pi()) / 180;
	$lng1 = ($lng1 * pi()) / 180;

	$lat2 = ($lat2 * pi()) / 180;
	$lng2 = ($lng2 * pi()) / 180;

	/*
	  Using the
	  Haversine formula
	  http://en.wikipedia.org/wiki/Haversine_formula
	  calculate the distance
	*/

	$calcLongitude = $lng2 - $lng1;
	$calcLatitude = $lat2 - $lat1;
	$stepOne = pow(sin($calcLatitude / 2), 2)
			   + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
	$stepTwo = 2 * asin(min(1, sqrt($stepOne)));
	$calculatedDistance = $earthRadius * $stepTwo;

	return round($calculatedDistance, 3);
}

/**
 * 经纬度获取省份\城市\区县
 *
 * @param $lat
 * @param $lng
 * @return array|mixed
 */
function reversePoi($lat, $lng) {
	$url = "http://api.map.baidu.com/geocoder/v2/?"
		   . http_build_query([
								  'location' => $lat.','.$lng,
								  'output'   => 'json',
								  'pois'     => 1,
								  'ak'       => BAIDU_MAP_AK
							  ]);
	$response = file_get_contents($url);
	$location = json_decode($response);
	if ($location !== null && $location->status === 0) {
		if (!empty($location->result)) {
			$address = $location->result->addressComponent;
			if (!empty($address)) {
				return json_stringify([
					'province' => $address->province,
					'city'     => $address->city,
					'district' => $address->district
				]);
			}
		}
	}
	return 'null';
}

/**
+----------------------------------------------------------
 * 生成随机字符串
+----------------------------------------------------------
 * @param int       $length  要生成的随机字符串长度
 * @param string    $type    随机码类型：0，数字+大小写字母；1，数字；2，小写字母；3，大写字母；4，特殊字符；-1，数字+大小写字母+特殊字符
+----------------------------------------------------------
 * @return string
+----------------------------------------------------------
 */
function randCode($length = 5, $type = 0) {
	$arr = array(
		1 => "0123456789",
		2 => "abcdefghijklmnopqrstuvwxyz",
		3 => "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
		4 => "~@#$%^&*(){}[]|");
	if ($type == 0) {
		array_pop($arr);
		$string = implode("", $arr);
	} elseif ($type == "-1") {
		$string = implode("", $arr);
	} else {
		$string = $arr[$type];
	}
	$count = strlen($string) - 1;
	$code = '';
	for ($i = 0; $i < $length; $i++) {
		$code .= $string[rand(0, $count)];
	}
	return $code;
}

function getListThumbnailUrl($url) {
	if (empty($url)) {
		return $url;
	}
	return $url . '?imageView2/1/w/200/h/200/format/jpg/q/75|imageslim';
}

function getOriginalImgUrl($url) {
	if (empty($url)) {
		return $url;
	}
	return $url . '?imageView2/0/format/jpg/q/75|imageslim';
}