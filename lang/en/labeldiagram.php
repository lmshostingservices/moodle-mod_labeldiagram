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
 * English strings for mod_labeldiagram.
 *
 * @package    mod_labeldiagram
 * @category   string
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addlabel'] = 'Add a label';
$string['addslides'] = 'Add diagram slides';
$string['addslides_help'] = 'Drop one or many images (or a ZIP of images). Each image becomes a slide, in file-name order. Up to 10 labelled parts per slide.';
$string['aihelper_steps'] = '<li>Copy the prompt below.</li><li>In ChatGPT (or Claude, Copilot, Gemini), attach this diagram image and paste the prompt.</li><li>Paste its answer into the box below and press <strong>Start placing</strong>, then click the image once for each label.</li>';
$string['aihelper_title'] = 'Add all labels and "what it does" in one go with ChatGPT';
$string['aiprompt_slide'] = 'I am building a "label the diagram" activity for students. I have attached one diagram image.

Identify up to {$a} of the most important parts shown in the image.

For each part write ONE line in exactly this format:
Part name | What it does

Rules:
- Part name: short (max 40 characters), exactly as students should learn it.
- What it does: 1-2 plain-English sentences explaining the main purpose of the part. You may use **bold** for key words.
- One part per line. No numbering, bullets, headings, blank lines or any other text.
- Only use the | character as the separator.
- List the parts from the top of the image to the bottom.';
$string['allplaced'] = 'All labels placed';
$string['attempt'] = 'Attempt';
$string['attemptclosed'] = 'This attempt has already been finished.';
$string['attemptsdeleted'] = '{$a} attempt(s) deleted. Grades and completion have been recalculated.';
$string['attemptsleft'] = 'Attempts left';
$string['attemptsused'] = 'Attempts: {$a->used} of {$a->max}';
$string['avgtries'] = 'Average tries to get it right';
$string['backtomenu'] = 'Back to menu';
$string['backtoslides'] = 'Back to slides';
$string['bestfirsttry'] = 'Best: {$a}% right first time';
$string['bestfor_practice'] = 'Best for practising until you know it';
$string['bestfor_study'] = 'Best for learning the parts';
$string['bestfor_test'] = 'Best for proving what you know';
$string['bestscore'] = 'Best: {$a}%';
$string['bold'] = 'Bold';
$string['bullets'] = 'Bullet list';
$string['clicktoplace'] = 'Click on the image to place';
$string['close'] = 'Close';
$string['cmp_attempts_n'] = '{$a} attempts';
$string['cmp_attempts_unlimited'] = 'unlimited attempts';
$string['cmp_feedback'] = 'Feedback';
$string['cmp_feedback_cards'] = '"What it does" cards';
$string['cmp_feedback_end'] = 'Only at the end';
$string['cmp_feedback_instant'] = 'Instant right / wrong';
$string['cmp_graded'] = 'Graded';
$string['cmp_graded_yes'] = 'Yes, {$a}';
$string['cmp_hints'] = 'Hints & answers';
$string['cmp_hints_no'] = 'None';
$string['cmp_hints_yes'] = 'Hint + show answers';
$string['cmp_labels'] = 'Labels';
$string['cmp_labels_shown'] = 'Already placed for you';
$string['cmp_labels_you'] = 'You place them';
$string['cmp_no'] = 'No';
$string['cmp_no_unlimited'] = 'No, unlimited tries';
$string['cmp_notneeded'] = 'Not needed';
$string['cmp_time_limit'] = 'Time limit: {$a}';
$string['cmp_time_none'] = 'No time limit';
$string['color'] = 'Colour';
$string['completiondetail:finish'] = 'Finish all slides';
$string['completionfinish'] = 'Finish the activity';
$string['completionfinish_desc'] = 'Student must finish all slides (in Test mode when Test mode is enabled, otherwise in Practice mode)';
$string['completionfinish_help'] = 'If enabled, the activity is complete once the student has finished an attempt covering every slide. When Test mode is enabled the attempt must be a Test attempt. Combine with "Receive a passing grade" to require a minimum score.';
$string['confirmdeleteattempts'] = 'Delete the selected attempts? Grades and completion will be recalculated. This cannot be undone.';
$string['confirmdeleteslide'] = 'Delete the slide "{$a}" and all its labels?';
$string['confirmsubmit'] = 'Some drop zones are still empty. Submit this slide anyway?';
$string['copied'] = 'Copied!';
$string['copyaiprompt'] = 'Copy ChatGPT prompt';
$string['copyimageprompt'] = 'Copy image prompt';
$string['correct'] = 'Correct';
$string['correctanswer'] = 'Correct answer';
$string['correctfeedback'] = 'Correct! {$a} is in the right place.';
$string['correctof'] = '{$a->correct} of {$a->total} parts correct';
$string['createslides'] = 'Create slides';
$string['defaultleaderboard'] = 'Leaderboard on by default';
$string['defaultleaderboard_desc'] = 'Default for the "Leaderboard" setting in new activities.';
$string['defaultsounds'] = 'Sounds on by default';
$string['defaultsounds_desc'] = 'Default for the "Sounds" setting in new activities.';
$string['deletedlabel'] = '(deleted label)';
$string['deletelabel'] = 'Delete';
$string['deleteselected'] = 'Delete selected attempts';
$string['diagram_many'] = 'diagrams';
$string['diagram_one'] = 'diagram';
$string['distractor'] = 'Distractor (extra wrong option, no drop zone)';
$string['distractor_help'] = 'Distractors appear in the label tray but have no drop zone.';
$string['downloadattempts'] = 'Download attempts';
$string['dropzone'] = 'Drop zone {$a}';
$string['dropzoneempty'] = 'Drop zone {$a}, empty';
$string['dropzonefilled'] = 'Drop zone {$a->number}: {$a->label}';
$string['duration'] = 'Time taken';
$string['editlabels'] = 'Edit labels';
$string['editorhelp'] = 'Tip: drag numbered markers to adjust them, use the arrow keys to nudge the selected marker (Shift for bigger steps), Delete to remove it. Changes save automatically.';
$string['emptyeditor'] = 'No labels yet. Type your labels in "Quick add" and click on the image to place each one.';
$string['errornomode'] = 'Enable at least one mode.';
$string['eventattemptfinished'] = 'Label diagram attempt finished';
$string['excellent'] = 'Excellent!';
$string['exit'] = 'Exit';
$string['exitfullscreen'] = 'Exit full screen';
$string['experience'] = 'Student experience';
$string['finish'] = 'Finish';
$string['firsttry'] = 'Right first time';
$string['fullscreen'] = 'Full screen';
$string['goodeffort'] = 'Good effort!';
$string['gradeaverage'] = 'Average grade';
$string['graded'] = 'Graded';
$string['gradefirst'] = 'First attempt';
$string['gradehighest'] = 'Highest grade';
$string['gradelast'] = 'Last attempt';
$string['grademethod'] = 'Grading method';
$string['grademethod_help'] = 'When multiple Test attempts are allowed, how the gradebook grade is calculated: highest, average, first or last attempt. Practice attempts are never graded.';
$string['greatjob'] = 'Great job!';
$string['guestnote'] = 'Log in to practise and take the test. Guests can use Study mode.';
$string['hint'] = 'Hint';
$string['imageprompt'] = 'I am making an interactive "label the diagram" activity for students. I have attached a photo or diagram.

Please create a clean version of this image:
- Landscape, 1600 x 1200 px (4:3 ratio).
- Keep the object exactly as it is: the same parts, shapes, proportions and positions. Do not add, remove or invent any parts.
- Remove ALL existing text, labels, numbers, arrows, callout lines, legends and captions.
- Replace the background with a plain, light neutral grey (#EEF1F5). No clutter, textures or strong colours.
- Soft, even lighting with no hard shadows or glare.
- Crop so the object fills the frame, leaving a small margin (about 5%) on every side.
- Keep it sharp and realistic. If the original is a drawing, keep it as clean line art.
- Save as JPG (or PNG for line drawings) and give me the download link.

If the file contains several diagrams, create one image for each and name them diagram-01, diagram-02 and so on.';
$string['imageprompt_help'] = 'Works with ChatGPT, Gemini, Copilot or any AI that can edit images. Attach your photo, diagram or PDF page, paste this prompt, then upload the image it gives you. Always check the result: AI can change small details.';
$string['imageprompt_title'] = 'Make the perfect image with AI';
$string['imagereplaced'] = 'Image replaced. Check the positions of the markers.';
$string['imagetip_background'] = '<strong>Background:</strong> use a plain, light neutral grey (about #EEF1F5). Avoid pure white (it blends into the page), busy workshop backgrounds and strong reds, blues or greens that hide the coloured lines. Use soft, even light with no hard shadows or glare.';
$string['imagetip_clean'] = '<strong>Remove answers:</strong> delete existing labels, numbers and callout lines from the image so students cannot see the answers.';
$string['imagetip_crop'] = '<strong>Crop:</strong> crop tightly around the diagram, leaving a small margin (about 5%) so markers near the edge are not cut off.';
$string['imagetip_format'] = '<strong>Format:</strong> JPG or WebP for photos, PNG for line drawings, SVG for vector diagrams. Keep each file under 500 KB where possible.';
$string['imagetip_replace'] = '<strong>Tip:</strong> markers are stored as percentages, so you can later replace an image with a sharper version of the same diagram and the markers stay in place.';
$string['imagetip_shape'] = '<strong>Shape:</strong> avoid portrait or very tall images — they appear small next to the label boxes. Very wide panoramas become short and hard to tap.';
$string['imagetip_size'] = '<strong>Best size:</strong> 1600 × 1200 px, landscape (4:3 or 3:2). At least 1200 px wide; bigger than 2000 px only makes files slower.';
$string['imagetip_spacing'] = '<strong>Spacing:</strong> keep labelled parts at least about 5% of the image width apart, so markers do not overlap on phones.';
$string['imagetips_title'] = 'Perfect image size';
$string['instructions_drag'] = 'Drag each label onto its numbered point — or tap a label, then tap the number.';
$string['instructions_study'] = 'Explore the diagram. Tap any numbered point or label to learn what that part does.';
$string['instructions_tap'] = 'Tap a label, then tap its numbered point (or drag it there).';
$string['instructionsfield'] = 'Instructions for students (optional)';
$string['intro_attempts'] = 'You have {$a->left} of {$a->max} attempts left';
$string['intro_back'] = 'Back';
$string['intro_completion'] = 'To complete this activity';
$string['intro_completion_viatest'] = 'To complete this activity, take the Test when you are ready';
$string['intro_content'] = '{$a->slides} {$a->word}, {$a->parts} parts to label';
$string['intro_content_study'] = '{$a->slides} {$a->word}, {$a->parts} parts to explore';
$string['intro_expect'] = 'What to expect';
$string['intro_feedback_practice'] = 'Instant feedback, hints and "show answers". Learn what each part does as you go.';
$string['intro_feedback_test'] = 'No feedback until you finish. Each slide is locked once submitted.';
$string['intro_go'] = 'Let\'s go';
$string['intro_graded'] = 'Your result goes to the gradebook';
$string['intro_howto'] = 'How to play';
$string['intro_howto_drag'] = 'Drag each coloured label to the white numbered circle it belongs to. Prefer clicking? Click a label, then click the number.';
$string['intro_howto_study'] = 'Tap any numbered point or label to see what that part does. Use Next to move to the next diagram.';
$string['intro_howto_tap'] = 'Tap a coloured label, then tap the white numbered circle (or its box) where it belongs. You can also drag.';
$string['intro_nopass'] = 'No pass mark — just do your best';
$string['intro_notime'] = 'No time limit';
$string['intro_pass'] = 'Score at least {$a}% to pass';
$string['intro_practice_completes'] = 'Finish all slides to complete this activity';
$string['intro_practice_nograde'] = 'Not graded: practise as often as you like';
$string['intro_requirements'] = 'What you need to achieve';
$string['intro_study_next'] = 'When you are ready, try Practice, then the Test';
$string['intro_study_nopressure'] = 'Nothing to achieve here: no marks, no timer, explore at your own pace';
$string['intro_sub_practice'] = 'Practise as often as you like. Mistakes help you learn — this is not graded.';
$string['intro_sub_study'] = 'All labels are shown. Explore each part and what it does.';
$string['intro_sub_test'] = 'Show what you know. Work through each slide from left to right.';
$string['intro_time'] = 'Time limit: {$a}';
$string['intro_title_practice'] = 'Practice';
$string['intro_title_study'] = 'Study the diagrams';
$string['intro_title_test'] = 'Ready for the test?';
$string['intro_unlimited'] = 'Unlimited attempts';
$string['invalidmode'] = 'Invalid mode.';
$string['invalidslide'] = 'Invalid slide.';
$string['italic'] = 'Italic';
$string['keeppractising'] = 'Keep practising!';
$string['kpiattempts'] = 'Finished attempts';
$string['kpiaverage'] = 'Average score';
$string['kpiavgtime'] = 'Average time';
$string['kpipassrate'] = 'Pass rate (best attempt)';
$string['kpistudents'] = 'Students';
$string['labeldiagram:addinstance'] = 'Add a new label diagram';
$string['labeldiagram:attempt'] = 'Attempt label diagrams';
$string['labeldiagram:manage'] = 'Manage slides and labels';
$string['labeldiagram:view'] = 'View label diagrams';
$string['labeldiagram:viewreports'] = 'View label diagram reports';
$string['labels'] = 'Labels';
$string['labeltray'] = 'Labels to place';
$string['leaderboard'] = 'Leaderboard';
$string['leaderboard_desc'] = 'Show a top-10 leaderboard (first name and last initial) after Test attempts';
$string['leftblank'] = 'Left blank';
$string['limitreached'] = 'A slide can have at most {$a} labelled parts. Extra lines were not added.';
$string['loading'] = 'Loading…';
$string['manageslides'] = 'Manage slides';
$string['maxattempts'] = 'Test attempts allowed';
$string['maxtries'] = 'Most tries';
$string['mode'] = 'Mode';
$string['modedisabled'] = 'This mode is not enabled for this activity.';
$string['modepractice'] = 'Practice';
$string['modepractice_card'] = 'Instant feedback, hints and show answers. Not graded — practise as much as you like.';
$string['modepractice_desc'] = 'Instant feedback, hints, show answers, "what it does" cards';
$string['modes'] = 'Modes';
$string['modesdone'] = '{$a->done} of {$a->total} modes done';
$string['modestudy'] = 'Study';
$string['modestudy_card'] = 'See every part labelled and learn what each one does.';
$string['modestudy_desc'] = 'All labels shown; tap parts to read what they do';
$string['modetest'] = 'Test';
$string['modetest_card'] = 'No hints. Submit each slide, then see your result. Graded.';
$string['modetest_desc'] = 'Graded, feedback at the end, optional time limit and attempt limit';
$string['modetest_help'] = 'Test attempts are marked on the server (answers are never sent to the browser) and pushed to the gradebook using the grading method below. Practice and Study modes are never graded.';
$string['modulename'] = 'Label diagram';
$string['modulename_help'] = 'Label diagram is an interactive, game-like activity where students drag (or tap) labels onto numbered points on images, one diagram after another in a left-to-right slideshow.

* Study mode shows every label and a "what it does" card for each part
* Practice mode gives instant feedback with sounds, hints and show answers
* Test mode is graded, with attempts, time limit and completion conditions

Teachers get per-part difficulty reports: how many tries each part takes in practice and the percentage of students getting each part wrong in tests.';
$string['modulename_link'] = 'mod/labeldiagram/view';
$string['modulename_summary'] = 'Drag-and-drop labelled diagrams with study, practice and graded test modes.';
$string['modulenameplural'] = 'Label diagrams';
$string['mostconfused'] = 'Most often confused with';
$string['moveleft'] = 'Move earlier';
$string['moveonimage'] = 'Move on image';
$string['moveright'] = 'Move later';
$string['mute'] = 'Mute sounds';
$string['newlabel'] = 'New label';
$string['next'] = 'Next';
$string['nextslide'] = 'Next slide';
$string['noattempts'] = 'No attempts yet.';
$string['nolabel'] = 'No label';
$string['nolabelsyet'] = 'No labels yet — click Edit labels';
$string['nomoreattempts'] = 'You have used all your test attempts.';
$string['nopurposeyet'] = 'Add a short explanation of what this part does (optional).';
$string['noslides'] = 'This activity has no slides yet.';
$string['notpassed'] = 'Not quite — try again';
$string['notready'] = 'This activity is not ready yet';
$string['notready_manager'] = 'Add diagram images and place the labels to get started.';
$string['notready_student'] = 'Your teacher is still setting up this activity. Please check back later.';
$string['notyourattempt'] = 'This is not your attempt.';
$string['part'] = 'Part';
$string['passed'] = 'You passed!';
$string['passmark'] = 'Passed — pass mark {$a}%';
$string['pctstudentswrong'] = 'Students who got it wrong';
$string['pctwrong'] = 'Responses wrong';
$string['pincount'] = '{$a} parts to label';
$string['pinsummary'] = '{$a->pins} of {$a->max} parts · {$a->distractors} distractors';
$string['placed'] = 'Placed';
$string['placeddirect'] = 'Labels with a position were placed straight away.';
$string['placedfeedback'] = '{$a->label} placed on point {$a->number}.';
$string['placingnow'] = 'Placing';
$string['placingof'] = 'Label {$a->current} of {$a->total}';
$string['pluginadministration'] = 'Label diagram administration';
$string['pluginname'] = 'Label diagram';
$string['previewstudent'] = 'Preview as student';
$string['previous'] = 'Previous';
$string['previousslide'] = 'Previous slide';
$string['privacy:metadata:attempt'] = 'Attempts made by users on label diagram activities.';
$string['privacy:metadata:attempt:attempt'] = 'The attempt number for this user and mode.';
$string['privacy:metadata:attempt:correct'] = 'How many labels were placed correctly.';
$string['privacy:metadata:attempt:duration'] = 'How long the attempt took in seconds.';
$string['privacy:metadata:attempt:grade'] = 'The percentage score of the attempt.';
$string['privacy:metadata:attempt:mode'] = 'Whether the attempt was in Study, Practice or Test mode.';
$string['privacy:metadata:attempt:state'] = 'Whether the attempt is in progress or finished.';
$string['privacy:metadata:attempt:timefinish'] = 'When the attempt was finished.';
$string['privacy:metadata:attempt:timestart'] = 'When the attempt was started.';
$string['privacy:metadata:attempt:total'] = 'How many labels the attempt contained.';
$string['privacy:metadata:attempt:userid'] = 'The user who made the attempt.';
$string['privacy:metadata:core_grades'] = 'Grades for test attempts are stored in the gradebook.';
$string['privacy:metadata:response'] = 'Where the user placed each label.';
$string['privacy:metadata:response:correct'] = 'Whether the placement was correct.';
$string['privacy:metadata:response:labelid'] = 'The drop zone (part).';
$string['privacy:metadata:response:placedlabelid'] = 'The label the user placed.';
$string['privacy:metadata:response:slideid'] = 'The slide (diagram) the answer belongs to.';
$string['privacy:metadata:response:timecreated'] = 'When the response was recorded.';
$string['privacy:metadata:response:tries'] = 'How many tries it took to place the label (Practice mode).';
$string['purpose'] = 'What does this part do? (shown in a card to students)';
$string['purpose_help'] = 'e.g. **Transfers engine thrust** to the pylon and wing structure.';
$string['purposepreview'] = 'Student card preview';
$string['quickadd'] = 'Quick add — click-to-place';
$string['quickadd_help'] = 'Type or paste your labels, one per line (up to {$a} per slide). Add what each part does after a | if you like, e.g. Thrust link | Transfers engine thrust to the pylon. Press Start placing, then click the image where each label belongs.';
$string['quickadd_placeholder'] = 'Thrust link | Transfers engine thrust to the pylon.
Thrust reverser | Redirects airflow forwards to slow the aircraft on landing.
Exhaust plug';
$string['regradeall'] = 'Recalculate all grades';
$string['regraded'] = 'Grades recalculated.';
$string['replaceimage'] = 'Replace image';
$string['reportattempts'] = 'Attempts';
$string['reportparts'] = 'Part difficulty';
$string['reportparts_practice_help'] = 'Practice mode: how many tries each part takes before the student gets it right (hints and "show answers" count as extra tries), and how often it is right first time.';
$string['reportparts_test_help'] = 'Test mode: the percentage of responses that were wrong for each part, the percentage of students who got it wrong in at least one attempt, and the label it is most often confused with.';
$string['reports'] = 'Reports';
$string['reset'] = 'Reset';
$string['resetattempts'] = 'Delete all label diagram attempts';
$string['responses'] = 'Responses';
$string['resultstitle'] = 'Results';
$string['returntotray'] = '{$a} returned to the tray.';
$string['review'] = 'Review';
$string['reviewanswers'] = 'Review answers';
$string['save'] = 'Save';
$string['saved'] = 'All changes saved';
$string['saving'] = 'Saving…';
$string['score'] = 'Score';
$string['selectedfeedback'] = '{$a} selected. Now choose a numbered point.';
$string['showanswers'] = 'Show answers';
$string['showpurpose'] = '"What it does" cards';
$string['showpurpose_always'] = 'After each correct placement (practice), in Study mode and in review';
$string['showpurpose_help'] = 'Each label can have a short explanation of what the part does. Choose when students see it as a pop-up card.';
$string['showpurpose_never'] = 'Never';
$string['showpurpose_review'] = 'Only in Study mode and when reviewing answers';
$string['shufflelabels'] = 'Shuffle the order of labels';
$string['skip'] = 'Skip';
$string['slidealreadysubmitted'] = 'This slide has already been submitted.';
$string['slidecomplete'] = 'Slide complete!';
$string['slidecount'] = '{$a} diagrams';
$string['slidedeleted'] = 'Slide deleted.';
$string['slideimage'] = 'Slide image';
$string['slideof'] = 'Slide {$a->current} of {$a->total}';
$string['slidescreated'] = '{$a} slide(s) created. Now click "Edit labels" on each slide.';
$string['slidetitle'] = 'Slide title';
$string['slidex'] = 'Slide {$a}';
$string['sounds'] = 'Sounds';
$string['sounds_desc'] = 'Play sound effects (pick up, drop zone, correct, wrong, finish). Students can mute.';
$string['start'] = 'Start';
$string['startagain'] = 'Start again';
$string['started'] = 'Started';
$string['startplacing'] = 'Start placing';
$string['state'] = 'Status';
$string['statefinished'] = 'Finished';
$string['stateinprogress'] = 'In progress';
$string['status_completed'] = 'Completed';
$string['status_notpassed'] = 'Not passed yet: you need {$a}%';
$string['status_passed'] = 'Passed';
$string['status_practised'] = 'Practised';
$string['status_studied'] = 'Studied';
$string['stop'] = 'Stop';
$string['studentsofx'] = '{$a->wrong} of {$a->total} students';
$string['studytap'] = 'Tap a part to learn more';
$string['submitslide'] = 'Submit slide';
$string['testsettings'] = 'Test settings';
$string['time'] = 'Time';
$string['timeexpired'] = 'The time limit for this attempt has expired.';
$string['timeleft'] = 'Time left';
$string['timelimit'] = 'Time limit';
$string['timelimit_help'] = 'Optional time limit for a whole Test attempt. When time runs out the current slide is submitted and the attempt is finished automatically.';
$string['timelimitx'] = 'Time limit: {$a}';
$string['timeup'] = 'Time is up!';
$string['toomanylabels'] = 'A slide can have at most {$a->pins} labelled parts and {$a->distractors} distractors.';
$string['tryagain'] = 'Try again';
$string['undo'] = 'Undo';
$string['unlimitedattempts'] = 'Unlimited';
$string['unmute'] = 'Turn sounds on';
$string['unsaved'] = 'Unsaved changes';
$string['uploadimages'] = 'Diagram images';
$string['uploadimages_help'] = 'PNG, JPG, GIF, WebP or SVG images, or a ZIP containing images. One slide is created per image, sorted by file name.

Best results: landscape images about 1600 × 1200 px (4:3), under 500 KB, cropped tightly, with the original labels removed.';
$string['whatitdoes'] = 'What it does';
$string['wrongfeedback'] = 'Not quite — {$a} goes somewhere else.';
$string['you'] = 'you';
$string['youneed'] = 'You need {$a}% to pass';
$string['yourlabel'] = 'Your label';
$string['zipinvalid'] = 'The ZIP file {$a} could not be read. Check that it is a valid ZIP archive.';
$string['ziptoolarge'] = 'This upload is too large. Upload up to {$a->files} images at a time, with ZIP files up to {$a->size} unpacked.';
