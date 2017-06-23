<?php
/**
 * Created by Cui Yi
 * 2017/5/24
 */

/**
 * 确保是合法的UTF-8字符串，否则会尝试从GBK编码转换，都失败的情况下会替换非法字符
 * @param $bytes
 * @return string
 */
function to_utf8($bytes) {
	switch (mb_detect_encoding($bytes, null, true)) {
		case 'UTF-8': //是合法UTF-8
			return $bytes;
		case 'CP936': //是GB18030/GBK/GB2312
			//用iconv似乎略快，但是iconv的实现和mb_convert_encoding可能有小差异，也许会报错
			//return iconv('CP936', 'UTF-8', $bytes);
			return mb_convert_encoding($bytes, 'UTF-8', 'CP936');
		default:
			//替换非法字符
			return mb_convert_encoding($bytes, 'UTF-8', 'UTF-8');
	}
}

if (!defined('UTF8_ERR_BREAK')) {
	define('UTF8_ERR_BREAK', 0x01); //返回第一个非UTF8字符之前的字符串
	define('UTF8_ERR_EXCEPTION', 0x02); //出现非UTF8字符时，抛出Exception
	define('UTF8_ERR_FILTER', 0x04); //返回过滤掉非UTF8字符之后的字符串
}

function utf8_filter($string, $mode = UTF8_ERR_FILTER) {
	//不考虑4位的UTF8字符，会让json_encode 和 decode 掉坑。
	$preg_str = '#('.
				'[\x09\x0A\x0D\x20-\x7E]|'.				// 000000 - 00007F | visible ASCII
				'[\xC2-\xDF][\x80-\xBF]|'.				// 000080 - 0007FF | non-overlong 2-byte
				'\xE0[\xA0-\xBF][\x80-\xBF]|'.			// 000800 -        | excluding overlong
				'[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|'.	//        /        | straight 3-byte
				'\xED[\x80-\x9F][\x80-\xBF]';			//        - 00D7FF | excluding surrogates
	$preg_str .= '|\xf0\x9f[\x8c-\x9b][\x80-\xBF]';	//emoji字符集。
	$preg_str .= ')++#';

	if (preg_match($preg_str, $string, $match)) {
		if ($match[0] != $string) { // 不完全符合
			if ($mode & UTF8_ERR_BREAK) { //在第一个非UTF-8字符的地方截断
				$string = $match[0];
			} elseif ($mode & UTF8_ERR_FILTER) {
				preg_match_all($preg_str, $string, $match);
				$string = join('', $match[0]);
			} elseif ($mode & UTF8_ERR_EXCEPTION) {
				throw new Exception('None UTF-8 string.');
			}
		}
	} else { // 完全不符合
		$string = '';
	}
	return $string;
}
