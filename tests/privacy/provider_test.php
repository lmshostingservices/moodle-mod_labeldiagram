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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_labeldiagram\local\manager;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(provider::class)]
/**
 * Tests for the privacy provider: metadata, export and every deletion mode.
 *
 * @package    mod_labeldiagram
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_labeldiagram\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass Course. */
    protected $course;

    /** @var \stdClass Activity instance. */
    protected $instance;

    /** @var \cm_info Course module. */
    protected $cm;

    /** @var \context_module Module context. */
    protected $context;

    /** @var \stdClass First student. */
    protected $student1;

    /** @var \stdClass Second student. */
    protected $student2;

    /**
     * Creates an activity with one slide and a finished Test attempt for two students.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->student1 = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');
        $this->instance = $gen->create_module('labeldiagram', ['course' => $this->course->id]);
        $path = make_request_directory() . '/diagram.png';
        imagepng(imagecreatetruecolor(40, 30), $path);
        $gen->get_plugin_generator('mod_labeldiagram')->create_slide(
            $this->instance,
            $path,
            'Engine',
            [['Fan', 20, 20, 'Moves air'], ['Nozzle', 70, 60, '']]
        );
        [$this->course, $this->cm] = get_course_and_cm_from_instance($this->instance->id, 'labeldiagram');
        $this->context = \context_module::instance($this->cm->id);
        $this->finish_attempt($this->student1);
        $this->finish_attempt($this->student2);
    }

    /**
     * Plays and finishes a Test attempt with every label correct.
     *
     * @param \stdClass $user
     */
    protected function finish_attempt(\stdClass $user): void {
        global $DB;
        $record = $DB->get_record('labeldiagram', ['id' => $this->instance->id]);
        $data = manager::start_attempt($record, $this->cm, $this->course, $this->context, 'test', $user->id);
        $attempt = $DB->get_record('labeldiagram_attempt', ['id' => $data['attemptid']]);
        $map = json_decode($attempt->pinmap, true);
        $tokens = array_flip($map['labels']);
        foreach ($data['slides'] as $slide) {
            $placements = [];
            foreach ($slide['pins'] as $pin) {
                $placements[] = ['pin' => $pin['token'], 'label' => $tokens[$map['pins'][$pin['token']]], 'tries' => 1];
            }
            manager::submit_slide($record, $this->context, $attempt, $slide['id'], $placements);
        }
        manager::finish_attempt($record, $this->cm, $this->course, $this->context, $attempt);
    }

    /**
     * Every declared metadata field exists in the table and has a language string.
     */
    public function test_get_metadata(): void {
        global $DB;
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_labeldiagram'));
        $items = $collection->get_collection();
        $this->assertCount(3, $items);
        $dbman = $DB->get_manager();
        foreach ($items as $item) {
            $this->assertTrue(get_string_manager()->string_exists($item->get_summary(), 'mod_labeldiagram'));
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                foreach ($item->get_privacy_fields() as $field => $stringkey) {
                    $this->assertTrue($dbman->field_exists($item->get_name(), $field), $item->get_name() . '.' . $field);
                    $this->assertTrue(get_string_manager()->string_exists($stringkey, 'mod_labeldiagram'), $stringkey);
                }
            }
        }
    }

    /**
     * Contexts and users are found for people with attempts only.
     */
    public function test_contexts_and_users(): void {
        $contextlist = provider::get_contexts_for_userid($this->student1->id);
        $this->assertEquals([$this->context->id], $contextlist->get_contextids());
        $nobody = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid($nobody->id)->get_contextids());

        $userlist = new userlist($this->context, 'mod_labeldiagram');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->student1->id, $this->student2->id], $userlist->get_userids());
    }

    /**
     * The export contains the real attempt and every answer.
     */
    public function test_export_user_data(): void {
        $this->export_context_data_for_user($this->student1->id, $this->context, 'mod_labeldiagram');
        $data = writer::with_context($this->context)->get_data([]);
        $this->assertNotEmpty($data->attempts);
        $attempt = $data->attempts[0];
        $this->assertEquals('test', $attempt->mode);
        $this->assertEquals('finished', $attempt->state);
        $this->assertEquals(2, $attempt->correct);
        $this->assertEquals(2, $attempt->total);
        $this->assertEquals(100, (float)$attempt->grade);
        $this->assertCount(2, $attempt->responses);
        $this->assertEquals('Engine', $attempt->responses[0]->slide);
        $this->assertContains($attempt->responses[0]->target, ['Fan', 'Nozzle']);
        $this->assertEquals($attempt->responses[0]->target, $attempt->responses[0]->placed);
    }

    /**
     * Deleting one user's data leaves the other user untouched.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        provider::delete_data_for_user(
            new approved_contextlist($this->student1, 'mod_labeldiagram', [$this->context->id])
        );
        $this->assertEquals(0, $DB->count_records('labeldiagram_attempt', ['userid' => $this->student1->id]));
        $this->assertEquals(1, $DB->count_records('labeldiagram_attempt', ['userid' => $this->student2->id]));
        $this->assertEquals(
            2,
            $DB->count_records_sql(
                'SELECT COUNT(1) FROM {labeldiagram_response} r JOIN {labeldiagram_attempt} a ON a.id = r.attemptid
              WHERE a.userid = :userid',
                ['userid' => $this->student2->id]
            )
        );
    }

    /**
     * Deleting a list of users only removes those users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        provider::delete_data_for_users(
            new approved_userlist($this->context, 'mod_labeldiagram', [$this->student2->id])
        );
        $this->assertEquals(1, $DB->count_records('labeldiagram_attempt', ['userid' => $this->student1->id]));
        $this->assertEquals(0, $DB->count_records('labeldiagram_attempt', ['userid' => $this->student2->id]));
    }

    /**
     * Deleting a context removes every attempt and answer in it, and keeps the teaching content.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertEquals(0, $DB->count_records('labeldiagram_attempt', ['labeldiagramid' => $this->instance->id]));
        $this->assertEquals(0, $DB->count_records('labeldiagram_response'));
        $this->assertEquals(1, $DB->count_records('labeldiagram_slide', ['labeldiagramid' => $this->instance->id]));
    }
}
