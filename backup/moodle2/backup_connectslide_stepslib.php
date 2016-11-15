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
 * @package connect
 * @subpackage backup-moodle2
 * @copyright 2012 Gary Menezes {@link http://www.refineddata.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_connect_activity_task
 */

/**
 * Define the complete connect structure for backup, with file and id annotations
 */
class backup_connectslide_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $connectslide = new backup_nested_element('connectslide', array('id'), array(
            'course', 'name', 'intro', 'introformat', 'url', 'start', 'display', 'displayoncourse', 'type', 'email', 'eventid', 
            'unenrol', 'compdelay', 'autocert', 'detailgrading',
            'initdelay', 'loops', 'loopdelay', 'timemodified', 'duration', 'addinroles' ));

        $entries = new backup_nested_element('entries');

        $entry   = new backup_nested_element('entry', array('id'), array(
            'userid', 'score', 'slides', 'minutes', 'positions',
            'type', 'views', 'grade', 'rechecks', 'rechecktime', 'timemodified'));

        $grades = new backup_nested_element('grades');

        $grade  = new backup_nested_element('grade', array('id'), array(
            'threshold', 'grade', 'timemodified'));

        /*$recurs = new backup_nested_element('recurring');

        $recur  = new backup_nested_element('recur', array('id'), array(
            'start', 'url', 'email', 'eventid', 'unenrol', 'compdelay', 'autocert' ));*/

        // Build the tree
        $connectslide->add_child($entries);
        $entries->add_child($entry);

        $connectslide->add_child($grades);
        $grades->add_child($grade);

        //$connect->add_child($recurs);
        //$grades->add_child($recur);

        // Define sources
        $connectslide->set_source_table('connectslide', array('id' => backup::VAR_ACTIVITYID));

        $grade->set_source_sql('
            SELECT *
              FROM {connectslide_grading}
             WHERE connectslideid = ?',
            array(backup::VAR_PARENTID));

        /*$recur->set_source_sql('
            SELECT *
              FROM {connectslide_recurring}
             WHERE connectslideid = ?',
            array(backup::VAR_PARENTID));*/

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $entry->set_source_table('connectslide_entries', array('connectslideid' => '../../id'));
        }

        // Define id annotations
        $entry->annotate_ids('user', 'userid');
        $connectslide->annotate_ids('certificate', 'autocert');

        // Define file annotations
        $connectslide->annotate_files('mod_connectslide', 'intro', null); // This file area hasn't itemid
        $connectslide->annotate_files('mod_connectslide', 'content', null); // By connect->id

        // Return the root element (connect), wrapped into standard activity structure
        return $this->prepare_activity_structure($connectslide);
    }
}
