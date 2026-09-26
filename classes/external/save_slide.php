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

namespace mod_labeldiagram\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_labeldiagram\local\manager;

/**
 * Saves a slide's title and labels from the editor.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_slide extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
            'slideid' => new external_value(PARAM_INT, 'Slide id'),
            'title' => new external_value(PARAM_TEXT, 'Slide title'),
            'instructions' => new external_value(PARAM_TEXT, 'Instructions', VALUE_DEFAULT, ''),
            'labels' => new external_multiple_structure(
                new external_single_structure(
                    [
                    'id' => new external_value(PARAM_INT, 'Label id, 0 for new', VALUE_DEFAULT, 0),
                    'label' => new external_value(PARAM_TEXT, 'Label text'),
                    'x' => new external_value(PARAM_FLOAT, 'X percent'),
                    'y' => new external_value(PARAM_FLOAT, 'Y percent'),
                    'color' => new external_value(PARAM_TEXT, 'Colour', VALUE_DEFAULT, ''),
                    'purpose' => new external_value(PARAM_TEXT, 'Purpose (Markdown)', VALUE_DEFAULT, ''),
                    'distractor' => new external_value(PARAM_INT, 'Distractor flag', VALUE_DEFAULT, 0),
                    ]
                ),
                'Labels',
                VALUE_DEFAULT,
                []
            ),
            ]
        );
    }

    /**
     * Saves.
     *
     * @param int $slideid
     * @param string $title
     * @param string $instructions
     * @param array $labels
     * @return array
     */
    public static function execute(int $slideid, string $title, string $instructions = '', array $labels = []): array {
        global $DB;
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
            'slideid' => $slideid, 'title' => $title, 'instructions' => $instructions, 'labels' => $labels,
            ]
        );
        $slide = $DB->get_record('labeldiagram_slide', ['id' => $params['slideid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('labeldiagram', $slide->labeldiagramid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/labeldiagram:manage', $context);
        $ids = manager::save_slide($slide, $params['title'], $params['instructions'], $params['labels']);
        return ['ids' => $ids];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
            'ids' => new external_multiple_structure(new external_value(PARAM_INT, 'Saved label id in order')),
            ]
        );
    }
}
