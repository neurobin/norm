<?php namespace models;

use Norm\MysqlBase; // this base model has two additional fields: updated_at and created_at.

class User extends MysqlBase{

    public static $_col_username = 'varchar(262)';
    public static $_col_password = 'varchar(255)';
    public static $_col_email = 'varchar(255)';

    //default values that will be used in PHP object mapping.
    //These won't change the db schema. If you want to set default values to
    //the db column schema itself, define it accordingly above (e.g DEFAULT NULL or DEFAULT 0 etc.)
    public static $_dfl_username = 'John Doe';
}


class UserProfile extends MysqlBase{
    public static $_col_user_id = 'int';
    public static $_col_first_name = 'varchar(255)';
    public static $_col_last_name = 'varchar(255)';
    public static $_col_address = 'varchar(255)';
    public static $_col_hobby_description = 'text';
    public static $_col_phone_number = 'varchar(35)';
    public static $_col_work_address = 'varchar(300)';


    //default values that will be used in PHP object mapping. (Yes, it can be a function that returns a value)
    //These won't change the db schema. If you want to set default values to
    //the db column schema itself, define it accordingly above (e.g DEFAULT NULL or DEFAULT 0 etc.)
    public static function _dfl_phone(){
        return '(+880)000-000000';
    }
}


class UserPost extends MysqlBase{
    public static $_col_user_id = 'int';
    public static $_col_post_header = 'text';
    public static $_col_post_content = 'text';
}
