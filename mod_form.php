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
 * Activity settings form.
 *
 * @package    mod_labeldiagram
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/labeldiagram/lib.php');

/**
 * Activity settings form.
 */
class mod_labeldiagram_mod_form extends moodleform_mod {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $config = get_config('mod_labeldiagram');

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        // Modes.
        $mform->addElement('header', 'modeshdr', get_string('modes', 'mod_labeldiagram'));
        $mform->setExpanded('modeshdr');
        $mform->addElement(
            'advcheckbox',
            'allowstudy',
            get_string('modestudy', 'mod_labeldiagram'),
            get_string('modestudy_desc', 'mod_labeldiagram')
        );
        $mform->setDefault('allowstudy', 1);
        $mform->addElement(
            'advcheckbox',
            'allowpractice',
            get_string('modepractice', 'mod_labeldiagram'),
            get_string('modepractice_desc', 'mod_labeldiagram')
        );
        $mform->setDefault('allowpractice', 1);
        $mform->addElement(
            'advcheckbox',
            'allowtest',
            get_string('modetest', 'mod_labeldiagram'),
            get_string('modetest_desc', 'mod_labeldiagram')
        );
        $mform->setDefault('allowtest', 1);
        $mform->addHelpButton('allowtest', 'modetest', 'mod_labeldiagram');

        // Test settings.
        $mform->addElement('header', 'testhdr', get_string('testsettings', 'mod_labeldiagram'));
        $attemptoptions = [0 => get_string('unlimited')];
        for ($i = 1; $i <= 10; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'mod_labeldiagram'), $attemptoptions);
        $mform->addElement(
            'duration',
            'timelimit',
            get_string('timelimit', 'mod_labeldiagram'),
            ['optional' => true, 'defaultunit' => 60]
        );
        $mform->addHelpButton('timelimit', 'timelimit', 'mod_labeldiagram');

        // Experience.
        $mform->addElement('header', 'experiencehdr', get_string('experience', 'mod_labeldiagram'));
        $mform->addElement('advcheckbox', 'shufflelabels', get_string('shufflelabels', 'mod_labeldiagram'));
        $mform->setDefault('shufflelabels', 1);
        $mform->addElement(
            'select',
            'showpurpose',
            get_string('showpurpose', 'mod_labeldiagram'),
            [
            1 => get_string('showpurpose_always', 'mod_labeldiagram'),
            2 => get_string('showpurpose_review', 'mod_labeldiagram'),
            0 => get_string('showpurpose_never', 'mod_labeldiagram'),
            ]
        );
        $mform->setDefault('showpurpose', 1);
        $mform->addHelpButton('showpurpose', 'showpurpose', 'mod_labeldiagram');
        $mform->addElement(
            'advcheckbox',
            'sounds',
            get_string('sounds', 'mod_labeldiagram'),
            get_string('sounds_desc', 'mod_labeldiagram')
        );
        $mform->setDefault('sounds', $config->defaultsounds ?? 1);
        $mform->addElement(
            'advcheckbox',
            'leaderboard',
            get_string('leaderboard', 'mod_labeldiagram'),
            get_string('leaderboard_desc', 'mod_labeldiagram')
        );
        $mform->setDefault('leaderboard', $config->defaultleaderboard ?? 0);

        // Grade.
        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 100);
        $mform->addElement(
            'select',
            'grademethod',
            get_string('grademethod', 'mod_labeldiagram'),
            [
            LABELDIAGRAM_GRADEHIGHEST => get_string('gradehighest', 'mod_labeldiagram'),
            LABELDIAGRAM_GRADEAVERAGE => get_string('gradeaverage', 'mod_labeldiagram'),
            LABELDIAGRAM_GRADEFIRST => get_string('gradefirst', 'mod_labeldiagram'),
            LABELDIAGRAM_GRADELAST => get_string('gradelast', 'mod_labeldiagram'),
            ]
        );
        $mform->addHelpButton('grademethod', 'grademethod', 'mod_labeldiagram');
        $mform->hideIf('grademethod', 'grade[modgrade_type]', 'eq', 'none');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Returns the form element name with the completion suffix (Moodle 4.3+).
     *
     * @param string $name
     * @return string
     */
    protected function suffixed(string $name): string {
        return method_exists($this, 'get_suffix') ? $name . $this->get_suffix() : $name;
    }

    /**
     * Adds custom completion rules.
     *
     * @return array element names
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $name = $this->suffixed('completionfinish');
        $mform->addElement(
            'advcheckbox',
            $name,
            get_string('completionfinish', 'mod_labeldiagram'),
            get_string('completionfinish_desc', 'mod_labeldiagram')
        );
        $mform->addHelpButton($name, 'completionfinish', 'mod_labeldiagram');
        return [$name];
    }

    /**
     * Whether a custom completion rule is enabled.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data[$this->suffixed('completionfinish')]);
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['allowstudy']) && empty($data['allowpractice']) && empty($data['allowtest'])) {
            $errors['allowtest'] = get_string('errornomode', 'mod_labeldiagram');
        }
        return $errors;
    }
}
