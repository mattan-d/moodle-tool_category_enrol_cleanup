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
 * Maps spreadsheet columns to Moodle fields.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_category_enrol_cleanup\form;

use tool_category_enrol_cleanup\importer;

defined('MOODLE_INTERNAL') || die();

/**
 * One dropdown per spreadsheet column.
 */
class mapping_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $headers = $this->_customdata['headers'];
        $samples = $this->_customdata['samples'];
        $options = importer::field_options();

        $mform->addElement('static', 'mappingintro', '', get_string('mappinghelp', 'tool_category_enrol_cleanup'));
        foreach ($headers as $index => $header) {
            $label = trim((string) $header);
            if ($label === '') {
                $label = get_string('column', 'tool_category_enrol_cleanup', $index + 1);
            }
            $mform->addElement('select', 'map' . $index, $label, $options);
            $mform->setDefault('map' . $index, importer::guess($header));
            $mform->setType('map' . $index, PARAM_ALPHANUMEXT);
            if (!empty($samples[$index])) {
                $mform->addElement('static', 'sample' . $index, '',
                    get_string('samplevalue', 'tool_category_enrol_cleanup', s($samples[$index])));
            }
        }

        $mform->addElement('hidden', 'importid', $this->_customdata['importid']);
        $mform->setType('importid', PARAM_INT);
        $this->add_action_buttons(true, get_string('preview', 'tool_category_enrol_cleanup'));
    }

    /**
     * Require a course column and a user column.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $chosen = [];
        foreach ($data as $key => $value) {
            if (strpos($key, 'map') !== 0 || $value === '') {
                continue;
            }
            if (isset($chosen[$value])) {
                $errors[$key] = get_string('mapduplicate', 'tool_category_enrol_cleanup');
            }
            $chosen[$value] = true;
        }

        $hascourse = false;
        foreach (importer::COURSE_FIELDS as $field) {
            if (!empty($chosen[$field])) {
                $hascourse = true;
            }
        }
        $hasuser = false;
        foreach (importer::USER_FIELDS as $field) {
            if (!empty($chosen[$field])) {
                $hasuser = true;
            }
        }
        if (!empty($chosen['firstname']) && !empty($chosen['lastname'])) {
            $hasuser = true;
        }
        if (!$hascourse) {
            $errors['map0'] = get_string('mapmissingcourse', 'tool_category_enrol_cleanup');
        } else if (!$hasuser) {
            $errors['map0'] = get_string('mapmissinguser', 'tool_category_enrol_cleanup');
        }
        return $errors;
    }
}
