<?php
namespace API\Core\DB;

class User extends Model
{
  const CACHE_PREFIX = 'cache_users_';
  protected static $table = 'users';

  protected $fields = array('id', 'username', 'password', 'email', 'last_login');

  private static function genSalt($rounds = 12)
  {
    return sprintf('$2a$%02d$', $rounds) . strtr(base64_encode(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM)), '+', '.');
  }
  public function checkPass($pass)
  {
    return crypt($pass, $this->data['password']) === $this->data['password'];
  }
  public function setPass($pass)
  {
    $this->data['password'] = crypt($pass, self::genSalt());
  }
  public function genToken()
  {
    $cache = Cache::getInstance();
    $token = md5($this->data['password'].microtime(1));
    $cache->set('authTokens_'.$token, $this->data['id'], 7200);
    return $token;
  }

  public static function delToken($token)
  {
    $cache = Cache::getInstance();
    $cache->del('authTokens_'.$token);
  }
  public function profile()
  {
    $res = array();
    foreach(array('id', 'username', 'email') as $f) {
      $res[$f] = $this->data[$f];
    }
    //todo: online state
    return $res;
  }

  public static function getByToken($token)
  {
    $cache = Cache::getInstance();
    $id = $cache->get('authTokens_'.$token);
    if (!$id) return false;
    return self::getById($id);
  }

  public static function getByUsername($username)
  {
    $key = self::CACHE_PREFIX.'_username_'.$username;
    $data = Cache::getInstance()->get($key);
    if (!$data) {
      $sql = "SELECT * FROM `".static::$table."` WHERE `username` = :username";
      $data = DB::getInstance()->row($sql, array(':username'=>$username));
    }
    if (!$data) return false;
    Cache::getInstance()->set($key, $data, self::CACHE_TIME);
    return new static($data);
  }
  public static function getByUsernameOrEmail($username, $email)
  {
    $key = self::CACHE_PREFIX.'_username_email_'.$username.'_'.$email;
    $data = Cache::getInstance()->get($key);
    if (!$data) {
      $sql = "SELECT * FROM `".static::$table."` WHERE `username` = :username OR `email` = :email ";
      $data = DB::getInstance()->row($sql, array(':username' => $username, ':email' => $email));
    }
    if (!$data) return false;
    Cache::getInstance()->set($key, $data, self::CACHE_TIME);
    return new static($data);
  }
}
