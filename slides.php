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
 * Manage slides: bulk upload, reorder, replace, delete.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_labeldiagram\local\manager;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$slideid = optional_param('slideid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'labeldiagram');
$instance = $DB->get_record('labeldiagram', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/labeldiagram:manage', $context);

$baseurl = new moodle_url('/mod/labeldiagram/slides.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($instance->name) . ': ' . get_string('manageslides', 'mod_labeldiagram'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
if ($node = $PAGE->settingsnav->find('labeldiagram_slides', navigation_node::TYPE_SETTING)) {
    $node->make_active();
}

$slide = null;
if ($slideid) {
    $slide = $DB->get_record('labeldiagram_slide', ['id' => $slideid, 'labeldiagramid' => $instance->id], '*', MUST_EXIST);
}

// Simple actions.
if (($action === 'up' || $action === 'down') && $slide) {
    require_sesskey();
    manager::move_slide($slide, $action === 'up' ? -1 : 1);
    redirect($baseurl);
}
if ($action === 'delete' && $slide) {
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        manager::delete_slide($context, $slide);
        redirect($baseurl, get_string('slidedeleted', 'mod_labeldiagram'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeleteslide', 'mod_labeldiagram', format_string($slide->title)),
        new moodle_url($baseurl, ['action' => 'delete', 'slideid' => $slide->id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}
if ($action === 'replace' && $slide) {
    $form = new \mod_labeldiagram\form\replace_image_form($baseurl);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        manager::replace_slide_image($context, $slide, (int)$data->image);
        redirect(
            $baseurl,
            get_string('imagereplaced', 'mod_labeldiagram'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    $draftid = file_get_submitted_draft_itemid('image');
    file_prepare_draft_area(
        $draftid,
        $context->id,
        'mod_labeldiagram',
        'slideimage',
        $slide->id,
        manager::image_filemanager_options(1)
    );
    $form->set_data(['id' => $cm->id, 'slideid' => $slide->id, 'image' => $draftid]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('replaceimage', 'mod_labeldiagram') . ': ' . format_string($slide->title), 3);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// Bulk upload form.
$addform = new \mod_labeldiagram\form\add_slides_form($baseurl);
$addform->set_data(['id' => $cm->id]);
if ($data = $addform->get_data()) {
    $count = manager::create_slides_from_draft($instance, $context, (int)$data->images);
    redirect(
        $baseurl,
        get_string('slidescreated', 'mod_labeldiagram', $count),
        null,
        $count ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
    );
}

// Slide cards.
$slides = manager::get_slides($instance->id);
$labels = manager::get_labels(array_keys($slides));
$cards = [];
$i = 0;
$total = count($slides);
foreach ($slides as $s) {
    $i++;
    [$url] = manager::get_slide_image($context, $s->id);
    $pins = count(array_filter($labels[$s->id], fn($l) => empty($l->distractor)));
    $sesskey = sesskey();
    $cards[] = [
        'number' => $i,
        'title' => format_string($s->title, true, ['context' => $context]),
        'image' => $url,
        'pins' => $pins,
        'distractors' => count($labels[$s->id]) - $pins,
        'nopins' => $pins === 0,
        'editurl' => (new moodle_url('/mod/labeldiagram/editor.php', ['id' => $cm->id, 'slideid' => $s->id]))->out(false),
        'upurl' => $i > 1 ? (new moodle_url($baseurl, ['action' => 'up', 'slideid' => $s->id, 'sesskey' => $sesskey]))
            ->out(false) : null,
        'downurl' => $i < $total ? (new moodle_url(
            $baseurl,
            ['action' => 'down', 'slideid' => $s->id,
            'sesskey' => $sesskey]
        ))->out(false) : null,
        'replaceurl' => (new moodle_url($baseurl, ['action' => 'replace', 'slideid' => $s->id]))->out(false),
        'deleteurl' => (new moodle_url($baseurl, ['action' => 'delete', 'slideid' => $s->id]))->out(false),
    ];
}

$PAGE->requires->js_call_amd('mod_labeldiagram/copy', 'init', ['.ld-copy']);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'mod_labeldiagram/slides',
    [
    'cards' => $cards,
    'hascards' => !empty($cards),
    'count' => $total,
    'maxpins' => manager::MAX_PINS,
    'viewurl' => (new moodle_url('/mod/labeldiagram/view.php', ['id' => $cm->id]))->out(false),
    'addform' => $addform->render(),
    'imageprompt' => get_string('imageprompt', 'mod_labeldiagram'),
    ]
);
echo $OUTPUT->footer();
