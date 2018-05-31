<?php

// This file is part of the connectslide module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This page lists all the instances of connectslide in a particular course
 *
 * @package    mod
 * @subpackage connectslide
 * @copyright  Elvis Li <elvis.li@refineddata.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);           // Course Module ID

// Ensure that the course specified is valid
if (!$course = $DB->get_record('course', array('id'=> $id))) {
    print_error('Course ID is incorrect');
}

// Requires a login
require_course_login($course);

// Declare variables
$currentsection = "";
$printsection = "";
$timenow = time();

// Strings used multiple times
$strconnectslides = get_string('modulenameplural', 'connectslide');
$strname  = get_string("name");
$strsectionname = get_string('sectionname', 'format_'.$course->format);

// Print the header
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/mod/connectslide/index.php', array('id'=>$course->id));
$PAGE->navbar->add($strconnectslides);
$PAGE->set_title($strconnectslides);
$PAGE->set_heading($course->fullname);

// Add the page view to the Moodle log
$event = \mod_connectslide\event\course_module_instance_list_viewed::create(array(
		'context' => context_course::instance($course->id)
));
$event->trigger();

// Get the connectslides, if there are none display a notice
if (!$connectslides = get_all_instances_in_course('connectslide', $course)) {
    echo $OUTPUT->header();
    notice(get_string('noconnectslides', 'connectslide'), "$CFG->wwwroot/course/view.php?id=$course->id");
    echo $OUTPUT->footer();
    exit();
}

$table = new html_table();

$table->head  = array ($strname);

foreach ($connectslides as $connectslide) {
    if (!$connectslide->visible) {
        // Show dimmed if the mod is hidden
        $link = html_writer::tag('a', $connectslide->name, array('class' => 'dimmed',
            'href' => $CFG->wwwroot . '/mod/connectslide/view.php?id=' . $connectslide->coursemodule));
    } else {
        // Show normal if the mod is visible
        $link = html_writer::tag('a', $connectslide->name, array('class' => 'dimmed',
            'href' => $CFG->wwwroot . '/mod/connectslide/view.php?id=' . $connectslide->coursemodule));
    }

    $table->data[] = array ($link );

}

echo $OUTPUT->header();
echo '<br />';
echo html_writer::table($table);
echo $OUTPUT->footer();