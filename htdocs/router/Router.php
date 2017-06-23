<?php
/**
 * Created by Cui Yi
 * 2017/5/24
 */

class Router {

	protected $router = [
	];

	/**
	 * @param mixed $router
	 * @return $this
	 */
	public function register($router) {
		$this->router = $router;
		return $this;
	}

	public function route(BoRequest $request, BoResponse $response) {
		$controller_name = false;
		try {
			$controller_name = $this->doRoute($this->router, $request, $response);
		} catch (Exception $exp) {
		}
		return $controller_name;
	}

	protected function doRoute(array $map, BoRequest $request, BoResponse $response) {
		foreach ($map as $rule => $ctl_name) {
			$m = getUrlMatches($rule, $request->url(), $request->uri());
			if ($m) {
				if (is_subclass_of($ctl_name, 'BaseController')) {
					$result = self::process($request, $response, $rule, $ctl_name);
					if ($result) {
						return $result;
					} else {
						continue;
					}
				}
			}
		}
		return false;
	}

	protected static function process(BoRequest $request, BoResponse $response, $pattern, $ctl_name) {
		$requestData = BoGlobalRequestData::createRequestData($request, $pattern);
		if (!$requestData) {
			return false;
		}

		$action = $ctl_name::selectAction($requestData);
		if (!$action) return false;

		$controller = new $ctl_name();

		if ($responseData = $controller->process($requestData, $action)) {
			$response->setData($responseData);
			return $ctl_name;
		} else {
			return false;
		}
	}
}
