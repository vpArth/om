<?php

namespace API;

use API\Core\Loader;
use API\Core\Router;
use API\Core\RouteData;
use API\Core\RouterException;

// \mb_internal_encoding("UTF-8");
error_reporting(-1);

require_once "core/loader.php";
Loader::getInstance();

class Ctrlr
{
  public function action($fv) {
    return "Hello, {$fv['__vars__'][1][0]}";
  }
}
  $router = Router::getInstance();
  $router->addRoute(new RouteData([
    'verb' => 'GET',
    'path' => "^/hello/(\w+)$",
    'classname' => new Ctrlr,
    'method' => 'action'
  ]));

try {
  echo $router->execURI();
} catch (RouterException $e) {
  echo $e->getMessage();
}
