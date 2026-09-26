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

namespace mod_labeldiagram\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_labeldiagram.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'labeldiagram_attempt',
            [
            'userid' => 'privacy:metadata:attempt:userid',
            'attempt' => 'privacy:metadata:attempt:attempt',
            'mode' => 'privacy:metadata:attempt:mode',
            'state' => 'privacy:metadata:attempt:state',
            'timestart' => 'privacy:metadata:attempt:timestart',
            'timefinish' => 'privacy:metadata:attempt:timefinish',
            'correct' => 'privacy:metadata:attempt:correct',
            'total' => 'privacy:metadata:attempt:total',
            'grade' => 'privacy:metadata:attempt:grade',
            'duration' => 'privacy:metadata:attempt:duration',
            ],
            'privacy:metadata:attempt'
        );
        $collection->add_database_table(
            'labeldiagram_response',
            [
            'slideid' => 'privacy:metadata:response:slideid',
            'labelid' => 'privacy:metadata:response:labelid',
            'placedlabelid' => 'privacy:metadata:response:placedlabelid',
            'correct' => 'privacy:metadata:response:correct',
            'tries' => 'privacy:metadata:response:tries',
            'timecreated' => 'privacy:metadata:response:timecreated',
            ],
            'privacy:metadata:response'
        );
        $collection->add_subsystem_link('core_grades', [], 'privacy:metadata:core_grades');
        return $collection;
    }

    /**
     * Contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {labeldiagram_attempt} a ON a.labeldiagramid = cm.instance
                 WHERE a.userid = :userid";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['ctxlevel' => CONTEXT_MODULE, 'modname' => 'labeldiagram', 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT a.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {labeldiagram_attempt} a ON a.labeldiagramid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['modname' => 'labeldiagram', 'cmid' => $context->instanceid]);
    }

    /**
     * Exports user data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('labeldiagram', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $attempts = $DB->get_records(
                'labeldiagram_attempt',
                ['labeldiagramid' => $cm->instance, 'userid' => $userid],
                'mode, attempt'
            );
            if (!$attempts) {
                continue;
            }
            $data = [];
            foreach ($attempts as $a) {
                $responses = $DB->get_records_sql(
                    "SELECT r.id, s.title AS slide, l.label AS target, p.label AS placed, r.correct, r.tries, r.timecreated
                       FROM {labeldiagram_response} r
                  LEFT JOIN {labeldiagram_slide} s ON s.id = r.slideid
                  LEFT JOIN {labeldiagram_label} l ON l.id = r.labelid
                  LEFT JOIN {labeldiagram_label} p ON p.id = r.placedlabelid
                      WHERE r.attemptid = :attemptid
                   ORDER BY r.id",
                    ['attemptid' => $a->id]
                );
                $data[] = (object)[
                    'attempt' => $a->attempt,
                    'mode' => $a->mode,
                    'state' => $a->state,
                    'timestart' => transform::datetime($a->timestart),
                    'timefinish' => $a->timefinish ? transform::datetime($a->timefinish) : '-',
                    'correct' => $a->correct,
                    'total' => $a->total,
                    'grade' => $a->grade,
                    'duration' => $a->duration,
                    'responses' => array_values(
                        array_map(
                            fn($r) => (object)[
                                'slide' => format_string((string)$r->slide, true, ['context' => $context]),
                                'target' => format_string((string)$r->target, true, ['context' => $context]),
                                'placed' => $r->placed === null ? '-' : format_string($r->placed, true, ['context' => $context]),
                                'correct' => transform::yesno($r->correct),
                                'tries' => $r->tries,
                                'timecreated' => $r->timecreated ? transform::datetime($r->timecreated) : '-',
                            ],
                            $responses
                        )
                    ),
                ];
            }
            $contextdata = helper::get_context_data($context, $contextlist->get_user());
            $contextdata->attempts = $data;
            writer::with_context($context)->export_data([], $contextdata);
            helper::export_context_files($context, $contextlist->get_user());
        }
    }

    /**
     * Deletes attempts for the given attempt condition.
     *
     * @param string $where
     * @param array $params
     */
    protected static function delete_attempts(string $where, array $params): void {
        global $DB;
        $ids = $DB->get_fieldset_select('labeldiagram_attempt', 'id', $where, $params);
        if ($ids) {
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('labeldiagram_response', "attemptid $insql", $inparams);
            $DB->delete_records_select('labeldiagram_attempt', "id $insql", $inparams);
        }
    }

    /**
     * Deletes all user data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('labeldiagram', $context->instanceid);
        if ($cm) {
            self::delete_attempts('labeldiagramid = :ldid', ['ldid' => $cm->instance]);
        }
    }

    /**
     * Deletes one user's data in the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('labeldiagram', $context->instanceid);
            if ($cm) {
                self::delete_attempts(
                    'labeldiagramid = :ldid AND userid = :userid',
                    ['ldid' => $cm->instance, 'userid' => $userid]
                );
            }
        }
    }

    /**
     * Deletes data for several users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('labeldiagram', $context->instanceid);
        if (!$cm || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        self::delete_attempts("labeldiagramid = :ldid AND userid $insql", ['ldid' => $cm->instance] + $params);
    }
}
