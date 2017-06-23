<?php
/**
 * Created by Cui Yi
 * 2017/5/24
 */

class BoResponse {
	use Singleton;

	/**
	 * @var BoResponseData
	 */
	private $responseData;

	public function __construct() {
		$this->foreverAlone();
		$this->responseData = new BoResponseData();
	}

	public function getData() {
		return $this->responseData;
	}

	public function setData(BoResponseData $data) {
		$this->responseData = $data;
	}

	public function __get($name) {
		return $this->responseData->$name;
	}

	/**
	 * send response, suggest do not call in controller.
	 * @param string $content see as replace()
	 * @param string $status see as status()
	 */
	public function send($content = null, $status = '') {
		$this->responseData->prepareForSend($content, $status);

		$output_content = $this->responseData->content;

		header("HTTP/1.1 {$this->responseData->status}");
		foreach ($this->responseData->headers as $key => $value) {
			header("{$key}: {$value}");
		}

		//send content
		echo $output_content;

		//should be end of request when response send.
		exit;
	}

	/**
	 * @param string $content append content.
	 * @return BoResponse for chaining
	 */
	public function append($content) {
		// 不推荐调用这个方法。按理最终的view应该由template和model render而来，
		// 或者简单的view就是直接setContent。
		// controller 层面应该只有 append params的需求，不应该有直接append content string 的需求 by Elfe
		$content = $this->responseData->content . $content;
		$this->responseData->content($content);
		return $this;
	}

	/**
	 * @param string $content set new content.
	 * @return BoResponse for chaining
	 */
	public function replace($content) {
		$this->responseData->content($content);
		return $this;
	}

	/**
	 * @param mixed $model
	 * @return BoResponse $this for chaining
	 */
	public function json($model) {
		$this->responseData->jsonmode()->params($model);
		return $this;
	}

	/** set status
	 * @param int $status short for usual code "200", "404" etc. or full as "503 Service Unavailable"
	 * @return BoResponse for chaining
	 * @throws Exception when set wrong status code (as not in $status_map).
	 */
	public function status($status) {
		$this->responseData->status($status);
		return $this;
	}

	/**
	 * set header
	 * @param string $key both key and value will be trim
	 * @param string $value
	 * @return BoResponse for chaining
	 * @throws Exception when any param contains new line.
	 */
	public function header($key, $value) {
		$this->responseData->header($key, $value);
		return $this;
	}

	/**
	 * set Content-Type header
	 * @param string $type
	 * @return BoResponse for chaining
	 */
	public function contentType($type) {
		$this->responseData->contentType($type);
		return $this;
	}
}
