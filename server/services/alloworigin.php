<?php
namespace API;

use API\SDK\IService;

use API\Core\DB;
use API\Core\Router;
use API\Core\RouteData;

use API\Core\ValidatorException;

class AllowOrigin implements IService
{
  protected $api;
  public function __construct($api)
  {
    $this->api = $api;
  }

  /**
   * Register game required routes to provided router
   *
   * @param Core\Router $router
   */
  public function registerRoutes(Router $router)
  {
    $router->addRoute(new RouteData(array(
      'verb' => 'OPTIONS',
      'path' => "^/",
      'classname' => $this,
      'method' => 'ping',
      'validators' => array()
    )));
  }



  //API actions

  //Login
  public function ping(array $fv)
  {
  }

}
