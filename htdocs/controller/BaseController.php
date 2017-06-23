<?php
/**
 * Created by Cui Yi
 * 2017/5/24
 */

class BaseController {
	// 继承类可能需要override的方法
	/**
	 * controller 级别的parse request。必须要parse出request对应的action。
	 * 默认是按pattern matches的元素取出action。
	 * @param BoRequestData $data
	 * @return String $action
	 */
	public static function selectAction(BoRequestData $data) {
		if (!empty($data->matches['action'])) {
			$action = $data->matches['action'];
		} else {
			$action = 'index';
		}
		return $action;
	}

	/**
	 * @param Exception $e
	 * @param BoRequestData $request
	 * @throws Exception
	 * @return BoResponseData
	 */
	protected function handleException(Exception $e, BoRequestData $request) {
		throw $e;
	}

	/**
	 * Controller的requestData是否需要parse。
	 * 内部调用时可以直接构造一个已经parse好的RequestData。
	 * @param BoRequestData $request
	 * @param $action
	 * @return BoResponseData $response
	 * @throws Exception
	 */
	final public function process(BoRequestData $request, $action) {

		if (is_callable([$this, $action])) {
			$this->checkAccess($request, $action);

			try {
				$response = $this->$action($request);
			} catch (Exception $e) {
				$response = $this->handleException($e, $request);
			}
		} else {
			throw new NotFoundException();
		}

		return $response;
	}

	private function checkAccess(BoRequestData $request, $action) {}
}

