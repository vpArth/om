<?php
namespace API\SDK;

use API\Core\Router;

interface IService
{
  public function registerRoutes(Router $router);
}
