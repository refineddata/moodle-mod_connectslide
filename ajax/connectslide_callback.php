<?php
/**
 * connect_callback.php.
 *
 * @author     Dmitriy
 * @since      11/07/14
 */

define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->dirroot . '/mod/connectslide/lib.php');

// This should be accessed by only valid logged in user.
if (!isloggedin() or isguestuser()) {
    die('Invalid access.');
}

$update_from_adobe = optional_param('update_from_adobe', null, PARAM_ALPHANUMEXT);
$connectslide_id = optional_param('connectslide_id', null, PARAM_INT);
if( $connectslide_id ){
    $connectslide = $DB->get_record( 'connectslide', array( 'id' => $connectslide_id ) );
}

if( !$connectslide ){
    echo '<div style="text-align:centre;"><img src="' . $CFG->wwwroot
        . '/mod/rtrecording/images/notfound.gif"/><br/>'
        . get_string('notfound', 'local_connect')
        . '</div>';
    die;
}

if( $course = $DB->get_record( 'course', array( 'id' => $connectslide->course ) ) ){
	$PAGE->set_context(context_course::instance($course->id));
}else{
	$PAGE->set_context(context_system::instance());
}

if( $update_from_adobe ){
    connectslide_update_from_adobe( $connectslide );
}

echo connectslide_create_display( $connectslide );
