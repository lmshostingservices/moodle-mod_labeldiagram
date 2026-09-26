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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Web services: permissions, attempt ownership and Test mode answer hiding.
 *
 * @package    mod_labeldiagram
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_labeldiagram\external\start_attempt
 * @covers     \mod_labeldiagram\external\submit_slide
 * @covers     \mod_labeldiagram\external\finish_attempt
 * @covers     \mod_labeldiagram\external\save_slide
 */
#[CoversClass(start_attempt::class)]
#[CoversClass(submit_slide::class)]
#[CoversClass(finish_attempt::class)]
#[CoversClass(save_slide::class)]
final class services_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    protected $course;

    /** @var \stdClass Activity instance. */
    protected $instance;

    /** @var \stdClass Course module. */
    protected $cm;

    /** @var \stdClass Slide. */
    protected $slide;

    /**
     * Creates a course with one activity and one slide.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('labeldiagram', ['course' => $this->course->id]);
        $path = make_request_directory() . '/diagram.png';
        imagepng(imagecreatetruecolor(40, 30), $path);
        $this->slide = $this->getDataGenerator()->get_plugin_generator('mod_labeldiagram')->create_slide(
            $this->instance,
            $path,
            'Pump',
            [['Inlet', 10, 10, 'Lets fluid in'], ['Outlet', 80, 80, 'Lets fluid out']]
        );
        $this->cm = get_coursemodule_from_instance('labeldiagram', $this->instance->id);
    }

    /**
     * A student can start a Test and the payload never contains answers or explanations.
     */
    public function test_start_attempt_hides_answers(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $result = start_attempt::execute($this->cm->id, 'test');
        $result = external_api::clean_returnvalue(start_attempt::execute_returns(), $result);
        $this->assertCount(1, $result['slides']);
        $this->assertEmpty($result['slides'][0]['answers']);
        $this->assertEmpty($result['slides'][0]['purposes']);
        $encoded = json_encode($result);
        $this->assertStringNotContainsString('Lets fluid', $encoded);
    }

    /**
     * Teachers without the attempt capability cannot start attempts.
     */
    public function test_teacher_cannot_start_attempt(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        start_attempt::execute($this->cm->id, 'test');
    }

    /**
     * A student cannot submit into, or finish, another student's attempt.
     */
    public function test_attempt_belongs_to_owner(): void {
        $owner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($owner);
        $result = start_attempt::execute($this->cm->id, 'test');
        $this->setUser($other);
        try {
            submit_slide::execute($result['attemptid'], $this->slide->id, []);
            $this->fail('Another student could submit into the attempt.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('notyourattempt', $e->errorcode);
        }
        $this->expectException(\moodle_exception::class);
        finish_attempt::execute($result['attemptid']);
    }

    /**
     * Labels from another slide are never accepted as answers.
     */
    public function test_labels_from_other_slides_are_rejected(): void {
        global $DB;
        $path = make_request_directory() . '/second.png';
        imagepng(imagecreatetruecolor(40, 30), $path);
        $second = $this->getDataGenerator()->get_plugin_generator('mod_labeldiagram')->create_slide(
            $this->instance,
            $path,
            'Second',
            [['Inlet', 50, 50, '']]
        );
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $result = start_attempt::execute($this->cm->id, 'test');
        $attempt = $DB->get_record('labeldiagram_attempt', ['id' => $result['attemptid']]);
        $map = json_decode($attempt->pinmap, true);
        $secondlabel = $DB->get_field('labeldiagram_label', 'id', ['slideid' => $second->id]);
        $foreigntoken = array_search((int)$secondlabel, $map['labels']);
        $placements = [];
        foreach ($map['pins'] as $pintoken => $labelid) {
            if ($DB->get_field('labeldiagram_label', 'slideid', ['id' => $labelid]) == $this->slide->id) {
                $placements[] = ['pin' => $pintoken, 'label' => $foreigntoken, 'tries' => 1];
            }
        }
        $results = submit_slide::execute($result['attemptid'], $this->slide->id, $placements);
        foreach ($results['results'] as $row) {
            $this->assertEquals(0, $row['correct']);
        }
        $this->assertEquals(
            0,
            $DB->count_records_select(
                'labeldiagram_response',
                'attemptid = :attemptid AND placedlabelid <> 0',
                ['attemptid' => $attempt->id]
            )
        );
    }

    /**
     * Only people who can manage the activity can save slides.
     */
    public function test_save_slide_requires_manage(): void {
        $labels = [['id' => 0, 'label' => 'Inlet', 'x' => 10, 'y' => 10, 'color' => '', 'purpose' => '', 'distractor' => 0]];
        $editor = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($editor);
        $saved = save_slide::execute($this->slide->id, 'Pump', '', $labels);
        $this->assertCount(1, $saved['ids']);

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        save_slide::execute($this->slide->id, 'Hacked', '', $labels);
    }
}
