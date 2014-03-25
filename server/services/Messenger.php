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
      'verb' => 'GET',
      'path' => "^/{$this->base}/ok$",
      'classname' => $this,
      'method' => 'ok',
      'validators' => array(
        'required' => array('token'),
      )
    )));
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
      'method' => 'user_list',
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

    $router->addRoute(new RouteData(array(
      'verb' => 'GET',
      'path' => "^/{$this->base}/messages$",
      'classname' => $this,
      'method' => 'message_list',
      'validators' => array(
        'required' => array('token'),
        'regular'  => array(array('page'=>'/^\d+$/', 'size'=>'/^\d+$/', 'cut'=>'/^\d+$/'))
      )
    )));
    $router->addRoute(new RouteData(array(
      'verb' => 'GET',
      'path' => "^/{$this->base}/messages/(\d+)$",
      'classname' => $this,
      'method' => 'action_dialog',
      'validators' => array(
        'required' => array('token'),
      )
    )));
    $router->addRoute(new RouteData(array(
      'verb' => 'POST',
      'path' => "^/{$this->base}/messages/(\d+)$",
      'classname' => $this,
      'method' => 'message_post',
      'validators' => array(
        'required' => array('token'),
      )
    )));

  }



  //API actions

  //Auth

  public function action_register(array $form)
  {
    $user = DB\User::getByUsernameOrEmail($form['username'], $form['email']);
    if ($user) {
      $field = $user['username'] === $form['username'] ? 'username' : 'email';
      throw new ValidatorException("User with same $field already exists", Errors::UNIQUE_FAILED);
    }
    $user = new DB\User(array(
      'username' => $form['username'],
      'email'    => $form['email'],
      'created'  => time(),
    ));
    $user->setPass($form['password']);
    $user->save();
  }
  public function action_login(array $form)
  {
    $user = DB\User::getByUsername($form['username']);
    if (!$user) throw new ValidatorException('Invalid credentials', Errors::BAD_CREDENTIALS);
    if(!$user->checkPass($form['password'])) throw new ValidatorException('Invalid credentials', Errors::BAD_CREDENTIALS);
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
  public function ok($form)
  {
    $this->auth($form['token']);
    return 'ok';
  }
  public function action_logout(array $form)
  {
    // $user = $this->auth($form['token']);
    DB\User::delToken($form['token']);
    return null;
  }

  //Users

  public function user_list(array $form)
  {
    $user = $this->auth($form['token']);

    $params = array();
    $params['fields'] = array('id', 'username', 'email', 'last_login');
    if (isset($form['page']))
      $params['page'] = $form['page'];
    if (isset($form['size']))
      $params['size'] = $form['size'];
    $params['exclude'] = $user['id'];

    return $user::widget($params);
  }
  public function action_profile(array $form)
  {
    $user = $this->auth($form['token']);
    $profile_id = $form['__vars__'][1][0];
    if($profile_id==$user['id'])
      return $user->profile();
    $profile = DB\User::getById($profile_id);
    if(!$profile) throw new ValidatorException("User $profile_id not found");

    return $profile->profile();
  }
  public function action_me(array $form)
  {
    $user = $this->auth($form['token']);
    return $user->profile();
  }
  public function action_options(array $form)
  {
    $user = $this->auth($form['token']);
    $flag = 0;
    foreach (array('username', 'email') as $field) {
      if (isset($form[$field]) && $form[$field] !== $user[$field]) {
        $flag = 1;
        $user[$field] = $form[$field];
      }
    }
    if (isset($form['password']) && !$user->checkPass($form['password'])) {
      $flag += 2;
      $user->setPass($form['password']);
    }
    if (!$flag) return 'No changes';

    if ($flag % 2) { //check unique
      $u = DB\User::getByUsernameOrEmail($form['username'], $form['email']);
      if ($u && $u['id']!==$user['id']) {
        $field = $u['username'] === $form['username'] ? 'username' : 'email';
        throw new ValidatorException("User with same $field already exists", Errors::UNIQUE_FAILED);
      }
    }

    $user->save();
    return 'Success';
  }

  //Messages

  public function message_list(array $form)
  {
    $user = $this->auth($form['token']);

    $params = array();
    if (isset($form['page']))
      $params['page'] = $form['page'];
    if (isset($form['size']))
      $params['size'] = $form['size'];
    if (isset($form['cut']))
      $params['cut'] = $form['cut'];
    $params['self'] = $user['id'];
    $params['order'] = 'created DESC';

    return DB\Message::data($params);
  }
  public function action_dialog(array $form)
  {
    $user = $this->auth($form['token']);
    return 'ok';
  }
  public function message_post(array $form)
  {
    $user = $this->auth($form['token']);
    return 'ok';
  }
}
