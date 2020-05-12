<?php

$pres = [
    'a'=> 3,
    'g'=> 2,
    'h'=> 3,
    'i'=> 4,
    'f'=> 2,
    'b'=> 4,
];

$curs = [
];


function _get_changed_props($curs, $pres){
    $ops = [];
    while(($cur=each($curs)) | ($pre=each($pres))){

        //handle cur properties
        if(!empty($cur['key'])){
            if(array_key_exists($cur['key'], $pres)){
                //cur_key exists in the pres, it has not been deleted but may be modified
                if($cur['value'] != $pres[$cur['key']]){
                    //modified
                    $ops[$cur['key']] = [
                        'op'=> 'mod',
                        'cur_key'=> $cur['key'],
                        'cur_val'=> $cur['value'],
                    ];
                }else{
                    //unmodified
                    //pass
                }
            }else{
                //cur_key does not exist in pres, it has been added a new.
                $ops[$cur['key']] = [
                    'op'=> 'add',
                    'cur_key'=> $cur['key'],
                    'cur_val'=> $cur['value'],
                ];
            }
        }
        //handle pre properties
        if(!empty($pre['key'])){
            if(array_key_exists($pre['key'], $curs)){
                //it has already been handled by cur handler
                //nothing to do here
            }else{
                // key is either deleted or renamed
                if(!empty($cur['key']) && !array_key_exists($cur['key'], $pres)){
                    //renamed
                    $ops[$cur['key']] = [
                        'op'=> 'rename',
                        'cur_key'=> $cur['key'],
                        'cur_val'=> $cur['value'],
                        'pre_key'=> $pre['key'],
                    ];
                }else{
                    //deleted
                    $ops[$pre['key']] = [
                        'op'=> 'delete',
                        'pre_key'=> $pre['key'],
                    ];
                }

            }
        }
    }
    return $ops;
}


$d = _get_changed_props($curs, $pres);

var_dump($d);

function _get_alter_column_sql($op, $table_name){
    if($op == 'rename'){
        $sql = "ALTER TABLE $table_name CHANGE COLUMN {$op['pre_key']} TO {$op['cur_key']} {$op['cur_val']};";
    }elseif($op == 'mod'){
        $sql = "ALTER TABLE $table_name MODIFY {$op['cur_key']} {$op['cur_val']};";
    }elseif($op == 'delete'){
        $sql = "ALTER TABLE $table_name DROP COLUMN {$op['pre_key']};";
    }elseif($op == 'add'){
        $sql = "ALTER TABLE $table_name ADD {$op['cur_key']} {$op['cur_val']};";
    }else{
        $sql = '';
    }
    return $sql;
}
