<?php namespace Norm;

use PDO;
use Exception;
use PDOException;


class _DB_{
    protected $pdo_instance;
    private $dsn;
    private $db_user;
    private $db_password;
    private $attrs;

    public function __construct($dsn, $db_user, $db_password, $attrs){
        $this->dsn = $dsn;
        $this->db_user = $db_user;
        $this->db_password = $db_password;
        $this->attrs = $attrs;
        $this->create_new_pdo();
    }

    public function create_new_pdo(){
        $this->pdo_instance = new PDO($this->dsn, $this->db_user, $this->db_password, $this->attrs);
    }

    public function close_connection(){
        $this->pdo_instance = null;
    }

    public function __call($method, $args){
        try{
            return call_user_func_array(array($this->pdo_instance, $method), $args);
        }catch(PDOException $e){
            $emsg = $e->getMessage();
            if(strpos($emsg, 'server has gone away') !== false ||
               strpos($emsg, 'server closed the connection') !== false){
                $this->create_new_pdo();
                return call_user_func_array(array($this->pdo_instance, $method), $args);
            }else{
                throw $e;
            }
        }
    }

    public function set_attrs($arr){
        if(empty($attrs)) return array();
        $previous_attrs = $this->attrs;
        foreach($arr as $k=>$v){
            try{
                $this->pdo_instance->setAttribute($k, $v);
                $this->attrs = $arr;
            }catch(PDOException $e){
                $emsg = $e->getMessage();
                if(strpos($emsg, 'server has gone away') !== false ||
                   strpos($emsg, 'server closed the connection') !== false){
                    $this->create_new_pdo();
                    $this->pdo_instance->setAttribute($k, $v); // do this separately
                    // so that attrs does not change on failure
                    $this->attrs = $arr;
                }else{
                    throw $e;
                }
            }
        }
        return $previous_attrs;
    }

    public function make_query($sql, $args = [], $options=array(), $attrs=[]){
        $previous_attrs = $this->set_attrs($attrs);
        $qobj = $this->prepare($sql, $options);
        $qobj->execute($args);
        $this->set_attrs($previous_attrs);
        return $qobj;
    }

    public function insert_assoc($table, &$assoc, $pk=''){
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
        $res = $this->make_query($sql, $values);
        if(!empty($pk)){
            $assoc[$pk] = $this->lastInsertId();
        }
        return $res;
    }

    public function update_assoc($table, $assoc, $where='?', $where_values=[0]){
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
        return $this->make_query($sql, $values);
    }

    public function delete_where($table, $where='?', $where_values=[0]){
        $sql = "DELETE FROM $table WHERE $where";
        return $this->make_query($sql, $where_values);
    }
}


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
    protected static $db_instance;
    public static $throw_connection_exception = true;

    public function __construct() {
        throw new Exception("ERROR: ".__CLASS__." does not allow object instantiation.");
    }

    public function __clone(){
        throw new Exception("ERROR: ".__CLASS__." does not allow object copy.");
    }

    public static function __callStatic($method, $args){
        return call_user_func_array(array(self::get_instance(), $method), $args);
    }

    public static function _create_error_message_($msg, $scope, $prefix="ERROR:"){
        return $prefix.$scope.$msg;
    }

    public static function _error_log_($msg){
        $err_file = fopen('php://stderr', 'w');
        fwrite($err_file, self::_create_error_message_($msg, __NAMESPACE__.__CLASS__));
    }

    public static function ping_test(){
        try{
            self::query('SELECT 1');
            return TRUE;
        }catch(Exception $e){
            // self::$_error_log_($e->getMessage());
            return FALSE;
        }
    }

    public static function close_connection(){
        self::get_instance()->close_connection();
        self::$db_instance = null;
    }

    public static function create_connection($options=[]){
        /*
        * On success: Returns True.
        * On failure: Throws exception if fails and if self::$throw_connection_exception
                      is true [default]. Otherwise returns False.
        */
        if(empty($options)){
            $options = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => TRUE, // must be enabled by default. sanitizes column names.
                // PDO::ATTR_STRINGIFY_FETCHES => FALSE, // this requires PDO::ATTR_EMULATE_PREPARES to be FALSE
            );
        }

        $dsn = DB_DRIVER.':host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        self::close_connection();
        try{
            self::$db_instance = new _DB_($dsn, DB_USER, DB_PASSWORD, $options);
            return TRUE;
        }catch (Exception $e){
            self::_error_log_($e->getMessage());
            if(self::$throw_connection_exception){
                throw new PDOException($e->getMessage());
            }
            return FALSE;
        }
    }

    public static function get_instance(){
        if(self::$db_instance === null){
            self::create_connection();
        }
        return self::$db_instance;
    }

}
