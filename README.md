A simple ORM built for speedy development. It comes with a migration tool and it uses PDO for communicating with databases. It does not do much abstraction like other ORMs and you will find that you are running SQL queries with PDO, except, it's easier than running those queries with PDO directly.

Also, you can not make spelling mistakes such as 'userrname' when your database schema is defined with correct spelling 'username'. It will warn you immediately where you make this mistake before doing anything with that misspelled model property.

Inheritance allows you to easily create new models based on previous models. Models can be active (with a db table) and passive (without a db table).

> Motivated by Django ORM.

# Database support

The goal is to support all databases supported by PDO. But currently, it is only tested on `MySQL`.

# Example Model

To write a model, you need to inherit from `Norm\Model` class. This model provides a default 'id' column for the primary key.

If you are working with `mysql`, you can inherit from `Norm\MysqlBase`. This one provides two additional columns: `created_at` and `updated_at`. Of course, you can make your own base models and inherit from them if you wish.

For a property `username`, you will define its schema as `$_col_username = 'varchar(65)'` and the default value as `$_dfl_username = 'John Doe'`.

```php
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


    //default values can be defined with functions too.
    public static function _dfl_phone(){
        return '(+880)000-000000';
    }
}


class UserPost extends MysqlBase{
    public static $_col_user_id = 'int';
    public static $_col_post_header = 'text';
    public static $_col_post_content = 'text';
}
```

As you can see, the columns are defined with SQL. You will be working very closely with SQL. This enables us to omit a lot of abstraction layers that would be quite unnecessary code bloat with degrading performance.

* `_col_<column_name>`: Defines a column name.
* `_dfl_<column_name>`: Defines the default value for the column name. It can be a variable or a function that returns the default value.

## Note

All member variable names not starting with an underscore are processed as though they are model properties corresponding to database columns. Thus you will get an error if you misspell any model property, because the models only allow access to pre-defined properties. If you want to add custom properties, use an underscore at the beginning of the name like `_username`.

# Working with data

You should use `Model::_save()` function for creation or update, and `Model::_delete()` for deletion.

## Create

```php
$user = new User(array(
    'username'=> 'JahidulHamid',
    'password'=> 'fjdsfljadf;sdf',
    'email'=> 'fdslfjds@fdsl.com',
));

$user->_save();
```

Or

```php
$user = new User();
$user->username = 'JahidulHamid';
$user->password ='fjdsfljadf;sdf';
$user->email = 'fdslfjds@fdsl.com';
$user->_save();
```

## Update

```php
$user14 = User::_get(14);
$user14->username = 'JohnDoe';
$user14->_save();
```

## Delete

```php
$user14 = User::_get(14);
$user14->_delete();
```

## Read (select)

You have several methods for this:

1. `_select`: This method will let you pass column names (as sql e.g `username,email`) and WHERE query to make a SELECT query. It uses PDO, thus you can pass where_query along with where_values array (e.g `where_query='email=?'`, `where_values=['someone@something.com']`) to make a prepared query.
2. `_filter`: Wrapper around `_select`. It fetches all columns, thus you only pass the WHERE query.
3. `_first`: Wrapper around `_filter`. Brings the first item.
4. `_get`: Get the item with pk (primary key).


## Direct SQL:

Use PDO. You can use `Norm\DB` as your shortcut to PDO and make queries like `DB::<query_function>`. Connection closed issue for long-running processes (like web scraper) will be handled for you.

There is a shortcut function `DB::make_query($sql, $args, $options, $attrs)`:

* `$sql`: Fed into a prepared statement using `$options`
* `$args`: Fed into `execute` method.
* `$attrs`: These attributes are set to the db connection handle, and reverted back after performing the query.


# Migration

Copy the `migrate.php.ini` file somewhere and rename it to `migrate.php`. Now you can run this file `php migrate.php` to do migrations. But before that, edit this file and put the list of models that you want to migrate. All of these will be active models that will have their own db tables.

It looks like this:

```php
// You should add all of your active native[1] models in this list.
// All models that are added in this list will have a corresponding db table.
// The name of the table will be taken from ModelClass:class. If you want to
// use a different name for your model, go to the corresponding model
// class and define a override a static method _get_table_name_ in your model class.
// Do not add abstract models or models that you only use for inheritance in this
// list as they are passive models.
$migrate_native = [
    models\User::class,         // example
    models\UserProfile::class,  // example
    models\UserPost::class,     // example
];


// native[1]: models that do not yet have a db table or their db table was created
// by this migrate script. If your model already have a db table that was created
// elsewhere and is in sync with current model definition, you should add it to
// the below $migrate_alien list.

// Alien models are those that were created elsewhere and already have
// a definition and a corresponding table. Alien models should be added here
// at least for the first time. After first run, an alien model becomes native
// and thus can be moved to the above $migrate_native list.
$migrate_alien = [
];
```

Now do a dry run (`php migrate.php dry`) and check the changes:

```bash
$ php migrate.php dry

======================
: models\User [DryRun]
======================
> MODIFY: username: varchar(262) --> varchar(62)
> RENAME: email --> email_id varchar(255)

=============================
: models\UserProfile [DryRun]
=============================
> No changes detected.

==========================
: models\UserPost [DryRun]
==========================
> ADD   : post_sub_header text
```

After reviewing the changes, you can apply the migration with `php migrate.php apply`:


```bash
$ php migrate.php apply

=============
: models\User
=============
> MODIFY: username: varchar(262) --> varchar(62)
> RENAME: email --> email_id varchar(255)

====================
: models\UserProfile
====================
> No changes detected.

=================
: models\UserPost
=================
> ADD   : post_sub_header text
```

You can also run an interactive migration with `php migrate.php`:

```bash
$ php migrate.php

======================
: models\User [DryRun]
======================
> MODIFY: username: varchar(62) --> varchar(262)
> RENAME: email_id --> email varchar(255)

Type a command to specify what you want to do:
    yes     I want to apply this migration
    none    I do not want to apply any migration.
    all     I want to apply all migrations (Dangerous/Blind action).

Just hit enter to not apply migration for this model.

Type a command:
```


# Customizations

* Table name: Define `public static function _get_table_name_()` in your model that returns the table name as string.
* Migration directory: Define `public static function _get_migration_dir_()` in your model and return the dir path as string. Path must **not** contain `/` at end.
* Migration base dir: Define `public static $_migration_base_dir_` in your model. It is ignored if a custom `_get_migration_dir_()` is used.
