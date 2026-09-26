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
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Records that the user has looked at every slide in Study mode.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class complete_study extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            ]
        );
    }

    /**
     * Stores a finished "study" attempt (once per user).
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB, $USER;
        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'labeldiagram');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/labeldiagram:attempt', $context);
        $params = ['labeldiagramid' => $cm->instance, 'userid' => $USER->id, 'mode' => 'study'];
        if (!$DB->record_exists('labeldiagram_attempt', $params)) {
            $now = time();
            $DB->insert_record(
                'labeldiagram_attempt',
                (object)($params + [
                'attempt' => 1, 'state' => 'finished', 'pinmap' => null, 'timestart' => $now, 'timefinish' => $now,
                'correct' => 0, 'total' => 0, 'grade' => null, 'duration' => 0,
                ])
            );
        }
        return ['status' => true];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
            'status' => new external_value(PARAM_BOOL, 'Recorded'),
            ]
        );
    }
}
