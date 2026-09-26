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
 * External functions for mod_labeldiagram.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_labeldiagram_start_attempt' => [
        'classname' => \mod_labeldiagram\external\start_attempt::class,
        'description' => 'Starts a practice or test attempt and returns the player data.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/labeldiagram:attempt',
    ],
    'mod_labeldiagram_submit_slide' => [
        'classname' => \mod_labeldiagram\external\submit_slide::class,
        'description' => 'Submits and marks the placements for one slide.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/labeldiagram:attempt',
    ],
    'mod_labeldiagram_finish_attempt' => [
        'classname' => \mod_labeldiagram\external\finish_attempt::class,
        'description' => 'Finishes and grades an attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/labeldiagram:attempt',
    ],
    'mod_labeldiagram_complete_study' => [
        'classname' => \mod_labeldiagram\external\complete_study::class,
        'description' => 'Records that the user has studied every slide.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/labeldiagram:attempt',
    ],
    'mod_labeldiagram_save_slide' => [
        'classname' => \mod_labeldiagram\external\save_slide::class,
        'description' => 'Saves a slide title and its labels.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/labeldiagram:manage',
    ],
];
