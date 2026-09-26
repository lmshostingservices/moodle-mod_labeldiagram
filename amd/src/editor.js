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
 * Slide editor: type/paste all labels, then click the image to drop each one in sequence (click-to-place queue).
 * Drag pins to adjust, arrow keys to nudge, per-label "what it does" text with live card preview, autosave.
 *
 * @module     mod_labeldiagram/editor
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getStrings} from 'core/str';
import {paint} from 'mod_labeldiagram/colour';
import Templates from 'core/templates';

const KEYS = [
    'backtoslides', 'slidetitle', 'save', 'saved', 'saving', 'unsaved', 'quickadd', 'quickadd_help', 'startplacing',
    'placingnow', 'placingof', 'skip', 'undo', 'stop', 'labels', 'addlabel', 'deletelabel', 'distractor',
    'distractor_help', 'purpose', 'purpose_help', 'purposepreview', 'moveonimage', 'clicktoplace', 'color',
    'limitreached', 'instructionsfield', 'emptyeditor', 'whatitdoes', 'previewstudent', 'newlabel', 'bold', 'italic',
    'bullets', 'previousslide', 'nextslide', 'slideof', 'editorhelp', 'nopurposeyet',
    'aiprompt_slide', 'copyaiprompt', 'copied', 'aihelper_title', 'aihelper_steps', 'placeddirect',
    'quickadd_placeholder',
];

let S = {};

const el = (tag, cls = '', attrs = {}) => {
    const n = document.createElement(tag);
    if (cls) {
        n.className = cls;
    }
    Object.entries(attrs).forEach(([k, v]) => {
        if (k === 'text') {
            n.textContent = v;
        } else if (k === 'children') {
            n.append(...v.filter((c) => c !== null && c !== undefined && c !== ''));
        } else if (k === 'formatted') {
            // Trusted markup from a language string (for example a list of steps).
            n.innerHTML = v;
        } else if (v !== null && v !== undefined && v !== false) {
            n.setAttribute(k, v === true ? '' : v);
        }
    });
    return n;
};

const fmt = (str, a) => (typeof a === 'object' ? str.replace(/\{\$a->(\w+)\}/g, (m, k) => (a[k] ?? ''))
    : str.replace(/\{\$a\}/g, a));

const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
    "'": '&#39;'}[c]));

const ICON = {
    back: '<path d="M15 18l-6-6 6-6"/>',
    next: '<path d="M9 18l6-6-6-6"/>',
    save: '<path d="M5 3h11l3 3v15H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/>',
    list: '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
    pin: '<circle cx="12" cy="10" r="3"/><path d="M12 21s-7-6.2-7-11a7 7 0 0114 0c0 4.8-7 11-7 11z"/>',
    trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    spark: '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/>',
    copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2h2"/>',
    move: '<path d="M12 3v18M3 12h18M8 7l4-4 4 4M8 17l4 4 4-4M7 8l-4 4 4 4M17 8l4 4-4 4"/>',
};
const icon = (n) => `<svg viewBox="0 0 24 24" aria-hidden="true">${ICON[n]}</svg>`;

/**
 * Returns an inline SVG icon element built from the plugin's own static icon set.
 *
 * @param {string} n
 * @returns {Element}
 */
const svgIcon = (n) => {
    const doc = new DOMParser().parseFromString(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">${ICON[n]}</svg>`,
        'image/svg+xml'
    );
    return document.importNode(doc.documentElement, true);
};

/**
 * Returns an element containing text only.
 *
 * @param {string} tag
 * @param {string} text
 * @returns {HTMLElement}
 */
const textEl = (tag, text) => {
    const n = document.createElement(tag);
    n.textContent = text;
    return n;
};

/**
 * Minimal, safe Markdown → HTML for the live preview (server renders the real thing with format_text).
 *
 * @param {string} md
 * @returns {string}
 */
const markdown = (md) => {
    const inline = (t) => esc(t).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/(^|[^*])\*(?!\s)(.+?)\*/g,
        '$1<em>$2</em>');
    const blocks = String(md || '').trim().split(/\n\s*\n/);
    return blocks.map((b) => {
        const lines = b.split('\n');
        if (lines.every((l) => /^\s*[-*]\s+/.test(l))) {
            return `<ul>${lines.map((l) => `<li>${inline(l.replace(/^\s*[-*]\s+/, ''))}</li>`).join('')}</ul>`;
        }
        if (lines.every((l) => /^\s*\d+[.)]\s+/.test(l))) {
            return `<ol>${lines.map((l) => `<li>${inline(l.replace(/^\s*\d+[.)]\s+/, ''))}</li>`).join('')}</ol>`;
        }
        return `<p>${lines.map(inline).join('<br>')}</p>`;
    }).join('');
};

/**
 * Parses one Quick add line: "Label", "Label | What it does" or "Label | What it does | x | y".
 * Also accepts "Label – What it does" / "Label: What it does" as typed by people or AI assistants.
 *
 * @param {string} line
 * @returns {{label: string, purpose: string, x: number|null, y: number|null}}
 */
const parseLine = (line) => {
    let text = String(line).replace(/^\s*(\d+[.)]|[-*•])\s*/, '').trim();
    let parts;
    if (text.includes('|')) {
        parts = text.split('|').map((p) => p.trim());
    } else {
        const m = text.match(/^(.{1,80}?)\s+[—–-]\s+(.+)$/) || text.match(/^([^:]{1,80}):\s+(.+)$/);
        parts = m ? [m[1].trim(), m[2].trim()] : [text];
    }
    let x = null;
    let y = null;
    if (parts.length >= 3 && /^\d+(\.\d+)?$/.test(parts[parts.length - 1]) && /^\d+(\.\d+)?$/.test(parts[parts.length - 2])) {
        y = Math.max(0, Math.min(100, parseFloat(parts.pop())));
        x = Math.max(0, Math.min(100, parseFloat(parts.pop())));
    }
    const label = (parts.shift() || '').replace(/\*\*/g, '').trim();
    return {label, purpose: parts.join(' | ').trim(), x, y};
};

/**
 * Turns pasted AI JSON ({"labels":[…]}, {"slides":[{"labels":[…]}]} or […]) into Quick add lines.
 *
 * @param {string} raw
 * @returns {Array|null} list of {label, purpose, x, y, distractor} or null when not JSON
 */
const parseJson = (raw) => {
    const t = raw.trim().replace(/^```[a-z]*\s*/i, '').replace(/```\s*$/, '');
    if (!/^[[{]/.test(t)) {
        return null;
    }
    try {
        let data = JSON.parse(t);
        if (data.slides) {
            data = data.slides[0] || {};
        }
        const list = Array.isArray(data) ? data : (data.labels || []);
        return list.filter((i) => i && (i.label || i.name)).map((i) => ({
            label: String(i.label || i.name).trim(),
            purpose: String(i.purpose || i.description || '').trim(),
            x: Number.isFinite(parseFloat(i.x)) ? parseFloat(i.x) : null,
            y: Number.isFinite(parseFloat(i.y)) ? parseFloat(i.y) : null,
            distractor: i.distractor ? 1 : 0,
        }));
    } catch (e) {
        return null;
    }
};

/**
 * The editor.
 */
class Editor {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root
     * @param {object} cfg
     */
    constructor(root, cfg) {
        this.root = root;
        this.cfg = cfg;
        this.labels = cfg.labels.map((l) => ({...l, key: this.key()}));
        this.selected = null;
        this.queue = [];
        this.leftover = [];
        this.queueTotal = 0;
        this.dirty = false;
        this.saving = null;
        this.render();
        window.addEventListener('beforeunload', (e) => {
            if (this.dirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        document.addEventListener('keydown', (e) => this.onKey(e));
    }

    /**
     * Unique client key.
     *
     * @returns {string}
     */
    key() {
        this.seq = (this.seq || 0) + 1;
        return `k${this.seq}`;
    }

    /**
     * Pins (non-distractor labels) in player order (top-to-bottom).
     *
     * @returns {Array}
     */
    ordered() {
        return this.labels.filter((l) => !l.distractor).sort((a, b) => (a.y - b.y) || (a.x - b.x));
    }

    /**
     * Player number of a label.
     *
     * @param {object} label
     * @returns {number|string}
     */
    number(label) {
        if (label.distractor) {
            return '–';
        }
        return this.ordered().indexOf(label) + 1;
    }

    /**
     * Next palette colour.
     *
     * @returns {string}
     */
    nextColor() {
        const used = new Set(this.labels.map((l) => l.color));
        return this.cfg.palette.find((c) => !used.has(c)) || this.cfg.palette[this.labels.length % this.cfg.palette.length];
    }

    /**
     * Number of pins.
     *
     * @returns {number}
     */
    pinCount() {
        return this.labels.filter((l) => !l.distractor).length;
    }

    /**
     * Builds the static layout.
     */
    render() {
        const c = this.cfg;
        const r = this.root;
        r.replaceChildren();

        const main = el('div', 'ld-ed-main');
        const side = el('div', 'ld-ed-side');

        // Toolbar.
        const bar = el('div', 'ld-ed-toolbar');
        bar.appendChild(el('a', 'ld-iconbtn', {href: c.backurl, title: S.backtoslides, 'aria-label': S.backtoslides,
            children: [svgIcon('back')]}));
        this.titleInput = el('input', 'ld-ed-titleinput', {type: 'text', value: c.title, 'aria-label': S.slidetitle,
            maxlength: '255'});
        this.titleInput.addEventListener('input', () => this.touch());
        bar.appendChild(this.titleInput);
        bar.appendChild(el('span', 'ld-counter-pill', {text: fmt(S.slideof, {current: c.position, total: c.total})}));
        if (c.prevurl) {
            bar.appendChild(el('a', 'ld-iconbtn', {href: c.prevurl, title: S.previousslide, 'aria-label': S.previousslide,
                children: [svgIcon('back')]}));
        }
        if (c.nexturl) {
            bar.appendChild(el('a', 'ld-iconbtn', {href: c.nexturl, title: S.nextslide, 'aria-label': S.nextslide,
                children: [svgIcon('next')]}));
        }
        this.stateEl = el('span', 'ld-savestate is-saved', {text: S.saved, 'aria-live': 'polite'});
        bar.appendChild(this.stateEl);
        const save = el('button', 'ld-btn ld-btn-primary', {type: 'button', children: [svgIcon('save'), textEl('span', S.save)]});
        save.addEventListener('click', () => this.save());
        bar.appendChild(save);
        main.appendChild(bar);

        // Canvas.
        const canvas = el('div', 'ld-ed-canvas');
        this.banner = el('div', 'ld-queue-banner', {hidden: true, 'aria-live': 'polite'});
        canvas.appendChild(this.banner);
        const fig = el('div', 'ld-ed-figure');
        fig.style.setProperty('--ar', `${c.width} / ${c.height}`);
        fig.style.setProperty('--arn', c.width / c.height);
        fig.appendChild(el('img', 'ld-image', {src: c.image || '', alt: '', draggable: 'false'}));
        this.cursor = el('div', 'ld-ed-cursor');
        fig.appendChild(this.cursor);
        this.figure = fig;
        canvas.appendChild(fig);
        canvas.appendChild(el('p', 'ld-mini mt-2 mb-0', {text: S.editorhelp}));
        main.appendChild(canvas);

        fig.addEventListener('pointermove', (e) => {
            if (!this.placingLabel()) {
                return;
            }
            const p = this.pct(e);
            this.cursor.style.left = `${p.x}%`;
            this.cursor.style.top = `${p.y}%`;
        });
        fig.addEventListener('click', (e) => {
            if (e.target.closest('.ld-pin') || this.suppress) {
                return;
            }
            this.placeAt(this.pct(e));
        });

        // Instructions.
        const instr = el('div', 'ld-panel');
        instr.appendChild(el('label', 'ld-mini d-block mb-1', {text: S.instructionsfield, 'for': 'ld-instr'}));
        this.instrInput = el('input', 'ld-input', {type: 'text', id: 'ld-instr', value: c.instructions || '',
            maxlength: '255'});
        this.instrInput.addEventListener('input', () => this.touch());
        instr.appendChild(this.instrInput);
        main.appendChild(instr);

        // Quick add (queue).
        const qa = el('div', 'ld-panel');
        qa.appendChild(el('h4', '', {children: [svgIcon('list'), S.quickadd]}));
        qa.appendChild(el('p', 'ld-mini', {text: fmt(S.quickadd_help, c.maxpins)}));
        // AI helper: one prompt gives every label and its "what it does" text in the Quick add format.
        const ai = el('div', 'ld-aihelper');
        ai.appendChild(el('div', 'ld-aihelper-title', {children: [svgIcon('spark'), textEl('strong', S.aihelper_title)]}));
        ai.appendChild(el('ol', 'ld-aihelper-steps', {formatted: S.aihelper_steps}));
        const promptText = fmt(S.aiprompt_slide, Math.max(1, c.maxpins - this.pinCount()));
        const copy = el('button', 'ld-btn ld-btn-ghost ld-btn-sm w-100', {type: 'button',
            children: [svgIcon('copy'), textEl('span', S.copyaiprompt)]});
        copy.addEventListener('click', async() => {
            const span = copy.querySelector('span');
            try {
                await navigator.clipboard.writeText(promptText);
            } catch (e) {
                const tmp = el('textarea', '', {});
                tmp.value = promptText;
                document.body.appendChild(tmp);
                tmp.select();
                document.execCommand('copy');
                tmp.remove();
            }
            span.textContent = S.copied;
            copy.classList.add('is-done');
            window.setTimeout(() => {
                span.textContent = S.copyaiprompt;
                copy.classList.remove('is-done');
            }, 2000);
        });
        ai.appendChild(copy);
        qa.appendChild(ai);
        this.queueInput = el('textarea', 'ld-textarea', {rows: '7', placeholder: S.quickadd_placeholder});
        qa.appendChild(this.queueInput);
        const qbtn = el('button', 'ld-btn ld-btn-primary mt-2 w-100', {type: 'button',
            children: [svgIcon('pin'), textEl('span', S.startplacing)]});
        qbtn.addEventListener('click', () => this.startQueue());
        qa.appendChild(qbtn);
        side.appendChild(qa);

        // Label list.
        const lp = el('div', 'ld-panel');
        const lh = el('h4', '', {children: [svgIcon('pin'), S.labels]});
        this.countPill = el('span', 'ld-counter-pill ms-auto');
        lh.appendChild(this.countPill);
        lp.appendChild(lh);
        this.list = el('div', 'ld-labellist');
        lp.appendChild(this.list);
        const add = el('button', 'ld-btn ld-btn-ghost mt-2 w-100', {type: 'button',
            children: [svgIcon('plus'), textEl('span', S.addlabel)]});
        add.addEventListener('click', () => {
            if (this.queueActive && this.queue.length) {
                this.queue.push(S.newlabel);
                this.queueTotal++;
                this.updateBanner();
                return;
            }
            this.queueInput.value = S.newlabel;
            this.startQueue();
        });
        lp.appendChild(add);
        side.appendChild(lp);

        const prev = el('a', 'ld-btn ld-btn-ghost', {href: c.previewurl,
            children: [svgIcon('eye'), textEl('span', S.previewstudent)]});
        side.appendChild(prev);

        r.append(main, side);
        this.drawPins();
        this.drawList();
    }

    /**
     * Pointer position as percentages of the figure.
     *
     * @param {PointerEvent|MouseEvent} e
     * @returns {{x: number, y: number}}
     */
    pct(e) {
        const r = this.figure.getBoundingClientRect();
        const x = Math.max(0, Math.min(100, ((e.clientX - r.left) / r.width) * 100));
        const y = Math.max(0, Math.min(100, ((e.clientY - r.top) / r.height) * 100));
        return {x: Math.round(x * 100) / 100, y: Math.round(y * 100) / 100};
    }

    /**
     * Current label being placed (queue head or a re-place target).
     *
     * @returns {string|object|null}
     */
    placingLabel() {
        return this.moveTarget || this.queue[0] || null;
    }

    /**
     * Starts the click-to-place queue from the textarea.
     */
    startQueue() {
        const raw = this.queueInput.value;
        let items = parseJson(raw);
        if (!items) {
            items = raw.split(/\r?\n/).map(parseLine).filter((i) => i.label);
        }
        if (!items.length) {
            this.queueInput.focus();
            return;
        }
        // Distractors and lines that already carry a position are added straight away.
        let room = this.cfg.maxpins - this.pinCount();
        const toQueue = [];
        const overflow = [];
        let direct = 0;
        items.forEach((item) => {
            if (item.distractor) {
                if (this.labels.filter((l) => l.distractor).length < this.cfg.maxdistractors) {
                    this.labels.push({id: 0, key: this.key(), label: item.label, x: 0, y: 0, color: this.nextColor(),
                        purpose: item.purpose, distractor: 1});
                    direct++;
                }
                return;
            }
            if (room <= 0) {
                overflow.push(item);
                return;
            }
            room--;
            if (item.x !== null && item.y !== null) {
                this.labels.push({id: 0, key: this.key(), label: item.label, x: item.x, y: item.y,
                    color: this.nextColor(), purpose: item.purpose, distractor: 0});
                direct++;
            } else {
                toQueue.push(item);
            }
        });
        const asLine = (i) => (i.purpose ? `${i.label} | ${i.purpose}` : i.label);
        this.queue = toQueue.map(asLine);
        this.leftover = overflow.map(asLine);
        this.queueActive = true;
        this.queueTotal = this.queue.length;
        this.queueDone = [];
        if (direct) {
            this.touch();
            this.drawPins();
            this.drawList();
        }
        if (overflow.length) {
            Notification.alert('', fmt(S.limitreached, this.cfg.maxpins));
        }
        this.updateBanner();
        this.figure.scrollIntoView({behavior: 'smooth', block: 'center'});
    }

    /**
     * Refreshes the queue banner.
     */
    updateBanner() {
        const target = this.placingLabel();
        this.figure.classList.toggle('is-placing', !!target);
        // While placing, the Quick add box mirrors what is still to be placed, so nothing pasted is lost (e.g. after Stop).
        if (this.queueActive) {
            this.queueInput.value = [...this.queue, ...this.leftover].join('\n');
            this.queueInput.readOnly = this.queue.length > 0;
            if (!this.queue.length) {
                this.queueActive = false;
            }
        }
        if (!target) {
            this.banner.hidden = true;
            return;
        }
        this.banner.hidden = false;
        this.banner.replaceChildren();
        const text = typeof target === 'string' ? parseLine(target).label : target.label;
        const info = el('div', 'ld-qlabel');
        info.appendChild(el('span', '', {text: S.clicktoplace}));
        info.appendChild(el('strong', '', {text}));
        if (!this.moveTarget) {
            info.appendChild(el('span', '', {text: fmt(S.placingof, {current: this.queueTotal - this.queue.length + 1,
                total: this.queueTotal})}));
        }
        this.banner.appendChild(info);
        if (!this.moveTarget) {
            // The whole pasted list stays visible: placed (ticked), current (highlighted), still to place (tap to pick).
            const steps = el('ol', 'ld-qsteps', {'aria-label': S.quickadd});
            this.queueDone.forEach((label) => {
                steps.appendChild(el('li', 'ld-qstep is-done', {text: `✓ ${label.label}`}));
            });
            this.queue.forEach((line, i) => {
                const text = parseLine(line).label;
                const li = el('li', `ld-qstep${i === 0 ? ' is-current' : ''}`);
                if (i === 0) {
                    li.textContent = text;
                    li.setAttribute('aria-current', 'step');
                } else {
                    const pick = el('button', '', {type: 'button', text});
                    pick.addEventListener('click', () => {
                        this.queue.splice(i, 1);
                        this.queue.unshift(line);
                        this.updateBanner();
                    });
                    li.appendChild(pick);
                }
                steps.appendChild(li);
            });
            this.banner.appendChild(steps);
            const skip = el('button', 'ld-btn ld-btn-ghost ld-btn-sm', {type: 'button', text: S.skip});
            skip.addEventListener('click', () => {
                this.queue.push(this.queue.shift());
                this.updateBanner();
            });
            const undo = el('button', 'ld-btn ld-btn-ghost ld-btn-sm', {type: 'button', text: S.undo});
            undo.disabled = !this.queueDone.length;
            undo.addEventListener('click', () => {
                const last = this.queueDone.pop();
                if (last) {
                    this.labels = this.labels.filter((l) => l !== last);
                    this.queue.unshift(last.line || last.label);
                    this.touch();
                    this.drawPins();
                    this.drawList();
                    this.updateBanner();
                }
            });
            this.banner.append(skip, undo);
        }
        const stop = el('button', 'ld-btn ld-btn-ghost ld-btn-sm', {type: 'button', text: S.stop});
        stop.addEventListener('click', () => {
            this.leftover = [...this.queue, ...this.leftover];
            this.queue = [];
            this.moveTarget = null;
            this.updateBanner();
        });
        this.banner.appendChild(stop);
    }

    /**
     * Handles a click on the image.
     *
     * @param {{x: number, y: number}} p
     */
    placeAt(p) {
        if (this.moveTarget) {
            this.moveTarget.x = p.x;
            this.moveTarget.y = p.y;
            const moved = this.moveTarget;
            this.moveTarget = null;
            this.touch();
            this.drawPins(moved);
            this.drawList();
            this.updateBanner();
            return;
        }
        if (!this.queue.length) {
            this.select(null);
            return;
        }
        const line = this.queue.shift();
        const item = parseLine(line);
        const label = {id: 0, key: this.key(), label: item.label, x: p.x, y: p.y, color: this.nextColor(),
            purpose: item.purpose, distractor: 0, line};
        this.labels.push(label);
        this.queueDone.push(label);
        this.touch();
        this.drawPins(label);
        this.drawList();
        this.updateBanner();
        if (!this.queue.length) {
            this.select(label);
        }
    }

    /**
     * Renders the pins on the figure.
     *
     * @param {object|null} fresh newly placed label to animate
     */
    drawPins(fresh = null) {
        this.figure.querySelectorAll('.ld-pin').forEach((p) => p.remove());
        this.ordered().forEach((label, i) => {
            const pin = el('button', `ld-pin is-filled${label === this.selected ? ' is-sel' : ''}${
                label === fresh ? ' is-new' : ''}`, {type: 'button', text: String(i + 1), 'aria-label': label.label});
            pin.style.left = `${label.x}%`;
            pin.style.top = `${label.y}%`;
            paint(pin, label.color);
            const tag = el('span', 'ld-pin-tag', {text: label.label});
            paint(tag, label.color);
            pin.appendChild(tag);
            pin.addEventListener('pointerdown', (e) => this.dragPin(e, label, pin));
            pin.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!this.suppress) {
                    this.select(label);
                }
            });
            this.figure.appendChild(pin);
        });
    }

    /**
     * Drag an existing pin.
     *
     * @param {PointerEvent} e
     * @param {object} label
     * @param {HTMLElement} pin
     */
    dragPin(e, label, pin) {
        if (e.button > 0) {
            return;
        }
        e.preventDefault();
        const sx = e.clientX;
        const sy = e.clientY;
        let moved = false;
        pin.setPointerCapture(e.pointerId);
        const move = (ev) => {
            if (!moved && Math.hypot(ev.clientX - sx, ev.clientY - sy) < 3) {
                return;
            }
            moved = true;
            const p = this.pct(ev);
            label.x = p.x;
            label.y = p.y;
            pin.style.left = `${p.x}%`;
            pin.style.top = `${p.y}%`;
        };
        const up = () => {
            pin.removeEventListener('pointermove', move);
            pin.removeEventListener('pointerup', up);
            pin.removeEventListener('pointercancel', up);
            if (moved) {
                this.suppress = true;
                window.setTimeout(() => {
                    this.suppress = false;
                }, 60);
                this.touch();
                this.selected = label;
                this.drawPins();
                this.drawList();
            }
        };
        pin.addEventListener('pointermove', move);
        pin.addEventListener('pointerup', up);
        pin.addEventListener('pointercancel', up);
    }

    /**
     * Selects a label.
     *
     * @param {object|null} label
     */
    select(label) {
        this.selected = label;
        this.drawPins();
        this.drawList();
        if (label) {
            const row = this.list.querySelector(`[data-key="${label.key}"]`);
            if (row) {
                row.scrollIntoView({block: 'nearest', behavior: 'smooth'});
            }
        }
    }

    /**
     * Renders the label list.
     */
    drawList() {
        const pins = this.pinCount();
        this.countPill.textContent = `${pins}/${this.cfg.maxpins}`;
        this.countPill.classList.toggle('is-full', pins >= this.cfg.maxpins);
        this.list.replaceChildren();
        if (!this.labels.length) {
            this.list.appendChild(el('p', 'ld-mini', {text: S.emptyeditor}));
            return;
        }
        const sorted = [...this.ordered(), ...this.labels.filter((l) => l.distractor)];
        sorted.forEach((label) => {
            const row = el('div', `ld-lrow${label === this.selected ? ' is-sel' : ''}`, {'data-key': label.key});
            paint(row, label.color);
            const head = el('div', 'ld-lrow-head');
            const num = el('span', `ld-lrow-num${label.distractor ? ' is-unplaced' : ''}`, {text: this.number(label)});
            const input = el('input', 'ld-input', {type: 'text', value: label.label, maxlength: '255',
                'aria-label': S.labels});
            input.addEventListener('focus', () => {
                if (this.selected !== label) {
                    this.selected = label;
                    this.drawPins();
                    this.list.querySelectorAll('.ld-lrow').forEach((r) => r.classList.toggle('is-sel',
                        r.dataset.key === label.key));
                }
            });
            input.addEventListener('input', () => {
                label.label = input.value;
                this.touch();
                const tag = this.figure.querySelector('.ld-pin.is-sel .ld-pin-tag');
                if (tag) {
                    tag.textContent = input.value;
                }
            });
            const sw = el('button', 'ld-swatch', {type: 'button', title: S.color, 'aria-label': S.color});
            sw.addEventListener('click', () => this.select(label));
            head.append(num, input, sw);
            row.appendChild(head);

            const body = el('div', 'ld-lrow-body');
            const pal = el('div', 'ld-palette');
            this.cfg.palette.forEach((col) => {
                const b = el('button', col === label.color ? 'is-on' : '', {type: 'button', 'aria-label': col});
                b.style.background = col;
                b.addEventListener('click', () => {
                    label.color = col;
                    this.touch();
                    this.drawPins();
                    this.drawList();
                });
                pal.appendChild(b);
            });
            body.appendChild(pal);

            const dl = el('label', 'ld-check-row');
            const dc = el('input', '', {type: 'checkbox'});
            dc.checked = !!label.distractor;
            dc.addEventListener('change', () => {
                if (!dc.checked && this.pinCount() >= this.cfg.maxpins) {
                    dc.checked = true;
                    Notification.alert('', fmt(S.limitreached, this.cfg.maxpins));
                    return;
                }
                label.distractor = dc.checked ? 1 : 0;
                this.touch();
                this.drawPins();
                this.drawList();
            });
            dl.append(dc, el('span', '', {text: S.distractor}));
            body.appendChild(dl);

            body.appendChild(el('label', 'ld-mini', {text: S.purpose}));
            const md = el('div', 'ld-mdbar');
            const ta = el('textarea', 'ld-textarea', {rows: '4', placeholder: S.purpose_help});
            ta.value = label.purpose || '';
            const wrap = (before, after, linePrefix) => {
                const s = ta.selectionStart;
                const e = ta.selectionEnd;
                const sel = ta.value.slice(s, e);
                const rep = linePrefix ? sel.split('\n').map((l) => linePrefix + l).join('\n') : before + sel + after;
                ta.setRangeText(rep, s, e, 'end');
                ta.dispatchEvent(new Event('input'));
                ta.focus();
            };
            [[textEl('strong', 'B'), S.bold, () => wrap('**', '**')], [textEl('em', 'I'), S.italic, () => wrap('*', '*')],
                ['•', S.bullets, () => wrap('', '', '- ')]].forEach(([t, title, fn]) => {
                const b = el('button', 'ld-mdbtn', {type: 'button', children: [el('span', 'ld-mdbtn-icon', {children: [t]}), title],
                    title});
                b.addEventListener('click', fn);
                md.appendChild(b);
            });
            body.append(md, ta);
            body.appendChild(el('span', 'ld-mini', {text: S.purposepreview}));
            const card = el('div', 'ld-preview-card');
            paint(card, label.color);
            let cardSeq = 0;
            const renderCard = async() => {
                const seq = ++cardSeq;
                // The label is escaped and markdown() escapes before adding its own tags.
                const {html, js} = await Templates.renderForPromise('mod_labeldiagram/player_card', {
                    num: this.number(label),
                    infoicon: icon('info'),
                    eyebrow: S.whatitdoes,
                    titlehtml: esc(label.label),
                    bodyhtml: label.purpose ? markdown(label.purpose) : '',
                    empty: S.nopurposeyet,
                    close: false,
                });
                if (seq === cardSeq) {
                    Templates.replaceNodeContents(card, html, js);
                }
            };
            ta.addEventListener('input', () => {
                label.purpose = ta.value;
                this.touch();
                renderCard().catch(Notification.exception);
            });
            renderCard().catch(Notification.exception);
            body.appendChild(card);

            const actions = el('div', 'd-flex gap-2 flex-wrap');
            if (!label.distractor) {
                const mv = el('button', 'ld-btn ld-btn-ghost ld-btn-sm', {type: 'button',
                    children: [svgIcon('move'), textEl('span', S.moveonimage)]});
                mv.addEventListener('click', () => {
                    this.leftover = [...this.queue, ...this.leftover];
                    this.queue = [];
                    this.moveTarget = label;
                    this.updateBanner();
                    this.figure.scrollIntoView({behavior: 'smooth', block: 'center'});
                });
                actions.appendChild(mv);
            }
            const del = el('button', 'ld-btn ld-btn-danger ld-btn-sm', {type: 'button',
                children: [svgIcon('trash'), textEl('span', S.deletelabel)]});
            del.addEventListener('click', () => this.remove(label));
            actions.appendChild(del);
            body.appendChild(actions);
            row.appendChild(body);
            this.list.appendChild(row);
        });
    }

    /**
     * Deletes a label.
     *
     * @param {object} label
     */
    remove(label) {
        this.labels = this.labels.filter((l) => l !== label);
        if (this.selected === label) {
            this.selected = null;
        }
        this.touch();
        this.drawPins();
        this.drawList();
    }

    /**
     * Keyboard shortcuts: Ctrl/Cmd+S save, arrows nudge, Delete removes, Esc stops placing.
     *
     * @param {KeyboardEvent} e
     */
    onKey(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            this.save();
            return;
        }
        if (e.key === 'Escape' && this.placingLabel()) {
            this.leftover = [...this.queue, ...this.leftover];
            this.queue = [];
            this.moveTarget = null;
            this.updateBanner();
            return;
        }
        const tag = (e.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || !this.selected || this.selected.distractor) {
            return;
        }
        const step = e.shiftKey ? 2 : 0.5;
        const d = {ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step]}[e.key];
        if (d) {
            e.preventDefault();
            this.selected.x = Math.max(0, Math.min(100, this.selected.x + d[0]));
            this.selected.y = Math.max(0, Math.min(100, this.selected.y + d[1]));
            this.touch();
            this.drawPins();
            const pin = this.figure.querySelector('.ld-pin.is-sel');
            if (pin) {
                pin.focus();
            }
        } else if ((e.key === 'Delete' || e.key === 'Backspace') && tag !== 'button') {
            e.preventDefault();
            this.remove(this.selected);
        }
    }

    /**
     * Marks changes and schedules autosave.
     */
    touch() {
        this.dirty = true;
        this.stateEl.className = 'ld-savestate is-dirty';
        this.stateEl.textContent = S.unsaved;
        window.clearTimeout(this.autosave);
        this.autosave = window.setTimeout(() => this.save(), 1500);
    }

    /**
     * Saves via web service (serialised).
     *
     * @returns {Promise}
     */
    async save() {
        window.clearTimeout(this.autosave);
        if (this.saving) {
            this.again = true;
            return this.saving;
        }
        this.stateEl.className = 'ld-savestate';
        this.stateEl.textContent = S.saving;
        const sent = this.labels.filter((l) => l.label.trim() !== '');
        const payload = sent.map((l) => ({id: l.id || 0, label: l.label.trim(), x: l.x, y: l.y, color: l.color,
            purpose: l.purpose || '', distractor: l.distractor ? 1 : 0}));
        this.dirty = false;
        this.saving = Promise.resolve(Ajax.call([{methodname: 'mod_labeldiagram_save_slide', args: {
            slideid: this.cfg.slideid, title: this.titleInput.value, instructions: this.instrInput.value, labels: payload,
        }}])[0]).then((res) => {
            res.ids.forEach((id, i) => {
                if (sent[i]) {
                    sent[i].id = id;
                }
            });
            if (!this.dirty) {
                this.stateEl.className = 'ld-savestate is-saved';
                this.stateEl.textContent = S.saved;
            }
            return res;
        }).catch((err) => {
            this.dirty = true;
            this.stateEl.className = 'ld-savestate is-dirty';
            this.stateEl.textContent = S.unsaved;
            Notification.exception(err);
        }).finally(() => {
            this.saving = null;
            if (this.again) {
                this.again = false;
                this.save();
            }
        });
        return this.saving;
    }
}

/**
 * Entry point.
 *
 * @param {string} selector
 */
export const init = async(selector) => {
    const root = document.querySelector(selector);
    if (!root) {
        return;
    }
    const values = await getStrings(KEYS.map((key) => ({key, component: 'mod_labeldiagram'})));
    KEYS.forEach((k, i) => {
        S[k] = values[i];
    });
    new Editor(root, JSON.parse(root.dataset.config));
};
