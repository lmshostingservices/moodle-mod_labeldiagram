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
 * Visual label editor for one slide (click-to-place queue, drag to adjust, purpose cards).
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_labeldiagram\local\manager;

$id = required_param('id', PARAM_INT);
$slideid = required_param('slideid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'labeldiagram');
$instance = $DB->get_record('labeldiagram', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/labeldiagram:manage', $context);
$slide = $DB->get_record('labeldiagram_slide', ['id' => $slideid, 'labeldiagramid' => $instance->id], '*', MUST_EXIST);

$PAGE->set_url('/mod/labeldiagram/editor.php', ['id' => $cm->id, 'slideid' => $slide->id]);
$PAGE->set_title(format_string($instance->name) . ': ' . format_string($slide->title));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
if ($node = $PAGE->settingsnav->find('labeldiagram_slides', navigation_node::TYPE_SETTING)) {
    $node->make_active();
}

$slides = array_values(manager::get_slides($instance->id));
$prev = $next = null;
$position = 0;
foreach ($slides as $i => $s) {
    if ($s->id == $slide->id) {
        $position = $i + 1;
        $prev = $slides[$i - 1] ?? null;
        $next = $slides[$i + 1] ?? null;
    }
}
$editorurl = fn($s) => $s ? (new moodle_url('/mod/labeldiagram/editor.php', ['id' => $cm->id, 'slideid' => $s->id]))
    ->out(false) : '';

$data = manager::editor_data($instance, $context, $slide);
$data += [
    'maxpins' => manager::MAX_PINS,
    'maxdistractors' => manager::MAX_DISTRACTORS,
    'position' => $position,
    'total' => count($slides),
    'prevurl' => $editorurl($prev),
    'nexturl' => $editorurl($next),
    'backurl' => (new moodle_url('/mod/labeldiagram/slides.php', ['id' => $cm->id]))->out(false),
    'previewurl' => (new moodle_url('/mod/labeldiagram/view.php', ['id' => $cm->id]))->out(false),
];

$PAGE->requires->js_call_amd('mod_labeldiagram/editor', 'init', ['#ld-editor']);

echo $OUTPUT->header();
echo html_writer::div(
    '',
    'ld-editor',
    [
    'id' => 'ld-editor',
    'data-config' => json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]
);
echo $OUTPUT->footer();
