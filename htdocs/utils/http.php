<?php
/**
 * Created by Cui Yi
 * 2017/5/24
 */

define('WITH_COLON', true);

function request_url_scheme($with_colon = false) {
	// 都支持 https 了
	$return = 'https';

	if ($with_colon) $return .= ':';

	return $return;
}
