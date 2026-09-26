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
 * Restore structure for mod_labeldiagram.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore structure step.
 */
class restore_labeldiagram_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the paths.
     *
     * @return array
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $paths = [];
        $paths[] = new restore_path_element('labeldiagram', '/activity/labeldiagram');
        $paths[] = new restore_path_element('labeldiagram_slide', '/activity/labeldiagram/slides/slide');
        $paths[] = new restore_path_element('labeldiagram_label', '/activity/labeldiagram/slides/slide/labels/label');
        if ($userinfo) {
            $paths[] = new restore_path_element('labeldiagram_attempt', '/activity/labeldiagram/attempts/attempt');
            $paths[] = new restore_path_element(
                'labeldiagram_response',
                '/activity/labeldiagram/attempts/attempt/responses/response'
            );
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the instance.
     *
     * @param array $data
     */
    protected function process_labeldiagram($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timemodified = time();
        $newid = $DB->insert_record('labeldiagram', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restores a slide.
     *
     * @param array $data
     */
    protected function process_labeldiagram_slide($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->labeldiagramid = $this->get_new_parentid('labeldiagram');
        $newid = $DB->insert_record('labeldiagram_slide', $data);
        $this->set_mapping('labeldiagram_slide', $oldid, $newid, true);
    }

    /**
     * Restores a label.
     *
     * @param array $data
     */
    protected function process_labeldiagram_label($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->slideid = $this->get_new_parentid('labeldiagram_slide');
        $newid = $DB->insert_record('labeldiagram_label', $data);
        $this->set_mapping('labeldiagram_label', $oldid, $newid);
    }

    /**
     * Restores an attempt.
     *
     * @param array $data
     */
    protected function process_labeldiagram_attempt($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->labeldiagramid = $this->get_new_parentid('labeldiagram');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!empty($data->pinmap)) {
            $map = json_decode($data->pinmap, true);
            if (is_array($map)) {
                foreach (['pins', 'labels'] as $key) {
                    foreach ($map[$key] ?? [] as $token => $labelid) {
                        $map[$key][$token] = (int)$this->get_mappingid('labeldiagram_label', $labelid);
                    }
                }
                $data->pinmap = json_encode($map);
            }
        }
        $newid = $DB->insert_record('labeldiagram_attempt', $data);
        $this->set_mapping('labeldiagram_attempt', $oldid, $newid);
    }

    /**
     * Restores a response.
     *
     * @param array $data
     */
    protected function process_labeldiagram_response($data) {
        global $DB;
        $data = (object)$data;
        $data->attemptid = $this->get_new_parentid('labeldiagram_attempt');
        $data->slideid = $this->get_mappingid('labeldiagram_slide', $data->slideid);
        $data->labelid = $this->get_mappingid('labeldiagram_label', $data->labelid);
        $data->placedlabelid = $data->placedlabelid ? (int)$this->get_mappingid(
            'labeldiagram_label',
            $data->placedlabelid
        ) : 0;
        $DB->insert_record('labeldiagram_response', $data);
    }

    /**
     * Restores files after the structure.
     */
    protected function after_execute() {
        $this->add_related_files('mod_labeldiagram', 'intro', null);
        $this->add_related_files('mod_labeldiagram', 'slideimage', 'labeldiagram_slide');
    }
}
