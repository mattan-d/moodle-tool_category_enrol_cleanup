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
 * Upgrade steps for category enrolment cleanup.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add spreadsheet import tables.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_category_enrol_cleanup_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026101800) {
        $table = new xmldb_table('tool_catenclean_run');
        $field = new xmldb_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'category', 'categoryid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $installxml = $CFG->dirroot . '/admin/tool/category_enrol_cleanup/db/install.xml';
        if (!$dbman->table_exists('tool_catenclean_row')) {
            $dbman->install_one_table_from_xmldb_file($installxml, 'tool_catenclean_row');
        }
        if (!$dbman->table_exists('tool_catenclean_import')) {
            $dbman->install_one_table_from_xmldb_file($installxml, 'tool_catenclean_import');
        }

        upgrade_plugin_savepoint(true, 2026101800, 'tool', 'category_enrol_cleanup');
    }

    if ($oldversion < 2026101801) {
        $table = new xmldb_table('tool_catenclean_run');
        $field = new xmldb_field('importid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026101801, 'tool', 'category_enrol_cleanup');
    }

    return true;
}
