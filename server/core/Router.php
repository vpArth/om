<?php
namespace API\Core;

use API\Errors;

/**
 * Class RouteData
 * Container for route information for Router::addRoute method
 *
 * @package APIGame\Core
 */
class RouteData
{
  public $verb;
  public $path;
  public $classname;
  public $method;
  public $validators;

  public function __construct($data)
  {
    $this->verb = isset($data['verb']) ? $data['verb'] : 'GET';
    $this->path = isset($data['path']) ? $data['path'] : '^/$';
    $this->classname = $data['classname'];
    $this->method = $data['method'];
    $this->validators = isset($data['validators']) ? $data['validators'] : array();
  }

  public function getData()
  {
    return array(
      'verb' => $this->verb,
      'path' => $this->path,
      'classname' => $this->classname,
      'method' => $this->method,
      'validators' => $this->validators
    );
  }

}

class RouterException extends \Exception
{
}

class ValidatorException extends \Exception
{
}

interface IRouter
{
  public function addRoute(RouteData $data);

  public function execURI();
}

class Router implements IRouter
{
  const VALIDATE_SUCCESS = 0;

  private static $instance = null;

  private function __clone()
  {
  }

  private function __construct()
  {
  }

  public static function getInstance()
  {
    return self::$instance ? : (self::$instance = new self());
  }

  private $routes = [];

  /**
   * Register routing rule
   *
   * @param RouteData $data
   * @return $this
   */
  public function addRoute(RouteData $data)
  {
    $this->routes[$data->verb . ' ' . $data->path] = $data->getData();
    return $this;
  }

  /**
   * Wrapper for user data
   *
   * @return array
   */
  protected function getParams($method)
  {
    $fv = array_merge($_GET, $_POST, $_FILES);
    if (!in_array($method, array('GET', 'OPTIONS'))) {
      $type = isset($_SERVER["HTTP_CONTENT_TYPE"])?$_SERVER["HTTP_CONTENT_TYPE"]:$_SERVER["CONTENT_TYPE"];

      if ($type && $type === 'application/json') {
        $body = json_decode(file_get_contents("php://input"), 1);
        if ($body)
          $fv = array_replace($fv, $body);
      }
    }
    if ($token = isset($_SERVER["AUTHORIZATION"])?$_SERVER["AUTHORIZATION"]:false)
      $fv['token'] = $token;
    unset($_GET, $_POST, $_FILES, $_REQUEST);
    //here can be some filters, modifications
    return $fv;
  }

  /**
   * Process request
   *
   * @return mixed
   * @throws RouterException
   */
  public function execURI()
  {
    foreach ($this->routes as $pattern => $data) {

      list($method, $path) = explode(' ', $pattern, 2);
      list($url) = explode('?', $_SERVER['REQUEST_URI'], 2);
      $matches = array();
      if ($method === $_SERVER['REQUEST_METHOD'] && preg_match_all('#' . $path . '#i', $url, $matches)) {
        $class = is_object($data['classname']) ? $data['classname'] : new $data['classname']();
        if (!method_exists($class, $data['method'])) {
          $classname = is_object($data['classname']) ? get_class($data['classname']) : $data['classname'];
          throw new RouterException("Method {$classname}->{$data['method']} not Exists");
        }
        $params = $this->getParams($method);
        if($matches)
          $params['__vars__'] = $matches;
        if (($error = $this->validate($data['validators'], $params)) !== self::VALIDATE_SUCCESS) {
          throw new ValidatorException($error, Errors::INVALID_PARAMS);
        }
        return $class->{$data['method']}($params);
      }
    }
    throw new RouterException("Unregistered route");
  }

  /**
   * required: ['field1', 'field2']
   * regular: [['field1'=>'/^reg1$/', 'field2'=>'/^reg2$/'], ['field1'=>'/^reg3$/']]
   */
  private function validate($validators, $data)
  {
    foreach ($validators as $type => $vals) {
      switch ($type) {
        case 'required':
          foreach ($vals as $field) {
            if (!isset($data[$field])) return "No required field {$field}.";
          }
        break;
        case 'regular':
          foreach ($vals as $rules) {
            foreach ($rules as $field => $reg) {
              if (isset($data['field'])) {
                if ($reg === 'email' && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                  return "Wrong {$field} format";
                }elseif (!preg_match($reg, $data[$field])) {
                  return "Wrong {$field} format";
                }
              }
            }
          }
        break;
      }
    }
    return self::VALIDATE_SUCCESS;
  }
}
