<?php
namespace API;

use API\SDK\IService;

use API\Core\DB;
use API\Core\Router;
use API\Core\RouteData;

use API\Core\ValidatorException;

class Messenger implements IService
{

  protected $base;

  public function __construct($base = 'messenger')
  {
    if (session_id() == '') session_start();
    else session_regenerate_id(true);

    $this->base = $base;
  }

  /**
   * Register game required routes to provided router
   *
   * @param Core\Router $router
   */
  public function registerRoutes(Router $router)
  {
    $router->addRoute(new RouteData(array(
      'verb' => 'POST',
      'path' => "^/{$this->base}/register$",
      'classname' => $this,
      'method' => 'action_register',
      'validators' => array(
        'required' => array('username', 'password', 'email'),
        'regular'  => array(array('email'=>"/^[\s\S]{,40}$/"),array('email' => 'email'))
      )
    )));
    $router->addRoute(new RouteData(array(
      'verb' => 'GET',
      'path' => "^/{$this->base}/login$",
      'classname' => $this,
      'method' => 'action_login',
      'validators' => array(
        'required' => array('username', 'password')
      )
    )));
    $router->addRoute(new RouteData(array(
      'verb' => 'GET',
      'path' => "^/{$this->base}/logout$",
      'classname' => $this,
      'method' => 'action_logout',
      'validators' => array(
        'required' => array('token')
      )
    )));
    $router->addRoute(new RouteData(array(
      'verb' => 'GET',
      'path' => "^/{$this->base}/users$",
      'classname' => $this,
      'method' => 'widget_users',
      'validators' => array(
        'required' => array('token'),
        'regular'  => array(array('page'=>'/^\d+$/', 'size'=>'/^\d+$/'))
      )
    )));
    $router->addRoute(new RouteData(array(
      'verb' => 'GET',
      'path' => "^/{$this->base}/user/(\d+)$",
      'classname' => $this,
      'method' => 'action_profile',
      'validators' => array(
        'required' => array('token'),
      )
    )));
    $router->addRoute(new RouteData(array(
      'verb' => 'GET',
      'path' => "^/{$this->base}/user/me$",
      'classname' => $this,
      'method' => 'action_me',
      'validators' => array(
        'required' => array('token'),
      )
    )));
    $router->addRoute(new RouteData(array(
      'verb' => 'PUT',
      'path' => "^/{$this->base}/user$",
      'classname' => $this,
      'method' => 'action_options',
      'validators' => array(
        'required' => array('token'),
      )
    )));

  }



  //API actions

  //Register
  public function action_register(array $fv)
  {
    $user = DB\User::getByUsernameOrEmail($fv['username'], $fv['email']);
    if ($user) {
      $field = $user['username'] === $fv['username'] ? 'username' : 'email';
      throw new ValidatorException("User with same $field already exists", Errors::UNIQUE_FAILED);
    }
    $user = new DB\User(array(
      'username' => $fv['username'],
      'email'    => $fv['email'],
      'created'  => time(),
    ));
    $user->setPass($fv['password']);
    $user->save();
  }

  //Login
  public function action_login(array $fv)
  {
    $user = DB\User::getByUsername($fv['username']);
    if (!$user) throw new ValidatorException('Invalid credentials', Errors::BAD_CREDENTIALS);
    if(!$user->checkPass($fv['password'])) throw new ValidatorException('Invalid credentials', Errors::BAD_CREDENTIALS);
    $user['last_login'] = time();
    $user->save();
    return array(
      'token' => $user->genToken()
    );
  }

  private function auth($token)
  {
    $user = DB\User::getByToken($token);
    if (!$user) throw new ValidatorException('Wrong or expired token', Errors::BAD_TOKEN);
    return $user;
  }

  //Logout
  public function action_logout(array $fv)
  {
    // $user = $this->auth($fv['token']);
    DB\User::delToken($fv['token']);
    return null;
  }

  //Users
  public function widget_users(array $fv)
  {
    $user = $this->auth($fv['token']);

    $params = array();
    $params['fields'] = array('id', 'username', 'email', 'last_login');
    if (isset($fv['page']))
      $params['page'] = $fv['page'];
    if (isset($fv['size']))
      $params['size'] = $fv['size'];
    $params['exclude'] = $user['id'];

    return $user::widget($params);
  }

  public function action_profile(array $fv)
  {
    $user = $this->auth($fv['token']);
    $id = $fv['__vars__'][1][0];
    $u = DB\User::getById($id);
    if(!$u) throw new ValidatorException("User $id not found");

    return $u->profile();
  }
  public function action_me(array $fv)
  {
    $user = $this->auth($fv['token']);
    return $user->profile();
  }
  public function action_options(array $fv)
  {
    $user = $this->auth($fv['token']);
    $flag = 0;
    foreach (array('username', 'email') as $field) {
      if (isset($fv[$field]) && $fv[$field] !== $user[$field]) {
        $flag = 1;
        $user[$field] = $fv[$field];
      }
    }
    if (isset($fv['password']) && $fv['password'] !== $user['password']) {
      $flag += 2;
      $user->setPass($fv['password']);
    }
    if (!$flag) return 'No changes';

    if ($flag % 2) { //check unique
      $u = DB\User::getByUsernameOrEmail($fv['username'], $fv['email']);
      if ($u && $u['id']!==$user['id']) {
        $field = $u['username'] === $fv['username'] ? 'username' : 'email';
        throw new ValidatorException("User with same $field already exists", Errors::UNIQUE_FAILED);
      }
    }

    $user->save();
    return 'Success';
  }
}
