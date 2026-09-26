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
 * Finishes an attempt.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finish_attempt extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            ]
        );
    }

    /**
     * Finishes the attempt.
     *
     * @param int $attemptid
     * @return array
     */
    public static function execute(int $attemptid): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['attemptid' => $attemptid]);
        $attempt = manager::get_user_attempt($params['attemptid'], (int)$USER->id);
        $instance = $DB->get_record('labeldiagram', ['id' => $attempt->labeldiagramid], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($instance->id, 'labeldiagram');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/labeldiagram:attempt', $context);
        return manager::finish_attempt($instance, $cm, $course, $context, $attempt);
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
            'mode' => new external_value(PARAM_ALPHA, 'Mode'),
            'correct' => new external_value(PARAM_INT, 'Correct placements'),
            'total' => new external_value(PARAM_INT, 'Total pins'),
            'percent' => new external_value(PARAM_FLOAT, 'Percentage'),
            'duration' => new external_value(PARAM_INT, 'Seconds'),
            'attemptsleft' => new external_value(PARAM_INT, '-1 = unlimited'),
            'best' => new external_value(PARAM_FLOAT, 'Best percentage'),
            'leaderboard' => new external_multiple_structure(
                new external_single_structure(
                    [
                    'rank' => new external_value(PARAM_INT, 'Rank'),
                    'name' => new external_value(PARAM_TEXT, 'Display name'),
                    'percent' => new external_value(PARAM_FLOAT, 'Percentage'),
                    'duration' => new external_value(PARAM_INT, 'Seconds'),
                    'me' => new external_value(PARAM_INT, 'Current user'),
                    ]
                )
            ),
            ]
        );
    }
}
