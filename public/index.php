<?php

session_start();

require_once '../vendor/autoload.php';
require_once '../config/router.php';
require_once '../config/dotenv.php';


//$container = new \Illuminate\Container\Container();
//$dispatcher = new \Illuminate\Events\Dispatcher($container);



$response = $router->dispatch($request);
echo $response->getContent();
