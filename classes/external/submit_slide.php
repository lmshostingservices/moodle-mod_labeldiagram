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
 * Submits the placements for one slide.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_slide extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'slideid' => new external_value(PARAM_INT, 'Slide id'),
            'placements' => new external_multiple_structure(
                new external_single_structure(
                    [
                    'pin' => new external_value(PARAM_ALPHANUM, 'Pin token'),
                    'label' => new external_value(PARAM_ALPHANUM, 'Label token (empty if none)', VALUE_DEFAULT, ''),
                    'tries' => new external_value(PARAM_INT, 'Number of tries (practice)', VALUE_DEFAULT, 1),
                    ]
                ),
                'Placements',
                VALUE_DEFAULT,
                []
            ),
            ]
        );
    }

    /**
     * Submits the slide.
     *
     * @param int $attemptid
     * @param int $slideid
     * @param array $placements
     * @return array
     */
    public static function execute(int $attemptid, int $slideid, array $placements = []): array {
        global $DB, $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['attemptid' => $attemptid, 'slideid' => $slideid, 'placements' => $placements]
        );
        $attempt = manager::get_user_attempt($params['attemptid'], (int)$USER->id);
        $instance = $DB->get_record('labeldiagram', ['id' => $attempt->labeldiagramid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('labeldiagram', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/labeldiagram:attempt', $context);
        $results = manager::submit_slide($instance, $context, $attempt, $params['slideid'], $params['placements']);
        return ['results' => $results];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
            'results' => new external_multiple_structure(
                new external_single_structure(
                    [
                    'pin' => new external_value(PARAM_ALPHANUM, 'Pin token'),
                    'placed' => new external_value(PARAM_ALPHANUM, 'Placed label token'),
                    'answer' => new external_value(PARAM_ALPHANUM, 'Correct label token'),
                    'correct' => new external_value(PARAM_INT, '1 if correct'),
                    'purpose' => new external_value(
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
        );
    }
}
