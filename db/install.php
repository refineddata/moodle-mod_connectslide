<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_connectslide_install() {
    global $DB, $CFG;
   
    // not proceed if mod connect is not installed.

    $connect = $DB->get_record('config_plugins', array('plugin' => 'mod_connect', 'name' => 'version'));
    if (empty($connect))
        return true;
    
    // copy from connect table for meeting type to connectslide table
    
    $sql = "insert into {connectslide} 
(id,course,name,intro,introformat,url,start,display,displayoncourse,type,email,eventid,unenrol,compdelay,complete,autocert,detailgrading,initdelay,
loops,loopdelay,maxviews,addinroles,ac_archive,ac_type,ac_phone,ac_pphone,ac_id,ac_views,template_start,telephony_start,timemodified,duration)
select id,course,name,intro,introformat,url,start,display,displayoncourse,type,email,eventid,unenrol,compdelay,complete,autocert,detailgrading,initdelay,
loops,loopdelay,maxviews,addinroles,ac_archive,ac_type,ac_phone,ac_pphone,ac_id,ac_views,template_start,telephony_start,timemodified,duration
from {connect} where type='slide'";
    
    $DB->execute($sql);
    
    // copy from connect_entries table for meeting type to connectslide_entries table
    
    $sql = "insert into {connectslide_entries} 
(id, connectslideid, userid, score, slides, minutes, positions, `type`, views, grade, rechecks, rechecktime, timemodified)
select e.id, e.connectid, e.userid, e.score, e.slides, e.minutes, e.positions, e.`type`, e.views, e.grade, e.rechecks, e.rechecktime, e.timemodified
from {connect_entries} e join
{connect} c on e.connectid = c.id 
where c.type = 'slide'";
    
    $DB->execute($sql);    
    
    // copy from connect_grading table for meeting type to connectslide_grading table
    
    $sql = "insert into {connectslide_grading} 
(id, connectslideid, threshold, grade, timemodified)
select g.id, g.connectid, g.threshold, g.grade, g.timemodified
from {connect_grading} g join
{connect} c on g.connectid = c.id 
where c.type = 'slide' order by g.id";
    
    $DB->execute($sql);    
       
    // alter course module
    
    $module = $DB->get_record('modules', array('name'=>'connectslide'));
    
    $sql = "update {course_modules} cm
join {modules} m on
    cm.module = m.id and m.name = 'connect' 
join {connect} c on c.id = cm.instance and c.`type` = 'slide'    
set module = $module->id";
    
    $DB->execute($sql);
    
    //alter grade_items
    
    $sql = "update {grade_items} gi
join {connect} c on gi.itemmodule = 'connect' and gi.iteminstance = c.id and c.`type` = 'slide'  
set itemmodule = 'connectslide'";
    
    $DB->execute($sql);
    
   /* //delete connect_recurring for meeting
    
    $sql = "delete from {connect_recurring} where id in (select id from {connectslide_recurring} )";
    
    $DB->execute($sql);
    
    //delete connect_recurring for meeting
    
    $sql = "delete from {connect_grading} where id in (select id from {connectslide_grading} )";
    
    $DB->execute($sql);
    
    //delete connect_recurring for meeting
    
    $sql = "delete from {connect_entries} where id in (select id from {connectslide_entries} )";
    
    $DB->execute($sql);
    
    //delete connect for meeting
    
    $sql = "delete from {connect} where id in (select id from {connectslide} )";
    
    $DB->execute($sql);*/
    
    //Update refined service  
    require_once($CFG->dirroot . '/mod/connectslide/connectlib.php');
    $external_connect_ids = $DB->get_fieldset_select('connectslide', 'id', '', array());
    if (!empty($external_connect_ids)) connect_update_connect_meetings($external_connect_ids, 'slide');
    
    //Hide connect activity    
    $module = $DB->get_record('modules', array('name'=>'connect'));
    
    if (!empty($module) && ($module->visible == 1)) {
        $module->visible = 0;
        $DB->update_record('modules', $module);
    }
    
    // diable Mod Connect Cron    
    $cron = $DB->get_record('task_scheduled', array('component'=>'mod_connect'));
    
    if (!empty($cron) && ($cron->disabled == 0)) {
        $cron->disabled = 1;
        $DB->update_record('task_scheduled', $cron);
    }
}

