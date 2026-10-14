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
 * Category selection form.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_category_enrol_cleanup\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Lets an admin pick a category from the site category tree.
 */
class cleanup_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $categories = \core_course_category::make_categories_list();
        if (!$categories) {
            $mform->addElement('static', 'nocategories', '', get_string('nocategories', 'tool_category_enrol_cleanup'));
            return;
        }

        $mform->addElement(
            'autocomplete',
            'categoryid',
            get_string('category', 'tool_category_enrol_cleanup'),
            $categories,
            ['noselectionstring' => get_string('selectcategory', 'tool_category_enrol_cleanup')]
        );
        $mform->addRule('categoryid', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('categoryid', 'category', 'tool_category_enrol_cleanup');
        $mform->setType('categoryid', PARAM_INT);

        $mform->addElement(
            'advcheckbox',
            'includesubcats',
            get_string('includesubcats', 'tool_category_enrol_cleanup')
        );
        $mform->addHelpButton('includesubcats', 'includesubcats', 'tool_category_enrol_cleanup');
        $mform->setDefault('includesubcats', 1);
        $mform->setType('includesubcats', PARAM_BOOL);

        $this->add_action_buttons(false, get_string('preview', 'tool_category_enrol_cleanup'));
    }

    /**
     * Validate the selected category.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $categoryid = (int)($data['categoryid'] ?? 0);
        if ($categoryid <= 0 || !\core_course_category::get($categoryid, IGNORE_MISSING, true)) {
            $errors['categoryid'] = get_string('invalidcategory', 'tool_category_enrol_cleanup');
        }
        return $errors;
    }
}
