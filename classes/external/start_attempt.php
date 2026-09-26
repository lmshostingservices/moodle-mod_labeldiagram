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
 * Starts a practice or test attempt.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_attempt extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'mode' => new external_value(PARAM_ALPHA, 'practice or test'),
            ]
        );
    }

    /**
     * Starts the attempt.
     *
     * @param int $cmid
     * @param string $mode
     * @return array
     */
    public static function execute(int $cmid, string $mode): array {
        global $DB, $USER;
        ['cmid' => $cmid, 'mode' => $mode] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'mode' => $mode]
        );
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'labeldiagram');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/labeldiagram:attempt', $context);
        $instance = $DB->get_record('labeldiagram', ['id' => $cm->instance], '*', MUST_EXIST);
        return manager::start_attempt($instance, $cm, $course, $context, $mode, (int)$USER->id);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'attempt' => new external_value(PARAM_INT, 'Attempt number'),
            'mode' => new external_value(PARAM_ALPHA, 'Mode'),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds'),
            'total' => new external_value(PARAM_INT, 'Total pins'),
            'slides' => new external_multiple_structure(
                new external_single_structure(
                    [
                    'id' => new external_value(PARAM_INT, 'Slide id'),
                    'title' => new external_value(PARAM_TEXT, 'Title'),
                    'instructions' => new external_value(PARAM_TEXT, 'Instructions'),
                    'image' => new external_value(PARAM_URL, 'Image URL', VALUE_OPTIONAL),
                    'width' => new external_value(PARAM_INT, 'Image width'),
                    'height' => new external_value(PARAM_INT, 'Image height'),
                    'pins' => new external_multiple_structure(
                        new external_single_structure(
                            [
                            'token' => new external_value(PARAM_ALPHANUM, 'Pin token'),
                            'x' => new external_value(PARAM_FLOAT, 'X percent'),
                            'y' => new external_value(PARAM_FLOAT, 'Y percent'),
                            'number' => new external_value(PARAM_INT, 'Pin number'),
                            ]
                        )
                    ),
                    'labels' => new external_multiple_structure(
                        new external_single_structure(
                            [
                            'token' => new external_value(PARAM_ALPHANUM, 'Label token'),
                            'text' => new external_value(PARAM_TEXT, 'Label text'),
                            'color' => new external_value(PARAM_TEXT, 'Colour'),
                            ]
                        )
                    ),
                    'answers' => new external_multiple_structure(
                        new external_single_structure(
                            [
                            'pin' => new external_value(PARAM_ALPHANUM, 'Pin token'),
                            'label' => new external_value(PARAM_ALPHANUM, 'Label token'),
                            ]
                        )
                    ),
                    'purposes' => new external_multiple_structure(
                        new external_single_structure(
                            [
                            'token' => new external_value(PARAM_ALPHANUM, 'Label token'),
                            'html' => new external_value(
                                // HTML already formatted by format_text() on the server, so it is returned unchanged.
                                // The release pipeline marker below must start in lowercase on the same line.
                                // phpcs:disable moodle.Commenting.InlineComment.NotCapital
                                PARAM_RAW, // pipeline-ignore: PARAM_RAW — format_text() output, already cleaned.
                                // phpcs:enable moodle.Commenting.InlineComment.NotCapital
                                'Purpose HTML'
                            ),
                            ]
                        )
                    ),
                    ]
                )
            ),
            ]
        );
    }
}
