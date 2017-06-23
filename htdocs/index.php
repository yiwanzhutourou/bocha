<?php
/**
 * Created by Cui Yi
 * 17/5/20
 */
include __DIR__ . "/init.php";

BoHttpRequest::init();
$request = BoRequest::instance();
$response = BoResponse::instance();

$routing_map = [
	'/api/' => 'ApiController',
];

$router = (new Router)
	->register($routing_map);

$controllerName = $router->route($request, $response);

$response->send();
