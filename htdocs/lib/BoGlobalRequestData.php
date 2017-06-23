<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

class BoGlobalRequestData extends BoRequestData {
	public static function createRequestData(BoRequest $request, $pattern) {
		if (empty($request)) {
			return null;
		}

		$matches = self::getUrlMatches($pattern, $request->url(), $request->uri());
		if (empty($matches)) {
			return null;
		}

		$data = new BoGlobalRequestData();

		try {
			$data->params = $request->getOrPost();
		} catch (Exception $e) {
			throw new NotFoundException();
		}
		$data->matches = $matches;

		$data->init();
		return $data;
	}

	public function host() {
		return BoHttpRequest::host();
	}

	public function scheme() {
		return BoHttpRequest::scheme();
	}

	public function fullUrl() {
		return BoHttpRequest::full_url();
	}

	public function param($name = null, $default = null) {
		return BoHttpRequest::getOrPost($name, $default);
	}

	public function get($name = null, $default = null) {
		return BoHttpRequest::get($name, $default);
	}

	public function post($name = null, $default = null) {
		return BoHttpRequest::post($name, $default);
	}

	public function referrer() {
		return BoHttpRequest::referrer();
	}

	public function method() {
		return BoHttpRequest::method();
	}

	public function uri() {
		return BoHttpRequest::uri();
	}
}
