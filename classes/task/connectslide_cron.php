<?php
namespace mod_connectslide\task;

class connectslide_cron extends \core\task\scheduled_task {      
    public function get_name() {
        // Shown in admin screens
        return get_string('connectslidecron', 'connectslide');
    }
                                                                     
    public function execute() { 
        global $CFG;
        mtrace('++ Connect Slideshow Cron Task: start');
        require_once($CFG->dirroot . '/mod/connectslide/lib.php');
        connectslide_cron_task();
        mtrace('++ Connect Slideshow Cron Task: end');
    }                                                                                                                               
} 