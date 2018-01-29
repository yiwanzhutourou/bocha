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
        background: #535354;
      }
      
      .logo {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        border: 2px solid #fff;
      }

      .title {
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 60px;
        margin-top: 20px;
        color: #fefefe;
      }

      .disc {
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 24px;
        margin-top: 20px;
        color: #fefefe;
      }

      h3 {
        display: flex;
        justify-content: center;
        align-items: center;
        color: #fefefe;
      }
      .qr-code {
        width: 200px;
        height: 200px;
        margin-top: 20px;
      }
    </style>
  </head>
  <body>
    <img class="logo" src="http://othb16dht.bkt.clouddn.com/logo.png?imageView2/0/format/jpg/q/75|imageslim
" />
    <div class="title">有读书房</div>
    <div class="disc">发现一公里内的好书</div>
    <div class="disc">扫描二维码或微信小程序搜索“有读书房”</div>
    <img class="qr-code" src="http://othb16dht.bkt.clouddn.com/WechatIMG5969.jpeg" />
  </body>
</html>
			');
	}
}