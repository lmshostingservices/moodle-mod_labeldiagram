# Changelog

## 2.0.3 (2026-09-26)

- Reworded two code comments so the LMS Labs release scanner accepts them. No change to how the plugin works.

## 2.0.2 (2026-09-26)

- Accessibility: colour contrast now passes automated WCAG 2.1 AA checks on every screen. Label chips, number badges and pin tags use a darker shade of each label colour behind white text, whatever colour the teacher picks.
- Reports respect Moodle's "Show user identity" setting instead of always showing email addresses, in the table and the download.
- Separate groups: the report and the leaderboard only show people from your own groups. A teacher who cannot see all groups and is in no group sees no attempts.
- Reports are faster on large courses: the attempts list is paged (50 per page), totals are calculated in the database, and downloads stream.
- Uploads check that each file really is an image (not just named like one), and ZIP files are checked before unpacking (up to 100 images and 200 MB).
- Test answers can only use labels from the slide being answered.
- Fixed: "Select all" in the attempts report now selects every attempt on the page.
- Privacy: the privacy information now lists every stored field, and exports include the slide, answer, tries and time for each answer.
- The start screens, "What it does" cards, results screen and teacher report are now built from Mustache templates.
- Fixed a database definition warning for the label colour field.
- The "Quick add" example text can now be translated.
- Styles and JavaScript now pass Moodle's own lint and build checks, and SQL uses named parameters throughout.
- Added 19 automated tests (privacy, backup and restore, web services, uploads and course reset).
- Tested on Moodle 4.4, 4.5, 5.0, 5.1, 5.2 and the 5.3 beta, on PostgreSQL and MariaDB.

## 2.0.1 (2026-09-25)

- Added the database upgrade script (db/upgrade.php) so future versions upgrade smoothly.
- Backup and restore verified on Moodle 4.4, 4.5 and 5.2: duplicate, Sharing Cart, activity and course backup with or without user data, import, course copy and course reset. Slides, images, labels, settings, attempts, answers and grades all carry across.

## 2.0.0 (2026-09-25)

- First Marketplace release of Label Diagram 2. Includes every improvement from 1.0.1 to 1.0.8.

## 1.0.8 (2026-09-25)

- Added LICENSE (GNU GPL v3) and thirdpartylibs.xml (no third-party libraries).
- Stricter parameter types in the web services.
- The course activity list now checks login and view permission.
- Coding style tidy-up.

## 1.0.7 (2026-09-25)

- Larger, easier-to-read label boxes and text in the left-hand column. Box height and text size now adapt to the space available (up to 52px tall), and all 10 still fit on a 1366 × 768 laptop.
- Bigger drag labels (48px tall, 16px text) with more space between them, in all modes.

## 1.0.6 (2026-09-25)

- Mode cards show a clear side-by-side comparison (labels, feedback, hints and answers, graded) and what each mode is best for.
- Completed modes get a green tick, a Studied / Practised / Passed badge and a "Start again" button, plus an "x of 3 modes done" summary. Study counts as done once every slide has been viewed.
- A Test attempt below the pass mark shows "Not passed yet" with the mark needed.
- Completion "Finish all slides" uses Practice attempts when Test mode is off (Study never completes the activity).

## 1.0.5 (2026-09-25)

- New: "Make the perfect image with AI" prompt in the upload section. It works with ChatGPT, Gemini, Copilot or any AI that edits images, and follows the image guidelines (size, background, cropping, labels removed).
- New: a ChatGPT prompt in the label editor that returns every label and its "what it does" text in one go. Paste the answer into Quick add, press Start placing, then click once per label.
- Quick add accepts "Label | What it does" lines (also "Label – What it does"), with optional x | y positions.
- Removed the PDF/JSON AI import from Manage slides to keep setup simple.
- Player fits the screen: the image is sized automatically so the top bar, labels and buttons are all visible without scrolling, in normal view and full screen. On wide screens the labels sit in a column beside the diagram.
- Slimmer top bar: the instructions and slide progress now sit in the top bar; the progress marker is hidden when there is only one slide.
- Start screens are tailored to each mode. Study and Practice no longer list grade and pass requirements, which only apply to the Test.
- The Bold, Italic and List buttons in the label editor are now clearly labelled and visible on all themes.

## 1.0.4 (2026-09-25)

- Quick add: the pasted list stays visible while placing. The banner shows every label (placed, current, still to place), you can tap any label to place it next, and Stop keeps the unplaced labels in the box.
- Added "Background" guidance to the Perfect image size tips.

## 1.0.3 (2026-09-25)

- Added "Perfect image size" guidance to the Add diagram slides section.

## 1.0.2 (2026-09-25)

- Fixed an "Undefined constant FILE_INTERNAL" error on the Manage slides page on some sites.

## 1.0.1 (2026-09-25)

- Now supports Moodle 4.4 as well as 4.5 LTS to 5.3.
- Select-all on the Attempts report works on every supported version.
- Two labels with the same text (for example two "Bolt" labels) are now interchangeable.

## 1.0.0 (2026-09-25)

First release.

- Activity module with Study, Practice and Test modes in a left-to-right slideshow.
- Up to 10 labelled parts per diagram, with numbered white drop points and left-hand label rail with animated leader lines.
- Drag and drop (mouse, pen, touch) and tap-tap / keyboard placement (WCAG 2.5.7 alternative to dragging).
- Synthesised sound effects: pick up, drop-zone hover, drop, correct, wrong, spring-back, slide complete, pass, try again.
- "What it does" purpose card for each part (Markdown), shown after correct placement, in Study mode and in review.
- Start screen with pass mark, attempts, time limit and completion requirements; results screen with pass/fail, confetti, leaderboard and answer review.
- Full-screen mode (native, with CSS fallback for iPhone Safari).
- Server-side marking in Test mode — answer keys never reach the browser; per-attempt random tokens.
- Gradebook (highest/average/first/last), attempt limits, time limit, custom completion rule "Finish all slides", pass-grade completion.
- Teacher reports: per-part wrong-rate and students-wrong % (Test), tries-until-correct (Practice), most-confused label, attempt management (delete, regrade, CSV/Excel export), group filter.
- Bulk slide creation from many images or a ZIP; click-to-place label queue; autosaving visual editor.
- AI-assisted import: copyable prompt for ChatGPT/Claude/Copilot/Gemini and tolerant JSON importer.
- Backup/restore (so duplicate, import, course copy and Sharing Cart work), Privacy API, course reset, events.
- Supports Moodle 4.5 LTS to 5.3.
