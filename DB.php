<?php

class DB{
    protected static $pdo_instance;
    protected $pdo;

    public function __construct() {

    }

    public function __clone(){}

    public static function err($msg){
        $err_file = fopen('php://stderr', 'w');
        fwrite($err_file, "ERROR:".__METHOD__.": $msg");
    }

    public static function close(){
        self::$pdo_instance = null;
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
        try{
            $this->pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $opt);
            return TRUE;
        }catch (Exception $e){
            self::err($e->getMessage());
            return FALSE;
        }
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
