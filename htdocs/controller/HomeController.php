<?php
/**
 * Created by Cui Yi
 * 2017/11/5
 */

class HomeController extends BaseController {
	// 先糊一个首页出来,后面要研究一下怎么接前端框架进来=。=
	protected function index(BoRequestData $req) {
		return BoResponseData::createDefault()
			->content('
<html>
  <head>
    <meta charset=\'utf-8\'>
    <meta name=\'keywords\' content=\'有读,书房,有读书房,阅读,借阅,附近,附近的好书\'>
    <meta name=\'description\' content=\'有读书房，发现一公里内的好书。\'>
    <title>有读书房</title>
    <link rel=\'shortcut icon\' href=\'http://s.youdushufang.com/favicon.png\' type=\'image/x-icon\' />
    <style type="text/css">
      body {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
      }

      h1 {
        display: flex;
        justify-content: center;
        align-items: center;
      }

      h2 {
        display: flex;
        justify-content: center;
        align-items: center;
      }

      h3 {
        display: flex;
        justify-content: center;
        align-items: center;
      }
    </style>
  </head>
  <body>
    <h1>有读书房</h1>
    <h2>发现一公里内的好书</h2>
    <h3>扫描二维码或者到微信小程序搜索“有读书房”</h3>
    <img class="qr-code" src=""
  </body>
</html>
			');
	}
}