# Label Diagram (mod_labeldiagram)

Label Diagram is a Moodle activity for learning with labelled diagrams. It looks and feels like a game. Students work through one diagram at a time, left to right. They drag coloured labels (or tap a label, then tap a spot) onto white numbered circles on each image, and each part can pop up a card explaining what it does.

It is built for technical and vocational training, such as aviation, automotive, engineering, health and science. It has three modes:

| Mode | What students get | Graded |
|---|---|---|
| **Study** | Every part is labelled. Tapping a part opens a "What it does" card. | No |
| **Practice** | Instant feedback with sounds, hints and "Show answers". A card explains each part as soon as it is placed correctly. | No |
| **Test** | No hints. Each slide is submitted and locked, and the results come at the end. Answers are marked on the server. Optional time limit and attempt limit. | Yes |

## Features

**Student experience**
- Premium light interface: soft grey, shaded cards and modern colours.
- Each mode has its own start screen explaining how it works. The Test start screen also shows what the student needs to achieve (pass mark, attempts, time limit, completion rules).
- The results screen has an animated score ring, Passed / Try again, confetti with a success sound (or a gentle "try again" sound), an optional leaderboard and an answer review.
- Up to **10 labelled parts per diagram**, with an optional 4 distractors. The labels sit in a left-hand rail, joined to the numbered points by animated curved lines. Text and boxes shrink automatically as the number of parts grows.
- **Drag and drop** for mouse, pen and touch:
  - the label tilts as it follows the pointer;
  - drop zones "breathe" and pins pulse while a label is held;
  - the nearest zone snaps and highlights, and the line animates;
  - labels spring back to the tray if dropped away from a zone;
  - particles burst on a correct answer and the zone shakes on a wrong one.
- **Tap-tap** on phones and tablets, and **keyboard** support (Tab, Enter/Space, Esc). This meets WCAG 2.2 SC 2.5.7 (an alternative to dragging), and a live region announces each action.
- **Sound effects**, all generated in the browser with no audio files: pick up, drop-zone hover tick, drop, correct, wrong, spring back, hint, card, slide swoosh, slide complete, pass fanfare, try again and timer ticks. Students can mute them, and teachers can switch them off per activity.
- **Full-screen button**: native full screen, with a CSS fallback for iPhone Safari.
- Respects `prefers-reduced-motion`.

**Moodle integration**
- Gradebook, with the grading method set to highest, average, first or last. "Grade to pass" is shown on the start and results screens.
- Completion: view, receive a grade, receive a passing grade, and the custom rule **Finish all slides**.
- Attempt limit, time limit (the server enforces it with a 30-second grace period), and label shuffling.
- Backup and restore, including user data. This means Duplicate, Import, Course copy, **Sharing Cart** and recycle bin all work.
- Privacy API (export and delete), course reset, events and logging, and group-aware reports.
- Supports Moodle **4.4 to 5.3**, including the `public/` directory layout from 5.1 onwards.

**Teacher tools**
- **Bulk slides**: drop many images, or a ZIP, and one slide is created per image in file-name order.
- **Click-to-place queue (Quick add)**: type or paste every label, one per line as `Label | What it does`, press Start placing, then click the image once per label.
- Drag markers to adjust them, nudge with the arrow keys, pick colours, and mark distractors. Changes autosave.
- **"What it does" text** for each part, written in Markdown (bold, italic, bullets), with a live preview of the student card.
- **AI helpers** (copy-and-paste prompts that work with ChatGPT or any other AI; the plugin itself makes no external calls):
  - *Make the perfect image with AI* in the upload section turns a photo, diagram or PDF page into a clean, label-free image that follows the image guidelines.
  - *Copy ChatGPT prompt* in the label editor returns every label and its "What it does" text in the Quick add format.
- **Image guidance** in the upload section: recommended size (1600 × 1200), format, background, cropping and spacing.
- **Reports**:
  - *Test*: for each part, the % of responses wrong, the % of students who got it wrong, how often it was left blank, and the label it is most often confused with.
  - *Practice*: for each part, the average and maximum number of tries before the student got it right, and the % right first time.
  - *Attempts*: filter by mode and group, delete selected attempts (grades and completion are recalculated automatically), recalculate all grades, and download as CSV, Excel or ODS.

## Security and integrity
- In Test mode the browser never receives the answer key. Each attempt gets random pin and label tokens, and the server marks every placement.
- A slide cannot be submitted twice, and submissions after the time limit are rejected.
- All web services check context and capability (`mod/labeldiagram:attempt`, `:manage`, `:viewreports`).

## Requirements
- Moodle 4.4 to 5.3. Tested on 4.4 and 4.5 (PHP 8.3), 5.0, 5.1 and 5.2 (PHP 8.4) and the 5.3 beta (PHP 8.4), on PostgreSQL 16 and 17 and MariaDB 10.11.
- No third-party libraries and no external calls.

## Installation
1. Unzip into `mod/labeldiagram`. On Moodle 5.1 and later this is `public/mod/labeldiagram`.
2. Visit *Site administration → Notifications*.
3. Add a **Label diagram** activity to a course. Then open **Manage slides** in the activity's navigation to upload images and place labels.

## Configuration
- *Site administration → Plugins → Activity modules → Label diagram*: defaults for **Sounds** and **Leaderboard** in new activities.
- Per activity: modes (Study, Practice, Test), Test attempts allowed, time limit, label shuffling, when "What it does" cards appear, sounds, leaderboard, grade, grading method and completion rules.

## Privacy and data
- Stored per user: attempts (mode, attempt number, state, start and finish times, time taken, number correct, score) and, for each attempt, where each label was placed, whether it was correct and how many tries it took.
- Test grades are stored in the gradebook.
- The leaderboard (optional) shows first name and last initial only, and respects separate groups.
- The reports show user identity fields only as allowed by the site's "Show user identity" setting.
- The mute setting is kept in the student's browser only.
- Nothing is sent outside your Moodle site. The Privacy API exports and deletes all stored data, and backup and restore include user data only when you choose to include it.

## Capabilities
| Capability | Default roles |
|---|---|
| `mod/labeldiagram:addinstance` | Editing teacher, Manager |
| `mod/labeldiagram:view` | Guest, Student, Teacher, Editing teacher, Manager |
| `mod/labeldiagram:attempt` | Student |
| `mod/labeldiagram:manage` | Editing teacher, Manager |
| `mod/labeldiagram:viewreports` | Teacher, Editing teacher, Manager |

## Development
- The JavaScript source is in `amd/src` (ES modules) and the built files are in `amd/build`. Rebuild with `npx grunt amd --root=mod/labeldiagram` from a Moodle checkout.
- CI: `.github/workflows/ci.yml` (in the source repository, not in the release ZIP) runs moodle-plugin-ci (phplint, phpcs, phpdoc, validate, savepoints, mustache, grunt, phpunit) on PostgreSQL and MariaDB.

## Licence
© 2026 LMS Hosting Services. GNU GPL v3 or later.
