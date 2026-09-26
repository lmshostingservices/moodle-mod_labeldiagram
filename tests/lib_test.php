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
use PHPUnit\Framework\Attributes\CoversFunction;

#[CoversFunction('labeldiagram_calculate_percent')]
#[CoversFunction('labeldiagram_reset_userdata')]
#[CoversFunction('labeldiagram_delete_instance')]
#[CoversClass(manager::class)]
/**
 * Library callbacks (grading methods, reset, delete) and image upload validation.
 *
 * @package    mod_labeldiagram
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::labeldiagram_calculate_percent
 * @covers     ::labeldiagram_reset_userdata
 * @covers     ::labeldiagram_delete_instance
 * @covers     \mod_labeldiagram\local\manager::create_slides_from_draft
 */
final class lib_test extends \advanced_testcase {
    /**
     * Loads lib.php.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/mod/labeldiagram/lib.php');
    }

    /**
     * Each grading method picks the right score.
     */
    public function test_calculate_percent(): void {
        $attempts = [
            (object)['attempt' => 1, 'grade' => 40],
            (object)['attempt' => 2, 'grade' => 90],
            (object)['attempt' => 3, 'grade' => 50],
        ];
        $this->assertEqualsWithDelta(90, labeldiagram_calculate_percent($attempts, LABELDIAGRAM_GRADEHIGHEST), 0.001);
        $this->assertEqualsWithDelta(60, labeldiagram_calculate_percent($attempts, LABELDIAGRAM_GRADEAVERAGE), 0.001);
        $this->assertEqualsWithDelta(40, labeldiagram_calculate_percent($attempts, LABELDIAGRAM_GRADEFIRST), 0.001);
        $this->assertEqualsWithDelta(50, labeldiagram_calculate_percent($attempts, LABELDIAGRAM_GRADELAST), 0.001);
    }

    /**
     * Creates an activity with a slide and one finished Test attempt.
     *
     * @return array [course, instance]
     */
    protected function create_with_attempt(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->getDataGenerator()->create_module('labeldiagram', ['course' => $course->id]);
        $path = make_request_directory() . '/diagram.png';
        imagepng(imagecreatetruecolor(40, 30), $path);
        $this->getDataGenerator()->get_plugin_generator('mod_labeldiagram')->create_slide(
            $instance,
            $path,
            'Pump',
            [['Inlet', 10, 10, '']]
        );
        [$course, $cm] = get_course_and_cm_from_instance($instance->id, 'labeldiagram');
        $context = \context_module::instance($cm->id);
        $record = $DB->get_record('labeldiagram', ['id' => $instance->id]);
        $data = manager::start_attempt($record, $cm, $course, $context, 'test', $student->id);
        $attempt = $DB->get_record('labeldiagram_attempt', ['id' => $data['attemptid']]);
        manager::submit_slide($record, $context, $attempt, $data['slides'][0]['id'], []);
        manager::finish_attempt($record, $cm, $course, $context, $attempt);
        return [$course, $record];
    }

    /**
     * Course reset removes attempts and answers but keeps slides.
     */
    public function test_reset_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $instance] = $this->create_with_attempt();
        $status = labeldiagram_reset_userdata(
            (object)[
            'courseid' => $course->id, 'reset_labeldiagram_attempts' => 1, 'timeshift' => 0,
            ]
        );
        $this->assertNotEmpty($status);
        $this->assertEquals(0, $DB->count_records('labeldiagram_attempt', ['labeldiagramid' => $instance->id]));
        $this->assertEquals(0, $DB->count_records('labeldiagram_response'));
        $this->assertEquals(1, $DB->count_records('labeldiagram_slide', ['labeldiagramid' => $instance->id]));
    }

    /**
     * Deleting the activity removes every row that belongs to it.
     */
    public function test_delete_instance(): void {
        global $DB;
        $this->resetAfterTest();
        [, $instance] = $this->create_with_attempt();
        $this->assertTrue(labeldiagram_delete_instance($instance->id));
        $this->assertFalse($DB->record_exists('labeldiagram', ['id' => $instance->id]));
        $this->assertEquals(0, $DB->count_records('labeldiagram_slide'));
        $this->assertEquals(0, $DB->count_records('labeldiagram_label'));
        $this->assertEquals(0, $DB->count_records('labeldiagram_attempt'));
        $this->assertEquals(0, $DB->count_records('labeldiagram_response'));
    }

    /**
     * Puts files in a draft area for the current user.
     *
     * @param array $files filename => content
     * @return int draft item id
     */
    protected function draft(array $files): int {
        global $USER;
        $draftid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($USER->id);
        foreach ($files as $name => $content) {
            get_file_storage()->create_file_from_string(
                [
                'contextid' => $usercontext->id, 'component' => 'user', 'filearea' => 'draft',
                'itemid' => $draftid, 'filepath' => '/', 'filename' => $name,
                ],
                $content
            );
        }
        return $draftid;
    }

    /**
     * Real images become slides; files that only look like images are ignored, including inside ZIPs.
     */
    public function test_upload_validation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('labeldiagram', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('labeldiagram', $instance->id);
        $context = \context_module::instance($cm->id);

        ob_start();
        imagepng(imagecreatetruecolor(20, 10));
        $png = ob_get_clean();
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';

        // A ZIP containing a real PNG, an SVG and a text file renamed to .png.
        $zippath = make_request_directory() . '/pack.zip';
        $zip = new \ZipArchive();
        $zip->open($zippath, \ZipArchive::CREATE);
        $zip->addFromString('b-real.png', $png);
        $zip->addFromString('c-vector.svg', $svg);
        $zip->addFromString('d-fake.png', 'this is not an image');
        $zip->close();

        $draftid = $this->draft(
            [
            'a-real.png' => $png,
            'e-fake.jpg' => 'not an image either',
            'pack.zip' => file_get_contents($zippath),
            ]
        );
        $count = manager::create_slides_from_draft($instance, $context, $draftid);
        $this->assertEquals(3, $count);
        $titles = array_values(array_map(fn($s) => $s->title, manager::get_slides($instance->id)));
        $this->assertCount(3, $titles);
        $this->assertStringContainsStringIgnoringCase('real', $titles[0]);
    }

    /**
     * A ZIP with too many files is refused before it is unpacked.
     */
    public function test_upload_zip_limit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('labeldiagram', ['course' => $course->id]);
        $context = \context_module::instance(get_coursemodule_from_instance('labeldiagram', $instance->id)->id);
        $zippath = make_request_directory() . '/big.zip';
        $zip = new \ZipArchive();
        $zip->open($zippath, \ZipArchive::CREATE);
        for ($i = 0; $i <= manager::MAX_UPLOAD_IMAGES * 2; $i++) {
            $zip->addFromString("f$i.png", 'x');
        }
        $zip->close();
        $draftid = $this->draft(['big.zip' => file_get_contents($zippath)]);
        try {
            manager::create_slides_from_draft($instance, $context, $draftid);
            $this->fail('An oversized ZIP was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('ziptoolarge', $e->errorcode);
        }
        $this->assertEmpty(manager::get_slides($instance->id));
    }
}
