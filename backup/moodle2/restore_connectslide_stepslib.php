<?php

// This file is part of Moodle - http://moodle.org/
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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_connect_activity_task
 */

/**
 * Structure step to restore one connect activity
 */
class restore_connectslide_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('connectslide', '/activity/connectslide');
        $paths[] = new restore_path_element('connectslide_grade', '/activity/connectslide/grades/grade');
        if ($userinfo) {
            $paths[] = new restore_path_element('connectslide_entry', '/activity/connectslide/entries/entry');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_connectslide($data) {
        global $DB, $CFG, $USER;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->start = $this->apply_date_offset($data->start);
//        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if( $data->autocert ){
            $data->autocert = $this->get_mappingid('certificate', $data->autocert);
        }

        // insert the connect record
        $newitemid = $DB->insert_record('connectslide', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
        // RT-394 Assign enrolled users to adobe group
        $connectslide = $DB->get_record('connectslide', array( 'id' => $newitemid));
        if ( $connectslide->type != 'video' AND !empty( $connectslide->url ) ) {
            require_once($CFG->dirroot . '/mod/connectslide/lib.php');

            // update display so it has new connectid
            $connectslide->display = preg_replace( "/~$oldid/", "~$newitemid", $connectslide->display );
            $DB->update_record( 'connectslide', $connectslide );

                //if (!empty($COURSE)) $course = $COURSE;
            	//else $course = $DB->get_record('course', 'id', $connectslide->course);
            	$result = connect_use_sco($connectslide->id, $connectslide->url, $connectslide->type, $data->course);
            	//if (!$result) {
            		//return false;
            	//}
            
            //$result = connect_use_sco($newitemid, $connect->url, $connect->type, $data->course);
            connect_add_access( $newitemid, $data->course, 'group', 'view', false, 'slide' );
        }
    }

    protected function process_connectslide_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->connectslideid = $this->get_new_parentid('connectslide');
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('connectslide_grading', $data);
        $this->set_mapping('connectslide_grading', $oldid, $newitemid);
    }

    protected function process_connectslide_entry($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->connectslideid = $this->get_new_parentid('connectslide');
        //$data->gradeid = $this->get_mappingid('connectslide_grading', $oldid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('connectslide_entries', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function after_execute() {
        // Add connect related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_connectslide', 'intro', null);
        // Add force icon related files, matching by item id (connect)
        $this->add_related_files('mod_connectslide', 'content', null);
    }
}
