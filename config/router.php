<?php


use Illuminate\Events\Dispatcher;

$request = \Illuminate\Http\Request::createFromGlobals();

function request()
{
    global $request;
    return $request;

}

$dispather = new Dispatcher();

$container = new \Illuminate\Container\Container();
$router = new \Illuminate\Routing\Router($dispather, $container);
function router()
{
    global $router;
    return $router;
}


$router->get('/callback','Leit040\AspasiaBot\Controllers\BotController@callback');

$router->get('/get','Leit040\AspasiaBot\Controllers\BotController@getUpdates');
