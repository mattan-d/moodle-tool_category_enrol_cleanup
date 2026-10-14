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
 * Event fired when a category enrolment cleanup is queued.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_category_enrol_cleanup\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Cleanup queued event.
 */
class cleanup_queued extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'tool_catenclean_run';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the localised name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcleanupqueued', 'tool_category_enrol_cleanup');
    }

    /**
     * Returns a description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' queued enrolment cleanup {$this->objectid} " .
            "for category {$this->other['categoryid']}.";
    }

    /**
     * URL of the report.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/admin/tool/category_enrol_cleanup/report.php', ['id' => $this->objectid]);
    }

    /**
     * Custom validation.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['categoryid'])) {
            throw new \coding_exception('The categoryid value must be set in other.');
        }
    }

    /**
     * This object is not backed up.
     *
     * @return int
     */
    public static function get_objectid_mapping() {
        return \core\event\base::NOT_MAPPED;
    }
}
