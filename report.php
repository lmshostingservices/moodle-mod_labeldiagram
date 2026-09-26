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
 * Teacher reports: per-part difficulty (test wrong-rates, practice tries-to-correct) and attempt management.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');

use mod_labeldiagram\local\manager;

$id = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'parts', PARAM_ALPHA);
$mode = optional_param('mode', 'test', PARAM_ALPHA);
$download = optional_param('download', '', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

[$course, $cm] = get_course_and_cm_from_cmid($id, 'labeldiagram');
$instance = $DB->get_record('labeldiagram', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/labeldiagram:viewreports', $context);
if (!in_array($mode, ['test', 'practice'], true)) {
    $mode = 'test';
}
if (!in_array($tab, ['parts', 'attempts'], true)) {
    $tab = 'parts';
}

$baseurl = new moodle_url('/mod/labeldiagram/report.php', ['id' => $cm->id, 'tab' => $tab, 'mode' => $mode]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($instance->name) . ': ' . get_string('reports', 'mod_labeldiagram'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->set_attrs(['description' => '', 'hidecompletion' => true]);
if ($node = $PAGE->settingsnav->find('labeldiagram_reports', navigation_node::TYPE_SETTING)) {
    $node->make_active();
}

// Group filter.
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
$userfilter = '';
$userparams = [];
$members = null;
if ($groupmode && $currentgroup) {
    $members = array_keys(groups_get_members($currentgroup, 'u.id'));
} else if ($groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
    // Not in any group and not allowed to see all groups: show nobody rather than everybody.
    $members = [];
}
if ($members !== null) {
    [$insql, $userparams] = $DB->get_in_or_equal($members ?: [0], SQL_PARAMS_NAMED, 'grpu');
    $userfilter = " AND a.userid $insql";
}

// Attempt management: delete selected attempts, then regrade + recalc completion.
if ($action === 'delete' && has_capability('mod/labeldiagram:manage', $context)) {
    require_sesskey();
    $ids = optional_param_array('attemptids', [], PARAM_INT);
    if ($ids) {
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params['ldid'] = $instance->id;
        $attempts = $DB->get_records_select('labeldiagram_attempt', "id $insql AND labeldiagramid = :ldid", $params);
        $users = [];
        foreach ($attempts as $a) {
            $DB->delete_records('labeldiagram_response', ['attemptid' => $a->id]);
            $DB->delete_records('labeldiagram_attempt', ['id' => $a->id]);
            $users[$a->userid] = true;
        }
        $completion = new completion_info($course);
        foreach (array_keys($users) as $uid) {
            labeldiagram_update_grades($instance, $uid);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_UNKNOWN, $uid);
            }
        }
        redirect(
            $baseurl,
            get_string('attemptsdeleted', 'mod_labeldiagram', count($attempts)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect($baseurl);
}
if ($action === 'regrade' && has_capability('mod/labeldiagram:manage', $context)) {
    require_sesskey();
    labeldiagram_update_grades($instance);
    redirect($baseurl, get_string('regraded', 'mod_labeldiagram'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Attempts query (shared by table and download). Identity fields follow the site's
// "Show user identity" setting and the viewer's moodle/site:viewuseridentity capability.
$identityfields = \core_user\fields::get_identity_fields($context, false);
$userfieldsql = \core_user\fields::for_identity($context, false)->with_name()->get_sql('u', true, '', '', true);
$params = ['ldid' => $instance->id, 'mode' => $mode] + $userparams + $userfieldsql->params;
$from = "FROM {labeldiagram_attempt} a
         JOIN {user} u ON u.id = a.userid
              {$userfieldsql->joins}
        WHERE a.labeldiagramid = :ldid AND a.mode = :mode $userfilter";
$sql = "SELECT a.* {$userfieldsql->selects}
        $from
     ORDER BY u.lastname, u.firstname, a.userid, a.attempt, a.id";

if ($download && $tab === 'attempts') {
    $columns = ['fullname' => get_string('fullname')];
    foreach ($identityfields as $field) {
        $columns[$field] = \core_user\fields::get_display_name($field);
    }
    $columns += [
        'mode' => get_string('mode', 'mod_labeldiagram'),
        'attempt' => get_string('attempt', 'mod_labeldiagram'),
        'state' => get_string('state', 'mod_labeldiagram'),
        'started' => get_string('started', 'mod_labeldiagram'),
        'duration' => get_string('duration', 'mod_labeldiagram'),
        'correct' => get_string('correct', 'mod_labeldiagram'),
        'total' => get_string('total'),
        'grade' => get_string('percentage', 'grades'),
    ];
    $rs = $DB->get_recordset_sql($sql, $params);
    $rows = (function () use ($rs, $identityfields) {
        try {
            foreach ($rs as $a) {
                $row = ['fullname' => fullname($a)];
                foreach ($identityfields as $field) {
                    $row[$field] = (string)($a->{$field} ?? '');
                }
                yield $row + [
                    'mode' => get_string('mode' . $a->mode, 'mod_labeldiagram'),
                    'attempt' => $a->attempt,
                    'state' => get_string('state' . $a->state, 'mod_labeldiagram'),
                    'started' => userdate($a->timestart, get_string('strftimedatetimeshort', 'langconfig')),
                    'duration' => $a->duration,
                    'correct' => $a->correct,
                    'total' => $a->total,
                    'grade' => $a->grade === null ? '' : round($a->grade, 2),
                ];
            }
        } finally {
            $rs->close();
        }
    })();
    \core\dataformat::download_data('labeldiagram_attempts_' . $cm->id, $download, $columns, $rows);
    exit;
}

$PAGE->requires->js_call_amd('core/checkbox-toggleall', 'init');

/**
 * Template data for a percentage bar.
 *
 * @param float $pct
 * @param bool $higherisbetter
 * @return array
 */
function labeldiagram_report_bar(float $pct, bool $higherisbetter = true): array {
    $good = $higherisbetter ? $pct : 100 - $pct;
    return [
        'pct' => round($pct, 1),
        'label' => round($pct),
        'class' => $good >= 75 ? '' : ($good >= 50 ? ' is-mid' : ' is-bad'),
    ];
}

$templatedata = [
    'tabs' => [],
    'modes' => [],
    'groupmenu' => groups_print_activity_menu($cm, $baseurl, true),
    'kpis' => [],
    'parts' => false,
    'attempts' => false,
];
foreach (['parts' => 'reportparts', 'attempts' => 'reportattempts'] as $key => $string) {
    $templatedata['tabs'][] = [
        'url' => (new moodle_url($baseurl, ['tab' => $key]))->out(false),
        'label' => get_string($string, 'mod_labeldiagram'),
        'active' => $tab === $key,
    ];
}
foreach (['test' => 'modetest', 'practice' => 'modepractice'] as $key => $string) {
    $templatedata['modes'][] = [
        'url' => (new moodle_url($baseurl, ['mode' => $key]))->out(false),
        'label' => get_string($string, 'mod_labeldiagram'),
        'active' => $mode === $key,
    ];
}

// KPIs, calculated in the database so large courses stay fast.
$kpiparams = ['ldid' => $instance->id, 'mode' => $mode, 'state' => 'finished'] + $userparams;
$kpiwhere = "a.labeldiagramid = :ldid AND a.mode = :mode AND a.state = :state $userfilter";
$kpi = $DB->get_record_sql(
    "SELECT COUNT(1) AS attempts, COUNT(DISTINCT a.userid) AS students, AVG(a.grade) AS avggrade,
            AVG(a.duration) AS avgtime
       FROM {labeldiagram_attempt} a
      WHERE $kpiwhere",
    $kpiparams
);
$gradeitem = grade_item::fetch(
    ['itemtype' => 'mod', 'itemmodule' => 'labeldiagram', 'iteminstance' => $instance->id,
    'courseid' => $course->id, 'itemnumber' => 0]
);
$passpct = ($gradeitem && $gradeitem->gradepass > 0 && $gradeitem->grademax > 0)
    ? $gradeitem->gradepass / $gradeitem->grademax * 100 : 0;
$kpis = [
    get_string('kpistudents', 'mod_labeldiagram') => (int)$kpi->students,
    get_string('kpiattempts', 'mod_labeldiagram') => (int)$kpi->attempts,
    get_string('kpiaverage', 'mod_labeldiagram') => round((float)$kpi->avggrade, 1) . '%',
    get_string('kpiavgtime', 'mod_labeldiagram') => format_time((int)round((float)$kpi->avgtime)),
];
if ($passpct && $mode === 'test') {
    $passed = $DB->count_records_sql(
        "SELECT COUNT(1)
           FROM (SELECT a.userid, MAX(a.grade) AS best
                   FROM {labeldiagram_attempt} a
                  WHERE $kpiwhere
               GROUP BY a.userid) b
          WHERE b.best >= :passpct",
        $kpiparams + ['passpct' => $passpct]
    );
    $kpis[get_string('kpipassrate', 'mod_labeldiagram')] = ($kpi->students ? round($passed / $kpi->students * 100) : 0) . '%';
}
foreach ($kpis as $label => $value) {
    $templatedata['kpis'][] = ['label' => $label, 'value' => (string)$value];
}

if ($tab === 'parts') {
    $slides = manager::get_slides($instance->id);
    $labels = manager::get_labels(array_keys($slides));
    $labeltext = [];
    foreach ($labels as $list) {
        foreach ($list as $l) {
            $labeltext[$l->id] = format_string($l->label, true, ['context' => $context]);
        }
    }
    $params = ['ldid' => $instance->id, 'mode' => $mode, 'state' => 'finished'] + $userparams;
    $stats = $DB->get_records_sql(
        "
        SELECT r.labelid, COUNT(r.id) AS responses, SUM(r.correct) AS correct,
               SUM(CASE WHEN r.placedlabelid = 0 THEN 1 ELSE 0 END) AS blank,
               COUNT(DISTINCT a.userid) AS users,
               COUNT(DISTINCT CASE WHEN r.correct = 0 THEN a.userid END) AS wrongusers,
               AVG(r.tries) AS avgtries, MAX(r.tries) AS maxtries
          FROM {labeldiagram_response} r
          JOIN {labeldiagram_attempt} a ON a.id = r.attemptid
         WHERE a.labeldiagramid = :ldid AND a.mode = :mode AND a.state = :state $userfilter
      GROUP BY r.labelid",
        $params
    );
    $confusions = [];
    if ($mode === 'test') {
        $rs = $DB->get_recordset_sql(
            "
            SELECT r.labelid, r.placedlabelid, COUNT(r.id) AS cnt
              FROM {labeldiagram_response} r
              JOIN {labeldiagram_attempt} a ON a.id = r.attemptid
             WHERE a.labeldiagramid = :ldid AND a.mode = :mode AND a.state = :state AND r.correct = 0
                   AND r.placedlabelid > 0 $userfilter
          GROUP BY r.labelid, r.placedlabelid",
            $params
        );
        foreach ($rs as $row) {
            if (!isset($confusions[$row->labelid]) || $row->cnt > $confusions[$row->labelid]->cnt) {
                $confusions[$row->labelid] = $row;
            }
        }
        $rs->close();
    }

    $parts = [
        'help' => get_string($mode === 'test' ? 'reportparts_test_help' : 'reportparts_practice_help', 'mod_labeldiagram'),
        'test' => $mode === 'test',
        'slides' => [],
    ];
    foreach ($slides as $slide) {
        $pins = manager::order_pins($labels[$slide->id]);
        if (!$pins) {
            continue;
        }
        $rows = [];
        foreach ($pins as $n => $label) {
            $s = $stats[$label->id] ?? null;
            $row = [
                'num' => $n + 1,
                'name' => $labeltext[$label->id],
                'color' => manager::label_color($label, array_search($label, $labels[$slide->id], true)),
                'responses' => $s ? (int)$s->responses : 0,
                'hasdata' => $s && $s->responses,
            ];
            if ($row['hasdata'] && $mode === 'test') {
                $row['wrong'] = labeldiagram_report_bar(($s->responses - $s->correct) / $s->responses * 100, false);
                $row['studentswrong'] = labeldiagram_report_bar($s->users ? $s->wrongusers / $s->users * 100 : 0, false);
                $row['studentsof'] = get_string(
                    'studentsofx',
                    'mod_labeldiagram',
                    ['wrong' => $s->wrongusers, 'total' => $s->users]
                );
                $row['blank'] = (int)$s->blank;
                $row['confused'] = isset($confusions[$label->id]) ? ($labeltext[$confusions[$label->id]->placedlabelid] ??
                    get_string('deletedlabel', 'mod_labeldiagram')) . ' (' . (int)$confusions[$label->id]->cnt . ')' : '-';
            } else if ($row['hasdata']) {
                $row['firsttry'] = labeldiagram_report_bar($s->correct / $s->responses * 100, true);
                $row['avgtries'] = format_float($s->avgtries, 2);
                $row['maxtries'] = (int)$s->maxtries;
            }
            $rows[] = $row;
        }
        $parts['slides'][] = [
            'title' => format_string($slide->title, true, ['context' => $context]),
            'rows' => $rows,
        ];
    }
    $templatedata['parts'] = $parts;
} else {
    // Attempts tab.
    $canmanage = has_capability('mod/labeldiagram:manage', $context);
    $totalattempts = $DB->count_records_sql("SELECT COUNT(1) $from", $params);
    $attempts = $totalattempts ? $DB->get_records_sql($sql, $params, $page * $perpage, $perpage) : [];
    $rows = [];
    foreach ($attempts as $a) {
        $identity = [];
        foreach ($identityfields as $field) {
            $identity[] = (string)($a->{$field} ?? '');
        }
        $rows[] = [
            'id' => (int)$a->id,
            'fullname' => fullname($a),
            'profileurl' => (new moodle_url('/user/view.php', ['id' => $a->userid, 'course' => $course->id]))->out(false),
            'identity' => $identity,
            'mode' => $a->mode,
            'modename' => get_string('mode' . $a->mode, 'mod_labeldiagram'),
            'attempt' => (int)$a->attempt,
            'state' => get_string('state' . $a->state, 'mod_labeldiagram'),
            'started' => userdate($a->timestart, get_string('strftimedatetimeshort', 'langconfig')),
            'duration' => $a->state === 'finished' ? format_time($a->duration) : '-',
            'correct' => (int)$a->correct,
            'total' => (int)$a->total,
            'grade' => $a->grade === null ? false : labeldiagram_report_bar((float)$a->grade, true),
        ];
    }
    $templatedata['attempts'] = [
        'canmanage' => $canmanage,
        // Moodle 5.1 renamed the toggle-all roles; 4.4 to 5.0 use master/slave.
        'togglemaster' => $CFG->branch >= 501 ? 'toggler' : 'master',
        'toggletarget' => $CFG->branch >= 501 ? 'target' : 'slave',
        'regradebutton' => $canmanage ? $OUTPUT->single_button(
            new moodle_url($baseurl, ['action' => 'regrade', 'sesskey' => sesskey()]),
            get_string('regradeall', 'mod_labeldiagram'),
            'post'
        ) : '',
        'noattempts' => $OUTPUT->notification(get_string('noattempts', 'mod_labeldiagram'), 'info'),
        'rows' => $rows,
        'hasrows' => !empty($rows),
        'pagingbar' => $OUTPUT->paging_bar($totalattempts, $page, $perpage, $baseurl),
        'actionurl' => $baseurl->out(false),
        'sesskey' => sesskey(),
        'identityheads' => array_map(fn($field) => \core_user\fields::get_display_name($field), $identityfields),
        'download' => $OUTPUT->download_dataformat_selector(
            get_string('downloadattempts', 'mod_labeldiagram'),
            $baseurl->out_omit_querystring(),
            'download',
            ['id' => $cm->id, 'tab' => 'attempts', 'mode' => $mode]
        ),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_labeldiagram/report', $templatedata);
echo $OUTPUT->footer();
