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

#[CoversClass(\backup_labeldiagram_activity_structure_step::class)]
#[CoversClass(\restore_labeldiagram_activity_structure_step::class)]
/**
 * Backup and restore: content, images, user data and id remapping.
 *
 * @package    mod_labeldiagram
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_labeldiagram_activity_structure_step
 * @covers     \restore_labeldiagram_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Loads the backup and restore libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    }

    /**
     * Creates a course with one activity, two slides and a finished Test attempt.
     *
     * @return array [course, instance, student]
     */
    protected function create_source(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $instance = $gen->create_module('labeldiagram', ['course' => $course->id, 'name' => 'Engine parts']);
        $plugingen = $gen->get_plugin_generator('mod_labeldiagram');
        foreach (['First', 'Second'] as $i => $title) {
            $path = make_request_directory() . "/diagram-$i.png";
            imagepng(imagecreatetruecolor(40 + $i, 30), $path);
            $plugingen->create_slide(
                $instance,
                $path,
                $title,
                [
                ["$title A", 10, 10, 'Does **A**'],
                ["$title B", 60, 60, ''],
                ["$title decoy", 0, 0, '', 1],
                ]
            );
        }
        [$course, $cm] = get_course_and_cm_from_instance($instance->id, 'labeldiagram');
        $context = \context_module::instance($cm->id);
        $record = $DB->get_record('labeldiagram', ['id' => $instance->id]);
        $data = manager::start_attempt($record, $cm, $course, $context, 'test', $student->id);
        $attempt = $DB->get_record('labeldiagram_attempt', ['id' => $data['attemptid']]);
        $map = json_decode($attempt->pinmap, true);
        $tokens = array_flip($map['labels']);
        foreach ($data['slides'] as $slide) {
            $placements = [];
            foreach ($slide['pins'] as $pin) {
                $placements[] = ['pin' => $pin['token'], 'label' => $tokens[$map['pins'][$pin['token']]], 'tries' => 1];
            }
            manager::submit_slide($record, $context, $attempt, $slide['id'], $placements);
        }
        manager::finish_attempt($record, $cm, $course, $context, $attempt);
        return [$course, $record, $student];
    }

    /**
     * Backs up a course and restores it into a new course.
     *
     * @param int $courseid
     * @param bool $users Include user data.
     * @return int New course id.
     */
    protected function backup_and_restore(int $courseid, bool $users): int {
        global $CFG, $USER;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($users);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $dir = 'labeldiagramtest' . random_string(6);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $dir);
        $newcourseid = \restore_dbops::create_new_course('Restored', 'RST' . random_string(4), 1);
        $rc = new \restore_controller(
            $dir,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value($users);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * Content, images and user data come across with every id remapped.
     */
    public function test_course_backup_with_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $source, $student] = $this->create_source();
        $newcourseid = $this->backup_and_restore($course->id, true);

        $restored = $DB->get_record('labeldiagram', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals('Engine parts', $restored->name);
        foreach (['grade', 'grademethod', 'allowtest', 'allowpractice', 'allowstudy', 'showpurpose', 'sounds'] as $field) {
            $this->assertEquals($source->$field, $restored->$field, $field);
        }
        $slides = manager::get_slides($restored->id);
        $this->assertCount(2, $slides);
        $cm = get_coursemodule_from_instance('labeldiagram', $restored->id);
        $context = \context_module::instance($cm->id);
        $labelids = [];
        foreach ($slides as $slide) {
            $this->assertNotNull(manager::get_slide_file($context, $slide->id), 'Image restored for ' . $slide->title);
            $labels = manager::get_labels([$slide->id])[$slide->id];
            $this->assertCount(3, $labels);
            $this->assertSame('Does **A**', reset($labels)->purpose);
            $labelids = array_merge($labelids, array_map(fn($l) => (int)$l->id, $labels));
        }

        $attempt = $DB->get_record('labeldiagram_attempt', ['labeldiagramid' => $restored->id], '*', MUST_EXIST);
        $this->assertEquals($student->id, $attempt->userid);
        $this->assertEquals(100, (float)$attempt->grade);
        $map = json_decode($attempt->pinmap, true);
        foreach (array_merge(array_values($map['pins']), array_values($map['labels'])) as $labelid) {
            $this->assertContains((int)$labelid, $labelids, 'Answer key points at restored labels');
        }
        $responses = $DB->get_records('labeldiagram_response', ['attemptid' => $attempt->id]);
        $this->assertCount(4, $responses);
        foreach ($responses as $response) {
            $this->assertArrayHasKey($response->slideid, $slides);
            $this->assertContains((int)$response->labelid, $labelids);
            $this->assertContains((int)$response->placedlabelid, $labelids);
        }
    }

    /**
     * Without user data only the teaching content is copied.
     */
    public function test_course_backup_without_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_source();
        $newcourseid = $this->backup_and_restore($course->id, false);
        $restored = $DB->get_record('labeldiagram', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertCount(2, manager::get_slides($restored->id));
        $this->assertEquals(0, $DB->count_records('labeldiagram_attempt', ['labeldiagramid' => $restored->id]));
    }

    /**
     * Duplicating an activity (also used by Sharing Cart and Import) copies content, not attempts.
     */
    public function test_duplicate(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $source] = $this->create_source();
        $cm = get_fast_modinfo($course)->get_cm(get_coursemodule_from_instance('labeldiagram', $source->id)->id);
        if (
            class_exists('\\core_courseformat\\formatactions')
                && method_exists('\\core_courseformat\\local\\cmactions', 'duplicate')
        ) {
            // Moodle 5.2 and later.
            $newcm = \core_courseformat\formatactions::cm($course)->duplicate($cm->id);
        } else {
            $newcm = duplicate_module($course, $cm);
        }
        $this->assertCount(2, manager::get_slides($newcm->instance));
        $this->assertEquals(0, $DB->count_records('labeldiagram_attempt', ['labeldiagramid' => $newcm->instance]));
        $context = \context_module::instance($newcm->id);
        foreach (manager::get_slides($newcm->instance) as $slide) {
            $this->assertNotNull(manager::get_slide_file($context, $slide->id));
        }
    }
}
