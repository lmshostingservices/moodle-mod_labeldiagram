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
 * Label diagram player: study, practice and test modes in a left-to-right slideshow.
 *
 * Interaction: drag and drop (mouse, pen, touch) OR tap a label then tap a drop zone / numbered point
 * (touch and keyboard friendly, WCAG 2.5.7 compliant alternative to dragging).
 *
 * @module     mod_labeldiagram/player
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getStrings} from 'core/str';
import * as Sound from 'mod_labeldiagram/sound';
import {paint} from 'mod_labeldiagram/colour';
import Templates from 'core/templates';

const STRING_KEYS = [
    'exit', 'slideof', 'hint', 'showanswers', 'reset', 'submitslide', 'next', 'previous', 'finish',
    'score', 'placed', 'time', 'timeleft', 'mute', 'unmute', 'fullscreen', 'exitfullscreen',
    'dropzone', 'dropzoneempty', 'dropzonefilled', 'labeltray', 'instructions_drag', 'instructions_tap',
    'instructions_study', 'correctfeedback', 'wrongfeedback', 'placedfeedback', 'selectedfeedback',
    'slidecomplete', 'whatitdoes', 'close', 'resultstitle', 'reviewanswers', 'tryagain', 'backtomenu',
    'correctof', 'leaderboard', 'excellent', 'greatjob', 'goodeffort', 'keeppractising', 'correctanswer',
    'yourlabel', 'nolabel', 'timeup', 'confirmsubmit', 'loading', 'attemptsleft', 'unlimitedattempts',
    'review', 'returntotray', 'allplaced', 'studytap', 'you', 'modestudy', 'modepractice', 'modetest',
    'intro_title_study', 'intro_title_practice', 'intro_title_test', 'intro_sub_study', 'intro_sub_practice',
    'intro_sub_test', 'intro_pass', 'intro_nopass', 'intro_attempts', 'intro_unlimited', 'intro_time',
    'intro_notime', 'intro_content', 'intro_howto_drag', 'intro_howto_tap', 'intro_completion', 'intro_go',
    'intro_back', 'intro_feedback_practice', 'intro_feedback_test', 'intro_graded', 'passed', 'notpassed',
    'passmark', 'youneed', 'intro_requirements', 'intro_howto', 'intro_expect', 'intro_content_study',
    'intro_practice_nograde', 'intro_practice_completes', 'intro_completion_viatest', 'intro_study_nopressure',
    'intro_study_next', 'diagram_one', 'diagram_many', 'intro_howto_study',
];

const REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const COARSE = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
const EASE = 'cubic-bezier(.22,1,.36,1)';
const SPRING = 'cubic-bezier(.34,1.56,.64,1)';
const SVGNS = 'http://www.w3.org/2000/svg';

let S = {};

/**
 * Loads language strings into S.
 *
 * @returns {Promise}
 */
const loadStrings = async() => {
    const values = await getStrings(STRING_KEYS.map((key) => ({key, component: 'mod_labeldiagram'})));
    STRING_KEYS.forEach((key, i) => {
        S[key] = values[i];
    });
};

/**
 * Simple {$a} / {$a->x} replacement for strings that take parameters at runtime.
 *
 * @param {string} str
 * @param {object|string|number} a
 * @returns {string}
 */
const fmt = (str, a) => {
    if (typeof a === 'object') {
        return str.replace(/\{\$a->(\w+)\}/g, (m, k) => (a[k] ?? ''));
    }
    return str.replace(/\{\$a\}/g, a);
};

/**
 * Creates an element.
 *
 * @param {string} tag
 * @param {string} cls
 * @param {object} attrs
 * @returns {HTMLElement}
 */
const el = (tag, cls = '', attrs = {}) => {
    const node = document.createElement(tag);
    if (cls) {
        node.className = cls;
    }
    Object.entries(attrs).forEach(([k, v]) => {
        if (k === 'children') {
            node.append(...v.filter((c) => c !== null && c !== undefined && c !== ''));
        } else if (k === 'formatted') {
            // Text already formatted and escaped by format_string() on the server.
            node.innerHTML = v;
        } else if (k === 'text') {
            node.textContent = v;
        } else if (v !== null && v !== undefined && v !== false) {
            node.setAttribute(k, v === true ? '' : v);
        }
    });
    return node;
};

const ICONS = {
    exit: '<path d="M15 18l-6-6 6-6"/>',
    sound: '<path d="M4 10v4h4l5 4V6L8 10z"/><path d="M16 9a4 4 0 010 6M18.5 6.5a8 8 0 010 11"/>',
    muted: '<path d="M4 10v4h4l5 4V6L8 10z"/><path d="M17 9l5 6M22 9l-5 6"/>',
    full: '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
    unfull: '<path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/>',
    hint: '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 00-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0012 3z"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    reset: '<path d="M4 4v6h6"/><path d="M4.5 15a8 8 0 102-8.5L4 10"/>',
    next: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    prev: '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
    cross: '<path d="M6 6l12 12M18 6L6 18"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    star: '<path d="M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    target: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    repeat: '<path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 013-3h15M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 01-3 3H3"/>',
    image: '<rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="11" r="2"/><path d="M21 16l-5-5-8 8"/>',
    hand: '<path d="M8 13V5.5a1.5 1.5 0 013 0V12M11 11.5v-2a1.5 1.5 0 013 0V12M14 10.5a1.5 1.5 0 013 0V15' +
        'a6 6 0 01-6 6h-.5A5.5 5.5 0 015 15.5L3.8 12.6a1.5 1.5 0 012.6-1.5L8 13"/>',
    flag: '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
    trophy: '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0z"/><path d="M17 5h3v2a3 3 0 01-3 3M7 5H4v2a3 3 0 003 3"/>',
};

/**
 * Returns an inline SVG icon.
 *
 * @param {string} name
 * @returns {string}
 */
const icon = (name) => `<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">${ICONS[name]}</svg>`;

/**
 * Returns an inline SVG icon element built from the plugin's own static icon set.
 *
 * @param {string} name
 * @returns {Element}
 */
const svgIcon = (name) => {
    const doc = new DOMParser().parseFromString(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${ICONS[name]}</svg>`,
        'image/svg+xml'
    );
    return document.importNode(doc.documentElement, true);
};

/**
 * Returns a text span.
 *
 * @param {string} text
 * @param {string} cls
 * @returns {HTMLElement}
 */
const textSpan = (text, cls = '') => {
    const node = document.createElement('span');
    if (cls) {
        node.className = cls;
    }
    node.textContent = text;
    return node;
};

/**
 * Formats seconds as m:ss.
 *
 * @param {number} secs
 * @returns {string}
 */
const clock = (secs) => {
    secs = Math.max(0, Math.round(secs));
    return `${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, '0')}`;
};

/**
 * Animates an element from one rect to its current position (FLIP).
 *
 * @param {HTMLElement} node
 * @param {DOMRect} from
 * @param {object} opts duration, easing
 * @returns {Promise}
 */
const flip = (node, from, opts = {}) => {
    const to = node.getBoundingClientRect();
    if (REDUCED || !node.animate || !to.width) {
        return Promise.resolve();
    }
    const dx = from.left - to.left;
    const dy = from.top - to.top;
    const sx = from.width / to.width;
    const sy = from.height / to.height;
    const anim = node.animate([
        {transform: `translate(${dx}px, ${dy}px) scale(${sx}, ${sy})`, transformOrigin: '0 0'},
        {transform: 'none', transformOrigin: '0 0'},
    ], {duration: opts.duration || 380, easing: opts.easing || SPRING});
    return anim.finished.catch(() => null);
};

/**
 * Confetti burst on a full-screen canvas.
 *
 * @param {HTMLElement} host
 * @param {number} amount
 */
const confetti = (host, amount = 140) => {
    if (REDUCED) {
        return;
    }
    const canvas = el('canvas', 'ld-confetti', {'aria-hidden': 'true'});
    host.appendChild(canvas);
    const rect = host.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    const c = canvas.getContext('2d');
    c.scale(dpr, dpr);
    const colors = ['#6366F1', '#0EA5E9', '#10B981', '#F59E0B', '#EF4444', '#EC4899', '#8B5CF6'];
    const parts = Array.from({length: amount}, () => ({
        x: rect.width / 2 + (Math.random() - 0.5) * rect.width * 0.3,
        y: rect.height * 0.35,
        vx: (Math.random() - 0.5) * 14,
        vy: -Math.random() * 13 - 4,
        r: Math.random() * 6 + 4,
        a: Math.random() * Math.PI,
        va: (Math.random() - 0.5) * 0.3,
        color: colors[Math.floor(Math.random() * colors.length)],
        shape: Math.random() > 0.5,
    }));
    const start = performance.now();
    const frame = (now) => {
        const t = now - start;
        c.clearRect(0, 0, rect.width, rect.height);
        parts.forEach((p) => {
            p.vy += 0.35;
            p.vx *= 0.985;
            p.x += p.vx;
            p.y += p.vy;
            p.a += p.va;
            c.save();
            c.globalAlpha = Math.max(0, 1 - t / 2600);
            c.translate(p.x, p.y);
            c.rotate(p.a);
            c.fillStyle = p.color;
            if (p.shape) {
                c.fillRect(-p.r / 2, -p.r / 4, p.r, p.r / 2);
            } else {
                c.beginPath();
                c.arc(0, 0, p.r / 2.6, 0, Math.PI * 2);
                c.fill();
            }
            c.restore();
        });
        if (t < 2600) {
            requestAnimationFrame(frame);
        } else {
            canvas.remove();
        }
    };
    requestAnimationFrame(frame);
};

/**
 * Small particle burst around a point (correct placement).
 *
 * @param {HTMLElement} host positioned container
 * @param {number} x px within host
 * @param {number} y px within host
 * @param {string} color
 */
const burst = (host, x, y, color) => {
    if (REDUCED) {
        return;
    }
    for (let i = 0; i < 12; i++) {
        const p = el('span', 'ld-particle');
        const angle = (Math.PI * 2 * i) / 12 + Math.random() * 0.4;
        const dist = 26 + Math.random() * 22;
        p.style.left = `${x}px`;
        p.style.top = `${y}px`;
        p.style.background = i % 3 === 0 ? '#FACC15' : color;
        host.appendChild(p);
        p.animate([
            {transform: 'translate(-50%, -50%) scale(1)', opacity: 1},
            {transform: `translate(calc(-50% + ${Math.cos(angle) * dist}px), calc(-50% + ${Math.sin(angle) * dist}px))
                scale(0.2)`, opacity: 0},
        ], {duration: 620 + Math.random() * 200, easing: 'cubic-bezier(.2,.8,.3,1)'}).finished
            .then(() => p.remove()).catch(() => p.remove());
    }
};

/**
 * The player application.
 */
class Player {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root
     * @param {object} config
     */
    constructor(root, config) {
        this.root = root;
        this.config = config;
        this.home = root.querySelector('[data-region="home"]');
        this.host = root.querySelector('[data-region="player"]');
        this.live = root.querySelector('[data-region="live"]');
        this.drag = null;
        this.selected = null;
        this.card = null;
        this.timerHandle = null;
        Sound.setAllowed(!!config.sounds);
        root.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action="start"]');
            if (btn && !btn.disabled && this.home.contains(btn)) {
                this.showIntro(btn.dataset.mode).catch(Notification.exception);
            }
        });
        this.onResize = () => {
            if (!this.slides) {
                return;
            }
            this.slides.forEach((s) => this.drawLines(s));
            this.syncHeight();
        };
        window.addEventListener('resize', this.onResize);
        document.addEventListener('fullscreenchange', () => this.syncFullscreen());
        document.addEventListener('webkitfullscreenchange', () => this.syncFullscreen());
        document.addEventListener('keydown', (e) => this.onKey(e));
    }

    /**
     * Announces a message to assistive technologies.
     *
     * @param {string} msg
     */
    say(msg) {
        this.live.textContent = '';
        window.setTimeout(() => {
            this.live.textContent = msg;
        }, 30);
    }

    /**
     * Shows the start screen: what the learner must achieve, attempts, time, how to play.
     *
     * @param {string} mode study|practice|test
     */
    async showIntro(mode) {
        Sound.play('select');
        const c = this.config;
        const items = [];
        const li = (ic, text, key = '') => ({icon: icon(ic), text, key: key === 'is-key'});
        const word = c.slidecount === 1 ? S.diagram_one : S.diagram_many;
        const rules = c.completion || [];
        if (mode === 'test') {
            items.push(c.passpercent > 0 ? li('target', fmt(S.intro_pass, c.passpercent), 'is-key') :
                li('target', S.intro_nopass));
            items.push(li('repeat', c.maxattempts ? fmt(S.intro_attempts, {left: c.attemptsleft, max: c.maxattempts}) :
                S.intro_unlimited));
            items.push(li('clock', c.timelimit ? fmt(S.intro_time, c.timelimittext) : S.intro_notime));
            items.push(li('image', fmt(S.intro_content, {slides: c.slidecount, word, parts: c.pincount})));
            if (c.graded) {
                items.push(li('trophy', S.intro_graded));
            }
            items.push(li('info', S.intro_feedback_test));
            // Completion rules are met in Test mode (pass mark is already shown above).
            rules.filter((r) => r.rule !== 'completionpassgrade' || !c.passpercent)
                .forEach((r) => items.push(li('flag', r.text)));
        } else if (mode === 'practice') {
            items.push(li('info', S.intro_feedback_practice));
            items.push(li('image', fmt(S.intro_content, {slides: c.slidecount, word, parts: c.pincount})));
            items.push(li('star', S.intro_practice_nograde));
            if (!c.allowtest && rules.some((r) => r.rule === 'completionfinish')) {
                items.push(li('flag', S.intro_practice_completes, 'is-key'));
            } else if (c.allowtest && rules.length) {
                items.push(li('flag', S.intro_completion_viatest));
            }
        } else {
            items.push(li('image', fmt(S.intro_content_study, {slides: c.slidecount, word, parts: c.pincount})));
            items.push(li('info', S.intro_study_nopressure));
            if (c.allowtest || rules.length) {
                items.push(li('flag', S.intro_study_next));
            }
        }
        const heading = mode === 'test' ? S.intro_requirements : S.intro_expect;
        const tapHowto = COARSE ? S.intro_howto_tap : S.intro_howto_drag;
        const howto = mode === 'study' ? S.intro_howto_study : tapHowto;
        const badgeIcon = {study: 'eye', practice: 'star', test: 'check'}[mode];

        const context = {
            mode,
            badgeicon: icon(badgeIcon),
            modename: S['mode' + mode],
            title: S['intro_title_' + mode],
            sub: S['intro_sub_' + mode],
            heading,
            items,
            howtoheading: S.intro_howto,
            study: mode === 'study',
            handicon: icon('hand'),
            howto,
            back: {label: S.intro_back, icon: icon('prev')},
            go: {label: S.intro_go, icon: icon('next')},
        };
        const seq = (this.introSeq = (this.introSeq || 0) + 1);
        const {html, js} = await Templates.renderForPromise('mod_labeldiagram/player_intro', context);
        if (seq !== this.introSeq) {
            return;
        }
        this.home.hidden = true;
        this.host.hidden = false;
        this.host.replaceChildren();
        const shell = el('div', `ld-shell ld-intro-shell ld-mode-${mode}`);
        Templates.appendNodeContents(shell, html, js);
        const back = shell.querySelector('[data-action="back"]');
        const go = shell.querySelector('[data-action="go"]');
        back.addEventListener('click', () => this.exit());
        go.addEventListener('click', () => {
            go.disabled = true;
            this.start(mode);
        });
        this.host.appendChild(shell);
        this.shell = shell;
        go.focus({preventScroll: true});
        this.scrollToShell();
    }

    /**
     * Starts a mode (after the intro screen).
     *
     * @param {string} mode study|practice|test
     */
    async start(mode) {
        Sound.play('select');
        this.mode = mode;
        this.home.classList.add('ld-leaving');
        let data;
        try {
            if (mode === 'study') {
                data = this.config.study;
            } else {
                data = await Ajax.call([{
                    methodname: 'mod_labeldiagram_start_attempt',
                    args: {cmid: this.config.cmid, mode},
                }])[0];
            }
        } catch (err) {
            this.home.classList.remove('ld-leaving');
            this.exit();
            Notification.exception(err);
            return;
        }
        this.data = data;
        this.attemptid = data.attemptid || 0;
        this.build();
    }

    /**
     * Builds the player shell and all slides.
     */
    build() {
        const mode = this.mode;
        this.index = 0;
        this.startedAt = Date.now();
        this.home.hidden = true;
        this.home.classList.remove('ld-leaving');
        this.host.hidden = false;
        this.host.replaceChildren();

        const shell = el('div', `ld-shell ld-mode-${mode}${this.review ? ' ld-reviewing' : ''}`);
        this.shell = shell;

        // Top bar.
        const top = el('div', 'ld-topbar');
        const exit = el('button', 'ld-iconbtn ld-exit', {type: 'button', 'aria-label': S.exit, title: S.exit,
            children: [svgIcon('exit')]});
        exit.addEventListener('click', () => this.exit());
        const title = el('div', 'ld-titlewrap');
        this.modeTag = el('span', `ld-modetag ld-modetag-${mode}`, {
            text: this.review ? S.review : S[`mode${mode}`]});
        this.titleEl = el('div', 'ld-slidetitle');
        this.counterEl = el('div', 'ld-counter');
        title.append(this.modeTag, this.titleEl);
        const tools = el('div', 'ld-tools');
        this.timerEl = el('div', 'ld-timer', {children: [svgIcon('clock'), textSpan('0:00')], role: 'timer'});
        this.scoreEl = el('div', 'ld-score');
        if (mode === 'study' || this.review) {
            this.timerEl.hidden = true;
            this.scoreEl.hidden = true;
        }
        this.muteBtn = el('button', 'ld-iconbtn', {type: 'button'});
        this.muteBtn.addEventListener('click', () => {
            Sound.toggleMute();
            this.syncMute();
        });
        if (!this.config.sounds) {
            this.muteBtn.hidden = true;
        }
        this.fullBtn = el('button', 'ld-iconbtn', {type: 'button'});
        this.fullBtn.addEventListener('click', () => this.toggleFullscreen());
        tools.append(this.timerEl, this.scoreEl, this.muteBtn, this.fullBtn);
        this.hintEl = el('div', 'ld-hint');
        top.append(exit, title, this.hintEl, tools);

        // Progress dots.
        this.dotsEl = el('div', 'ld-dots', {role: 'tablist'});
        this.data.slides.forEach((s, i) => {
            const dot = el('button', 'ld-dot', {type: 'button', role: 'tab', 'aria-label': fmt(S.slideof,
                {current: i + 1, total: this.data.slides.length})});
            dot.addEventListener('click', () => {
                if (this.canNavigateTo(i)) {
                    this.goTo(i);
                }
            });
            this.dotsEl.appendChild(dot);
        });

        // Viewport + track.
        const viewport = el('div', 'ld-viewport');
        this.viewport = viewport;
        this.track = el('div', 'ld-track');
        viewport.appendChild(this.track);

        // Footer actions.
        this.actions = el('div', 'ld-actions');

        if (this.data.slides.length < 2) {
            this.dotsEl.hidden = true;
        }
        shell.append(top, viewport, this.actions);
        this.host.appendChild(shell);

        this.slides = this.data.slides.map((s, i) => this.buildSlide(s, i));
        this.syncMute();
        this.syncFullscreen();
        this.goTo(0, true);
        this.startTimer();
        this.updateScore();

        this.resizeObserver = window.ResizeObserver ? new ResizeObserver(() => this.onResize()) : null;
        if (this.resizeObserver) {
            this.resizeObserver.observe(viewport);
        }
        shell.setAttribute('tabindex', '-1');
        shell.focus({preventScroll: true});
        window.setTimeout(() => {
            this.syncHeight();
            this.scrollToShell();
        }, 60);
    }

    /**
     * Builds one slide.
     *
     * @param {object} data
     * @param {number} index
     * @returns {object} slide state
     */
    buildSlide(data, index) {
        const mode = this.mode;
        const slide = {data, index, slots: new Map(), chips: new Map(), complete: false, submitted: false,
            results: null, purposes: new Map(), answers: new Map()};
        (data.purposes || []).forEach((p) => slide.purposes.set(p.token, p.html));
        (data.answers || []).forEach((a) => slide.answers.set(a.pin, a.label));

        const section = el('section', 'ld-slide', {'aria-roledescription': 'slide',
            'aria-label': fmt(S.slideof, {current: index + 1, total: this.data.slides.length})});
        slide.el = section;

        const tapHint = COARSE ? S.instructions_tap : S.instructions_drag;
        let hint = mode === 'study' ? S.instructions_study : tapHint;
        if (this.review) {
            hint = S.studytap;
        }
        slide.hint = (!this.review && data.instructions) || hint;
        // Teacher instructions are already formatted by format_string(); built-in hints are plain text.
        slide.hintFormatted = !this.review && !!data.instructions;

        const n = data.pins.length;
        const board = el('div', `ld-board ld-n${Math.min(n, 10)}${n > 6 ? ' ld-compact' : ''}`);
        board.style.setProperty('--n', n);
        slide.board = board;

        const rail = el('div', 'ld-rail', {role: 'list'});
        const figure = el('div', 'ld-figure');
        figure.style.setProperty('--ar', `${data.width} / ${data.height}`);
        figure.style.setProperty('--arn', data.width / data.height);
        const img = el('img', 'ld-image', {src: data.image || '', alt: data.title || '', draggable: 'false'});
        img.addEventListener('load', () => {
            this.drawLines(slide);
            if (this.slides && this.slides[this.index] === slide) {
                this.syncHeight();
            }
        });
        figure.appendChild(img);

        const svg = document.createElementNS(SVGNS, 'svg');
        svg.setAttribute('class', 'ld-lines');
        svg.setAttribute('aria-hidden', 'true');
        slide.svg = svg;

        data.pins.forEach((pin) => {
            const slot = el('div', 'ld-slot', {role: 'listitem'});
            const zone = el('div', 'ld-slot-zone', {tabindex: '0', role: 'button', 'data-pin': pin.token});
            const num = el('span', 'ld-slot-num', {text: pin.number});
            const body = el('div', 'ld-slot-body');
            zone.append(num, body);
            slot.appendChild(zone);
            rail.appendChild(slot);

            const pinEl = el('button', 'ld-pin', {type: 'button', 'data-pin': pin.token, text: pin.number,
                tabindex: '-1'});
            pinEl.style.left = `${pin.x}%`;
            pinEl.style.top = `${pin.y}%`;
            figure.appendChild(pinEl);

            const path = document.createElementNS(SVGNS, 'path');
            path.setAttribute('class', 'ld-line');
            const dotA = document.createElementNS(SVGNS, 'circle');
            dotA.setAttribute('class', 'ld-line-end');
            dotA.setAttribute('r', '3');
            svg.append(path, dotA);

            const state = {token: pin.token, number: pin.number, zone, body, pinEl, path, dotA, chip: null,
                locked: false, tries: 0, color: null, label: pin.label || null};
            slide.slots.set(pin.token, state);
            this.labelZone(state);

            const activate = (e) => {
                e.preventDefault();
                this.onZoneActivate(slide, state);
            };
            zone.addEventListener('click', activate);
            pinEl.addEventListener('click', activate);
            zone.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    activate(e);
                }
            });
            // Allow dragging a placed label out of a slot.
            zone.addEventListener('pointerdown', (e) => {
                if (state.chip && !state.locked) {
                    this.pointerDown(e, slide, slide.chips.get(state.chip));
                }
            });
        });

        board.append(rail, figure, svg);
        section.appendChild(board);

        // Label tray.
        const tray = el('div', 'ld-tray', {role: 'group', 'aria-label': S.labeltray});
        slide.tray = tray;
        (data.labels || []).forEach((label) => {
            const chip = el('button', 'ld-chip', {type: 'button', 'data-token': label.token, formatted: label.text,
                'aria-pressed': 'false'});
            paint(chip, label.color);
            const state = {token: label.token, el: chip, text: label.text, color: label.color, slot: null};
            slide.chips.set(label.token, state);
            tray.appendChild(chip);
            chip.addEventListener('pointerdown', (e) => this.pointerDown(e, slide, state));
            chip.addEventListener('click', (e) => {
                e.stopPropagation();
                if (this.suppressClick) {
                    e.preventDefault();
                    return;
                }
                this.toggleSelect(slide, state);
            });
        });
        tray.appendChild(el('div', 'ld-tray-done', {children: [svgIcon('check'), textSpan(S.allplaced)]}));
        tray.addEventListener('click', (e) => {
            if (e.target === tray && this.selected && this.selected.chip.slot) {
                this.unplace(slide, this.selected.chip, true);
                this.clearSelection();
            }
        });
        if (mode !== 'study') {
            section.appendChild(tray);
            section.classList.add('has-tray');
        }

        // Study mode: labels are shown in place.
        if (mode === 'study') {
            data.pins.forEach((pin) => {
                const state = slide.slots.get(pin.token);
                const chip = el('div', 'ld-chip ld-chip-static', {formatted: pin.label});
                paint(chip, pin.color);
                state.body.appendChild(chip);
                state.color = pin.color;
                state.zone.classList.add('is-filled');
                state.pinEl.classList.add('is-filled');
                paint(state.pinEl, pin.color);
                state.purpose = pin.purpose;
                state.locked = true;
            });
            slide.complete = true;
        }

        this.track.appendChild(section);
        requestAnimationFrame(() => this.drawLines(slide));
        return slide;
    }

    /**
     * Sets a zone's accessible label.
     *
     * @param {object} slot
     */
    labelZone(slot) {
        const chipText = slot.chip ? this.slides?.[this.index]?.chips.get(slot.chip)?.text : null;
        const text = chipText ? fmt(S.dropzonefilled, {number: slot.number, label: this.plain(chipText)})
            : fmt(S.dropzoneempty, slot.number);
        slot.zone.setAttribute('aria-label', text);
        slot.pinEl.setAttribute('aria-label', text);
    }

    /**
     * Strips HTML.
     *
     * @param {string} html
     * @returns {string}
     */
    plain(html) {
        return new DOMParser().parseFromString(String(html), 'text/html').body.textContent;
    }

    /**
     * Draws the leader lines from rail slots to pins.
     *
     * @param {object} slide
     */
    drawLines(slide) {
        const boardRect = slide.board.getBoundingClientRect();
        if (!boardRect.width) {
            return;
        }
        slide.svg.setAttribute('viewBox', `0 0 ${boardRect.width} ${boardRect.height}`);
        slide.svg.setAttribute('width', boardRect.width);
        slide.svg.setAttribute('height', boardRect.height);
        slide.slots.forEach((slot) => {
            const z = slot.zone.getBoundingClientRect();
            const p = slot.pinEl.getBoundingClientRect();
            const x1 = z.right - boardRect.left + 3;
            const y1 = z.top + z.height / 2 - boardRect.top;
            const x2 = p.left + p.width / 2 - boardRect.left;
            const y2 = p.top + p.height / 2 - boardRect.top;
            const dx = Math.max(24, (x2 - x1) * 0.45);
            slot.path.setAttribute('d', `M${x1},${y1} C${x1 + dx},${y1} ${x2 - dx},${y2} ${x2},${y2}`);
            slot.dotA.setAttribute('cx', x1);
            slot.dotA.setAttribute('cy', y1);
            const filled = !!(slot.chip || slot.color);
            slot.path.classList.toggle('is-filled', filled);
            slot.dotA.classList.toggle('is-filled', filled);
            const color = slot.color || '';
            slot.path.style.setProperty('--line-c', filled ? color : '');
            slot.dotA.style.fill = filled ? color : '';
        });
    }

    /**
     * Animates the leader line being drawn.
     *
     * @param {object} slide
     * @param {object} slot
     */
    animateLine(slide, slot) {
        this.drawLines(slide);
        if (REDUCED || !slot.path.getTotalLength) {
            return;
        }
        const len = slot.path.getTotalLength();
        slot.path.animate([
            {strokeDasharray: `${len}`, strokeDashoffset: `${len}`},
            {strokeDasharray: `${len}`, strokeDashoffset: '0'},
        ], {duration: 520, easing: EASE});
    }

    /**
     * Whether the learner may jump to a slide.
     *
     * @param {number} i
     * @returns {boolean}
     */
    canNavigateTo(i) {
        if (this.mode === 'study' || this.review) {
            return true;
        }
        // Forward only when the current slide is done; always allowed to look back.
        return i <= this.index || this.slides.slice(0, i).every((s) => s.complete || s.submitted);
    }

    /**
     * Moves the slideshow to a slide.
     *
     * @param {number} i
     * @param {boolean} instant
     */
    goTo(i, instant = false) {
        const total = this.slides.length;
        i = Math.max(0, Math.min(total - 1, i));
        const changed = i !== this.index;
        this.index = i;
        this.clearSelection();
        this.closeCard();
        this.track.style.transition = instant || REDUCED ? 'none' : `transform 640ms ${EASE}`;
        this.track.style.transform = `translate3d(${-100 * i}%, 0, 0)`;
        this.slides.forEach((s, k) => {
            const active = k === i;
            s.el.classList.toggle('is-active', active);
            s.el.setAttribute('aria-hidden', active ? 'false' : 'true');
            if ('inert' in s.el) {
                s.el.inert = !active;
            }
        });
        Array.from(this.dotsEl.children).forEach((dot, k) => {
            const s = this.slides[k];
            dot.classList.toggle('is-active', k === i);
            dot.classList.toggle('is-done', !!(s.complete || s.submitted) && this.mode !== 'study');
            dot.setAttribute('aria-selected', k === i ? 'true' : 'false');
        });
        const slide = this.slides[i];
        if (this.mode === 'study' && !this.review) {
            this.visited = this.visited || new Set();
            this.visited.add(i);
            if (this.visited.size === total && !this.studyRecorded && this.config.canattempt) {
                this.studyRecorded = true;
                Promise.resolve(Ajax.call([{methodname: 'mod_labeldiagram_complete_study',
                    args: {cmid: this.config.cmid}}])[0]).catch(() => null);
            }
        }
        const counter = textSpan(fmt(S.slideof, {current: i + 1, total}), 'ld-counter');
        counter.appendChild(this.dotsEl);
        this.titleEl.replaceChildren(el('span', 'ld-slidename', {formatted: slide.data.title}), ' ', counter);
        if (slide.hintFormatted) {
            this.hintEl.replaceChildren(el('span', '', {formatted: slide.hint}));
        } else {
            this.hintEl.textContent = slide.hint;
        }
        if (changed && !instant) {
            Sound.play('whoosh');
        }
        this.renderActions();
        this.syncHeight();
        window.setTimeout(() => {
            this.drawLines(slide);
            this.syncHeight();
        }, instant ? 0 : 660);
        this.say(`${this.plain(slide.data.title)}. ${fmt(S.slideof, {current: i + 1, total})}`);
    }

    /**
     * Sizes the slideshow viewport to the active slide (smoothly), so short slides leave no gap.
     */
    syncHeight() {
        const slide = this.slides?.[this.index];
        if (!slide || !this.viewport) {
            return;
        }
        this.fit(slide);
        this.viewport.style.height = `${slide.el.offsetHeight}px`;
    }

    /**
     * Sizes the diagram so the whole player (top bar, board, labels, buttons) fits on screen,
     * using every spare pixel for the image. Runs on start, resize, slide change and full screen.
     *
     * @param {object} slide
     */
    fit(slide) {
        const shell = this.shell;
        const figure = slide.board.querySelector('.ld-figure');
        if (!shell || !figure) {
            return;
        }
        // Tray beside the board when there is room for it, otherwise underneath.
        const wide = shell.clientWidth >= 1000;
        shell.classList.toggle('ld-wide', wide);
        const full = shell.classList.contains('is-full');
        const navbar = full ? 0 : (document.querySelector('nav.navbar.fixed-top')?.offsetHeight || 0);
        const available = window.innerHeight - navbar - (full ? 0 : 16);
        // Everything in the shell except the board (top bar, buttons, labels underneath on narrow screens)…
        const board = slide.board;
        const cs = window.getComputedStyle(board);
        const boardChrome = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom) +
            parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth);
        // Measured from the shell's children (a full-screen shell is always screen-height, so its own height can't be used).
        const scs = window.getComputedStyle(shell);
        const kids = Array.from(shell.children).filter((k) => k.offsetParent !== null && k !== this.viewport &&
            !k.classList.contains('ld-overlay') && !k.classList.contains('ld-confetti'));
        const gap = parseFloat(scs.rowGap) || 0;
        const shellChrome = kids.reduce((sum, k) => sum + k.offsetHeight, 0) + gap * kids.length +
            parseFloat(scs.paddingTop) + parseFloat(scs.paddingBottom) +
            parseFloat(scs.borderTopWidth) + parseFloat(scs.borderBottomWidth);
        const chrome = shellChrome + (slide.el.offsetHeight - board.offsetHeight) + boardChrome;
        // …and the image gets the rest.
        const maxh = Math.max(220, Math.floor(available - chrome - 4));
        shell.style.setProperty('--maxh', `${maxh}px`);
        // Label boxes: as tall and readable as the image height allows (34-52px, bigger text in taller boxes).
        if (window.innerWidth > 700) {
            const n = Math.max(1, slide.data.pins.length);
            const gap = maxh / n >= 44 ? 8 : 5;
            const slotH = Math.max(30, Math.min(52, Math.floor((maxh - gap * (n - 1) - 8) / n)));
            let fs = '.86rem';
            if (slotH >= 46) {
                fs = '1.02rem';
            } else if (slotH >= 40) {
                fs = '.96rem';
            } else if (slotH >= 34) {
                fs = '.9rem';
            }
            board.style.setProperty('--slot-h', `${slotH}px`);
            board.style.setProperty('--chip-fs', fs);
            board.style.setProperty('--rail-gap', `${gap}px`);
        } else {
            board.style.removeProperty('--slot-h');
            board.style.removeProperty('--chip-fs');
        }
    }

    /**
     * Scrolls the page so the player sits just under Moodle's fixed header.
     */
    scrollToShell() {
        const navbar = document.querySelector('nav.navbar.fixed-top')?.offsetHeight || 0;
        const top = this.host.getBoundingClientRect().top + window.scrollY - navbar - 8;
        window.scrollTo({top, behavior: REDUCED ? 'auto' : 'smooth'});
    }

    /**
     * Renders the footer action buttons for the current state.
     */
    renderActions() {
        const slide = this.slides[this.index];
        const last = this.index === this.slides.length - 1;
        const a = this.actions;
        a.replaceChildren();
        const btn = (cls, label, ic, handler, opts = {}) => {
            const b = el('button', `ld-btn ${cls}`, {type: 'button',
                children: opts.after ? [textSpan(label), svgIcon(ic)] : [svgIcon(ic), textSpan(label)]});
            if (opts.disabled) {
                b.disabled = true;
            }
            b.addEventListener('click', handler);
            return b;
        };
        const left = el('div', 'ld-actions-left');
        const right = el('div', 'ld-actions-right');
        a.append(left, right);

        if (this.index > 0) {
            left.appendChild(btn('ld-btn-ghost', S.previous, 'prev', () => this.goTo(this.index - 1)));
        }

        if (this.mode === 'study' || this.review) {
            if (!last) {
                right.appendChild(btn('ld-btn-primary', S.next, 'next', () => this.goTo(this.index + 1), {after: true}));
            } else {
                right.appendChild(btn('ld-btn-primary', this.review ? S.resultstitle : S.backtomenu, 'next', () => {
                    if (this.review) {
                        this.showSummary(this.summary).catch(Notification.exception);
                    } else {
                        this.exit(true);
                    }
                }, {after: true}));
            }
            return;
        }

        if (this.mode === 'practice') {
            if (!slide.complete) {
                left.appendChild(btn('ld-btn-ghost', S.hint, 'hint', () => this.hint(slide)));
                left.appendChild(btn('ld-btn-ghost', S.showanswers, 'eye', () => this.showAnswers(slide)));
            }
            const b = btn('ld-btn-primary', last ? S.finish : S.next, 'next', () => {
                if (last) {
                    this.finish();
                } else {
                    this.goTo(this.index + 1);
                }
            }, {after: true, disabled: !slide.complete});
            if (slide.complete) {
                b.classList.add('ld-pulse');
            }
            right.appendChild(b);
            return;
        }

        // Test mode.
        if (!slide.submitted) {
            left.appendChild(btn('ld-btn-ghost', S.reset, 'reset', () => this.resetSlide(slide)));
            const b = btn('ld-btn-primary', last ? S.finish : S.submitslide, last ? 'check' : 'next',
                () => this.submitTestSlide(slide), {after: true});
            if (this.allPlaced(slide)) {
                b.classList.add('ld-pulse');
            }
            right.appendChild(b);
        } else if (!last) {
            right.appendChild(btn('ld-btn-primary', S.next, 'next', () => this.goTo(this.index + 1), {after: true}));
        } else {
            right.appendChild(btn('ld-btn-primary', S.finish, 'check', () => this.finish(), {after: true}));
        }
    }

    /**
     * Whether every slot of a slide holds a label.
     *
     * @param {object} slide
     * @returns {boolean}
     */
    allPlaced(slide) {
        return Array.from(slide.slots.values()).every((s) => s.chip);
    }

    /* ------------------------------------------------------------------ */
    /* Tap / keyboard selection                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Selects or deselects a chip.
     *
     * @param {object} slide
     * @param {object} chip
     */
    toggleSelect(slide, chip) {
        if (this.review || this.mode === 'study') {
            return;
        }
        if (chip.slot && slide.slots.get(chip.slot).locked) {
            return;
        }
        if (this.selected && this.selected.chip === chip) {
            this.clearSelection();
            return;
        }
        this.clearSelection();
        this.selected = {slide, chip};
        chip.el.classList.add('is-selected');
        chip.el.setAttribute('aria-pressed', 'true');
        slide.board.classList.add('is-targeting');
        Sound.play('select');
        this.say(fmt(S.selectedfeedback, this.plain(chip.text)));
    }

    /**
     * Clears the current selection.
     */
    clearSelection() {
        if (!this.selected) {
            return;
        }
        this.selected.chip.el.classList.remove('is-selected');
        this.selected.chip.el.setAttribute('aria-pressed', 'false');
        this.selected.slide.board.classList.remove('is-targeting');
        this.selected = null;
    }

    /**
     * A zone or pin was clicked/tapped/activated by keyboard.
     *
     * @param {object} slide
     * @param {object} slot
     */
    onZoneActivate(slide, slot) {
        if (this.suppressClick) {
            return;
        }
        if (this.mode === 'study' || this.review || slot.locked) {
            if (slot.purpose || slot.reviewPurpose) {
                this.openCard(slide, slot, slot.purpose || slot.reviewPurpose).catch(Notification.exception);
            }
            return;
        }
        if (this.selected && this.selected.slide === slide) {
            const chip = this.selected.chip;
            const from = chip.el.getBoundingClientRect();
            this.clearSelection();
            this.place(slide, chip, slot, from);
            return;
        }
        // Nothing selected: selecting a filled slot picks up its label.
        if (slot.chip) {
            this.toggleSelect(slide, slide.chips.get(slot.chip));
        }
    }

    /* ------------------------------------------------------------------ */
    /* Drag and drop (pointer events: mouse, pen, touch)                  */
    /* ------------------------------------------------------------------ */

    /**
     * Pointer down on a chip.
     *
     * @param {PointerEvent} e
     * @param {object} slide
     * @param {object} chip
     */
    pointerDown(e, slide, chip) {
        if (this.review || this.mode === 'study' || e.button > 0 || this.drag) {
            return;
        }
        if (chip.slot && slide.slots.get(chip.slot).locked) {
            return;
        }
        this.suppressClick = false;
        this.drag = {slide, chip, startX: e.clientX, startY: e.clientY, id: e.pointerId, active: false, over: null,
            lastX: e.clientX};
        const move = (ev) => this.pointerMove(ev);
        const up = (ev) => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', up);
            this.pointerUp(ev);
        };
        window.addEventListener('pointermove', move, {passive: false});
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', up);
    }

    /**
     * Pointer move.
     *
     * @param {PointerEvent} e
     */
    pointerMove(e) {
        const d = this.drag;
        if (!d || e.pointerId !== d.id) {
            return;
        }
        if (!d.active) {
            if (Math.hypot(e.clientX - d.startX, e.clientY - d.startY) < 6) {
                return;
            }
            this.beginDrag(e);
        }
        e.preventDefault();
        const vx = e.clientX - d.lastX;
        d.lastX = e.clientX;
        d.tilt = Math.max(-10, Math.min(10, (d.tilt || 0) * 0.7 + vx * 0.9));
        d.ghost.style.transform = `translate3d(${e.clientX - d.offX}px, ${e.clientY - d.offY}px, 0)
            rotate(${REDUCED ? 0 : d.tilt}deg) scale(1.06)`;
        this.updateOver(e.clientX, e.clientY);
    }

    /**
     * Starts the visual drag.
     *
     * @param {PointerEvent} e
     */
    beginDrag(e) {
        const d = this.drag;
        d.active = true;
        this.suppressClick = true;
        this.clearSelection();
        this.closeCard();
        const r = d.chip.el.getBoundingClientRect();
        d.offX = e.clientX - r.left;
        d.offY = e.clientY - r.top;
        const ghost = d.chip.el.cloneNode(true);
        ghost.className = 'ld-chip ld-ghost';
        paint(ghost, d.chip.color);
        ghost.style.width = `${r.width}px`;
        ghost.style.height = `${r.height}px`;
        ghost.style.transform = `translate3d(${r.left}px, ${r.top}px, 0)`;
        (document.fullscreenElement || document.body).appendChild(ghost);
        d.ghost = ghost;
        d.chip.el.classList.add('is-lifted');
        d.slide.board.classList.add('is-dragging');
        d.slide.tray.classList.add('is-dragging');
        document.body.classList.add('ld-noselect');
        Sound.play('pickup');
    }

    /**
     * Finds the drop target under / near the pointer and highlights it.
     *
     * @param {number} x
     * @param {number} y
     */
    updateOver(x, y) {
        const d = this.drag;
        let target = null;
        let best = Infinity;
        const magnet = COARSE ? 48 : 38;
        d.slide.slots.forEach((slot) => {
            if (slot.locked) {
                return;
            }
            const z = slot.zone.getBoundingClientRect();
            if (x >= z.left - 6 && x <= z.right + 6 && y >= z.top - 6 && y <= z.bottom + 6) {
                target = slot;
                best = -1;
                return;
            }
            const p = slot.pinEl.getBoundingClientRect();
            const dist = Math.hypot(x - (p.left + p.width / 2), y - (p.top + p.height / 2));
            if (dist < magnet && dist < best) {
                best = dist;
                target = slot;
            }
        });
        let overTray = false;
        if (!target) {
            const t = d.slide.tray.getBoundingClientRect();
            overTray = x >= t.left && x <= t.right && y >= t.top && y <= t.bottom;
        }
        if (target !== d.over) {
            if (d.over) {
                d.over.zone.classList.remove('is-over');
                d.over.pinEl.classList.remove('is-over');
                d.over.path.classList.remove('is-over');
            }
            if (target) {
                target.zone.classList.add('is-over');
                target.pinEl.classList.add('is-over');
                target.path.classList.add('is-over');
                paint(target.path, d.chip.color);
                Sound.play('zone');
                if (navigator.vibrate && COARSE) {
                    navigator.vibrate(8);
                }
            }
            d.over = target;
        }
        d.slide.tray.classList.toggle('is-over', overTray);
        d.overTray = overTray;
    }

    /**
     * Pointer released.
     *
     * @param {PointerEvent} e
     */
    pointerUp(e) {
        const d = this.drag;
        this.drag = null;
        if (!d || !d.active) {
            return;
        }
        e.preventDefault();
        const ghostRect = d.ghost.getBoundingClientRect();
        d.ghost.remove();
        d.chip.el.classList.remove('is-lifted');
        d.slide.board.classList.remove('is-dragging');
        d.slide.tray.classList.remove('is-dragging', 'is-over');
        document.body.classList.remove('ld-noselect');
        if (d.over) {
            d.over.zone.classList.remove('is-over');
            d.over.pinEl.classList.remove('is-over');
            d.over.path.classList.remove('is-over');
            this.place(d.slide, d.chip, d.over, ghostRect);
        } else if (d.overTray && d.chip.slot) {
            this.unplace(d.slide, d.chip, true, ghostRect);
        } else {
            // Spring back home.
            flip(d.chip.el, ghostRect, {duration: 460});
            Sound.play('back');
        }
        window.setTimeout(() => {
            this.suppressClick = false;
        }, 50);
    }

    /* ------------------------------------------------------------------ */
    /* Placement logic                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Places a chip into a slot.
     *
     * @param {object} slide
     * @param {object} chip
     * @param {object} slot
     * @param {DOMRect} fromRect where the chip visually comes from
     */
    place(slide, chip, slot, fromRect) {
        if (slot.locked) {
            return;
        }
        if (chip.slot === slot.token) {
            flip(chip.el, fromRect);
            return;
        }
        const prevSlot = chip.slot ? slide.slots.get(chip.slot) : null;

        if (this.mode === 'practice') {
            const answer = slide.chips.get(slide.answers.get(slot.token));
            // Identical label texts (e.g. two "Bolt" labels) are interchangeable.
            const correct = slide.answers.get(slot.token) === chip.token ||
                (!!answer && this.plain(answer.text).trim().toLowerCase() === this.plain(chip.text).trim().toLowerCase());
            // Temporarily show in the slot, then judge.
            this.attach(slide, chip, slot, fromRect, prevSlot);
            Sound.play('drop');
            if (correct) {
                slot.tries++;
                slot.locked = true;
                slot.zone.classList.add('is-correct');
                slot.pinEl.classList.add('is-correct');
                chip.el.classList.add('is-locked');
                chip.el.disabled = true;
                window.setTimeout(() => {
                    Sound.play('correct');
                    this.popCheck(slot);
                    const b = slide.board.getBoundingClientRect();
                    const p = slot.pinEl.getBoundingClientRect();
                    burst(slide.board, p.left + p.width / 2 - b.left, p.top + p.height / 2 - b.top, chip.color);
                }, 140);
                this.say(fmt(S.correctfeedback, this.plain(chip.text)));
                const purpose = slide.purposes.get(chip.token);
                slot.purpose = purpose || null;
                if (purpose && this.config.showpurpose === 1) {
                    window.setTimeout(() => this.openCard(slide, slot, purpose).catch(Notification.exception), 520);
                }
                this.updateScore();
                this.checkPracticeComplete(slide);
            } else {
                slot.tries++;
                slot.zone.classList.add('is-wrong');
                slot.pinEl.classList.add('is-wrong');
                window.setTimeout(() => Sound.play('wrong'), 120);
                this.say(fmt(S.wrongfeedback, this.plain(chip.text)));
                window.setTimeout(() => {
                    slot.zone.classList.remove('is-wrong');
                    slot.pinEl.classList.remove('is-wrong');
                    this.unplace(slide, chip, false);
                    Sound.play('back');
                }, 650);
            }
            return;
        }

        // Test mode: free placement, swap if occupied.
        if (slot.chip) {
            const other = slide.chips.get(slot.chip);
            if (prevSlot) {
                const otherRect = other.el.getBoundingClientRect();
                this.detach(slide, other);
                this.attach(slide, other, prevSlot, otherRect, null);
            } else {
                this.unplace(slide, other, false);
            }
        }
        this.attach(slide, chip, slot, fromRect, prevSlot);
        Sound.play('drop');
        this.say(fmt(S.placedfeedback, {label: this.plain(chip.text), number: slot.number}));
        this.updateScore();
        this.renderActions();
    }

    /**
     * Moves a chip DOM node into a slot and animates it.
     *
     * @param {object} slide
     * @param {object} chip
     * @param {object} slot
     * @param {DOMRect} fromRect
     * @param {object|null} prevSlot
     */
    attach(slide, chip, slot, fromRect, prevSlot) {
        if (prevSlot && prevSlot.chip === chip.token) {
            prevSlot.chip = null;
            prevSlot.color = null;
            prevSlot.zone.classList.remove('is-filled');
            prevSlot.pinEl.classList.remove('is-filled');
            this.labelZone(prevSlot);
        }
        chip.slot = slot.token;
        slot.chip = chip.token;
        slot.color = chip.color;
        slot.body.appendChild(chip.el);
        slot.zone.classList.add('is-filled');
        slot.pinEl.classList.add('is-filled');
        paint(slot.pinEl, chip.color);
        this.labelZone(slot);
        flip(chip.el, fromRect);
        this.animateLine(slide, slot);
        if (prevSlot) {
            this.drawLines(slide);
        }
        this.collapseTray(slide);
    }

    /**
     * Detaches a chip from its slot (without moving DOM).
     *
     * @param {object} slide
     * @param {object} chip
     */
    detach(slide, chip) {
        if (!chip.slot) {
            return;
        }
        const slot = slide.slots.get(chip.slot);
        slot.chip = null;
        slot.color = null;
        slot.zone.classList.remove('is-filled');
        slot.pinEl.classList.remove('is-filled');
        this.labelZone(slot);
        chip.slot = null;
    }

    /**
     * Returns a chip to the tray.
     *
     * @param {object} slide
     * @param {object} chip
     * @param {boolean} sound
     * @param {DOMRect|null} fromRect
     */
    unplace(slide, chip, sound, fromRect = null) {
        const from = fromRect || chip.el.getBoundingClientRect();
        this.detach(slide, chip);
        slide.tray.appendChild(chip.el);
        flip(chip.el, from, {duration: 520});
        this.drawLines(slide);
        this.collapseTray(slide);
        if (sound) {
            Sound.play('back');
            this.say(fmt(S.returntotray, this.plain(chip.text)));
        }
        this.updateScore();
        if (this.mode === 'test') {
            this.renderActions();
        }
    }

    /**
     * Marks the tray empty/non-empty for styling.
     *
     * @param {object} slide
     */
    collapseTray(slide) {
        slide.tray.classList.toggle('is-empty', !slide.tray.querySelector('.ld-chip'));
        window.setTimeout(() => this.syncHeight(), 320);
    }

    /**
     * Pops a check mark on a slot.
     *
     * @param {object} slot
     */
    popCheck(slot) {
        const mark = el('span', 'ld-check', {children: [svgIcon('check')], 'aria-hidden': 'true'});
        slot.zone.appendChild(mark);
    }

    /**
     * Practice: checks whether the slide is complete.
     *
     * @param {object} slide
     */
    checkPracticeComplete(slide) {
        const done = Array.from(slide.slots.values()).every((s) => s.locked);
        if (!done || slide.complete) {
            return;
        }
        slide.complete = true;
        window.setTimeout(() => {
            Sound.play('slide');
            this.say(S.slidecomplete);
            slide.board.classList.add('is-complete');
            const banner = el('div', 'ld-banner', {children: [svgIcon('star'), textSpan(S.slidecomplete)]});
            slide.el.appendChild(banner);
            window.setTimeout(() => banner.classList.add('is-out'), 2200);
            window.setTimeout(() => banner.remove(), 2800);
            if (!this.card) {
                confetti(slide.el, 60);
            }
        }, 450);
        this.submitPractice(slide);
        this.renderActions();
        Array.from(this.dotsEl.children)[slide.index].classList.add('is-done');
    }

    /**
     * Practice: records the slide result on the server.
     *
     * @param {object} slide
     */
    submitPractice(slide) {
        const placements = Array.from(slide.slots.values()).map((s) => ({pin: s.token, label: s.chip || '',
            tries: s.tries}));
        slide.submitting = Promise.resolve(Ajax.call([{methodname: 'mod_labeldiagram_submit_slide', args: {
            attemptid: this.attemptid, slideid: slide.data.id, placements}}])[0])
            .then((res) => {
                slide.submitted = true;
                slide.results = res.results;
                return res;
            }).catch(Notification.exception);
    }

    /**
     * Practice: hint — highlights where the selected (or first) label belongs.
     *
     * @param {object} slide
     */
    hint(slide) {
        const chip = this.selected?.chip || Array.from(slide.chips.values()).find((c) => !c.slot &&
            Array.from(slide.answers.values()).includes(c.token));
        if (!chip) {
            return;
        }
        let pin = null;
        slide.answers.forEach((label, p) => {
            if (label === chip.token) {
                pin = p;
            }
        });
        const slot = slide.slots.get(pin);
        if (!slot || slot.locked) {
            return;
        }
        slot.tries++;
        Sound.play('hint');
        chip.el.classList.add('ld-hinted');
        slot.zone.classList.add('ld-hinted');
        slot.pinEl.classList.add('ld-hinted');
        window.setTimeout(() => {
            chip.el.classList.remove('ld-hinted');
            slot.zone.classList.remove('ld-hinted');
            slot.pinEl.classList.remove('ld-hinted');
        }, 1800);
        this.updateScore();
    }

    /**
     * Practice: reveals all remaining answers with a staggered animation.
     *
     * @param {object} slide
     */
    showAnswers(slide) {
        this.clearSelection();
        let delay = 0;
        slide.slots.forEach((slot) => {
            if (slot.locked) {
                return;
            }
            const chip = slide.chips.get(slide.answers.get(slot.token));
            if (!chip) {
                return;
            }
            slot.tries += 2;
            window.setTimeout(() => {
                if (slot.chip && slot.chip !== chip.token) {
                    this.unplace(slide, slide.chips.get(slot.chip), false);
                }
                const from = chip.el.getBoundingClientRect();
                this.attach(slide, chip, slot, from, chip.slot ? slide.slots.get(chip.slot) : null);
                slot.locked = true;
                slot.zone.classList.add('is-revealed');
                slot.pinEl.classList.add('is-correct');
                chip.el.classList.add('is-locked');
                chip.el.disabled = true;
                slot.purpose = slide.purposes.get(chip.token) || null;
                Sound.play('drop');
                this.checkPracticeComplete(slide);
            }, delay);
            delay += REDUCED ? 0 : 180;
        });
        this.updateScore();
    }

    /**
     * Test: returns all placed labels to the tray.
     *
     * @param {object} slide
     */
    resetSlide(slide) {
        slide.chips.forEach((chip) => {
            if (chip.slot) {
                this.unplace(slide, chip, false);
            }
        });
        Sound.play('back');
    }

    /**
     * Test: submits the current slide and moves on.
     *
     * @param {object} slide
     */
    async submitTestSlide(slide) {
        if (slide.submitted || slide.submitting) {
            return;
        }
        if (!this.allPlaced(slide)) {
            const ok = await this.confirm(S.confirmsubmit);
            if (!ok) {
                return;
            }
        }
        const placements = Array.from(slide.slots.values()).map((s) => ({pin: s.token, label: s.chip || '',
            tries: 1}));
        slide.submitting = true;
        try {
            const res = await Ajax.call([{methodname: 'mod_labeldiagram_submit_slide', args: {
                attemptid: this.attemptid, slideid: slide.data.id, placements}}])[0];
            slide.results = res.results;
            slide.submitted = true;
            slide.complete = true;
            slide.slots.forEach((s) => {
                s.locked = true;
            });
            slide.board.classList.add('is-submitted');
            slide.tray.hidden = true;
        } catch (err) {
            slide.submitting = false;
            Notification.exception(err);
            return;
        }
        slide.submitting = false;
        Sound.play('slide');
        if (this.index < this.slides.length - 1) {
            this.goTo(this.index + 1);
        } else {
            this.finish();
        }
    }

    /**
     * Lightweight in-player confirm dialog.
     *
     * @param {string} message
     * @returns {Promise<boolean>}
     */
    confirm(message) {
        return new Promise((resolve) => {
            const overlay = el('div', 'ld-overlay');
            const box = el('div', 'ld-dialog', {role: 'alertdialog', 'aria-modal': 'true'});
            box.appendChild(el('p', '', {text: message}));
            const row = el('div', 'ld-dialog-actions');
            const no = el('button', 'ld-btn ld-btn-ghost', {type: 'button', text: S.close});
            const yes = el('button', 'ld-btn ld-btn-primary', {type: 'button', text: S.submitslide});
            row.append(no, yes);
            box.appendChild(row);
            overlay.appendChild(box);
            this.shell.appendChild(overlay);
            yes.focus();
            const done = (v) => {
                overlay.remove();
                resolve(v);
            };
            no.addEventListener('click', () => done(false));
            yes.addEventListener('click', () => done(true));
            overlay.addEventListener('click', (e) => e.target === overlay && done(false));
        });
    }

    /* ------------------------------------------------------------------ */
    /* Purpose card                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Opens the "what it does" card for a slot.
     *
     * @param {object} slide
     * @param {object} slot
     * @param {string} html
     */
    async openCard(slide, slot, html) {
        this.closeCard(true);
        if (!html) {
            return;
        }
        const seq = this.cardSeq;
        const chip = slot.chip ? slide.chips.get(slot.chip) : null;
        const title = chip ? chip.text : (slot.label || '');
        const color = slot.color || chip?.color || '#6366F1';
        // Title is format_string() output and html is format_text() output, both formatted on the server.
        const rendered = await Templates.renderForPromise('mod_labeldiagram/player_card', {
            num: slot.number,
            infoicon: icon('info'),
            eyebrow: S.whatitdoes,
            titlehtml: title,
            bodyhtml: html,
            empty: '',
            close: {label: S.close, icon: icon('cross')},
        });
        if (seq !== this.cardSeq || !slide.board.isConnected) {
            return;
        }
        const card = el('div', 'ld-card', {role: 'dialog', 'aria-modal': 'false', tabindex: '-1'});
        paint(card, color);
        Templates.appendNodeContents(card, rendered.html, rendered.js);
        card.querySelector('[data-action="close"]').addEventListener('click', () => this.closeCard());
        card.setAttribute('aria-label', `${this.plain(title)} — ${S.whatitdoes}`);

        const board = slide.board;
        board.appendChild(card);
        const b = board.getBoundingClientRect();
        const p = slot.pinEl.getBoundingClientRect();
        const narrow = b.width < 620;
        if (narrow) {
            card.classList.add('is-sheet');
        } else {
            const cw = Math.min(360, b.width * 0.46);
            card.style.width = `${cw}px`;
            const px = p.left + p.width / 2 - b.left;
            const py = p.top + p.height / 2 - b.top;
            let left = px + 22;
            if (left + cw > b.width - 8) {
                left = px - cw - 22;
            }
            left = Math.max(8, left);
            card.style.left = `${left}px`;
            const ch = card.offsetHeight;
            let top = py - ch / 2;
            top = Math.max(8, Math.min(b.height - ch - 8, top));
            card.style.top = `${top}px`;
            card.style.transformOrigin = left > px ? '0% 50%' : '100% 50%';
        }
        slot.pinEl.classList.add('is-focus');
        this.card = {el: card, slot};
        requestAnimationFrame(() => card.classList.add('is-open'));
        Sound.play('card');
        card.focus({preventScroll: true});
    }

    /**
     * Closes the purpose card.
     *
     * @param {boolean} instant
     */
    closeCard(instant = false) {
        // Cancel any card that is still being rendered.
        this.cardSeq = (this.cardSeq || 0) + 1;
        if (!this.card) {
            return;
        }
        const {el: card, slot} = this.card;
        this.card = null;
        slot.pinEl.classList.remove('is-focus');
        if (instant || REDUCED) {
            card.remove();
            return;
        }
        card.classList.remove('is-open');
        card.classList.add('is-closing');
        window.setTimeout(() => card.remove(), 260);
    }

    /* ------------------------------------------------------------------ */
    /* Score, timer, fullscreen, keyboard                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Updates the score / progress chip.
     */
    updateScore() {
        if (!this.scoreEl || this.mode === 'study' || this.review) {
            return;
        }
        let total = 0;
        let value = 0;
        this.slides?.forEach((s) => {
            s.slots.forEach((slot) => {
                total++;
                if (this.mode === 'practice') {
                    value += slot.locked && slot.tries <= 1 ? 1 : 0;
                } else {
                    value += slot.chip ? 1 : 0;
                }
            });
        });
        const label = this.mode === 'practice' ? S.score : S.placed;
        const strongEl = document.createElement('strong');
        strongEl.textContent = value;
        this.scoreEl.replaceChildren(svgIcon(this.mode === 'practice' ? 'star' : 'check'), textSpan(label, 'ld-score-label'),
            strongEl, textSpan(`/${total}`, 'ld-score-total'));
        const strong = this.scoreEl.querySelector('strong');
        if (!REDUCED && strong && this.lastScore !== value && this.lastScore !== undefined) {
            strong.animate([{transform: 'scale(1.5)'}, {transform: 'scale(1)'}], {duration: 360, easing: SPRING});
        }
        this.lastScore = value;
    }

    /**
     * Starts the timer (count-down if a time limit is set, else count-up).
     */
    startTimer() {
        window.clearInterval(this.timerHandle);
        if (this.mode === 'study' || this.review) {
            return;
        }
        const limit = this.data.timelimit || 0;
        const span = this.timerEl.querySelector('span');
        const tick = () => {
            const elapsed = (Date.now() - this.startedAt) / 1000;
            if (limit) {
                const left = limit - elapsed;
                span.textContent = clock(left);
                this.timerEl.classList.toggle('is-warning', left <= 30);
                this.timerEl.classList.toggle('is-danger', left <= 10);
                if (left <= 10 && left > 0) {
                    Sound.play('tick');
                }
                if (left <= 0) {
                    window.clearInterval(this.timerHandle);
                    this.timeUp();
                }
            } else {
                span.textContent = clock(elapsed);
            }
        };
        tick();
        this.timerHandle = window.setInterval(tick, 1000);
    }

    /**
     * Time limit reached: submit what we have and finish.
     */
    async timeUp() {
        this.say(S.timeup);
        const slide = this.slides[this.index];
        if (!slide.submitted) {
            const placements = Array.from(slide.slots.values()).map((s) => ({pin: s.token, label: s.chip || ''}));
            try {
                const res = await Ajax.call([{methodname: 'mod_labeldiagram_submit_slide', args: {
                    attemptid: this.attemptid, slideid: slide.data.id, placements}}])[0];
                slide.results = res.results;
                slide.submitted = true;
            } catch (e) {
                // Server may already consider the attempt overdue; finishing still grades it.
            }
        }
        this.finish(true);
    }

    /**
     * Updates the mute button.
     */
    syncMute() {
        const muted = Sound.isMuted();
        this.muteBtn.replaceChildren(svgIcon(muted ? 'muted' : 'sound'));
        this.muteBtn.setAttribute('aria-label', muted ? S.unmute : S.mute);
        this.muteBtn.setAttribute('title', muted ? S.unmute : S.mute);
        this.muteBtn.setAttribute('aria-pressed', muted ? 'true' : 'false');
    }

    /**
     * Toggles fullscreen (native where available, CSS fallback e.g. iPhone Safari).
     */
    toggleFullscreen() {
        const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
        if (fsEl || this.shell.classList.contains('is-pseudo-full')) {
            if (fsEl) {
                (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            }
            this.shell.classList.remove('is-pseudo-full');
            document.documentElement.classList.remove('ld-lock-scroll');
            this.syncFullscreen();
            return;
        }
        const req = this.shell.requestFullscreen || this.shell.webkitRequestFullscreen;
        if (req) {
            Promise.resolve(req.call(this.shell)).catch(() => this.pseudoFull());
        } else {
            this.pseudoFull();
        }
    }

    /**
     * CSS fullscreen fallback.
     */
    pseudoFull() {
        this.shell.classList.add('is-pseudo-full');
        document.documentElement.classList.add('ld-lock-scroll');
        this.syncFullscreen();
    }

    /**
     * Updates the fullscreen button and redraws.
     */
    syncFullscreen() {
        if (!this.fullBtn) {
            return;
        }
        const on = !!(document.fullscreenElement || document.webkitFullscreenElement) ||
            this.shell.classList.contains('is-pseudo-full');
        this.shell.classList.toggle('is-full', on);
        this.closeCard(true);
        this.fullBtn.replaceChildren(svgIcon(on ? 'unfull' : 'full'));
        this.fullBtn.setAttribute('aria-label', on ? S.exitfullscreen : S.fullscreen);
        this.fullBtn.setAttribute('title', on ? S.exitfullscreen : S.fullscreen);
        window.setTimeout(() => this.onResize(), 120);
    }

    /**
     * Global keyboard shortcuts.
     *
     * @param {KeyboardEvent} e
     */
    onKey(e) {
        if (!this.shell || this.host.hidden) {
            return;
        }
        if (e.key === 'Escape') {
            if (this.drag && this.drag.active) {
                this.drag.over = null;
                this.drag.overTray = false;
                this.pointerUp(new PointerEvent('pointerup'));
            } else if (this.card) {
                this.closeCard();
            } else if (this.selected) {
                this.clearSelection();
            } else if (this.shell.classList.contains('is-pseudo-full')) {
                this.toggleFullscreen();
            }
        }
        const tag = (e.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea') {
            return;
        }
        if ((this.mode === 'study' || this.review) && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) {
            this.goTo(this.index + (e.key === 'ArrowRight' ? 1 : -1));
        }
    }

    /* ------------------------------------------------------------------ */
    /* Finish, summary, review                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Finishes the attempt and shows the summary.
     */
    async finish() {
        if (this.finishing) {
            return;
        }
        this.finishing = true;
        window.clearInterval(this.timerHandle);
        try {
            await Promise.all(this.slides.map((s) => s.submitting).filter((p) => p && p.then));
            const summary = await Ajax.call([{methodname: 'mod_labeldiagram_finish_attempt',
                args: {attemptid: this.attemptid}}])[0];
            this.summary = summary;
            this.finishedSlides = this.slides.map((s) => ({data: s.data, results: s.results,
                purposes: s.purposes}));
            await this.showSummary(summary, true);
        } catch (err) {
            this.finishing = false;
            Notification.exception(err);
        }
    }

    /**
     * Headline for the results screen.
     *
     * @param {number} pct
     * @param {number} pass pass mark (0 = none)
     * @param {boolean} passed
     * @returns {string}
     */
    resultMessage(pct, pass, passed) {
        if (pass) {
            return passed ? S.passed : S.notpassed;
        }
        if (pct >= 90) {
            return S.excellent;
        }
        if (pct >= 70) {
            return S.greatjob;
        }
        return pct >= 50 ? S.goodeffort : S.keeppractising;
    }

    /**
     * Colour of the score ring.
     *
     * @param {number} pct
     * @param {number} pass
     * @param {boolean} passed
     * @returns {string}
     */
    ringColor(pct, pass, passed) {
        if (passed) {
            return 'var(--ld-success)';
        }
        return pct >= (pass || 70) * 0.7 ? 'var(--ld-warning)' : 'var(--ld-danger)';
    }

    /**
     * Renders the results screen.
     *
     * @param {object} summary
     * @param {boolean} fresh
     */
    async showSummary(summary, fresh = false) {
        this.review = false;
        this.closeCard(true);
        const pct = summary.percent;
        const pass = this.config.passpercent > 0 ? this.config.passpercent : 0;
        const passed = pass ? pct >= pass : pct >= 70;
        const msg = this.resultMessage(pct, pass, passed);
        const stats = [{icon: icon('clock'), label: S.time, value: clock(summary.duration)}];
        if (summary.mode === 'test') {
            stats.push({icon: icon('trophy'), label: S.attemptsleft,
                value: summary.attemptsleft < 0 ? '∞' : String(summary.attemptsleft)});
        }
        const ring = 2 * Math.PI * 52;
        const context = {
            ring,
            verdict: pass ? {pass: passed, icon: icon(passed ? 'check' : 'repeat'),
                text: passed ? fmt(S.passmark, pass) : fmt(S.youneed, pass)} : false,
            title: msg,
            sub: fmt(S.correctof, {correct: summary.correct, total: summary.total}),
            stats,
            leaderboard: summary.leaderboard && summary.leaderboard.length ? {
                icon: icon('trophy'),
                heading: S.leaderboard,
                rows: summary.leaderboard.map((r) => ({
                    rank: r.rank,
                    name: r.me ? `${r.name} (${S.you})` : r.name,
                    me: !!r.me,
                    percent: r.percent,
                    time: clock(r.duration),
                })),
            } : false,
            review: {label: S.reviewanswers, icon: icon('eye')},
            again: {label: S.tryagain, icon: icon('reset')},
            againdisabled: summary.mode === 'test' && summary.attemptsleft === 0,
            back: {label: S.backtomenu, icon: icon('next')},
        };
        const {html, js} = await Templates.renderForPromise('mod_labeldiagram/player_summary', context);
        const shell = el('div', `ld-shell ld-summary-shell ld-mode-${summary.mode}`);
        Templates.appendNodeContents(shell, html, js);
        const card = shell.querySelector('.ld-summary');
        shell.querySelector('[data-action="review"]').addEventListener('click', () => this.startReview());
        shell.querySelector('[data-action="again"]').addEventListener('click', () => {
            this.finishing = false;
            if (summary.mode === 'test') {
                this.config.attemptsleft = summary.attemptsleft;
            }
            this.showIntro(summary.mode).catch(Notification.exception);
        });
        shell.querySelector('[data-action="back"]').addEventListener('click', () => this.exit(true));
        this.host.replaceChildren();
        this.host.appendChild(shell);
        this.shell = shell;
        this.slides = null;

        const fg = card.querySelector('.ld-ring-fg');
        const count = card.querySelector('[data-count]');
        fg.style.stroke = this.ringColor(pct, pass, passed);
        requestAnimationFrame(() => {
            fg.style.transition = REDUCED ? 'none' : `stroke-dashoffset 1400ms ${EASE}`;
            fg.style.strokeDashoffset = `${ring * (1 - pct / 100)}`;
        });
        if (REDUCED) {
            count.textContent = Math.round(pct);
        } else {
            const t0 = performance.now();
            const step = (now) => {
                const k = Math.min(1, (now - t0) / 1400);
                count.textContent = Math.round(pct * (1 - Math.pow(1 - k, 3)));
                if (k < 1) {
                    requestAnimationFrame(step);
                }
            };
            requestAnimationFrame(step);
        }
        card.classList.add(passed ? 'is-pass' : 'is-fail');
        if (fresh) {
            if (passed) {
                Sound.play('finish');
                window.setTimeout(() => confetti(shell, 200), 450);
                window.setTimeout(() => confetti(shell, 120), 1300);
            } else {
                Sound.play('fail');
            }
        }
        this.say(`${msg} ${fmt(S.correctof, {correct: summary.correct, total: summary.total})}`);
        shell.setAttribute('tabindex', '-1');
        shell.focus({preventScroll: true});
    }

    /**
     * Review mode: shows each slide with the learner's answers marked and the correct answers.
     */
    startReview() {
        const src = this.finishedSlides || [];
        this.review = true;
        this.data = {slides: src.map((s) => s.data), timelimit: 0};
        const mode = this.mode;
        this.build();
        this.slides.forEach((slide, i) => {
            const results = src[i].results || [];
            slide.tray.hidden = true;
            const byPin = new Map(results.map((r) => [r.pin, r]));
            slide.slots.forEach((slot) => {
                const r = byPin.get(slot.token);
                slot.locked = true;
                const answerChip = r ? slide.chips.get(r.answer) : null;
                const placedChip = r && r.placed ? slide.chips.get(r.placed) : null;
                const shown = placedChip || answerChip;
                if (shown) {
                    const c = el('div', 'ld-chip ld-chip-static', {formatted: shown.text});
                    paint(c, shown.color);
                    slot.body.appendChild(c);
                    slot.color = shown.color;
                    slot.label = answerChip ? answerChip.text : '';
                    slot.zone.classList.add('is-filled');
                    slot.pinEl.classList.add('is-filled');
                    paint(slot.pinEl, shown.color);
                }
                const correct = r && (r.correct || (mode === 'practice' && r.placed === r.answer));
                slot.zone.classList.add(correct ? 'is-correct' : 'is-incorrect');
                slot.pinEl.classList.add(correct ? 'is-correct' : 'is-incorrect');
                const mark = el('span', `ld-check${correct ? '' : ' ld-cross'}`, {children: [svgIcon(correct ? 'check' : 'cross')],
                    'aria-hidden': 'true'});
                slot.zone.appendChild(mark);
                if (!correct && answerChip) {
                    slot.body.appendChild(el('div', 'ld-correction', {children: [`${S.correctanswer}: `,
                        el('strong', '', {formatted: answerChip.text})]}));
                }
                slot.reviewPurpose = r && r.purpose ? r.purpose : null;
                slot.zone.setAttribute('aria-label', `${slot.number}. ${correct ? '✓' : '✗'} ${
                    answerChip ? this.plain(answerChip.text) : ''}`);
                slot.chip = null;
            });
            this.drawLines(slide);
        });
        this.renderActions();
        window.setTimeout(() => this.syncHeight(), 50);
    }

    /**
     * Returns to the mode chooser.
     *
     * @param {boolean} reload whether to reload (to refresh attempt counts)
     */
    exit(reload = false) {
        window.clearInterval(this.timerHandle);
        if (document.fullscreenElement) {
            document.exitFullscreen();
        }
        document.documentElement.classList.remove('ld-lock-scroll');
        if (reload || (this.mode === 'test' && this.attemptid)) {
            window.location.reload();
            return;
        }
        this.host.hidden = true;
        this.host.replaceChildren();
        this.home.hidden = false;
        this.slides = null;
        this.shell = null;
        this.review = false;
    }
}

/**
 * Entry point.
 *
 * @param {string} selector
 */
export const init = async(selector) => {
    const root = document.querySelector(selector);
    if (!root || root.dataset.ldInit) {
        return;
    }
    root.dataset.ldInit = '1';
    try {
        await loadStrings();
        const config = JSON.parse(root.dataset.config || '{}');
        new Player(root, config);
    } catch (err) {
        Notification.exception(err);
    }
};
