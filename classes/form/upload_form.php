<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spreadsheet upload form.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_category_enrol_cleanup\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Upload an Excel file of courses and lecturers.
 */
class upload_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'excelfile', get_string('excelfile', 'tool_category_enrol_cleanup'), null, [
            'maxbytes' => 0,
            'accepted_types' => ['.xlsx', '.xls', '.csv'],
        ]);
        $mform->addRule('excelfile', get_string('required'), 'required', null, 'client');
        $this->add_action_buttons(true, get_string('uploadexcel', 'tool_category_enrol_cleanup'));
    }
}
