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

namespace mod_labeldiagram;

use mod_labeldiagram\local\manager;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(manager::class)]
/**
 * Tests for the attempt workflow, grading and label limits.
 *
 * @package    mod_labeldiagram
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_labeldiagram\local\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Creates a small PNG for slides.
     *
     * @return string path
     */
    protected function make_image(): string {
        $path = make_request_directory() . '/diagram-01.png';
        $im = imagecreatetruecolor(40, 30);
        imagepng($im, $path);
        return $path;
    }

    /**
     * Test attempts are marked on the server, answers are hidden, and grades reach the gradebook.
     */
    public function test_test_attempt_grading(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->getDataGenerator()->create_module('labeldiagram', ['course' => $course->id, 'maxattempts' => 1]);
        /** @var \mod_labeldiagram_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_labeldiagram');
        $slide = $gen->create_slide(
            $instance,
            $this->make_image(),
            'Slide',
            [
            ['Alpha', 10, 10, 'Does **alpha**'], ['Beta', 50, 50, ''], ['Gamma', 80, 80, ''], ['Decoy', 0, 0, '', 1],
            ]
        );
        [$course, $cm] = get_course_and_cm_from_instance($instance->id, 'labeldiagram');
        $context = \context_module::instance($cm->id);
        $record = $DB->get_record('labeldiagram', ['id' => $instance->id]);

        $data = manager::start_attempt($record, $cm, $course, $context, 'test', $student->id);
        $this->assertSame(3, $data['total']);
        $this->assertEmpty($data['slides'][0]['answers']);
        $this->assertCount(4, $data['slides'][0]['labels']);

        $attempt = $DB->get_record('labeldiagram_attempt', ['id' => $data['attemptid']]);
        $map = json_decode($attempt->pinmap, true);
        $tokens = array_flip($map['labels']);
        $placements = [];
        foreach ($data['slides'][0]['pins'] as $i => $pin) {
            // Two right, one left empty.
            if ($i < 2) {
                $placements[] = ['pin' => $pin['token'], 'label' => $tokens[$map['pins'][$pin['token']]], 'tries' => 1];
            }
        }
        $results = manager::submit_slide($record, $context, $attempt, $slide->id, $placements);
        $this->assertCount(2, array_filter($results, fn($r) => $r['correct']));

        $summary = manager::finish_attempt($record, $cm, $course, $context, $attempt);
        $this->assertEqualsWithDelta(66.7, $summary['percent'], 0.1);
        $grades = grade_get_grades($course->id, 'mod', 'labeldiagram', $instance->id, $student->id);
        $this->assertEqualsWithDelta(66.67, (float)$grades->items[0]->grades[$student->id]->grade, 0.01);

        $this->expectException(\moodle_exception::class);
        manager::start_attempt($record, $cm, $course, $context, 'test', $student->id);
    }

    /**
     * Saving a slide keeps purposes and enforces the 10-part limit.
     */
    public function test_save_slide_limits(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('labeldiagram', ['course' => $course->id]);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_labeldiagram');
        $slide = $gen->create_slide($instance, $this->make_image(), 'Original');
        $labels = [];
        for ($i = 1; $i <= 10; $i++) {
            $labels[] = ['id' => 0, 'label' => "Part $i", 'x' => $i * 5, 'y' => $i * 6, 'color' => '',
                'purpose' => "Does **$i**", 'distractor' => 0];
        }
        $ids = manager::save_slide($slide, 'Renamed', '', $labels);
        $this->assertCount(10, $ids);
        $saved = manager::get_labels([$slide->id])[$slide->id];
        $this->assertSame('Does **1**', reset($saved)->purpose);

        $labels[] = ['id' => 0, 'label' => 'Part 11', 'x' => 1, 'y' => 1, 'color' => '', 'purpose' => '', 'distractor' => 0];
        $this->expectException(\moodle_exception::class);
        manager::save_slide($slide, 'Renamed', '', $labels);
    }
}
