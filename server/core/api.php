<?php
namespace API;

use API\Core\Router;
use API\Core\Response;
use API\Core\RouterException;
use API\Core\ValidatorException;

use API\SDK\IService;

class API
{
  private $start;
  private $router = null;
  private $response = null;

  public function __construct()
  {
    $this->start = microtime(1);
    $this->router = Router::getInstance();
    $this->response = new Response();
  }

  /**
   * Register service routing to API
   *
   * @param SDK\IService $svc
   * @return $this
   */
  public function addService(IService $svc)
  {
    $svc->registerRoutes($this->router);
    return $this;
  }

  /**
   * Process API request
   *
   */
  public function run()
  {
    //todo: add error codes later
    $res = array();
    $res['status'] = 0;
    try {
      if ($r = $this->router->execURI())
        $res['result'] = $r;
    } catch (\Exception $e) {
      $res['error'] = $e->getMessage();//'Something wrong happened';
      switch(get_class($e)) {
        default: $res['type'] = 'internal'; break;
        case 'API\Core\RouterException': $res['type'] = 'routing'; break;
        case 'API\Core\ValidatorException': $res['type'] = 'validation'; break;
      }
      $res['status'] = $e->getCode() ?: 500;
    }

    $res['time'] = microtime(1) - $this->start;

    $db = Core\DB\DB::getInstance();
    $res['db'] = array(
      'count' => $db->getQCount(),
      'time' =>  $db->getQTime()
    );
    $cache = Core\DB\Cache::getInstance();
    $res['cache'] = array(
      'count' => $cache->getQCount(),
      'time' =>  $cache->getQTime()
    );
    $this->response->send($res);
  }
}
