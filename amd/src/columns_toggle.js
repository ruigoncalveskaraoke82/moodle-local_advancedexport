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
 * "Select all / none" link for the question columns of the export form.
 *
 * Moodle's add_checkbox_controller() resets the group on every page load, which would discard
 * the remembered choices, so this small module toggles the boxes only when the link is clicked.
 *
 * @module     local_advancedexport/columns_toggle
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Attach the click handler.
 *
 * @param {string} linkId id of the toggle link
 * @param {string} boxClass class shared by the checkboxes to toggle
 */
export const init = (linkId, boxClass) => {
    const link = document.getElementById(linkId);
    if (!link) {
        return;
    }
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const boxes = document.querySelectorAll('input[type=checkbox].' + boxClass);
        const anyOff = Array.from(boxes).some((box) => !box.checked);
        boxes.forEach((box) => {
            box.checked = anyOff;
        });
    });
};
