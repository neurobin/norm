<?php namespace Norm;

use PDO;
use Exception;
use PDOException;

class DB{
    /* *
     * Required constants:
     *  DB_DRIVER   mysql, pgsql etc..
     *  DB_HOST     e.g localhost or 127.0.0.1
     *  DB_NAME     database name
     *  DB_CHARSET  e.g utf8
     *  DB_USER     username
     *  DB_PASSWORD password
     *
     * * Conventions:
     * --------------
     *
     * 1. Method names must be in snake case with at least two words.
     *    PDO uses camelCase, we use snake case to distinguish our methods from PDO.
     * 2. Internal method names must start with a single underscore.
     *
     * */
    protected static $pdo_instance;
    public static $throw_connection_exception = true;

    public function __construct() {
        throw new Exception("ERROR: ".__CLASS__." does not allow object instantiation.");
    }

    public function __clone(){
        throw new Exception("ERROR: ".__CLASS__." does not allow object copy.");
    }

    public static function _create_error_message($msg, $scope, $prefix="ERROR:"){
        return $prefix.$scope.$msg;
    }

    public static function _error_log($msg){
        $err_file = fopen('php://stderr', 'w');
        fwrite($err_file, self::_create_error_message($msg, __NAMESPACE__.__CLASS__));
    }

    public static function close_connection(){
        self::$pdo_instance = null;
    }

    public static function ping_test(){
        try{
            self::query('SELECT 1');
            return TRUE;
        }catch(Exception $e){
            // self::$_error_log($e->getMessage());
            return FALSE;
        }
    }

    public static function create_new_pdo($options=[]){
        /*
        * On success: Returns True.
        * On failure: Throws exception if fails and if self::$throw_connection_exception
                      is true [default]. Otherwise returns False.
        */
        if(empty($options)){
            $options = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => FALSE,
                PDO::ATTR_STRINGIFY_FETCHES => FALSE,
            );
        }

        $dsn = DB_DRIVER.':host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        self::close_connection();
        try{
            self::$pdo_instance = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
            return TRUE;
        }catch (Exception $e){
            self::_error_log($e->getMessage());
            if(self::$throw_connection_exception){
                throw new PDOException($e->getMessage());
            }
            return FALSE;
        }
    }

    public static function get_instance(){
        if(self::$pdo_instance === null){
            self::create_new_pdo();
        }
        return self::$pdo_instance;
    }

    public static function __callStatic($method, $args){
        try{
            return call_user_func_array(array(self::get_instance(), $method), $args);
        }catch(PDOException $e){
            $emsg = $e->getMessage();
            if(strpos($emsg, 'server has gone away') !== false ||
               strpos($emsg, 'server closed the connection') !== false){
                self::create_new_pdo();
                return call_user_func_array(array(self::get_instance(), $method), $args);
            }else{
                throw $e;
            }
        }
    }

    public static function make_query($sql, $args = [], $options=array()){
        if(!$args){
             return self::query($sql);
        }
        $qobj = self::prepare($sql, $options);
        $qobj->execute($args);
        return $qobj;
    }

    public static function insert_assoc($table, &$assoc, $pk=''){
        $sql = "INSERT INTO $table (";
        $nq = '';
        $vq = '';
        $values = [];
        foreach($assoc as $n=>$v){
            $nq .= "$n,";
            $vq .= "?,";
            $values[] = $v;
        }
        $nq = trim($nq, ',');
        $vq = trim($vq, ',');
        $sql .= "$nq) VALUES ($vq)";
        $res = self::make_query($sql, $values);
        if(!empty($pk)){
            $assoc[$pk] = self::lastInsertId();
        }
        return $res;
    }

    public static function update_assoc($table, $assoc, $where='?', $where_values=[0]){
        $sql = "UPDATE $table SET ";
        $nq = '';
        $values = [];
        foreach($assoc as $n=>$v){
            $nq .= "$n=?,";
            $values[] = $v;
        }
        $nq = trim($nq, ',');
        $sql .= "$nq WHERE $where";
        $values = array_merge($values, $where_values);
        return self::make_query($sql, $values);
    }

    public static function delete_where($table, $where='?', $where_values=[0]){
        $sql = "DELETE FROM $table WHERE $where";
        return self::make_query($sql, $where_values);
    }
}
