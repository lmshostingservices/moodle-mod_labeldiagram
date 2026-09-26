<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Backup structure for mod_labeldiagram.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup structure step.
 */
class backup_labeldiagram_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $labeldiagram = new backup_nested_element(
            'labeldiagram',
            ['id'],
            [
            'name', 'intro', 'introformat', 'grade', 'grademethod', 'maxattempts', 'allowpractice', 'allowtest',
            'allowstudy', 'timelimit', 'shufflelabels', 'showpurpose', 'leaderboard', 'sounds', 'completionfinish',
            'timecreated', 'timemodified',
            ]
        );
        $slides = new backup_nested_element('slides');
        $slide = new backup_nested_element('slide', ['id'], ['sortorder', 'title', 'instructions', 'timemodified']);
        $labels = new backup_nested_element('labels');
        $label = new backup_nested_element(
            'label',
            ['id'],
            [
            'sortorder', 'label', 'x', 'y', 'color', 'purpose', 'purposeformat', 'distractor',
            ]
        );
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element(
            'attempt',
            ['id'],
            [
            'userid', 'attempt', 'mode', 'state', 'pinmap', 'timestart', 'timefinish', 'correct', 'total', 'grade',
            'duration',
            ]
        );
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element(
            'response',
            ['id'],
            [
            'slideid', 'labelid', 'placedlabelid', 'correct', 'tries', 'timecreated',
            ]
        );

        $labeldiagram->add_child($slides);
        $slides->add_child($slide);
        $slide->add_child($labels);
        $labels->add_child($label);
        $labeldiagram->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($responses);
        $responses->add_child($response);

        $labeldiagram->set_source_table('labeldiagram', ['id' => backup::VAR_ACTIVITYID]);
        $slide->set_source_table('labeldiagram_slide', ['labeldiagramid' => backup::VAR_PARENTID], 'sortorder ASC');
        $label->set_source_table('labeldiagram_label', ['slideid' => backup::VAR_PARENTID], 'sortorder ASC');
        if ($userinfo) {
            $attempt->set_source_table('labeldiagram_attempt', ['labeldiagramid' => backup::VAR_PARENTID], 'id ASC');
            $response->set_source_table('labeldiagram_response', ['attemptid' => backup::VAR_PARENTID], 'id ASC');
        }
        $attempt->annotate_ids('user', 'userid');

        $labeldiagram->annotate_files('mod_labeldiagram', 'intro', null);
        $slide->annotate_files('mod_labeldiagram', 'slideimage', 'id');

        return $this->prepare_activity_structure($labeldiagram);
    }
}
