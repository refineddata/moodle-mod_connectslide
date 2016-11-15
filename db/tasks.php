<?php
defined('MOODLE_INTERNAL') || die();

$tasks = array(                                                                                                                     
    array(                                                                                                                          
        'classname' => 'mod_connectslide\task\connectslide_cron',                                                                            
        'blocking' => 0,                                                                                                            
        'minute' => '*/15',
        'hour' => '*',                                                                                                              
        'day' => '*',                                                                                                               
        'dayofweek' => '*',                                                                                                         
        'month' => '*'                                                                                                              
    )
);