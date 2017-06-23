<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

class Params extends ArrayObject {
	public function __construct($array = []) {
		parent::__construct($array, ArrayObject::ARRAY_AS_PROPS);
	}

	public function offsetGet($index) {
		return isset($this[$index]) ? parent::offsetGet($index) : null;
	}

	# debug only
	public function __toString() {
		return var_export(get_object_vars($this), true);
	}
}
