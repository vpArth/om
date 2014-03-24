<?php

namespace API\Core\DB;

use API\Core\Single;
use \PDO;
use \PDOException;

class DB extends Single
{
  private $pdo = null;
  private $statement = null;

  private $affectedRows = 0;
  private $lastId = null;

  private $queryCount = 0;
  private $queryTime = 0;

  protected function init($params)
  {

    $this->dsn      = isset($params['dsn'])      ? $params['dsn']      : 'mysql:host=localhost;dbname=messenger';
    $this->username = isset($params['username']) ? $params['username'] : 'root';
    $this->password = isset($params['password']) ? $params['password'] : '';
    $this->options  = isset($params['options'])  ? $params['options']  : array(
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8;"
    );
  }

  private function connect()
  {
    try {
      $this->pdo = new PDO($this->dsn, $this->username, $this->password, $this->options);
    } catch (PDOException $e) {
      throw new \Exception($e->getMessage());
    }
  }

  private static function getPDOType($var)
  {
    if (is_int($var)) return PDO::PARAM_INT;
    if (is_bool($var)) return PDO::PARAM_BOOL;
    if (is_null($var)) return PDO::PARAM_NULL;
    return PDO::PARAM_STR;
  }

  private function bindParam($field, &$value)
  {
    $this->statement->bindValue(":{$field}", $value, self::getPDOType($value));
    return $this;
  }

  private function bindParams(array &$params)
  {
    foreach ($params as $field => &$value) {
      $this->bindParam($field, $value);
    }
    return $this;
  }

  public function prepare($sql)
  {
    $this->statement = $this->pdo->prepare($sql);
    return $this;
  }

  public function execPrepared(array $params = array())
  {
    if ($params) {
      $this->bindParams($params);
      $this->statement->execute($params);
    } else {
      $this->statement->execute();
    }
    return $this;
  }

  public function exec($sql, array $params = array())
  {
    if(!$this->pdo) $this->connect();
    $start = microtime(true);
    $this->prepare($sql)->execPrepared($params);
    $this->lastId = $this->pdo->lastInsertId();
    $this->affectedRows = $this->statement->rowCount();
    $this->queryTime += microtime(true) - $start;
    $this->queryCount++;
    echo $sql."\n";print_r($params);echo "\n\t---\n";
    return $this;
  }

  public function getLastId() {
    return $this->lastId;
  }

  public function getQCount() { return $this->queryCount; }
  public function getQTime()  { return $this->queryTime; }

  public function cell($sql, array $params = array(), $col = 0)
  {
    $this->exec($sql, $params);
    return $this->statement->fetchColumn((int)$col);
  }

  public function col($sql, array $params = array(), $col = 0)
  {
    $this->exec($sql, $params);
    return $this->statement->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_COLUMN, (int)$col);
  }

  public function row($sql, array $params = array())
  {
    $this->exec($sql, $params);
    return $this->statement->fetch(PDO::FETCH_ASSOC);
  }

  public function rows($sql, array $params = array())
  {
    $this->exec($sql, $params);
    return $this->statement->fetchAll(PDO::FETCH_ASSOC);
  }

}
