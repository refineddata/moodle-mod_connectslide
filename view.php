<?php // $Id: view.php
/**
 * This page prints a particular instance of a connect Activity
 *
 * @author  Gary Menezes
 * @version $Id: view.php
 * @package connect
 **/

require_once("../../config.php");
require_once("$CFG->dirroot/mod/connectslide/lib.php");
global $CFG, $OUTPUT, $PAGE;

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or
$cid = optional_param('a', 0, PARAM_INT); // connect ID

if (isset($id) AND $id) {
    if (!$cm = $DB->get_record("course_modules", array("id" => $id))) print_error(get_string("moduleiderror", "connectslide"));
    if (!$course = $DB->get_record("course", array("id" => $cm->course))) print_error(get_string("courseerror", "connectslide"));
    if (!$connectslide = $DB->get_record("connectslide", array("id" => $cm->instance))) print_error(get_string("iderror", "connectslide"));
} else {
    if (!$connectslide = $DB->get_record("connectslide", array("id" => $cid))) print_error(get_string("iderror", "connectslide"));
    if (!$course = $DB->get_record("connectslide", array("id" => $connectslide->course))) print_error(get_string("courseerror", "connectslide"));
    $cm = get_coursemodule_from_instance('connectslide', $connectslide->id);
}

require_login($course);
$context = context_course::instance($course->id);
$strtitle = get_string('view');

$PAGE->set_url('/mod/connectslide/view.php?id=' . $id);
$PAGE->set_context($context);
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add($strtitle, $PAGE->url);

$event = \mod_connectslide\event\course_module_viewed::create(array(
    'objectid' => $cm->instance,
    'context' => context_module::instance($cm->id),
));
$event->add_record_snapshot('course', $course);
// In the next line you can use $PAGE->activityrecord if you have set it, or skip this line if you don't have a record.
$event->add_record_snapshot('connectslide', $connectslide);
$event->trigger();


//    $PAGE->requires->jquery();
//	$PAGE->requires->jquery_plugin('qtip');
//	$PAGE->requires->jquery_plugin('qtip-css');

echo $OUTPUT->header();
include($CFG->dirroot . '/filter/connect/scripts/styles.css');

if ($connectslide->type == 'video') {
    $text = format_text('<center>[[flashvideo#' . $connectslide->url . ']]</center>');
} else {
    $text = connectslide_create_display( $connectslide );;
}

echo $text;

echo '<br/><br/><center>' . $OUTPUT->single_button($CFG->wwwroot . '/course/view.php?id=' . $course->id, get_string('returntocourse', 'connectslide')) . '</center>';
echo $OUTPUT->footer();
