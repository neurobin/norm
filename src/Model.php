<?php namespace Norm;

use Norm\_Model_;

abstract class Model extends _Model_{
    /**
     * Base class for inheritance. All models should inherit from this class.
     *
     * It provides a default primary key ('id') and the $_pk_ static variable
     * will be set as `public static $_pk_ = 'id'`.
     *
     * All properties that start with '_col_' in their names, will be regarded as a
     * database column. The $_col_* variables are used as the sql definition of the
     * corresponding column.
     *
     * If you want to define a variable `username` that corresponds to a column on a db table,
     * you can do this:
     *
     * ```php
     * public static $_col_username = 'varchar(262)'; // sql definition of column
     * public static $_dfl_username = 'John Doe'; // default value [optional]
     * // Note that _dfl_username can also be a public (static/non-static) function
     * ```
     *
     */

    public static $_col_id = 'INT PRIMARY KEY AUTO_INCREMENT';
    public static $_pk_ = 'id';
}
