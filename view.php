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
 * Activity view page: mode chooser and player.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_labeldiagram\local\manager;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'labeldiagram');
$instance = $DB->get_record('labeldiagram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/labeldiagram:view', $context);

labeldiagram_view($instance, $course, $cm, $context);

$PAGE->set_url('/mod/labeldiagram/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

$canattempt = has_capability('mod/labeldiagram:attempt', $context) && !isguestuser();
$canmanage = has_capability('mod/labeldiagram:manage', $context);
$slides = manager::get_slides($instance->id);
$labels = manager::get_labels(array_keys($slides));
$pincount = 0;
foreach ($labels as $list) {
    $pincount += count(array_filter($list, fn($l) => empty($l->distractor)));
}
$summary = manager::user_summary($instance, (int)$USER->id);
$ready = $slides && $pincount > 0;

// Pass mark from the gradebook (Grade to pass), as a percentage.
$passpercent = 0;
if ($instance->grade > 0) {
    require_once($CFG->libdir . '/gradelib.php');
    $gradeitem = grade_item::fetch(
        ['itemtype' => 'mod', 'itemmodule' => 'labeldiagram',
        'iteminstance' => $instance->id, 'courseid' => $course->id, 'itemnumber' => 0]
    );
    if ($gradeitem && $gradeitem->gradepass > 0 && $gradeitem->grademax > 0) {
        $passpercent = round($gradeitem->gradepass / $gradeitem->grademax * 100, 1);
    }
}

// What this user has already done in each mode (shown as ticks on the mode cards).
$done = ['study' => false, 'practice' => null, 'test' => null];
if (!isguestuser()) {
    $rows = $DB->get_records_sql(
        "SELECT mode, MAX(grade) AS best, COUNT(1) AS n
                                    FROM {labeldiagram_attempt}
                                   WHERE labeldiagramid = :ldid AND userid = :userid AND state = :state
                                GROUP BY mode",
        ['ldid' => $instance->id, 'userid' => $USER->id, 'state' => 'finished']
    );
    $done['study'] = isset($rows['study']);
    $done['practice'] = isset($rows['practice']) ? round((float)$rows['practice']->best, 1) : null;
    $done['test'] = isset($rows['test']) ? round((float)$rows['test']->best, 1) : null;
}
$testpassed = $done['test'] !== null && (!$passpercent || $done['test'] >= $passpercent);

// Comparison rows shown on every mode card, in the same order so the modes are easy to compare.
$compare = function (array $values): array {
    $rows = [];
    foreach ($values as $key => [$yes, $text]) {
        $rows[] = ['label' => get_string('cmp_' . $key, 'mod_labeldiagram'), 'text' => $text, 'yes' => $yes];
    }
    return $rows;
};
$str = fn($k, $a = null) => get_string($k, 'mod_labeldiagram', $a);
$testtime = $instance->timelimit ? $str('cmp_time_limit', format_time($instance->timelimit)) : $str('cmp_time_none');
$testattempts = $instance->maxattempts ? $str('cmp_attempts_n', $instance->maxattempts) : $str('cmp_attempts_unlimited');

$modes = [];
if ($instance->allowstudy) {
    $modes[] = ['key' => 'study', 'icon' => 'study', 'enabled' => $ready,
        'title' => get_string('modestudy', 'mod_labeldiagram'),
        'desc' => get_string('modestudy_card', 'mod_labeldiagram'),
        'bestfor' => $str('bestfor_study'),
        'rows' => $compare(
            [
            'labels' => [true, $str('cmp_labels_shown')],
            'feedback' => [true, $str('cmp_feedback_cards')],
            'hints' => [false, $str('cmp_notneeded')],
            'graded' => [false, $str('cmp_no')],
            ]
        ),
        'done' => $done['study'],
        'status' => $done['study'] ? get_string('status_studied', 'mod_labeldiagram') : null];
}
if ($instance->allowpractice) {
    $modes[] = ['key' => 'practice', 'icon' => 'practice', 'enabled' => $ready && $canattempt,
        'title' => get_string('modepractice', 'mod_labeldiagram'),
        'desc' => get_string('modepractice_card', 'mod_labeldiagram'),
        'meta' => $done['practice'] !== null ? get_string('bestfirsttry', 'mod_labeldiagram', $done['practice']) : '',
        'bestfor' => $str('bestfor_practice'),
        'rows' => $compare(
            [
            'labels' => [true, $str('cmp_labels_you')],
            'feedback' => [true, $str('cmp_feedback_instant')],
            'hints' => [true, $str('cmp_hints_yes')],
            'graded' => [false, $str('cmp_no_unlimited')],
            ]
        ),
        'done' => $done['practice'] !== null,
        'status' => $done['practice'] !== null ? get_string('status_practised', 'mod_labeldiagram') : null];
}
if ($instance->allowtest) {
    $meta = [];
    if ($instance->maxattempts) {
        $meta[] = get_string(
            'attemptsused',
            'mod_labeldiagram',
            ['used' => $summary['attemptsused'], 'max' => $instance->maxattempts]
        );
    }
    if ($instance->timelimit) {
        $meta[] = get_string('timelimitx', 'mod_labeldiagram', format_time($instance->timelimit));
    }
    if ($summary['best'] !== null) {
        $meta[] = get_string('bestscore', 'mod_labeldiagram', $summary['best']);
    }
    $modes[] = ['key' => 'test', 'icon' => 'test',
        'enabled' => $ready && $canattempt && $summary['attemptsleft'] !== 0,
        'title' => get_string('modetest', 'mod_labeldiagram'),
        'desc' => get_string('modetest_card', 'mod_labeldiagram'),
        'meta' => implode(' · ', $meta),
        'bestfor' => $str('bestfor_test'),
        'rows' => $compare(
            [
            'labels' => [true, $str('cmp_labels_you')],
            'feedback' => [false, $str('cmp_feedback_end')],
            'hints' => [false, $str('cmp_hints_no')],
            'graded' => [$instance->grade != 0, $instance->grade != 0 ? $str('cmp_graded_yes', $testattempts) :
                $str('cmp_no')],
            ]
        ),
        'extra' => $testtime,
        'nomore' => $summary['attemptsleft'] === 0 && !$testpassed,
        'graded' => $instance->grade != 0 && $done['test'] === null,
        'done' => $testpassed,
        'status' => $testpassed ? get_string($passpercent ? 'status_passed' : 'status_completed', 'mod_labeldiagram')
            : null,
        'warn' => ($done['test'] !== null && !$testpassed) ? get_string(
            'status_notpassed',
            'mod_labeldiagram',
            $passpercent
        ) : null];
}

// Completion requirements, as shown on the start screen.
$completionrules = [];
$cminfo = get_fast_modinfo($course)->get_cm($cm->id);
if ($cminfo->completion == COMPLETION_TRACKING_AUTOMATIC && !isguestuser()) {
    $details = \core_completion\cm_completion_details::get_instance($cminfo, (int)$USER->id);
    foreach ($details->get_details() as $rule => $detail) {
        if ($rule === 'completionview') {
            continue; // Met simply by opening the activity.
        }
        $completionrules[] = ['rule' => $rule, 'text' => $detail->description];
    }
}

$config = [
    'cmid' => (int)$cm->id,
    'canattempt' => $canattempt,
    'passpercent' => $passpercent,
    'graded' => $instance->grade != 0,
    'maxattempts' => (int)$instance->maxattempts,
    'attemptsleft' => (int)$summary['attemptsleft'],
    'timelimit' => (int)$instance->timelimit,
    'timelimittext' => $instance->timelimit ? format_time($instance->timelimit) : '',
    'slidecount' => count($slides),
    'pincount' => $pincount,
    'completion' => $completionrules,
    'allowtest' => (int)$instance->allowtest,
    'name' => format_string($instance->name, true, ['context' => $context]),
    'sounds' => (int)$instance->sounds,
    'showpurpose' => (int)$instance->showpurpose,
    'leaderboard' => (int)$instance->leaderboard,
    'study' => ($instance->allowstudy && $ready) ? manager::study_data($instance, $context) : null,
];

$templatedata = [
    'uniqid' => 'ld-' . $cm->id,
    'config' => json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    'modes' => $modes,
    'donecount' => count(array_filter($modes, fn($m) => !empty($m['done']))),
    'modecount' => count($modes),
    'slidecount' => count($slides),
    'pincount' => $pincount,
    'ready' => $ready,
    'canmanage' => $canmanage,
    'manageurl' => (new moodle_url('/mod/labeldiagram/slides.php', ['id' => $cm->id]))->out(false),
    'reporturl' => has_capability('mod/labeldiagram:viewreports', $context)
        ? (new moodle_url('/mod/labeldiagram/report.php', ['id' => $cm->id]))->out(false) : null,
    'guest' => !$canattempt,
];

$PAGE->requires->js_call_amd('mod_labeldiagram/player', 'init', ['#ld-' . $cm->id]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_labeldiagram/view', $templatedata);
echo $OUTPUT->footer();
