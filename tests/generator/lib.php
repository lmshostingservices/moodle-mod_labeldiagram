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
 * Data generator for mod_labeldiagram.
 *
 * @package    mod_labeldiagram
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_labeldiagram_generator extends testing_module_generator {
    /**
     * Creates an instance with sensible defaults.
     *
     * @param array|stdClass $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        $defaults = [
            'grade' => 100, 'grademethod' => 1, 'maxattempts' => 0, 'allowpractice' => 1, 'allowtest' => 1,
            'allowstudy' => 1, 'timelimit' => 0, 'shufflelabels' => 1, 'showpurpose' => 1, 'leaderboard' => 0,
            'sounds' => 1, 'completionfinish' => 0,
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($record->$key)) {
                $record->$key = $value;
            }
        }
        return parent::create_instance($record, (array)$options);
    }

    /**
     * Creates a slide with an image and labels.
     *
     * @param stdClass $instance
     * @param string $imagepath absolute path to an image file
     * @param string $title
     * @param array $labels list of [label, x, y, purpose]
     * @return stdClass slide
     */
    public function create_slide(stdClass $instance, string $imagepath, string $title, array $labels = []): stdClass {
        global $DB;
        $cm = get_coursemodule_from_instance('labeldiagram', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $sortorder = 1 + (int)$DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {labeldiagram_slide} WHERE labeldiagramid = :ldid',
            ['ldid' => $instance->id]
        );
        $slide = (object)['labeldiagramid' => $instance->id, 'sortorder' => $sortorder, 'title' => $title,
            'instructions' => '', 'timemodified' => time()];
        $slide->id = $DB->insert_record('labeldiagram_slide', $slide);
        get_file_storage()->create_file_from_pathname(
            [
            'contextid' => $context->id, 'component' => 'mod_labeldiagram', 'filearea' => 'slideimage',
            'itemid' => $slide->id, 'filepath' => '/', 'filename' => basename($imagepath),
            ],
            $imagepath
        );
        $data = [];
        foreach ($labels as $l) {
            $data[] = ['id' => 0, 'label' => $l[0], 'x' => $l[1], 'y' => $l[2], 'color' => '',
                'purpose' => $l[3] ?? '', 'distractor' => $l[4] ?? 0];
        }
        \mod_labeldiagram\local\manager::save_slide($slide, $title, '', $data);
        return $DB->get_record('labeldiagram_slide', ['id' => $slide->id]);
    }
}
