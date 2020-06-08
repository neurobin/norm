<?php namespace Norm;

use Norm\Model;

class MysqlBase extends Model{

    public static $_col_created_at = 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
    public static $_col_updated_at = 'DATETIME NOT NULL';
    public static function _dfl_updated_at(){
        return date("Y-m-d H:i:s", strtotime("+0 days"));
    }
}
