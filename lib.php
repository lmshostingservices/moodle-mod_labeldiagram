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
 * Library of interface functions for mod_labeldiagram.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Grade method: highest attempt. */
define('LABELDIAGRAM_GRADEHIGHEST', 1);
/** Grade method: average of attempts. */
define('LABELDIAGRAM_GRADEAVERAGE', 2);
/** Grade method: first attempt. */
define('LABELDIAGRAM_GRADEFIRST', 3);
/** Grade method: last attempt. */
define('LABELDIAGRAM_GRADELAST', 4);

/**
 * Declares the features this module supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function labeldiagram_supports($feature) {
    if (defined('FEATURE_MOD_OTHERPURPOSE') && $feature === FEATURE_MOD_OTHERPURPOSE) {
        return MOD_PURPOSE_INTERACTIVECONTENT;
    }
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Normalises form data before saving.
 *
 * @param stdClass $data
 * @return stdClass
 */
function labeldiagram_prepare_instance_data(stdClass $data): stdClass {
    foreach (['allowpractice', 'allowtest', 'allowstudy', 'shufflelabels', 'leaderboard', 'sounds'] as $flag) {
        $data->$flag = empty($data->$flag) ? 0 : 1;
    }
    if (empty($data->allowpractice) && empty($data->allowtest) && empty($data->allowstudy)) {
        $data->allowpractice = 1;
    }
    $data->completionfinish = empty($data->completionfinish) ? 0 : 1;
    if (!isset($data->grade)) {
        $data->grade = 100;
    }
    $data->timelimit = empty($data->timelimit) ? 0 : (int)$data->timelimit;
    return $data;
}

/**
 * Adds a new instance.
 *
 * @param stdClass $data
 * @param mod_labeldiagram_mod_form|null $mform
 * @return int new instance id
 */
function labeldiagram_add_instance($data, $mform = null) {
    global $DB;
    $data = labeldiagram_prepare_instance_data($data);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('labeldiagram', $data);
    labeldiagram_grade_item_update($data);
    if (!empty($data->completionexpected)) {
        \core_completion\api::update_completion_date_event(
            $data->coursemodule,
            'labeldiagram',
            $data->id,
            $data->completionexpected
        );
    }
    return $data->id;
}

/**
 * Updates an instance.
 *
 * @param stdClass $data
 * @param mod_labeldiagram_mod_form|null $mform
 * @return bool
 */
function labeldiagram_update_instance($data, $mform = null) {
    global $DB;
    $data = labeldiagram_prepare_instance_data($data);
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('labeldiagram', $data);
    $instance = $DB->get_record('labeldiagram', ['id' => $data->id], '*', MUST_EXIST);
    labeldiagram_grade_item_update($instance);
    labeldiagram_update_grades($instance, 0, false);
    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'labeldiagram',
        $data->id,
        $data->completionexpected ?? null
    );
    return true;
}

/**
 * Deletes an instance and all its data.
 *
 * @param int $id
 * @return bool
 */
function labeldiagram_delete_instance($id) {
    global $DB;
    if (!$instance = $DB->get_record('labeldiagram', ['id' => $id])) {
        return false;
    }
    $attemptids = $DB->get_fieldset_select('labeldiagram_attempt', 'id', 'labeldiagramid = :ldid', ['ldid' => $id]);
    if ($attemptids) {
        [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('labeldiagram_response', "attemptid $insql", $params);
    }
    $DB->delete_records('labeldiagram_attempt', ['labeldiagramid' => $id]);
    $slideids = $DB->get_fieldset_select('labeldiagram_slide', 'id', 'labeldiagramid = :ldid', ['ldid' => $id]);
    if ($slideids) {
        [$insql, $params] = $DB->get_in_or_equal($slideids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('labeldiagram_label', "slideid $insql", $params);
    }
    $DB->delete_records('labeldiagram_slide', ['labeldiagramid' => $id]);
    labeldiagram_grade_item_delete($instance);
    $DB->delete_records('labeldiagram', ['id' => $id]);
    return true;
}

/**
 * Adds completion rule data to the course module info cache.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|false
 */
function labeldiagram_get_coursemodule_info($coursemodule) {
    global $DB;
    $fields = 'id, name, intro, introformat, completionfinish';
    if (!$instance = $DB->get_record('labeldiagram', ['id' => $coursemodule->instance], $fields)) {
        return false;
    }
    $result = new cached_cm_info();
    $result->name = $instance->name;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('labeldiagram', $instance, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionfinish'] = $instance->completionfinish;
    }
    return $result;
}

/**
 * Creates or updates the grade item.
 *
 * @param stdClass $instance
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int GRADE_UPDATE_OK etc.
 */
function labeldiagram_grade_item_update($instance, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $params = ['itemname' => $instance->name];
    if (isset($instance->cmidnumber)) {
        $params['idnumber'] = $instance->cmidnumber;
    }
    if ($instance->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $instance->grade;
        $params['grademin'] = 0;
    } else if ($instance->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$instance->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/labeldiagram', $instance->course, 'mod', 'labeldiagram', $instance->id, 0, $grades, $params);
}

/**
 * Deletes the grade item.
 *
 * @param stdClass $instance
 * @return int
 */
function labeldiagram_grade_item_delete($instance) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update(
        'mod/labeldiagram',
        $instance->course,
        'mod',
        'labeldiagram',
        $instance->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Returns user grades computed from finished test attempts.
 *
 * @param stdClass $instance
 * @param int $userid 0 for all users
 * @return array userid => stdClass(userid, rawgrade, dategraded)
 */
function labeldiagram_get_user_grades($instance, $userid = 0) {
    global $DB;
    $params = ['ldid' => $instance->id, 'mode' => 'test', 'state' => 'finished'];
    $where = 'labeldiagramid = :ldid AND mode = :mode AND state = :state';
    if ($userid) {
        $where .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }
    $attempts = $DB->get_records_select(
        'labeldiagram_attempt',
        $where,
        $params,
        'userid, attempt',
        'id, userid, attempt, grade, timefinish'
    );
    $byuser = [];
    foreach ($attempts as $attempt) {
        $byuser[$attempt->userid][] = $attempt;
    }
    $grades = [];
    foreach ($byuser as $uid => $list) {
        $percent = labeldiagram_calculate_percent($list, (int)$instance->grademethod);
        $last = end($list);
        $grade = new stdClass();
        $grade->userid = $uid;
        $grade->rawgrade = $instance->grade > 0 ? round($percent * $instance->grade / 100, 5) : null;
        $grade->dategraded = $last->timefinish;
        $grade->datesubmitted = $last->timefinish;
        $grades[$uid] = $grade;
    }
    return $grades;
}

/**
 * Applies the grading method to a list of attempts (ordered by attempt number).
 *
 * @param array $attempts
 * @param int $method
 * @return float percentage
 */
function labeldiagram_calculate_percent(array $attempts, int $method): float {
    $attempts = array_values($attempts);
    if (!$attempts) {
        return 0.0;
    }
    switch ($method) {
        case LABELDIAGRAM_GRADEAVERAGE:
            $sum = 0;
            foreach ($attempts as $a) {
                $sum += (float)$a->grade;
            }
            return $sum / count($attempts);
        case LABELDIAGRAM_GRADEFIRST:
            return (float)$attempts[0]->grade;
        case LABELDIAGRAM_GRADELAST:
            return (float)$attempts[count($attempts) - 1]->grade;
        default:
            $max = 0.0;
            foreach ($attempts as $a) {
                $max = max($max, (float)$a->grade);
            }
            return $max;
    }
}

/**
 * Pushes grades to the gradebook.
 *
 * @param stdClass $instance
 * @param int $userid
 * @param bool $nullifnone
 */
function labeldiagram_update_grades($instance, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    if ($instance->grade == 0) {
        labeldiagram_grade_item_update($instance);
        return;
    }
    if ($grades = labeldiagram_get_user_grades($instance, $userid)) {
        labeldiagram_grade_item_update($instance, $grades);
    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        labeldiagram_grade_item_update($instance, $grade);
    } else {
        labeldiagram_grade_item_update($instance);
    }
}

/**
 * Serves plugin files (slide images).
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if file not found
 */
function labeldiagram_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_course_login($course, true, $cm);
    if ($filearea !== 'slideimage') {
        return false;
    }
    require_capability('mod/labeldiagram:view', $context);
    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_labeldiagram', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}

/**
 * Adds plugin pages to the activity's secondary navigation.
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $node
 */
function labeldiagram_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node) {
    $cm = $settingsnav->get_page()->cm;
    if (!$cm) {
        return;
    }
    $context = context_module::instance($cm->id);
    if (has_capability('mod/labeldiagram:manage', $context)) {
        $node->add(
            get_string('manageslides', 'mod_labeldiagram'),
            new moodle_url('/mod/labeldiagram/slides.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'labeldiagram_slides',
            new pix_icon('i/edit', '')
        );
    }
    if (has_capability('mod/labeldiagram:viewreports', $context)) {
        $node->add(
            get_string('reports', 'mod_labeldiagram'),
            new moodle_url('/mod/labeldiagram/report.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'labeldiagram_reports',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Marks the activity viewed and triggers the event.
 *
 * @param stdClass $instance
 * @param stdClass $course
 * @param stdClass|cm_info $cm
 * @param context_module $context
 */
function labeldiagram_view($instance, $course, $cm, $context) {
    $event = \mod_labeldiagram\event\course_module_viewed::create(
        [
        'objectid' => $instance->id,
        'context' => $context,
        ]
    );
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('labeldiagram', $instance);
    $event->trigger();
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Adds reset elements to the course reset form.
 *
 * @param MoodleQuickForm $mform
 */
function labeldiagram_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'labeldiagramheader', get_string('modulenameplural', 'mod_labeldiagram'));
    $mform->addElement('advcheckbox', 'reset_labeldiagram_attempts', get_string('resetattempts', 'mod_labeldiagram'));
}

/**
 * Reset form defaults.
 *
 * @param stdClass $course
 * @return array
 */
function labeldiagram_reset_course_form_defaults($course) {
    return ['reset_labeldiagram_attempts' => 1];
}

/**
 * Removes user data when a course is reset.
 *
 * @param stdClass $data
 * @return array status
 */
function labeldiagram_reset_userdata($data) {
    global $DB;
    $status = [];
    if (!empty($data->reset_labeldiagram_attempts)) {
        $instances = $DB->get_records('labeldiagram', ['course' => $data->courseid]);
        foreach ($instances as $instance) {
            $attemptids = $DB->get_fieldset_select(
                'labeldiagram_attempt',
                'id',
                'labeldiagramid = :ldid',
                ['ldid' => $instance->id]
            );
            if ($attemptids) {
                [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
                $DB->delete_records_select('labeldiagram_response', "attemptid $insql", $params);
            }
            $DB->delete_records('labeldiagram_attempt', ['labeldiagramid' => $instance->id]);
            if (empty($data->reset_gradebook_grades)) {
                labeldiagram_grade_item_update($instance, 'reset');
            }
        }
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_labeldiagram'),
            'item' => get_string('resetattempts', 'mod_labeldiagram'),
            'error' => false,
        ];
    }
    return $status;
}

/**
 * Resets gradebook grades for all instances in a course.
 *
 * @param int $courseid
 * @param string $type
 */
function labeldiagram_reset_gradebook($courseid, $type = '') {
    global $DB;
    $instances = $DB->get_records('labeldiagram', ['course' => $courseid]);
    foreach ($instances as $instance) {
        labeldiagram_grade_item_update($instance, 'reset');
    }
}
