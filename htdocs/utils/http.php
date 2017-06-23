<?php
/**
 * Created by Cui Yi
 * 2017/5/24
 */

define('WITH_COLON', true);

function request_url_scheme($with_colon = false) {
	$return = 'http';
	if (isset($_SERVER["HTTP_X_EBAY_REQUEST_PROTO"])
		&& $_SERVER["HTTP_X_EBAY_REQUEST_PROTO"] == 'HTTPS') {
		$return = 'https';
	}

	if ($with_colon) $return .= ':';

	return $return;
}
