<?php
/**
 * Created by Cui Yi
 * 2017/5/25
 */

date_default_timezone_set('PRC');

define('ROOT', __DIR__);

spl_autoload_register(function ($name) {
	$lib_folders = [
		'controller',
		'lib',
		'router',
		'exception',
	];
	$name = strtr($name, '\\', DIRECTORY_SEPARATOR);
	foreach ($lib_folders as $folder) {
		if (file_exists(ROOT . "/{$folder}/{$name}.php")) {
			require ROOT . "/{$folder}/{$name}.php";
			return;
		}
	}
});

// Graph
require_once ROOT . '/Graph/Graph.php';

// functions
require_once ROOT . '/utils/http.php';
require_once ROOT . '/utils/url.php';
require_once ROOT . '/utils/utf8.php';
require_once ROOT . '/utils/utils.php';
require_once ROOT . '/utils/api.php';

// contant
require_once ROOT . '/constant.php';
