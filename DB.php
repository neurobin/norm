<?php

class DB{
    protected static $pdo_instance;
    protected static $pdo;

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

    public static function create_new_pdo($opt=[]){
        if(empty($opt)){
            $opt = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // PDO::ATTR_EMULATE_PREPARES   => FALSE,
            );
        }

        $dsn = DB_DRIVER.':host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        self::close();
        try{
            self::$pdo_instance = new PDO($dsn, DB_USER, DB_PASSWORD, $opt);
            return TRUE;
        }catch (Exception $e){
            self::errlog($e->getMessage());
            return FALSE;
        }
    }

    public static function get_instance(){
        if(self::$pdo_instance === null || !self::ping()){
            self::create_new_pdo();
        }
        return self::$pdo_instance;
    }

    // a proxy to native PDO methods
    public function __call($method, $args)
    {
        return call_user_func_array(array($this->pdo, $method), $args);
    }

    // a helper function to run prepared statements smoothly
    public function run($sql, $args = [])
    {
        if (!$args)
        {
             return $this->query($sql);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt;
    }
}
