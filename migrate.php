<?php

require __DIR__ . '/vendor/autoload.php';   // setup autoloader
require 'models.php';                       // import models
require 'db_config.php';                    // import db config

use Norm\Migrate;


// You should add all of your active native[1] models in this list.
// All models that are added in this list will have a corresponding db table.
// The name of the table will be taken from ModelClass:class. If you want to
// use a different name for your model, go to the corresponding model
// class and define a override a static method _get_table_name_ in your model class.
// Do not add abstract models or models that you only use for inheritance in this
// list as they are passive models.
$migrate_native = [
    models\Users::class,
    models\UserProfile::class,
];


// native[1]: models that do not yet have a db table or whose db table was created by this migrate script.
// If your model already have a db table that was created elsewhere and is in sync with current model
// definition, you should add it to the below $migrate_alien list.

// Alien models are those that were created elsewhere and already have
// a definition and a corresponding table. Alien models should be added here
// at least for the first time. After first run, an alien model becomes native
// and thus can be moved to the above $migrate_native list.
$migrate_alien = [
];








################################################################################
########################## Migration runners ###################################
################################################################################

define('APPLY_METHOD_ALL', 'all');
define('APPLY_METHOD_NONE', 'none');
define('APPLY_METHOD_DEFAULT', '');

if(!empty($argv[1]) && $argv[1] == 'apply'){
    $apply_method = APPLY_METHOD_ALL;
    $apply = true;
}
elseif(!empty($argv[1]) && $argv[1] == 'dry'){
    $apply_method = APPLY_METHOD_NONE;
    $apply = false;
}
else{
    $apply_method = APPLY_METHOD_DEFAULT;
    $apply = false;
}



function get_ans(&$apply_method, &$apply, $status){
    $msg = "
Type a command to specify what you want to do:
    yes     I want to apply this migration
    none    I do not want to apply any migration.
    all     I want to apply all migrations (Dangerous action).

Just hit enter to not apply migration for this model.

";
    if(empty($apply_method) && $status){
        echo $msg;
        $ans = readline("Type a command: ");
        readline_add_history($ans);
        if($ans == 'yes' || $ans == 'all'){
            if($ans == 'all'){
                $apply_method = APPLY_METHOD_ALL;
                $apply = true;
            }
            return TRUE;
        }elseif($ans == 'none'){
            $apply_method = APPLY_METHOD_NONE;
        }
    }
    return FALSE;
}

// The native migration runner
foreach($migrate_native as $class){
    $status = Migrate::run($class, $apply);
    if(get_ans($apply_method, $apply, $status)){
        Migrate::run($class, true);
    }
}

// The alien migration runner
foreach($migrate_alien as $class){
    $status = Migrate::run_alien($class, $apply);
    if(get_ans($apply_method, $apply, $status)){
        Migrate::run_alien($class, true);
    }
}

################################################################################
