# Market research and design rationale (September 2026)

## How the main labelling tools compare

| Tool | Game modes | Feedback | How fast to author | Keyboard / screen reader | Analytics | Moodle gradebook |
|---|---|---|---|---|---|---|
| Wordwall "Labelled diagram" | Drag to pins, timer, shuffle | Show answers at the end | Very good (Enter adds the next pin) | Not documented | Assignment results only; this template has no leaderboard | No (only embeds as an iframe) |
| H5P Drag and Drop | 1 | Instant or on check, retry, solution | Medium | Keyboard yes / screen reader no | Score via xAPI | Yes |
| Moodle core ddimageortext / ddmarker | 1 | Hints and penalties | Slow (one zone at a time) | Keyboard yes / screen reader no | Quiz statistics | Yes |
| Articulate Rise labelled graphic | Explore only | None | Good | Yes / yes | None | Completion only (SCORM) |
| Storyline / iSpring | Custom | Custom | Slow | Drag is not keyboard accessible | SCORM | Via SCORM |
| Kahoot Pin answer | 1 pin per question | Live | Good | Limited | Reports | No |
| Quizlet Diagrams | Learn, Match, Test | Wrong items repeat | Good | Limited | Minimal | No |
| Seterra | Pin, Pin Hard, Place, Type | Time plus accuracy | Custom quizzes | Limited | High scores | No |

Common weaknesses across these tools:

- **Accessibility:** almost all of them fail screen-reader users when dragging.
- **Reporting:** none gives teachers per-part difficulty analytics.
- **Mobile:** layouts are poor on phones, for example the H5P portrait layout.
- **Authoring:** creating many diagrams is slow.
- **Moodle integration:** Wordwall only embeds as an iframe, so there is no gradebook or completion integration.

## How mod_labeldiagram responds

| Gap | Our answer |
|---|---|
| Engagement | A slideshow of several diagrams, animated leader lines, magnetic drop zones, synthesised sound effects, confetti, leaderboard, start screen and results screen. |
| Learning, not just testing | Study mode and a "What it does" card for each part. Practice mode gives instant feedback, hints and show answers. |
| Assessment integrity | Marking happens on the server, and the answer key is never sent to the browser. Slides lock after submission. Time limits are enforced on the server. |
| Accessibility | Tap-tap and keyboard as an alternative to dragging (WCAG 2.5.7), live-region announcements, targets of at least 24 px, visible focus and reduced-motion support. |
| Mobile | Pointer Events for mouse, touch and pen. Tap-tap is the default on touch screens. A compact rail for up to 10 parts, full-screen mode with an iOS fallback, and the card becomes a bottom sheet on phones. |
| Authoring speed | Bulk upload of images or a ZIP, a click-to-place queue, autosave, a copyable AI prompt for every label and its "What it does" text, and an AI prompt for preparing clean images. |
| Teacher insight | Wrong-rate and "students who got it wrong" for each part, tries-until-correct in practice, most-confused label, attempt management and CSV/Excel export. |
| Moodle integration | Gradebook, custom completion, backup and restore (so Sharing Cart and duplicate work), Privacy API, reset, groups and events. |

## Roadmap ideas (not in 1.0)

- Moodle App (mobile app) support using a mobile handler and Ionic template.
- Pinch-zoom on very large diagrams.
- Spaced-repetition practice (parts students miss come back more often).
- A live classroom mode (teacher-paced).
- A printable worksheet with an answer key.
- Using the Moodle AI subsystem (`core_ai`) to suggest "What it does" text.
- Polygon hotspots and a "find it" mode (click the part that matches the name shown).

## Moodle Marketplace readiness checklist

The Plugins directory has been replaced by the Moodle Marketplace (marketplace.moodle.com) in 2026. It lists both free and paid plugins, and free plugins moved over automatically.

| Requirement | Status |
|---|---|
| GPL v3 or later, boilerplate and `@copyright` in every file | Done |
| Frankenstyle naming and CSS scoped to `.path-mod-labeldiagram` | Done |
| English language pack only, all strings through `get_string` | Done |
| `version.php` with `requires` 2024042200 and `supported` [404, 503] | Done |
| Privacy API provider | Done |
| Backup and restore for the activity module | Done (tested: duplicate, and backup/restore with user data) |
| No external calls, no third-party libraries (`thirdpartylibs.xml` not needed) | Done |
| Security: `require_login`, capabilities, sesskey, parameterised SQL, external API validation | Done |
| Tested on PostgreSQL | Done (4.4, 4.5 LTS and 5.2). **Still to do:** a MySQL/MariaDB run in CI. |
| moodle-cs (`moodle-extra` standard) | 0 errors, 0 warnings |
| ESLint (Moodle core config) | 0 errors, 0 warnings |
| AMD build files committed | Done |
| PHPUnit tests and a data generator | Done |
| GitHub Actions moodle-plugin-ci workflow | Included |
| **Still to do:** public repository `moodle-mod_labeldiagram`, public issue tracker, documentation URL, screenshots, English description consistent with the README | Needs setting up before submission |
