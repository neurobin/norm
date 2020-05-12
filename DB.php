<?php

class DB{
    protected static $pdo_instance;
    public static $throw_connection_exception = true;

    public function __construct() {
        throw Exception("ERROR: ".__CLASS__." does not allow object instantiation.");
    }

    public function __clone(){
        throw Exception("ERROR: ".__CLASS__." does not allow object copy.");
    }

    public static function create_error_message($msg, $scope, $prefix="ERROR:"){
        return $prefix.$scope.$msg;
    }

    public static function errlog($msg){
        $err_file = fopen('php://stderr', 'w');
        fwrite($err_file, self::create_error_message($msg, __NAMESPACE__.__CLASS__));
    }

    public static function close(){
        self::$pdo_instance = null;
    }

    public static function ping(){
        try{
            self::$pdo_instance->query('SELECT 1');
            return TRUE;
        }catch(Exception $e){
            self::$errlog($e->getMessage());
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
                // PDO::ATTR_EMULATE_PREPARES   => FALSE,
            );
        }

        $dsn = DB_DRIVER.':host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        self::close();
        try{
            self::$pdo_instance = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
            return TRUE;
        }catch (Exception $e){
            self::errlog($e->getMessage());
            if(self::$throw_connection_exception){
                throw new PDOException($e->getMessage());
            }
            return FALSE;
        }
    }

    public static function get_instance(){
        if(self::$pdo_instance === null || !self::ping()){
            self::create_new_pdo();
        }
        return self::$pdo_instance;
    }

    public static function __callStatic($method, $args){
        return call_user_func_array(array(self::get_instance(), $method), $args);
    }

    public static function mquery($sql, $args = []){
        if(!$args){
             return self::query($sql);
        }
        $qobj = self::prepare($sql);
        $qobj->execute($args);
        return $qobj;
    }
}
