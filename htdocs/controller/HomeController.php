<?php
/**
 * Created by Cui Yi
 * 2017/11/5
 */

class HomeController extends BaseController {
	protected function index(BoRequestData $req) {
		return BoResponseData::createDefault()->content('Hello, Youdu');
	}
}