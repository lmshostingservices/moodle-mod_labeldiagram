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
 * Label colours that keep white text readable (WCAG 2.1 AA, 4.5:1).
 *
 * Each label has a bright colour used for pins, lines and borders. Where white text sits on that
 * colour (label chips, number badges, pin tags) a darker shade is used instead, calculated here so
 * that any colour a teacher picks stays readable.
 *
 * @module     mod_labeldiagram/colour
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const cache = new Map();

/**
 * Relative luminance of an sRGB colour.
 *
 * @param {number[]} rgb Red, green and blue from 0 to 255.
 * @returns {number}
 */
const luminance = (rgb) => {
    const [r, g, b] = rgb.map((v) => {
        const c = v / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

/**
 * Returns a shade of the colour that gives white text at least 4.6:1 contrast.
 *
 * @param {string} hex Colour as #RRGGBB.
 * @returns {string} Colour as #RRGGBB (unchanged when it is already dark enough).
 */
export const textSafe = (hex) => {
    const key = String(hex || '').toLowerCase();
    if (cache.has(key)) {
        return cache.get(key);
    }
    const match = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/.exec(key);
    if (!match) {
        return hex;
    }
    const base = match.slice(1).map((v) => parseInt(v, 16));
    const shade = (factor) => base.map((v) => Math.round(v * factor));
    let factor = 1;
    let rgb = base;
    while (factor > 0.2 && 1.05 / (luminance(rgb) + 0.05) < 4.6) {
        factor -= 0.02;
        rgb = shade(factor);
    }
    const out = '#' + rgb.map((v) => v.toString(16).padStart(2, '0')).join('');
    cache.set(key, out);
    return out;
};

/**
 * Applies a label colour to an element: --c for pins and lines, --c-bg behind white text.
 *
 * @param {HTMLElement} node
 * @param {string} hex
 */
export const paint = (node, hex) => {
    node.style.setProperty('--c', hex);
    node.style.setProperty('--c-bg', textSafe(hex));
};
