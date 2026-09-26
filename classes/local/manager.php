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

namespace mod_labeldiagram\local;

use context_module;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Core business logic for slides, labels and attempts.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string[] Default label colour palette. */
    public const PALETTE = ['#6366F1', '#0EA5E9', '#10B981', '#F59E0B', '#EF4444', '#EC4899', '#8B5CF6', '#14B8A6',
        '#F97316', '#84CC16', '#06B6D4', '#D946EF'];

    /** @var int Seconds of grace allowed after a time limit expires (network latency). */
    public const TIME_GRACE = 30;

    /** @var int Maximum labelled parts (pins) per slide. */
    public const MAX_PINS = 10;

    /** @var int Maximum distractor labels per slide. */
    public const MAX_DISTRACTORS = 4;

    /** @var string[] Accepted image types. */
    public const IMAGE_TYPES = ['.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg'];

    /** @var int Most images taken from one upload (including images inside ZIP files). */
    public const MAX_UPLOAD_IMAGES = 100;

    /** @var int Largest total unpacked size of a ZIP upload, in bytes. */
    public const MAX_ZIP_BYTES = 200 * 1024 * 1024;

    /**
     * Returns the slides of an instance, ordered.
     *
     * @param int $ldid
     * @return stdClass[]
     */
    public static function get_slides(int $ldid): array {
        global $DB;
        return $DB->get_records('labeldiagram_slide', ['labeldiagramid' => $ldid], 'sortorder ASC, id ASC');
    }

    /**
     * Returns labels grouped by slide id.
     *
     * @param int[] $slideids
     * @return array slideid => stdClass[]
     */
    public static function get_labels(array $slideids): array {
        global $DB;
        $result = array_fill_keys($slideids, []);
        if (!$slideids) {
            return $result;
        }
        [$insql, $params] = $DB->get_in_or_equal($slideids, SQL_PARAMS_NAMED);
        $labels = $DB->get_records_select('labeldiagram_label', "slideid $insql", $params, 'sortorder ASC, id ASC');
        foreach ($labels as $label) {
            $result[$label->slideid][] = $label;
        }
        return $result;
    }

    /**
     * Colour for a label, falling back to the palette.
     *
     * @param stdClass $label
     * @param int $index
     * @return string
     */
    public static function label_color(stdClass $label, int $index): string {
        if (!empty($label->color) && preg_match('/^#[0-9a-fA-F]{6}$/', $label->color)) {
            return strtoupper($label->color);
        }
        return self::PALETTE[$index % count(self::PALETTE)];
    }

    /**
     * Returns the stored image for a slide.
     *
     * @param \context $context
     * @param int $slideid
     * @return \stored_file|null
     */
    public static function get_slide_file(\context $context, int $slideid): ?\stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_labeldiagram', 'slideimage', $slideid, 'sortorder, id', false);
        return $files ? reset($files) : null;
    }

    /**
     * Image information for a slide.
     *
     * @param \context $context
     * @param int $slideid
     * @return array [url|null, width, height]
     */
    public static function get_slide_image(\context $context, int $slideid): array {
        $file = self::get_slide_file($context, $slideid);
        if (!$file) {
            return [null, 4, 3];
        }
        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_labeldiagram',
            'slideimage',
            $slideid,
            $file->get_filepath(),
            $file->get_filename()
        );
        $url->param('rev', $file->get_timemodified());
        $info = $file->get_imageinfo();
        $width = !empty($info['width']) ? (int)$info['width'] : 1200;
        $height = !empty($info['height']) ? (int)$info['height'] : 800;
        return [$url->out(false), $width, $height];
    }

    /**
     * Makes a readable title from a file name.
     *
     * @param string $filename
     * @return string
     */
    public static function title_from_filename(string $filename): string {
        $title = preg_replace('/\.[^.]+$/', '', $filename);
        $title = trim(preg_replace('/[_\-]+/', ' ', $title));
        return \core_text::substr($title !== '' ? $title : $filename, 0, 255);
    }

    /**
     * Checks that a file on disk really is a supported image, not just named like one.
     *
     * @param string $path
     * @return bool
     */
    protected static function is_image_path(string $path): bool {
        if (preg_match('/\.svg$/i', $path)) {
            $size = filesize($path);
            return $size !== false && $size <= 5 * 1024 * 1024 && self::is_svg_content((string)file_get_contents($path));
        }
        // Check the file signature (magic bytes), not the name.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return false;
        }
        $info = getimagesize($path);
        return $info !== false && $info[0] > 0 && $info[1] > 0;
    }

    /**
     * Checks that content is an SVG document.
     *
     * @param string $content
     * @return bool
     */
    protected static function is_svg_content(string $content): bool {
        return (bool)preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*>\s*)?<svg[\s>]/is', $content);
    }

    /**
     * Creates one slide per image found in a draft area.
     *
     * @param stdClass $instance
     * @param \context $context
     * @param int $draftitemid
     * @return int number of slides created
     */
    public static function create_slides_from_draft(stdClass $instance, \context $context, int $draftitemid): int {
        global $DB, $USER;
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'filename', false);

        // Collect image sources: uploaded images plus images inside any ZIP archives.
        $sources = [];
        foreach ($files as $file) {
            $filename = $file->get_filename();
            if (preg_match('/\.zip$/i', $filename)) {
                $packer = get_file_packer('application/zip');
                // Check the archive before unpacking it, so a huge or malicious ZIP cannot fill the disk.
                $listed = $file->list_files($packer);
                if ($listed === false) {
                    throw new \moodle_exception('zipinvalid', 'mod_labeldiagram', '', s($filename));
                }
                $bytes = 0;
                $entries = 0;
                foreach ($listed as $entry) {
                    if (!$entry->is_directory) {
                        $bytes += (int)$entry->size;
                        $entries++;
                    }
                }
                if ($bytes > self::MAX_ZIP_BYTES || $entries > self::MAX_UPLOAD_IMAGES * 2) {
                    throw new \moodle_exception(
                        'ziptoolarge',
                        'mod_labeldiagram',
                        '',
                        (object)[
                        'files' => self::MAX_UPLOAD_IMAGES,
                        'size' => display_size(self::MAX_ZIP_BYTES),
                        ]
                    );
                }
                $tmpdir = make_request_directory();
                $file->extract_to_pathname($packer, $tmpdir);
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $tmpdir,
                        \FilesystemIterator::SKIP_DOTS
                    )
                );
                foreach ($iterator as $path) {
                    $name = $path->getFilename();
                    if (
                        $path->isFile() && !$path->isLink() && strpos($name, '.') !== 0 &&
                            strpos($path->getPathname(), '__MACOSX') === false &&
                            preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $name) && self::is_image_path($path->getPathname())
                    ) {
                        $sources[] = ['name' => $name, 'path' => $path->getPathname()];
                    }
                }
            } else if (
                $file->is_valid_image() ||
                ($file->get_mimetype() === 'image/svg+xml' && self::is_svg_content($file->get_content()))
            ) {
                $sources[] = ['name' => $filename, 'file' => $file];
            }
        }
        if (count($sources) > self::MAX_UPLOAD_IMAGES) {
            throw new \moodle_exception(
                'ziptoolarge',
                'mod_labeldiagram',
                '',
                (object)[
                'files' => self::MAX_UPLOAD_IMAGES,
                'size' => display_size(self::MAX_ZIP_BYTES),
                ]
            );
        }
        usort($sources, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

        $sortorder = (int)$DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {labeldiagram_slide} WHERE labeldiagramid = :ldid',
            ['ldid' => $instance->id]
        );
        $count = 0;
        foreach ($sources as $source) {
            $sortorder++;
            $slide = (object)[
                'labeldiagramid' => $instance->id,
                'sortorder' => $sortorder,
                'title' => self::title_from_filename($source['name']),
                'instructions' => '',
                'timemodified' => time(),
            ];
            $slide->id = $DB->insert_record('labeldiagram_slide', $slide);
            $record = [
                'contextid' => $context->id,
                'component' => 'mod_labeldiagram',
                'filearea' => 'slideimage',
                'itemid' => $slide->id,
                'filepath' => '/',
                'filename' => clean_param($source['name'], PARAM_FILE) ?: 'image.png',
            ];
            if (isset($source['file'])) {
                $fs->create_file_from_storedfile($record, $source['file']);
            } else {
                $fs->create_file_from_pathname($record, $source['path']);
            }
            $count++;
        }
        return $count;
    }

    /**
     * Replaces the image of an existing slide from a draft area.
     *
     * @param \context $context
     * @param stdClass $slide
     * @param int $draftitemid
     */
    public static function replace_slide_image(\context $context, stdClass $slide, int $draftitemid): void {
        global $DB;
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'mod_labeldiagram',
            'slideimage',
            $slide->id,
            self::image_filemanager_options(1)
        );
        $DB->set_field('labeldiagram_slide', 'timemodified', time(), ['id' => $slide->id]);
    }

    /**
     * File manager options for slide images.
     *
     * @param int $maxfiles
     * @return array
     */
    public static function image_filemanager_options(int $maxfiles = -1): array {
        global $CFG;
        // FILE_INTERNAL lives in repository/lib.php, which is not always loaded yet.
        require_once($CFG->dirroot . '/repository/lib.php');
        return [
            'subdirs' => 0,
            'maxfiles' => $maxfiles,
            'accepted_types' => self::IMAGE_TYPES,
            'return_types' => FILE_INTERNAL,
        ];
    }

    /**
     * Deletes a slide, its labels and image.
     *
     * @param \context $context
     * @param stdClass $slide
     */
    public static function delete_slide(\context $context, stdClass $slide): void {
        global $DB;
        $DB->delete_records('labeldiagram_label', ['slideid' => $slide->id]);
        $DB->delete_records('labeldiagram_slide', ['id' => $slide->id]);
        get_file_storage()->delete_area_files($context->id, 'mod_labeldiagram', 'slideimage', $slide->id);
        self::normalise_sortorder($slide->labeldiagramid);
    }

    /**
     * Re-numbers slide sort order 1..n.
     *
     * @param int $ldid
     */
    public static function normalise_sortorder(int $ldid): void {
        global $DB;
        $i = 0;
        foreach (self::get_slides($ldid) as $slide) {
            $i++;
            if ((int)$slide->sortorder !== $i) {
                $DB->set_field('labeldiagram_slide', 'sortorder', $i, ['id' => $slide->id]);
            }
        }
    }

    /**
     * Moves a slide up or down.
     *
     * @param stdClass $slide
     * @param int $direction -1 up, 1 down
     */
    public static function move_slide(stdClass $slide, int $direction): void {
        global $DB;
        self::normalise_sortorder($slide->labeldiagramid);
        $slides = array_values(self::get_slides($slide->labeldiagramid));
        foreach ($slides as $index => $s) {
            if ($s->id == $slide->id) {
                $target = $index + $direction;
                if (isset($slides[$target])) {
                    $DB->set_field('labeldiagram_slide', 'sortorder', $slides[$target]->sortorder, ['id' => $s->id]);
                    $DB->set_field('labeldiagram_slide', 'sortorder', $s->sortorder, ['id' => $slides[$target]->id]);
                }
                return;
            }
        }
    }

    /**
     * Saves the title and labels of a slide (editor).
     *
     * @param stdClass $slide
     * @param string $title
     * @param string $instructions
     * @param array $labels list of arrays(id,label,x,y,color,purpose,distractor)
     * @return array saved label ids in order
     */
    public static function save_slide(stdClass $slide, string $title, string $instructions, array $labels): array {
        global $DB;
        $pincount = count(array_filter($labels, fn($l) => empty($l['distractor']) && trim($l['label']) !== ''));
        $distractorcount = count(array_filter($labels, fn($l) => !empty($l['distractor']) && trim($l['label']) !== ''));
        if ($pincount > self::MAX_PINS || $distractorcount > self::MAX_DISTRACTORS) {
            throw new moodle_exception(
                'toomanylabels',
                'mod_labeldiagram',
                '',
                ['pins' => self::MAX_PINS,
                'distractors' => self::MAX_DISTRACTORS]
            );
        }
        $transaction = $DB->start_delegated_transaction();
        $slide->title = \core_text::substr(trim($title) !== '' ? trim($title) : $slide->title, 0, 255);
        $slide->instructions = $instructions;
        $slide->timemodified = time();
        $DB->update_record('labeldiagram_slide', $slide);

        $existing = $DB->get_records('labeldiagram_label', ['slideid' => $slide->id], '', 'id');
        $keep = [];
        $ids = [];
        $sortorder = 0;
        foreach ($labels as $data) {
            $text = trim(clean_param($data['label'], PARAM_TEXT));
            if ($text === '') {
                continue;
            }
            $sortorder++;
            $record = (object)[
                'slideid' => $slide->id,
                'sortorder' => $sortorder,
                'label' => \core_text::substr($text, 0, 255),
                'x' => max(0, min(100, round((float)$data['x'], 4))),
                'y' => max(0, min(100, round((float)$data['y'], 4))),
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'] ?? '') ? strtoupper($data['color']) : '',
                'purpose' => (string)($data['purpose'] ?? ''),
                'purposeformat' => FORMAT_MARKDOWN,
                'distractor' => empty($data['distractor']) ? 0 : 1,
            ];
            if (!empty($data['id']) && isset($existing[$data['id']])) {
                $record->id = (int)$data['id'];
                $DB->update_record('labeldiagram_label', $record);
            } else {
                $record->id = $DB->insert_record('labeldiagram_label', $record);
            }
            $keep[$record->id] = true;
            $ids[] = (int)$record->id;
        }
        foreach ($existing as $id => $unused) {
            if (!isset($keep[$id])) {
                $DB->delete_records('labeldiagram_label', ['id' => $id]);
            }
        }
        $transaction->allow_commit();
        return $ids;
    }

    /**
     * Formats the purpose text of a label.
     *
     * @param stdClass $label
     * @param \context $context
     * @return string HTML
     */
    public static function format_purpose(stdClass $label, \context $context): string {
        if (trim((string)$label->purpose) === '') {
            return '';
        }
        return format_text(
            $label->purpose,
            $label->purposeformat ?? FORMAT_MARKDOWN,
            ['context' => $context, 'noclean' => false, 'para' => false]
        );
    }

    /**
     * Data for the slide editor.
     *
     * @param stdClass $instance
     * @param \context $context
     * @param stdClass $slide
     * @return array
     */
    public static function editor_data(stdClass $instance, \context $context, stdClass $slide): array {
        [$url, $w, $h] = self::get_slide_image($context, $slide->id);
        $labels = self::get_labels([$slide->id])[$slide->id];
        $out = [];
        foreach ($labels as $i => $label) {
            $out[] = [
                'id' => (int)$label->id,
                'label' => $label->label,
                'x' => (float)$label->x,
                'y' => (float)$label->y,
                'color' => self::label_color($label, $i),
                'purpose' => (string)$label->purpose,
                'distractor' => (int)$label->distractor,
            ];
        }
        return [
            'slideid' => (int)$slide->id,
            'title' => $slide->title,
            'instructions' => (string)$slide->instructions,
            'image' => $url,
            'width' => $w,
            'height' => $h,
            'labels' => $out,
            'palette' => self::PALETTE,
        ];
    }

    /**
     * Summary of a user's attempts.
     *
     * @param stdClass $instance
     * @param int $userid
     * @return array
     */
    public static function user_summary(stdClass $instance, int $userid): array {
        global $DB;
        $tests = $DB->get_records(
            'labeldiagram_attempt',
            ['labeldiagramid' => $instance->id, 'userid' => $userid,
            'mode' => 'test'],
            'attempt ASC'
        );
        $finished = array_filter($tests, fn($a) => $a->state === 'finished');
        $used = count($tests);
        $best = null;
        foreach ($finished as $a) {
            $best = $best === null ? (float)$a->grade : max($best, (float)$a->grade);
        }
        $max = (int)$instance->maxattempts;
        return [
            'attemptsused' => $used,
            'maxattempts' => $max,
            'attemptsleft' => $max ? max(0, $max - $used) : -1,
            'best' => $best === null ? null : round($best, 1),
            'final' => $finished ? round(labeldiagram_calculate_percent($finished, (int)$instance->grademethod), 1) : null,
        ];
    }

    /**
     * Data for study (explore) mode — all answers visible.
     *
     * @param stdClass $instance
     * @param \context $context
     * @return array
     */
    public static function study_data(stdClass $instance, \context $context): array {
        $slides = self::get_slides($instance->id);
        $labels = self::get_labels(array_keys($slides));
        $out = [];
        foreach ($slides as $slide) {
            [$url, $w, $h] = self::get_slide_image($context, $slide->id);
            $pins = [];
            $n = 0;
            foreach (self::order_pins($labels[$slide->id]) as $label) {
                $n++;
                $pins[] = [
                    'token' => 'p' . $label->id,
                    'x' => (float)$label->x,
                    'y' => (float)$label->y,
                    'number' => $n,
                    'label' => $label->label,
                    'color' => self::label_color($label, array_search($label, $labels[$slide->id], true)),
                    'purpose' => $instance->showpurpose ? self::format_purpose($label, $context) : '',
                ];
            }
            $out[] = [
                'id' => (int)$slide->id,
                'title' => format_string($slide->title, true, ['context' => $context]),
                'instructions' => format_string((string)$slide->instructions, true, ['context' => $context]),
                'image' => $url,
                'width' => $w,
                'height' => $h,
                'pins' => $pins,
                'labels' => [],
            ];
        }
        return ['mode' => 'study', 'slides' => $out];
    }

    /**
     * Returns the non-distractor labels ordered for pin numbering (top-to-bottom).
     *
     * @param stdClass[] $labels
     * @return stdClass[]
     */
    public static function order_pins(array $labels): array {
        $pins = array_values(array_filter($labels, fn($l) => empty($l->distractor)));
        // Top-to-bottom so leader lines to the left-hand label rail never cross needlessly.
        usort($pins, fn($a, $b) => ((float)$a->y <=> (float)$b->y) ?: ((float)$a->x <=> (float)$b->x));
        return $pins;
    }

    /**
     * Starts an attempt and returns the player payload.
     *
     * @param stdClass $instance
     * @param \cm_info|stdClass $cm
     * @param stdClass $course
     * @param \context $context
     * @param string $mode practice|test
     * @param int $userid
     * @return array
     */
    public static function start_attempt(
        stdClass $instance,
        $cm,
        stdClass $course,
        \context $context,
        string $mode,
        int $userid
    ): array {
        global $DB;
        if (!in_array($mode, ['practice', 'test'], true)) {
            throw new moodle_exception('invalidmode', 'mod_labeldiagram');
        }
        if (($mode === 'practice' && !$instance->allowpractice) || ($mode === 'test' && !$instance->allowtest)) {
            throw new moodle_exception('modedisabled', 'mod_labeldiagram');
        }

        // Close any unfinished attempts in the same mode (they are graded as they stand).
        $open = $DB->get_records(
            'labeldiagram_attempt',
            ['labeldiagramid' => $instance->id, 'userid' => $userid,
            'mode' => $mode, 'state' => 'inprogress']
        );
        foreach ($open as $attempt) {
            self::finish_attempt($instance, $cm, $course, $context, $attempt);
        }

        if ($mode === 'test' && $instance->maxattempts) {
            $used = $DB->count_records(
                'labeldiagram_attempt',
                ['labeldiagramid' => $instance->id, 'userid' => $userid,
                'mode' => 'test']
            );
            if ($used >= $instance->maxattempts) {
                throw new moodle_exception('nomoreattempts', 'mod_labeldiagram');
            }
        }

        $slides = self::get_slides($instance->id);
        if (!$slides) {
            throw new moodle_exception('noslides', 'mod_labeldiagram');
        }
        $labels = self::get_labels(array_keys($slides));
        $map = ['pins' => [], 'labels' => []];
        $payload = [];
        $total = 0;
        foreach ($slides as $slide) {
            [$url, $w, $h] = self::get_slide_image($context, $slide->id);
            $pins = [];
            $chips = [];
            $answers = [];
            $purposes = [];
            $labeltokens = [];
            foreach ($labels[$slide->id] as $i => $label) {
                $token = 'l' . random_string(12);
                $map['labels'][$token] = (int)$label->id;
                $labeltokens[$label->id] = $token;
                $chip = [
                    'token' => $token,
                    'text' => format_string($label->label, true, ['context' => $context]),
                    'color' => self::label_color($label, $i),
                ];
                $chips[] = $chip;
                if ($mode === 'practice' && $instance->showpurpose == 1) {
                    $html = self::format_purpose($label, $context);
                    if ($html !== '') {
                        $purposes[] = ['token' => $token, 'html' => $html];
                    }
                }
            }
            $n = 0;
            foreach (self::order_pins($labels[$slide->id]) as $label) {
                $n++;
                $token = 'p' . random_string(12);
                $map['pins'][$token] = (int)$label->id;
                $pins[] = ['token' => $token, 'x' => (float)$label->x, 'y' => (float)$label->y, 'number' => $n];
                if ($mode === 'practice') {
                    $answers[] = ['pin' => $token, 'label' => $labeltokens[$label->id]];
                }
                $total++;
            }
            if ($instance->shufflelabels) {
                shuffle($chips);
            }
            $payload[] = [
                'id' => (int)$slide->id,
                'title' => format_string($slide->title, true, ['context' => $context]),
                'instructions' => format_string((string)$slide->instructions, true, ['context' => $context]),
                'image' => $url,
                'width' => $w,
                'height' => $h,
                'pins' => $pins,
                'labels' => $chips,
                'answers' => $answers,
                'purposes' => $purposes,
            ];
        }

        $attemptno = 1 + (int)$DB->get_field_sql(
            'SELECT MAX(attempt) FROM {labeldiagram_attempt}
            WHERE labeldiagramid = :ldid AND userid = :userid AND mode = :mode',
            ['ldid' => $instance->id, 'userid' => $userid, 'mode' => $mode]
        );
        $attempt = (object)[
            'labeldiagramid' => $instance->id,
            'userid' => $userid,
            'attempt' => $attemptno,
            'mode' => $mode,
            'state' => 'inprogress',
            'pinmap' => json_encode($map),
            'timestart' => time(),
            'timefinish' => 0,
            'correct' => 0,
            'total' => $total,
            'grade' => null,
            'duration' => 0,
        ];
        $attempt->id = $DB->insert_record('labeldiagram_attempt', $attempt);

        return [
            'attemptid' => (int)$attempt->id,
            'attempt' => $attemptno,
            'mode' => $mode,
            'timelimit' => $mode === 'test' ? (int)$instance->timelimit : 0,
            'total' => $total,
            'slides' => $payload,
        ];
    }

    /**
     * Loads and validates an attempt belonging to the user.
     *
     * @param int $attemptid
     * @param int $userid
     * @return stdClass
     */
    public static function get_user_attempt(int $attemptid, int $userid): stdClass {
        global $DB;
        $attempt = $DB->get_record('labeldiagram_attempt', ['id' => $attemptid], '*', MUST_EXIST);
        if ((int)$attempt->userid !== $userid) {
            throw new moodle_exception('notyourattempt', 'mod_labeldiagram');
        }
        return $attempt;
    }

    /**
     * Whether the attempt is past its time limit (including grace).
     *
     * @param stdClass $instance
     * @param stdClass $attempt
     * @return bool
     */
    public static function is_overdue(stdClass $instance, stdClass $attempt): bool {
        return $attempt->mode === 'test' && $instance->timelimit > 0
            && time() > $attempt->timestart + $instance->timelimit + self::TIME_GRACE;
    }

    /**
     * Records the placements for one slide and returns the marked results.
     *
     * @param stdClass $instance
     * @param \context $context
     * @param stdClass $attempt
     * @param int $slideid
     * @param array $placements list of [pin => token, label => token, tries => int]
     * @return array results
     */
    public static function submit_slide(
        stdClass $instance,
        \context $context,
        stdClass $attempt,
        int $slideid,
        array $placements
    ): array {
        global $DB;
        if ($attempt->state !== 'inprogress') {
            throw new moodle_exception('attemptclosed', 'mod_labeldiagram');
        }
        if (self::is_overdue($instance, $attempt)) {
            throw new moodle_exception('timeexpired', 'mod_labeldiagram');
        }
        if (!$DB->record_exists('labeldiagram_slide', ['id' => $slideid, 'labeldiagramid' => $instance->id])) {
            throw new moodle_exception('invalidslide', 'mod_labeldiagram');
        }
        if ($DB->record_exists('labeldiagram_response', ['attemptid' => $attempt->id, 'slideid' => $slideid])) {
            throw new moodle_exception('slidealreadysubmitted', 'mod_labeldiagram');
        }
        $map = json_decode($attempt->pinmap, true);
        $labelsbyid = $DB->get_records('labeldiagram_label', ['slideid' => $slideid]);
        $tokenforlabel = array_flip($map['labels']);

        $placed = [];
        foreach ($placements as $p) {
            $pin = $p['pin'] ?? '';
            if (!isset($map['pins'][$pin])) {
                continue;
            }
            $placedid = isset($map['labels'][$p['label'] ?? '']) ? (int)$map['labels'][$p['label']] : 0;
            // Only labels from this slide can be placed on it.
            $placed[$pin] = [
                'label' => isset($labelsbyid[$placedid]) ? $placedid : 0,
                'tries' => max(0, (int)($p['tries'] ?? 1)),
            ];
        }

        $results = [];
        $now = time();
        foreach ($map['pins'] as $pintoken => $labelid) {
            if (!isset($labelsbyid[$labelid])) {
                continue;
            }
            $placedid = $placed[$pintoken]['label'] ?? 0;
            $tries = $placed[$pintoken]['tries'] ?? 0;
            // Identical label texts (e.g. two "Bolt" labels) are interchangeable.
            $correct = $placedid && ((int)$placedid === (int)$labelid || (isset($labelsbyid[$placedid]) &&
                \core_text::strtolower(trim($labelsbyid[$placedid]->label)) ===
                \core_text::strtolower(trim($labelsbyid[$labelid]->label))));
            if ($attempt->mode === 'practice') {
                // In practice mode only first-try placements score.
                $correct = $correct && $tries <= 1;
            }
            $DB->insert_record(
                'labeldiagram_response',
                (object)[
                'attemptid' => $attempt->id,
                'slideid' => $slideid,
                'labelid' => $labelid,
                'placedlabelid' => (int)$placedid,
                'correct' => $correct ? 1 : 0,
                'tries' => $tries,
                'timecreated' => $now,
                ]
            );
            $label = $labelsbyid[$labelid];
            $results[] = [
                'pin' => $pintoken,
                'placed' => $placedid ? ($tokenforlabel[$placedid] ?? '') : '',
                'answer' => $tokenforlabel[$labelid] ?? '',
                'correct' => $correct ? 1 : 0,
                'purpose' => $instance->showpurpose ? self::format_purpose($label, $context) : '',
            ];
        }
        return $results;
    }

    /**
     * Finishes an attempt, grades it and updates gradebook and completion.
     *
     * @param stdClass $instance
     * @param \cm_info|stdClass $cm
     * @param stdClass $course
     * @param \context $context
     * @param stdClass $attempt
     * @return array summary
     */
    public static function finish_attempt(
        stdClass $instance,
        $cm,
        stdClass $course,
        \context $context,
        stdClass $attempt
    ): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/labeldiagram/lib.php');

        if ($attempt->state !== 'finished') {
            $correct = $DB->count_records('labeldiagram_response', ['attemptid' => $attempt->id, 'correct' => 1]);
            $total = max(1, (int)$attempt->total);
            $now = time();
            $duration = $now - $attempt->timestart;
            if ($attempt->mode === 'test' && $instance->timelimit > 0) {
                $duration = min($duration, (int)$instance->timelimit);
            }
            $attempt->state = 'finished';
            $attempt->correct = $correct;
            $attempt->grade = round($correct / $total * 100, 5);
            $attempt->timefinish = $now;
            $attempt->duration = $duration;
            $DB->update_record('labeldiagram_attempt', $attempt);

            if ($attempt->mode === 'test') {
                labeldiagram_update_grades($instance, $attempt->userid);
            }
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $attempt->userid);
            }
            $event = \mod_labeldiagram\event\attempt_finished::create(
                [
                'objectid' => $attempt->id,
                'context' => $context,
                'relateduserid' => $attempt->userid,
                'other' => ['mode' => $attempt->mode, 'grade' => $attempt->grade],
                ]
            );
            $event->add_record_snapshot('labeldiagram_attempt', $attempt);
            $event->trigger();
        }

        $summary = self::user_summary($instance, (int)$attempt->userid);
        return [
            'attemptid' => (int)$attempt->id,
            'mode' => $attempt->mode,
            'correct' => (int)$attempt->correct,
            'total' => (int)$attempt->total,
            'percent' => round((float)$attempt->grade, 1),
            'duration' => (int)$attempt->duration,
            'attemptsleft' => $summary['attemptsleft'],
            'best' => $summary['best'] ?? 0,
            'leaderboard' => ($instance->leaderboard && $attempt->mode === 'test') ? self::leaderboard(
                $instance,
                (int)$attempt->userid
            ) : [],
        ];
    }

    /**
     * Top scores (best test attempt per user).
     *
     * @param stdClass $instance
     * @param int $currentuserid
     * @param int $limit
     * @return array
     */
    public static function leaderboard(stdClass $instance, int $currentuserid, int $limit = 10): array {
        global $DB;
        $userfields = \core_user\fields::for_name()->get_sql('u', true, '', '', false)->selects;
        $params = ['ldid' => $instance->id, 'mode' => 'test', 'state' => 'finished'];
        $groupsql = '';
        // Separate groups: students only see classmates from their own groups.
        $cm = get_coursemodule_from_instance('labeldiagram', $instance->id, $instance->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        if (
            groups_get_activity_groupmode($cm) == SEPARATEGROUPS
                && !has_capability('moodle/site:accessallgroups', $context, $currentuserid)
        ) {
            $groupids = array_keys(groups_get_all_groups($cm->course, $currentuserid, $cm->groupingid, 'g.id'));
            $memberids = [$currentuserid];
            foreach ($groupids as $groupid) {
                $memberids = array_merge($memberids, array_keys(groups_get_members($groupid, 'u.id')));
            }
            [$insql, $inparams] = $DB->get_in_or_equal(array_unique($memberids), SQL_PARAMS_NAMED, 'lbu');
            $groupsql = " AND a.userid $insql";
            $params += $inparams;
        }
        $sql = "SELECT a.id, a.userid, a.grade, a.duration, $userfields
                  FROM {labeldiagram_attempt} a
                  JOIN {user} u ON u.id = a.userid
                 WHERE a.labeldiagramid = :ldid AND a.mode = :mode AND a.state = :state AND u.deleted = 0 $groupsql
              ORDER BY a.grade DESC, a.duration ASC, a.timefinish ASC, a.id ASC";
        $rs = $DB->get_recordset_sql($sql, $params);
        $seen = [];
        $out = [];
        try {
            foreach ($rs as $row) {
                if (isset($seen[$row->userid])) {
                    continue;
                }
                $seen[$row->userid] = true;
                $initial = \core_text::substr((string)$row->lastname, 0, 1);
                $out[] = [
                    'rank' => count($out) + 1,
                    'name' => trim($row->firstname . ' ' . ($initial !== '' ? $initial . '.' : '')),
                    'percent' => round((float)$row->grade, 1),
                    'duration' => (int)$row->duration,
                    'me' => (int)$row->userid === $currentuserid ? 1 : 0,
                ];
                if (count($out) >= $limit) {
                    break;
                }
            }
        } finally {
            $rs->close();
        }
        return $out;
    }
}
