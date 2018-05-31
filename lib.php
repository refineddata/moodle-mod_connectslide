<?php // $Id: lib.php
/**
 * Library of functions and constants for module connect
 *
 * @author  Gary Menezes
 * @version $Id: lib.php
 * @package connect
 **/

require_once($CFG->dirroot . '/mod/connectslide/connectlib.php');
require_once($CFG->dirroot . '/lib/completionlib.php');

global $PAGE;
//$PAGE->requires->js('/mod/connectslide/js/mod_connectslide_coursepage.js');

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted connect record
 **/
function connectslide_add_instance($connectslide) {
    global $CFG, $USER, $COURSE, $DB;
    require_once($CFG->libdir . '/gdlib.php');

    $cmid = $connectslide->coursemodule;

    $connectslide->timemodified = time();
    // complete url for video to check
    
    if (empty($connectslide->url) and !empty($connectslide->newurl)) {
        $connectslide->url = $connectslide->newurl;
    }
    
    
    	$connectslide->url = preg_replace( '/\//', '', $connectslide->url ); // if someone tries to save with slashes, get ride of it
    

    $connectslide->display = '';
    $connectslide->complete = 0;
    
    if( isset( $connectslide->addinroles ) && is_array( $connectslide->addinroles ) ){
    	$connectslide->addinroles = implode( ',', $connectslide->addinroles );
    }
    
    if( !isset( $connectslide->displayoncourse ) ) $connectslide->displayoncourse = 0;

    //insert instance
    if ($connectslide->id = $DB->insert_record("connectslide", $connectslide)) {
        // Update display to include ID and save custom file if needed
        $connectslide = connectslide_set_forceicon($connectslide);
        $display = connectslide_translate_display($connectslide);
        if ($display != $connectslide->display) {
            $DB->set_field('connectslide', 'display', $display, array('id' => $connectslide->id));
            $connectslide->display = $display;
        }

        // Save the grading
        $DB->delete_records('connectslide_grading', array('connectslideid' => $connectslide->id));
        if (isset($connectslide->detailgrading) && $connectslide->detailgrading) {
            for ($i = 1; $i < 4; $i++) {

                $grading = new stdClass;
                $grading->connectslideid = $connectslide->id;
                if ($connectslide->detailgrading == 3) {
                    $grading->threshold = $connectslide->vpthreshold[$i];
                    $grading->grade = $connectslide->vpgrade[$i];
                } else {
                    $grading->threshold = $connectslide->threshold[$i];
                    $grading->grade = $connectslide->grade[$i];
                }
                if (!$DB->insert_record('connectslide_grading', $grading, false)) {
                    return "Could not save connect grading.";
                }
            }
        }

        if (isset($connectslide->reminders) && $connectslide->reminders) {
            $event = new stdClass();
            $event->name = $connectslide->name;
            $event->description = isset($connectslide->intro) ? $connectslide->intro : '';
            $event->format = 1;
            $event->courseid = $connectslide->course;
            $event->modulename = (empty($CFG->connect_courseevents) OR !$CFG->connect_courseevents) ? 'connectslide' : '';
            $event->instance = (empty($CFG->connect_courseevents) OR !$CFG->connect_courseevents) ? $connectslide->id : 0;
            $event->eventtype = 'course';
            $event->timestart = $connectslide->start;
            $event->timeduration = $connectslide->duration;
            $event->uuid = '';
            $event->visible = 1;
            $event->acurl = $connectslide->url;
            $event->timemodified = time();

            if ($event->id = $DB->insert_record('event', $event)) {
                $DB->set_field('connectslide', 'eventid', $event->id, array('id' => $connectslide->id));
                $connectslide->eventid = $event->id;
                if (isset($CFG->local_reminders) AND $CFG->local_reminders) {
                    require_once($CFG->dirroot . '/local/reminders/lib.php');
                    reminders_update($event->id, $connectslide);
                }
            }
        }
        // Create meeting on connect
            if (!empty($connectslide->url)) {
            	if (!empty($COURSE)) $course = $COURSE;
            	else $course = $DB->get_record('course', 'id', $connectslide->course);
            	$result = connect_use_sco($connectslide->id, $connectslide->url, $connectslide->type, $course->id);
            	if (!$result) {
            		return false;
            	}
            }
    }

    //create grade item for locking
    $entry = new stdClass;
    $entry->grade = 0;
    $entry->userid = $USER->id;
    connectslide_gradebook_update($connectslide, $entry);

    connectslide_update_from_adobe( $connectslide );

    return $connectslide->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function connectslide_update_instance($connectslide) {
    global $CFG, $DB;

    $connectslide->timemodified = time();
    

    if (!isset($connectslide->detailgrading)) {
        $connectslide->detailgrading = 0;
    }

    if (isset($connectslide->iconsize) && $connectslide->iconsize == 'custom') {
        $connectslide = connectslide_set_forceicon($connectslide);
    } else {
        $connectslide->forceicon = '';
    }
    $connectslide->display = connectslide_translate_display($connectslide);
    $connectslide->complete = 0;
    
    
    	$connectslide->url = preg_replace( '/\//', '', $connectslide->url ); // if someone tries to save with slashes, get ride of it
    
    
    if( isset( $connectslide->addinroles ) && is_array( $connectslide->addinroles ) ){
    	$connectslide->addinroles = implode( ',', $connectslide->addinroles );
    }
    
    if( !isset( $connectslide->displayoncourse ) ) $connectslide->displayoncourse = 0;
    
    //update instance
    if (!$DB->update_record("connectslide", $connectslide)) {
        return false;
    }

    // Save the grading
    $DB->delete_records('connectslide_grading', array('connectslideid' => $connectslide->id));
    if (isset($connectslide->detailgrading) && $connectslide->detailgrading) {
        for ($i = 1; $i < 4; $i++) {
            $grading = new stdClass;
            $grading->connectslideid = $connectslide->id;
            if ($connectslide->detailgrading == 3) {
                $grading->threshold = $connectslide->vpthreshold[$i];
                $grading->grade = $connectslide->vpgrade[$i];
            } else {
                $grading->threshold = $connectslide->threshold[$i];
                $grading->grade = $connectslide->grade[$i];
            }
            $grading->timemodified = time();
            if (!$DB->insert_record('connectslide_grading', $grading, false)) {
                return false;
            }
        }
    }

    if (isset($connectslide->reminders) && $connectslide->reminders) {
        if (isset($connectslide->eventid) AND $connectslide->eventid){
        	$event = $DB->get_record('event', array('id' => $connectslide->eventid));
        }else{
        	$event = new stdClass();
        }

        $event->name = $connectslide->name;
        $event->description = isset($connectslide->intro) ? $connectslide->intro : '';
        $event->format = 1;
        $event->courseid = $connectslide->course;
        $event->modulename = (empty($CFG->connect_courseevents) OR !$CFG->connect_courseevents) ? 'connectslide' : '';
        $event->instance = (empty($CFG->connect_courseevents) OR !$CFG->connect_courseevents) ? $connectslide->id : 0;
        $event->timestart = $connectslide->start;
        $event->timeduration = $connectslide->duration;
        $event->visible = 1;
        $event->uuid = '';
        $event->sequence = 1;
        $event->acurl = $connectslide->url;
        $event->timemodified = time();

        if (isset($event->id) AND $event->id) $DB->update_record('event', $event);
        else $event->id = $DB->insert_record('event', $event);

        if (isset($event->id) AND $event->id) {
            if ($connectslide->eventid != $event->id) $DB->set_field('connectslide', 'eventid', $event->id, array('id' => $connectslide->id));

            if (isset($CFG->local_reminders) AND $CFG->local_reminders) {
                $DB->delete_records('reminders', array('event' => $event->id));
                require_once($CFG->dirroot . '/local/reminders/lib.php');
                reminders_update($event->id, $connectslide);
            }
        }
    } elseif (isset($connectslide->eventid) AND $connectslide->eventid) {
        $DB->delete_records('reminders', array('event' => $connectslide->eventid));
        $DB->delete_records('event', array('id' => $connectslide->eventid));
    }

    // Update connect
    if (isset($CFG->connect_update) AND $CFG->connect_update AND !empty($connectslide->url)) {
        $date_begin = 0;
        $date_end = 0;
        if (isset($CFG->connect_updatedts) AND $CFG->connect_updatedts && isset( $connectslide->start ) && isset( $connectslide->duration )) {
            $date_begin = $connectslide->start;
            $date_end = $connectslide->start + $connectslide->duration;
        }
        connect_update_sco($connectslide->id, $connectslide->name, $connectslide->intro, $date_begin, $date_end, 'slide');
    }

    //create grade item for locking
    global $USER;
    $entry = new stdClass;
    $entry->grade = 0; 
    $entry->userid = $USER->id;
    connectslide_gradebook_update($connectslide, $entry);

    connectslide_update_from_adobe( $connectslide );

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function connectslide_delete_instance($id) {
    global $DB;

    if (!$connectslide = $DB->get_record('connectslide', array('id' => $id))) {
        return false;
    }

    // Delete area files (must be done before deleting the instance)
    $cm = get_coursemodule_from_instance('connectslide', $id);
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_connectslide');

    // Delete dependent records
    if (isset($connectslide->eventid) AND $connectslide->eventid) $DB->delete_records('reminders', array('event' => $connectslide->eventid));
    if (isset($connectslide->eventid) AND $connectslide->eventid) $DB->delete_records('event', array('id' => $connectslide->eventid));

    // Delete connect records
    $DB->delete_records("connectslide_grading", array("connectslideid" => $id));
    $DB->delete_records("connectslide_entries", array("connectslideid" => $id));
    //$DB->delete_records("connectslide_recurring", array("connectslideid" => $id));
    $DB->delete_records("connectslide", array('id' => $id));

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 **/
function connectslide_user_outline($course, $user, $mod, $connectslide) {
    global $DB;

    if ($grade = $DB->get_record('connectslide_entries', array('userid' => $user->id, 'connectslideid' => $connectslide->id))) {

        $result = new stdClass;
        if ((float)$grade->grade) {
            $result->info = get_string('grade') . ':&nbsp;' . $grade->grade;
        }
        $result->time = $grade->timemodified;
        return $result;
    }
    return NULL;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 **/
function connectslide_user_complete($course, $user, $mod, $connectslide) {
    global $DB;

    if ($grade = $DB->get_record('connectslide_entries', array('userid' => $user->id, 'connectslideid' => $connectslide->id))) {
        echo get_string('grade') . ': ' . $grade->grade;
        echo ' - ' . userdate($grade->timemodified) . '<br />';
    } else {
        print_string('nogrades', 'connectslide');
    }

    return true;
}



/**
 * Runs each time cron runs.
 *  Updates meeting completion and recurring meetings.
 *  Gets and processes entries who's recheck time has elapsed.
 *
 * @return boolean
 **/
function connectslide_cron_task() {
    echo '+++++ connectslide_cron+++++'."\n";
    global $CFG, $DB;
    $now = time();

    
    //Instant Grading - just return
    //if (isset($CFG->connect_instant_grade) AND $CFG->connect_instant_grade == 1) return true;
    //echo "SELECT * FROM {$CFG->prefix}connectslide_entries WHERE rechecks > 0 AND rechecktime < $now \n";
    //Entries Every 15min

    if (!$entries = $DB->get_records_sql("SELECT * FROM {$CFG->prefix}connectslide_entries WHERE rechecks > 0 AND rechecktime < $now")) return true;

    foreach ($entries as $entry) {
        
        //echo json_encode($entry);
        if (!$connectslide = $DB->get_record("connectslide", array("id" => $entry->connectslideid))) break;
        
        if (!$user = $DB->get_record("user", array("id" => $entry->userid))) break;
                
        $entry->timemodified = time();
        $entry->rechecks--;
        $entry->rechecktime = time() + $connectslide->loopdelay;
        if ($entry->rechecks < 0) $entry->rechecktime = 0;
        
        $oldgrade = isset( $entry->grade ) ? $entry->grade : 0;

        if (!connectslide_grade_entry($user->id, $connectslide, $entry)) continue;

        $DB->update_record('connectslide_entries', $entry);

        if ($entry->grade == 100 AND $cm = get_coursemodule_from_instance('connectslide', $connectslide->id)) {
            // Mark Users Complete
            if ($cmcomp = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cm->id, 'userid' => $user->id))) {
                $cmcomp->completionstate = 1;
                $cmcomp->viewed = 1;
                $cmcomp->timemodified = time();
                $DB->update_record('course_modules_completion', $cmcomp);
            } else {
                $cmcomp = new stdClass;
                $cmcomp->coursemoduleid = $cm->id;
                $cmcomp->userid = $user->id;
                $cmcomp->completionstate = 1;
                $cmcomp->viewed = 1;
                $cmcomp->timemodified = time();
                $DB->insert_record('course_modules_completion', $cmcomp);
            }
            rebuild_course_cache($connectslide->course);
        }
    }
    return true;
}

function connectslide_grade_based_on_range( $userid, $connectslideid, $startdaterange, $enddaterange, $regrade ){
    if( function_exists( 'local_connect_grade_based_on_range' ) ){
        return local_connect_grade_based_on_range( $userid, $connectslideid, $startdaterange, $enddaterange, $regrade, 'connectslide' );
    }else{
        return false;
    }
}

function connectslide_complete_meeting($connectslide, $startdaterange = 0, $enddaterange = 0) {
    global $CFG, $DB;

    $regrade = $startdaterange ? 1 : 0; // if we are passed a date range, this is a regrade
    if( !$startdaterange ){
        $startdaterange = $connectslide->start;
        $enddaterange = $connectslide->start + $connectslide->compdelay + (60*60*2);
    }

    if ($connectslide->start > 0 AND ($connectslide->start + $connectslide->compdelay) < time() AND $connectslide->complete == 0) {
        $complete = true;
    } else {
        $complete = false;
    }

    $cm = get_coursemodule_from_instance('connectslide', $connectslide->id);
    if ($cm && connectslide_grade_meeting(0, '', $connectslide, $startdaterange, $enddaterange, $regrade)) {
        $context = context_course::instance($connectslide->course);
        $course = $DB->get_record('course', array('id' => $connectslide->course));
        if ($users = get_enrolled_users($context)) {
            //Certificate Setup
            if ($DB->get_record('modules', array('name' => 'certificate'))) {
                global $certificate; // To deal with bad code in certificate_issue;
                if ($connectslide->autocert AND $certificate = $DB->get_record('certificate', array('id' => $connectslide->autocert))) {
                    require_once($CFG->dirroot . '/mod/certificate/lib.php');
                    require_once($CFG->libdir . '/pdflib.php');
                    $cmcert = get_coursemodule_from_instance('certificate', $certificate->id);
                    $certctx = get_context_instance(CONTEXT_MODULE, $cmcert->id);
                }
            }

            //Loop through each user
            foreach ($users as $user) {
                
                // skip them if they have a grade outside the range
                if( !connectslide_grade_based_on_range( $user->id, $connectslide->id, $startdaterange, $enddaterange, $regrade ) ) continue;

                if ($grade = $DB->get_field('connectslide_entries', 'grade', array('connectslideid' => $connectslide->id, 'userid' => $user->id)) AND $grade == 100) {
                    // Mark Users Complete
                    if ($cmcomp = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cm->id, 'userid' => $user->id))) {
                        $cmcomp->completionstate = 1;
                        $cmcomp->viewed = 1;
                        $cmcomp->timemodified = time();
                        $DB->update_record('course_modules_completion', $cmcomp);
                    } else {
                        $cmcomp = new stdClass;
                        $cmcomp->coursemoduleid = $cm->id;
                        $cmcomp->userid = $user->id;
                        $cmcomp->completionstate = 1;
                        $cmcomp->viewed = 1;
                        $cmcomp->timemodified = time();
                        $DB->insert_record('course_modules_completion', $cmcomp);
                    }

                    // Issue Certificates
                    if (!empty($certctx) AND !$DB->get_record('certificate_issues', array('certificateid' => $certificate->id, 'userid' => $user->id, 'notified' => 1))) {
                        global $USER, $pdf, $certificate;
                        $session_user = $USER;
                        //TODO: Remove the $USER cloning
                        $USER = clone($user);
                        if ($certrecord = certificate_get_issue($course, $user, $certificate, $cmcert)) {
                            //RT-1458 Certificates not being emailed / Activity completion not updating for certificates when issued from meeting completion.
                            //It is because we remove $certificate->savecert setting not to save pdf file in the file system
                            //if ($certificate->savecert) {

                                $studentname = '';
                                $student = $user;
                                $certrecord->studentname = $student->firstname . ' ' . $student->lastname;

                                $classname = '';
                                $certrecord->classname = $course->fullname;

                                require($CFG->dirroot . '/mod/certificate/type/' . $certificate->certificatetype . '/certificate.php');
                                $file_contents = $pdf->Output('', 'S');
                                $filename = clean_filename($certificate->name . '.pdf');
                                certificate_save_pdf($file_contents, $certrecord->id, $filename, $certctx->id, $user);

                                if ($certificate->delivery == 2) {
                                    certificate_email_student($course, $certificate, $certrecord, $certctx, $user, $file_contents);
                                }
                            
                                // Mark certificate as viewed
                                $cm = get_coursemodule_from_instance('certificate', $certificate->id, $certificate->course);
                                $completion = new completion_info($course);
                                $completion->set_module_viewed($cm, $user->id);
                            //}
                        }
                        $USER = $session_user;
                    }
                } else $grade = 0;

                // Unenrol All(1), Attended(2) or Absent(3)
                if ( !$regrade && ( ($grade == 100 AND $connectslide->unenrol == 2) OR ($complete AND ($connectslide->unenrol == 1 OR ($grade < 100 AND $connectslide->unenrol == 3))))) {
                    if ($enrols = $DB->get_records_sql("SELECT e.* FROM {$CFG->prefix}user_enrolments u, {$CFG->prefix}enrol e WHERE u.enrolid = e.id AND u.userid = {$user->id} AND e.courseid = {$connectslide->course}")) {
                        foreach ($enrols as $enrol) {
                            $plugin = enrol_get_plugin($enrol->enrol);
                            $plugin->unenrol_user($enrol, $user->id);
                        }
                    }
                    role_unassign($CFG->studentrole, $user->id, $context->id);
                }
            }
        }

        // Attendance Report
        if ( !$regrade && $complete AND !empty($connectslide->email)) {
            require_once($CFG->dirroot . '/filter/connect/lib.php');
            if (!$to = $DB->get_record('user', array('email' => $connectslide->email))) {
                $to = new stdClass;
                $to->firstname = 'Attendance';
                $to->lastname = 'Report';
                $to->email = $connectslide->email;
                $to->mailformat = 1;
                $to->maildisplay = true;
            }
            $subj = 'Attendance Report for ' . $connectslide->url;
            $body = connectslide_attendance_output($connectslide->url);
            $text = html_to_text($body);
            email_to_user($to, 'LMS Admin', $subj, $text, $body);
        }

        if (!$regrade && $complete) {
            // Next instance or mark complete
            if ($instance = $DB->get_record_sql("SELECT * FROM {$CFG->prefix}connectslide_recurring WHERE connectslideid={$connectslide->id} AND record_used=0 ORDER BY start LIMIT 1")) {
                $newurl = false;
                if ($connectslide->url != $instance->url) $newurl = true;

                $connectslide->start = $instance->start;
                $connectslide->display = str_replace($connectslide->url, $instance->url, $connectslide->display);
                $connectslide->url = $instance->url;
                $connectslide->email = $instance->email;
                $connectslide->eventid = $instance->eventid;
                $connectslide->unenrol = $instance->unenrol;
                $connectslide->compdelay = $instance->compdelay;
                $connectslide->autocert = $instance->autocert;
                $connectslide->timemodified = time();

                // Update Adobe
                $date_begin = 0;
                $date_end = 0;
                if (isset($CFG->connect_updatedts) AND $CFG->connect_updatedts) {
                    $date_begin = $connectslide->start;
                    $date_end = $connectslide->start + $instance->duration;
                }
                connect_update_sco($connectslide->id, $connectslide->name, $connectslide->intro, $date_begin, $date_end, 'slide');

                if (isset($newurl) AND $newurl) connect_add_access($connectslide->id, $course->id, 'group', 'view', false, 'slide');

                // Update Grouping
                if (isset($instance->groupingid) AND $instance->groupingid AND $cm) {
                    $cm->groupingid = $instance->groupingid;
                    $DB->update_record('course_modules', $cm);
                }

                $instance->record_used = 1;
                $DB->update_record('connectslide_recurring', $instance);
            } else $connectslide->complete = 1;

            rebuild_course_cache($connectslide->course);
            $DB->update_record('connectslide', $connectslide);
        }
    }

    return;
}

function connectslide_process_options(&$connectslide) {
    return true;
}

function connectslide_install() {
    return true;
}

function connectslide_get_view_actions() {
    return array('launch', 'view all');
}

function connectslide_get_post_actions() {
    return array('');
}

function connectslide_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return false;

        default:
            return null;
    }
}

function connectslide_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;

    return $DB->record_exists('connectslide_entries', array('connectslideid' => $cm->instance, 'userid' => $userid));
}

function connectslide_cm_info_dynamic($mod) {
    global $DB, $USER;

    if (!$mod->available) return;

    $connectslide = $DB->get_record('connectslide', array('id' => $mod->instance));
    if (!empty($connectslide->display) && $connectslide->displayoncourse) {
        
            $mod->set_content( connectslide_create_display( $connectslide ) );
        
        // If set_no_view_link is TRUE - it's not showing on Activity Report (https://app.liquidplanner.com/space/73723/projects/show/9961959)
        if( method_exists( $mod, 'rt_set_no_view_link' ) ){
            $mod->rt_set_no_view_link();
        }
    }
    return;
}

function connectslide_cm_info_view($mod) {
    global $CFG, $OUTPUT, $DB;    
    return;
}

//////////////////////////////////////////////////////////////////////////////////////
/// Any other connect functions go here.  Each of them must have a name that
/// starts with connect_

/**
 * Called from /filters/connect/launch.php each time connect is launched.
 * Works out if it is an activity, and if so, updates the grade or sets up cron to.
 *
 * @param string $acurl The unique connect url for the resource
 * @param boolean $fullupdate Whethr all information should be updated even if max grade reached
 **/
function connectslide_launch($acurl, $courseid = 1, $regrade = false, $cm = 0) {
    global $CFG, $USER, $DB, $PAGE;

    if (!$connectslide = $DB->get_record('connectslide', array('url' => $acurl, 'course' => $courseid), '*', IGNORE_MULTIPLE)) {
        return;
    }

    if (!$entry = $DB->get_record('connectslide_entries', array('userid' => $USER->id, 'connectslideid' => $connectslide->id))) {
        $entry = new stdClass;
        $entry->connectslideid = $connectslide->id;
        $entry->userid = $USER->id;
        $entry->type = $connectslide->type;
        $entry->views = 0;
    }

    if (!is_siteadmin() AND isset($CFG->connect_maxviews) AND $CFG->connect_maxviews >= 0 AND isset($connectslide->maxviews) AND $connectslide->maxviews > 0 AND $connectslide->maxviews <= $entry->views) {
        $PAGE->set_url('/');
        notice(get_string('overmaxviews', 'connectslide'), $CFG->wwwroot . '/course/view.php?id=' . $connectslide->course);
    }

    $entry->timemodified = time();
    $entry->views++;
    
    $oldgrade = isset( $entry->grade ) ? $entry->grade : 0;

    // Without detail grading, just set the grade to 100 and return
    if (!$connectslide->detailgrading) {
        $entry->grade = 100;
        connectslide_gradebook_update($connectslide, $entry);
    } elseif (!isset($entry->grade) OR $entry->grade < 100) {
        connectslide_grade_entry($USER->id, $connectslide, $entry);
    }
    
    $entry->rechecks = $entry->grade == 100 ? 0 : $connectslide->loops;
    $entry->rechecktime = $entry->grade == 100 ? 0 : time() + $connectslide->initdelay;

    if (!isset($entry->id)) {
        $DB->insert_record('connectslide_entries', $entry);
    } else {
        $DB->update_record('connectslide_entries', $entry);
    }

    if ($cm) {
        $course = $DB->get_record('course', array('id' => $courseid));
        //error_log('+++ $course' . json_encode($course));
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm)) {
            if ( $cm->completiongradeitemnumber == null and $cm->completionview == 1){
                $completion->set_module_viewed($cm);
            }
        }
    }

    //if ($regrade) return;

    if ($cm) {
    	$description = '';
    	$action = '';
    	

    			$scores = connect_sco_scores($connectslide->id, $USER->id, 'slide');
    			if (isset($scores->slides)) {
    				$entry->slides = (int)$scores->slides;
    			}
    			$description = "Slides: $entry->slides";
    			$description.= ", Grade: $entry->grade";
    			$description.= ", Views: $entry->views";
    			$description.= ", Connect ID: $entry->connectslideid";
    			$action = 'connect_slides';
    			
    	
        $event = \mod_connectslide\event\connectslide_launch::create(array(
            'objectid' => $connectslide->id,
            'other' => array('acurl' => $acurl, 'description' => "$action - $connectslide->name ( $acurl ) - $description")
        ));
        $event->trigger();
    }
}


/**
 * returns updated entry record based on grading
 * called from launch and cron
 *
 * @param char $url Custom URL of Adobe connect Resource
 * @param char $userid Login acp_login (Adobe connect Username)
 * @param object $connectslide Original connect record
 * @param object $entry Original entry record
 **/
function connectslide_grade_entry($userid, $connectslide, &$entry, $scores = null) {
    global $CFG, $DB;

    if (!$scores) $scores = connect_sco_scores($connectslide->id, $userid, 'slide');
    
    if (isset($scores->slides)) {
        $entry->slides = (int)$scores->slides;
        $threshold = (int)$scores->slides;
    } else $threshold = 0;

    if ($specs = $DB->get_field_sql("SELECT MAX(grade) AS grade FROM {$CFG->prefix}connectslide_grading WHERE connectslideid = {$connectslide->id} AND threshold <= $threshold AND threshold > 0")) {
        $grade = (int)$specs;
    } elseif ($specs = $DB->get_field_sql("SELECT MAX(grade) AS grade FROM {$CFG->prefix}connectslide_grading WHERE connectslideid = {$connectslide->id} AND threshold > 0")) {
        $grade = 0;
    } else $grade = (int)$threshold;

    if (!isset($entry->grade) OR $entry->grade < $grade) {
        $entry->grade = $grade;
        connectslide_gradebook_update($connectslide, $entry);
    }

    if ($grade == 100) {
        $entry->rechecks = 0;
        $entry->rechecktime = 0;
    }

    return true;
}

/**
 * Update gradebook
 *
 * @param object $entry connect instance
 */
function connectslide_gradebook_update($connectslide, $entry) {
    if( function_exists( 'local_connect_gradebook_update' ) ){
        return local_connect_gradebook_update( $connectslide, $entry, 'connectslide' );
    }else{
        return false;
    }
}

function connectslide_update_from_adobe( &$connectslide ){
    global $DB;

    $sco = connect_get_sco_by_url( $connectslide->url, 1 );
    if( $sco ){
        if(isset( $sco->name ))$connectslide->name = $sco->name;
        if(isset( $sco->desc ))$connectslide->intro = $sco->desc;
        if(isset( $sco->archive ))$connectslide->ac_archive = $sco->archive;
        if(isset($sco->type))$connectslide->ac_type = $sco->type;
        if(isset($sco->phone))$connectslide->ac_phone = $sco->phone;
        if(isset($sco->pphone))$connectslide->ac_pphone = $sco->pphone;
        if(isset($sco->id))$connectslide->ac_id=$sco->id;
        if(isset($sco->views))$connectslide->ac_views = $sco->views;
        $DB->update_record( 'connectslide', $connectslide );
    }
}

function connectslide_translate_display($connectslide, $forviewpage = 0) {
    global $CFG;

    
        if ( !$forviewpage && (empty($connectslide->url) OR empty($connectslide->iconsize) OR $connectslide->iconsize == 'none')) return ''; 
        $flags = '-';

        if (!empty($connectslide->iconpos) AND $connectslide->iconpos) $flags .= $connectslide->iconpos;
        if (!empty($connectslide->iconsilent) AND $connectslide->iconsilent) $flags .= 's';
        if (!empty($connectslide->iconphone) AND $connectslide->iconphone) $flags .= 'p';
        //if (!empty($connectslide->iconmouse) AND $connectslide->iconmouse) $flags .= 'm';
        if (!empty($connectslide->iconguests) AND $connectslide->iconguests) $flags .= 'g';
        if (!empty($connectslide->iconnorec) AND $connectslide->iconnorec) $flags .= 'a';

        $start = ''; //TODO - get start and end from Restrict Access area
        $end = ''; 
        $extrahtml = empty($connectslide->extrahtml) ? '' : $connectslide->extrahtml;

        if( !isset( $connectslide->iconsize ) )$connectslide->iconsize = 'large';
        $options = $connectslide->iconsize . $flags . '~' . $start . '~' . $end . '~' . $extrahtml . '~' . $connectslide->forceicon . '~' . $connectslide->id;

        $display = '<div class="connectslide_display_block" ';
        $display.= 'data-courseid="' . $connectslide->course . '" ';
        $display.= 'data-acurl="' . $connectslide->url . '" ';
        $display.= 'data-sco="' . json_encode(false) . '" ';
        $display.= 'data-options="' . preg_replace( '/"/', '%%quote%%', $options ) . '" ';
        $display.= 'data-frommymeetings="0" ';
        $display.= 'data-frommyrecordings="0" >'
            . '<div id="id_ajax_spin" class="rt-loading-image"></div>'
            . '</div>';
        
//        $display = '[[connect#' . $connectslide->url . '#' . $connectslide->iconsize . $flags . '#' . $start . '#' . $end . '#' . $extrahtml . '#' . $connectslide->forceicon . '#' . $connectslide->id . ']]';

        return $display;
    
}

function connectslide_create_display( $connectslide ){
    global $USER, $CFG, $PAGE, $DB, $OUTPUT;

    if( !$connectslide ){
        return '<div style="text-align:center;"><img src="' . $CFG->wwwroot
            . '/mod/connectslide/images/notfound.gif"/><br/>'
            . get_string('notfound', 'connectslide')
            . '</div>';
    }

    if( !$connectslide->ac_id ){ // no ac id, probably first load of this activity after upgrade, lets update
        connectslide_update_from_adobe( $connectslide );
        if( !$connectslide->ac_id ){// must no longer exist in AC
            return '<div style="text-align:center;"><img src="' . $CFG->wwwroot
            . '/mod/connectslide/images/notfound.gif"/><br/>'
            . get_string('notfound', 'connectslide')
            . '</div>';
        }
    }

    if( !$connectslide->display || preg_match( '/\[\[/', $connectslide->display ) ){
        $connectslide = connectslide_set_forceicon($connectslide);
        $connectslide->display = connectslide_translate_display( $connectslide, 1 );
        $DB->update_record( 'connectslide', $connectslide );
    }   
    preg_match('/data-options="([^"]+)"/', $connectslide->display, $matches);
    if( isset( $matches[1] ) ){
        $element = explode('~', $matches[1] );
    }

    $sizes = array(
        "medium" => "_md",
        "med" => "_md",
        "md" => "_md",
        "_md" => "_md",
        "small" => "_sm",
        "sml" => "_sm",
        "sm" => "_sm",
        "_sm" => "_sm",
        "block" => "_sm",
        "sidebar" => "_sm"
    );
    $types = array("meeting" => "meeting", "content" => "presentation");
    $breaks = array("_md" => "<br/>", "_sm" => "<br/>");

    $thisdir = $CFG->wwwroot . '/mod/connectslide';


    $iconsize = '';
    $iconalign = 'center';
    $silent = false;
    $telephony = true;
    $mouseovers = true;
    $allowguests = false;
    $viewlimit = '';    

    if (isset($element[0])) {
        $iconopts = explode("-", strtolower($element[0]));
        $iconsize = empty($iconopts[0]) ? '' : $iconopts[0];
        if (isset($iconopts[1])) {
            $silent = strpos($iconopts[1], 's') !== false; // no text output
            $autoarchive = strpos($iconopts[1], 'a') === false; // point to the recording unless the 'a' is included
            $telephony = strpos($iconopts[1], 'p') === false; // no phone info
            $allowguests = strpos($iconopts[1], 'g') !== false; // allow guest user access
            //$mouseovers = strpos($iconopts[1], 'm') === false; // no mouseover
            if (strpos($iconopts[1], 'l') !== false) $iconalign = 'left';
            elseif (strpos($iconopts[1], 'r') !== false) $iconalign = 'right';
        }
    }
    if (empty($CFG->connect_telephony))
        $telephony = false;
    //if (empty($CFG->connect_mouseovers))
        //$mouseovers = false;

    $startdate = empty($element[1]) ? '' : $element[1];
    $enddate = empty($element[2]) ? '' : $element[2];
    $extra_html = empty($element[3]) ? '' : $element[3];
    $extra_html = preg_replace( '/%%quote%%/', '"', $extra_html );
    $force_icon = empty($element[4]) ? '' : $element[4];
    $connectslideid = empty($element[5]) ? 0 : $element[5];
    $grouping = '';

    if (!(!empty($PAGE->context) && $PAGE->user_allowed_editing())) {
        if (!empty($startdate) and time() < strtotime($startdate)) return;
        if (!empty($enddate) and time() > strtotime($enddate)) return;
    } else $nomouseover = false;

    if ($connectslide->start) {
        $connectslide->end = $connectslide->start + $connectslide->duration;
    }elseif ($connectslide->eventid AND $event = $DB->get_record('event', array('id' => $connectslide->eventid))) {
        $connectslide->start = $event->timestart;
        $connectslide->end = $event->timestart + $event->timeduration;
    }else{
        $connectslide->end = 0;
    }
    if ($connectslide->end > time()) unset($connectslide->ac_archive);
    if ($connectslide->maxviews) {
        if (!$views = $DB->get_field('connectslide_entries', 'views', array('connectslideid' => $connectslide->id, 'userid' => $USER->id))) $views = 0;
        $viewlimit = get_string('viewlimit', 'connectslide') . $views . '/' . $connectslide->maxviews . '<br/>';
    }

    // Check for grouping
    $grouping = '';
    $mod = get_coursemodule_from_instance('connectslide', $connectslide->id, $connectslide->course);
    if (!empty($mod->groupingid) && has_capability('moodle/course:managegroups', context_course::instance($mod->course))) {
        $groupings = groups_get_all_groupings($mod->course);
        $textclasses = isset( $textclasses ) ? $textclasses : '';
        $grouping = html_writer::tag('span', '('.format_string($groupings[$mod->groupingid]->name).')',
                array('class' => 'groupinglabel '.$textclasses));
    }

    // check for addin launch settings
    if( isset( $CFG->connect_adobe_addin ) && $CFG->connect_adobe_addin && isset( $connectslide->addinroles ) && $connectslide->addinroles ){
        $forceaddin = 1;
        $roleids = explode( ',', $connectslide->addinroles );
        $userroles = get_user_roles( context_course::instance( $connectslide->course ), $USER->id );
        foreach( $userroles as $userrole ){
            if( in_array( $userrole->roleid, $roleids ) ){
                $forceaddin = 2; // one of there roles is marked to launch from browser
                break;
            }
        }
    }

    // Custom icon from activity settings
    if (!empty($force_icon)) {
        // get the custom icon file url
        // TODO consider storing file name in display so as not to fetch it from the database here
        if ($cm = get_coursemodule_from_instance('connectslide', $connectslide->id, $connectslide->course, false)) {
            $context = context_module::instance($cm->id);
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($context->id, 'mod_connectslide', 'content', 0, 'sortorder', false)) {
                $iconfile = reset($files);

                $filename = $iconfile->get_filename();
                $path = "/$context->id/mod_connectslide/content/0";
                $iconurl = moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
                $iconsize = '';
                $icondiv = 'force_icon';
            }
        }

        // Custom icon from editor has the url in the force icon but no connect id
    } else if (!$connectslide->id and !empty($force_icon)) {
        $iconurl = $force_icon;
        $iconsize = '';
        $icondiv = 'force_icon';
    }

    // No custom icon, see if there is a custom default for this type
    if (empty($iconurl)) {
        $icontype = 'slideshow';
        
        $iconsize = isset($sizes[$iconsize]) ? $sizes[$iconsize] : '';

        $context = context_system::instance();
        $fs = get_file_storage();
        if ($files = $fs->get_area_files($context->id, 'mod_connectslide', $icontype . '_icon', 0, 'sortorder', false)) {
            $iconfile = reset($files);

            $filename = $iconfile->get_filename();
            $path = "/$context->id/mod_connectslide/{$icontype}_icon/0";
            $iconurl = moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
            $icondiv = $icontype . '_icon' . $iconsize;

            if ($iconsize == '_md') {
                $iconforcewidth = 120;
            } elseif ($iconsize == '_sm') {
                $iconforcewidth = 60;
            } else {
                $iconforcewidth = 180;
            }

        }
    }

    // No custom icon so just display the default icon
    if (empty($iconurl)) {
        $scotype = 'content';
        $icontype = isset($types[$scotype]) ? $types[$scotype] : 'misc';
        if ($autoarchive AND !empty($sco->archive)) $icontype = 'archive';
        $iconsize = isset($sizes[$iconsize]) ? $sizes[$iconsize] : '';
        $iconurl = new moodle_url("/mod/connectslide/images/$icontype$iconsize.jpg");
        $icondiv = $icontype . '_icon' . $iconsize;
    }

    $strtime = '';
    if ($connectslide->ac_type == 'meeting' AND $connectslide->end > time()) {
        $strtime .= userdate($connectslide->start, '%a %b %d, %Y', $USER->timezone);
        if ($iconsize == '_md' OR $iconsize == '_sm') $strtime .= "<br/>";
        $strtime .= userdate($connectslide->start, "@ %I:%M%p") . ' - ';
        $strtime .= userdate($connectslide->end, "%I:%M%p ") . connectslide_mod_tzabbr() . '<br/>';
    }

    $strtele = '';
    if ($connectslide->ac_type == 'meeting' AND $telephony AND $connectslide->end > time()) {
        $strtele .= '<b>';
        if (!empty($connectslide->ac_phone)) {
            $strtele .= get_string('tollfree', 'connectslide') . ' ' . $connectslide->ac_phone;
            if ($iconsize == '_md' OR $iconsize == '_sm') $strtele .= "<br/>";
        }
        if (!empty($connectslide->ac_pphone)){
            $strtele .= " (";
            $strtele .= get_string('pphone', 'connectslide');
            $strtele .= $connectslide->ac_pphone . ')';
        }
        $strtele .= '</b><br/>';
    }

    if (!$silent) {
        $font = '<font>';
        if ($iconsize == '_sm') {
            $font = '<font size="1">';
        }
        $instancename = html_writer::tag('span', $connectslide->name, array('class' => 'instancename')) . '<br/>';
        $aftertext = $font . $instancename . $strtime . $strtele . $viewlimit . $grouping . $extra_html . '</font>';
    } else {
        $aftertext = $extra_html;
    }

    $archive = '';
    if ($autoarchive AND !empty($connectslide->ac_archive)) $archive = '&archive=' . $connectslide->ac_archive;

    if( !isset( $forceaddin ) || !$forceaddin ){
        $forceaddin = 0;
    }
    $linktarget = $forceaddin == 1 ? '_self' : '_blank';

    $link = $thisdir . '/launch.php?acurl='.$connectslide->url.'&connect_id=' . $connectslide->id . $archive . '&guests=' . ($allowguests ? 1 : 0) . '&course=' . $connectslide->course.'&forceaddin='.$forceaddin;

    $overtext = '';
    if ($mouseovers || is_siteadmin($USER)) {
        $overtext = '<div align="right"><br /><br /><br />';
        /*$overtext .= '<div align="left"><a href="' . $link . '" target="'.$linktarget.'" >';
        if (!empty($archive)) $overtext .= '<b>' . get_string('launch_archive', 'connectslide') . '</a></b><br/>';
        else $overtext .= '<b>' . get_string('launch_' . $connectslide->ac_type, 'connectslide') . '</a></b><br/>';*/

        if (!empty($connectslide->intro)) {
            $search = '/\[\[user#([^\]]+)\]\]/is';
            $connectslide->intro = preg_replace_callback($search, 'mod_connectslide_user_callback', $connectslide->intro);
            $overtext .= str_replace("\n", "<br />", $connectslide->intro) . '<br/>';
        }
        $overtext .= $strtime . $strtele;

        if (($PAGE->context) && !empty($PAGE->context->id) && $PAGE->user_allowed_editing() && !empty($USER->editing) && empty(strstr($PAGE->url, 'launch')) && empty(strstr($PAGE->url, 'modedit')) && empty(strstr($PAGE->url, 'rest'))) {
            if( $course = $DB->get_record( 'course', array( 'id' => $connectslide->course ) ) ){
                $editcontext = context_course::instance($course->id);
            }else{
                $editcontext = context_system::instance();
            }
            if (has_capability('filter/connect:editresource', $editcontext)) {
                $overtext .= '<a href="' . $link . '&edit=' . $connectslide->ac_id . '&type=' . $connectslide->ac_type . '" target="'.$linktarget.'" >';
                //$overtext .= '<img src="' . $CFG->wwwroot . '/mod/connectslide/images/adobe.gif" border="0" align="middle"> ';
                //$overtext .= get_string('launch_edit', 'connectslide') . '</a><br/>';
                $overtext .= "<img src='" . $OUTPUT->pix_url('/t/edit') . "' class='iconsmall' title='" . get_string('launch_edit', 'connectslide')  ."' />". "</a>";

                $overtext .= '<a href="#" id="connectslide-update-from-adobe" data-connectslideid="'.$connectslide->id.'">';
                //$overtext .= '<img src="' . $CFG->wwwroot . '/mod/connectslide/images/adobe.gif" border="0" align="middle"> ';
                //$overtext .= get_string('update_from_adobe', 'connectslide') . '</a><br/>';
                $overtext .= "<img src='" . $OUTPUT->pix_url('/i/return') . "' class='iconsmall' title='" . get_string('update_from_adobe', 'connectslide')  ."' />". "</a>";
            }

            /*if ($connectslide->ac_type == 'meeting') {
                if ($connectslide->start > time()) {
                } else {
                    if( file_exists( $CFG->dirroot.'/filter/connect/attendees.php' ) ){
                        $overtext .= '<a href="' . $CFG->wwwroot . '/filter/connect/attendees.php?acurl=' . $connectslide->url . '&course=' . $connectslide->course . '">';
                        $overtext .= '<img src="' . $CFG->wwwroot . '/filter/connect/images/attendee.gif" border="0" align="middle"> ' . get_string('viewattendees', 'filter_connect') . '</a>';
                    }
                    $overtext .= '<a href="' . $CFG->wwwroot . '/mod/connectslide/past_sessions.php?acurl=' . $connectslide->url . '&course=' . $connectslide->course . '">';
                    $overtext .= '<br /><img src="' . $CFG->wwwroot . '/mod/connectslide/images/attendee.gif" border="0" align="middle"> ' . get_string('viewpastsessions', 'connectslide') . '</a>';
                }
            }*/
        }
        $overtext .= '</div>';
    }

    $clock = '';
    if ($connectslide->ac_type == 'meeting' AND time() > ($connectslide->start - 1800) AND $connectslide->end > time()) {
        $clock = '<img id="tooltipimage" class="clock" src="' . $CFG->wwwroot . '/mod/connectslide/images/clock';
        if ($iconsize == '_sm') $clock .= '-s';
        $clock .= '.gif" border="0" id="clock"' . $link . '>';
        // do qtip here
    }

    $height = (isset($CFG->connect_popup_height) ? 'height=' . $CFG->connect_popup_height . ',' : '');
    $width = (isset($CFG->connect_popup_width) ? 'width=' . $CFG->connect_popup_width . ',' : '');

    $font = '';
    if ($iconsize == '_sm') $font = '<font size="1">';

    $onclick = $link;
    $onclick = str_replace("'", "\'", htmlspecialchars($link));
    $onclick = str_replace('"', '\"', $onclick);
    if( $linktarget == '_self' ){
        $onclick = "window.location.href='$onclick'";
    }else{
        $onclick = ' onclick="return window.open(' . "'" . $onclick . "' , 'connectslide', '{$height}{$width}menubar=0,location=0,scrollbars=0,resizable=1' , 0);" . '"';
    }

    $iconwidth = (isset($iconforcewidth)) ? "width=\"$iconforcewidth\" " : "";
    $iconheight = (isset($iconforceheight)) ? "height=\"$iconforceheight\" " : "";



    $display = '<div id="connectslidecontent'.$connectslide->id.'" style="text-align: '.$iconalign.'; width: 100%;">
        <div class="connect-course-icon-'.$iconalign.'" id="'.$icondiv.'">
            <a href="'.$link.'" 
                '.($mouseovers || is_siteadmin($USER) ? 'class="mod_connectslide_tooltip"' : '').'
                style="display: inline-block;" target="'.$linktarget.'">
                <img src="'.$iconurl.'" border="0"/>
                '.$clock.'
            </a>
        </div>
        <div class="connect-course-aftertext-'.$iconalign.'">
        '.$aftertext.'
        </div>
        <div class="mod_connectslide_popup" style="display: block;">
                '.$overtext.'
            </div>
    </div>';

    return $display;
}

// User substitutions
function mod_connectslide_user_callback($link) {
    global $CFG, $USER, $PAGE;
    $disallowed = array('password', 'aclogin', 'ackey');

    $PAGE->set_cacheable(false);
    // don't show any content to users who are not logged in using an authenticated account
    if (!isloggedin()) return;

    if (!isset($USER->{$link[1]}) || in_array($link[1], $disallowed)) return;

    return $USER->{$link[1]};
}

function connectslide_mod_tzabbr() {
    global $USER, $CFG;
    if ($USER->timezone == 99) {
        $userTimezone = $CFG->timezone;
    } else {
        $userTimezone = $USER->timezone;
    }
    $dt = new DateTime("now", new DateTimeZone($userTimezone));
    return $dt->format('T');
}

function connectslide_grade_meeting($courseid, $url, $connectslide = null, $startdaterange, $enddaterange, $regrade) {
    global $CFG, $DB, $USER;

    if (!$connectslide AND !$connectslide = $DB->get_record('connectslide', array('course' => $courseid, 'url' => $url))) return false;

    if ($connectslide->detailgrading == 2) {
        //Fast-Track
        if ($scores = ft_get_scores($connectslide->url)) {
            foreach ($scores as $userid => $grade) {
                
                // skip them if they have a grade outside the range
                if( !connectslide_grade_based_on_range( $userid, $connectslide->id, $startdaterange, $enddaterange, $regrade ) ) continue;

                if (empty($userid)) continue;
                $field = 'id';
                if (!$user = $DB->get_record('user', array($field => $userid, 'deleted' => 0))) continue;
                if (!$entry = $DB->get_record('connectslide_entries', array('connectslideid' => $connectslide->id, 'userid' => $user->id))) {
                    $entry = new stdClass();
                    $entry->connectslideid = $connectslide->id;
                    $entry->userid = $user->id;
                    $entry->type = 'meeting';
                    $entry->minutes = 0;
                    $entry->slides = 0;
                    $entry->positions = 0;
                    $entry->score = 0;
                    $entry->timemodified = time();
                }

                if (!isset($entry->grade) OR $entry->grade < $grade) $entry->grade = $grade;
                if (!isset($entry->id)) $entry->id = $DB->insert_record('connectslide_entries', $entry);
                else $DB->update_record('connectslide_entries', $entry);
                connectslide_gradebook_update($connectslide, $entry);
            }
        }
    } elseif ($connectslide->detailgrading == 3) { //Vantage Point
        $context = context_course::instance($connectslide->course);
        $course = $DB->get_record('course', array('id' => $connectslide->course));
        $users = get_enrolled_users($context);
        if (!$users) return true; // no enroled users, nothing to grade

        foreach ($users as $user) {

            // skip them if they have a grade outside the range
            if( !connectslide_grade_based_on_range( $user->id, $connectslide->id, $startdaterange, $enddaterange, $regrade ) ) continue;

            $grade = connectslide_vp_get_score($connectslide, $user);

            if ($grade == -1) {
                return false; // scores not ready yet, return false so meeting won't be completed yet and will check again next cron
            } elseif ($grade == -2) {
                return true; // vantage point couldn't find any grades, meeting will complete without it
            } elseif ($grade > 0) { // woo, we have a grade!!
                if (!$entry = $DB->get_record('connectslide_entries', array('connectslideid' => $connectslide->id, 'userid' => $user->id))) {
                    $entry = new stdClass();
                    $entry->connectslideid = $connectslide->id;
                    $entry->userid = $user->id;
                    $entry->type = 'meeting';
                    $entry->minutes = 0;
                    $entry->slides = 0;
                    $entry->positions = 0;
                    $entry->score = 0;
                    $entry->timemodified = time();
                }

                $scores = new stdClass;
                $scores->minutes = $grade;
                connectslide_grade_entry('', $connectslide, $entry, $scores);
                if (!isset($entry->id)) $entry->id = $DB->insert_record('connectslide_entries', $entry);
                else $DB->update_record('connectslide_entries', $entry);
            }
        }
    } else {
        //Adobe Connect
        if (!$sco = connect_get_sco_by_url($connectslide->url, 1)) return false;
        if ( !isset( $sco->type ) || $sco->type != 'meeting') return false;

        if (isset($sco->times)) {
            foreach ($sco->times as $userid => $time) {
                if (empty($userid)) continue;
                // Bug fix - $field is aclogin by default for table user.
                //$field = 'email';
                $field = 'id';

                // skip them if they have a grade outside the range
                if( !connectslide_grade_based_on_range( $userid, $connectslide->id, $startdaterange, $enddaterange, $regrade ) ) continue;
                
                if (!$user = $DB->get_record('user', array($field => $userid, 'deleted' => 0))) continue;
                if (!$entry = $DB->get_record('connectslide_entries', array('connectslideid' => $connectslide->id, 'userid' => $user->id))) {
                    $entry = new stdClass();
                    $entry->connectslideid = $connectslide->id;
                    $entry->userid = $user->id;
                    $entry->type = 'meeting';
                    $entry->grade = 0;
                    $entry->minutes = 0;
                    $entry->score = 0;
                    $entry->slides = 0;
                    $entry->positions = 0;
                    $entry->timemodified = time();
                }

                $scores = new stdClass;
                $scores->minutes = $time;
                connectslide_grade_entry($userid, $connectslide, $entry, $scores);
                if (!isset($entry->id)) $DB->insert_record('connectslide_entries', $entry);
                else $DB->update_record('connectslide_entries', $entry);
            }
        }
    }

    return true;
}

function connectslide_vp_get_score($connectslide, $user){
    $connect_instance = _connect_get_instance();
    $params = array(
        'external_connect_id' => $connectslide->id,
        'external_user_id'    => $user->id,
        'start'               => $connectslide->start,
        'duration'            => $connectslide->duration
    );    
    $result =  $connect_instance->connect_call('vp-get-score', $params);  
    return $result;
}

// Called when about to be locked out based on a Connect Activity
// Called from locklib
// Requires $CFG->connect_instant_grade > 0;
function connectslide_regrade_one($connectslideid, $userid) {
    global $CFG, $DB, $USER;

    if (!$user = $DB->get_record('user', array('id' => $userid))) return false;
    if (!$connectslide = $DB->get_record('connectslide', array('id' => $connectslideid))) return false;
    if (!$entry = $DB->get_record('connectslide_entries', array('userid' => $user->id, 'connectslideid' => $connectslideid))) return false;
    if ( !connectslide_grade_entry($user->id, $connectslide, $entry)) return false;
    elseif (!connectslide_grade_entry($user->id, $connectslide, $entry)) return false;
    $DB->update_record('connectslide_entries', $entry);
    return $entry->grade;
}

function connectslide_set_forceicon($connectslide) {
    if( function_exists( 'local_connect_set_forceicon' ) ){
        return local_connect_set_forceicon( $connectslide, 'connectslide' );
    }else{
        return false;
    }
}

/**
 * Serves the resource files.
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - just send the file
 */
function connectslide_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if( function_exists( 'local_connect_pluginfile' ) ){
        return local_connect_pluginfile( $course, $cm, $context, $filearea, $args, $forcedownload, $options, 'connectslide' );
    }else{
        return false;
    }
}

function connectslide_regrade_fullquiz($connectslide, $shh = true, $user_tograde_id = 0 ) {
    global $CFG, $USER, $DB;

    if ($connectslide->type != 'cquiz')
        return false;
    
    if( isset( $USER->usercourseconnects ) ){
    	$USER->usercourseconnects.= "$connectslide->id";
    }else{
    	$USER->usercourseconnects = "$connectslide->id";
    }

    if( $sco = connect_get_sco_by_url($connectslide->url, 1)) {
        if ($sco->scores) {
            foreach ($sco->scores as $userid => $item) {
                if( $user_tograde_id && $user_tograde_id != $userid ) continue; // we only want to grade one user, if this is not them, skip them
            	$score = $item->score;
                if ($user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0))) {
                	if( $user->id == $USER->id ){
	                	if( isset( $USER->usercourseconnects ) ){
					    	$USER->usercourseconnectswithgrade.= "$connectslide->id";
					    }else{
					    	$USER->usercourseconnectswithgrade = "connect->id";
					    }
                	}
                	
                    if (!$entry = $DB->get_record('connectslide_entries', array('userid' => $user->id, 'connectslideid' => $connectslide->id))) {
                        $entry = new stdClass;
                        $entry->connectid = $connectslide->id;
                        $entry->userid = $user->id;
                        $entry->type = $connectslide->type;
                        $entry->views = 0;
                    }
                    $entry->timemodified = time();
                    
                    $oldgrade = isset( $entry->grade ) ? $entry->grade : 0;

                    // Without detail grading, just set the grade to 100 and return
                    if (!$connectslide->detailgrading) {
                        $entry->grade = 100;
                        connectslide_gradebook_update($connectslide, $entry);
                    } elseif (!isset($entry->grade) OR $entry->grade < 100) {
                        $scores = new stdClass;
                        $scores->score = $score;
                        connectslide_grade_entry($user->id, $connectslide, $entry, $scores);
                    }
                    
                    if( $oldgrade == 0 && $entry->grade > 0 ){
                    	//means they had not submitted anythning before, but have now, do event
                    	$event = \mod_connectslide\event\connectslide_quizsubmitted::create(array(
                    			'objectid' => $connectslide->id,
                    			'relateduserid' => $user->id,
                    			'other' => array( 'acurl' => $connectslide->url, 'description' => "Quiz submitted: $connectslide->name" )
                    	));
                    	$event->trigger();
                    }
                    
                    $entry->rechecks = $entry->grade == 100 ? 0 : $connectslide->loops;
                    $entry->rechecktime = $entry->grade == 100 ? 0 : time() + $connectslide->initdelay;

                    if (!isset($entry->id)) $DB->insert_record('connect_entries', $entry);
                    else $DB->update_record('connectslide_entries', $entry);

                    if (!$shh) echo '-- Updating ' . fullname($user) . ' (' . $userid . ')  with a score of ' . $score . ' to a grade of ' . $entry->grade . '%<br/>';

                    if ($cm = get_coursemodule_from_instance('connectslide', $connectslide->id)) {
                        if ($course = $DB->get_record('course', array('id' => $connectslide->course))) {
                            $completion = new completion_info($course);
                            if ($completion->is_enabled($cm)) $completion->update_state($cm, COMPLETION_COMPLETE, $user->id);
                        }
                    }
                }
            }
        }
    }
    return true;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function connectslide_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-connectslide-*' => 'Any connect page type');
    return $module_pagetype;
}

/**
 * Runs Instant Grading
 *
 *  Gets and processes entries who's recheck time has elapsed.
 *
 * @return boolean
 **/
function connectslide_instant_regrade($connectslide, $userid=null) {

    global $CFG, $USER, $DB;

    if (empty($userid)){
        $userid = $USER->id;
    }

    //Instant Grading - just return
    //if (isset($CFG->connect_instant_grade) AND $CFG->connect_instant_grade == 1) return true;
    //echo "SELECT * FROM {$CFG->prefix}connectslide_entries WHERE rechecks > 0 AND rechecktime < $now \n";
    //Entries Every 15min
    $now = time();

    if (!$entries = $DB->get_records_sql("SELECT * FROM {$CFG->prefix}connectslide_entries WHERE grade < 100 AND rechecktime < $now AND connectslideid=". $connectslide->id . " and userid=" . $userid)) return true;

    foreach ($entries as $entry) {

        if (!connectslide_grade_entry($userid, $connectslide, $entry)) continue;

        $DB->update_record('connectslide_entries', $entry);

        if ($cm = get_coursemodule_from_instance('connectslide', $connectslide->id)) {
            if ($course = $DB->get_record('course', array('id' => $connectslide->course))) {
                $completion = new completion_info($course);
                if ($completion->is_enabled($cm)) {
                    $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
                    //rebuild_course_cache($connectslide->course);
                }
            }
        }
        /*
        if ($entry->grade == 100 AND $cm = get_coursemodule_from_instance('connectslide', $connectslide->id)) {
            // Mark Users Complete
            if ($cmcomp = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cm->id, 'userid' => $userid))) {
                $cmcomp->completionstate = 1;
                $cmcomp->viewed = 1;
                $cmcomp->timemodified = time();
                $DB->update_record('course_modules_completion', $cmcomp);
            } else {
                $cmcomp = new stdClass;
                $cmcomp->coursemoduleid = $cm->id;
                $cmcomp->userid = $userid;
                $cmcomp->completionstate = 1;
                $cmcomp->viewed = 1;
                $cmcomp->timemodified = time();
                $DB->insert_record('course_modules_completion', $cmcomp);
            }
            rebuild_course_cache($connectslide->course);
        }*/
    }
    return true;
}
