<?php

require __DIR__ . '/../vendor/autoload.php';    // setup autoloader
require 'models.php';                           // import your models
require 'db_config.php';                        // import db config


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


################################################################################
########################## Migration runner ####################################
################################################################################

Norm\Migrate::run_all($migrate_native, $migrate_alien);

################################################################################
