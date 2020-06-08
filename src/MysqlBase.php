<?php namespace Norm;

use Norm\Model;

class MysqlBase extends Model{

    public static $_col_created_at = 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
    public static $_col_updated_at = 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
}
