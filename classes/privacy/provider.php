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
 * Privacy provider.
 *
 * The plugin stores the user who queued a cleanup. It does not store the users who were unenrolled.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_category_enrol_cleanup\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy API implementation.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe stored user data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_catenclean_run', [
            'userid' => 'privacy:metadata:tool_catenclean_run:userid',
            'categoryname' => 'privacy:metadata:tool_catenclean_run:categoryname',
            'timecreated' => 'privacy:metadata:tool_catenclean_run:timecreated',
        ], 'privacy:metadata:tool_catenclean_run');
        $collection->add_database_table('tool_catenclean_row', [
            'userid' => 'privacy:metadata:tool_catenclean_row:userid',
            'userlabel' => 'privacy:metadata:tool_catenclean_row:userlabel',
            'courselabel' => 'privacy:metadata:tool_catenclean_row:courselabel',
        ], 'privacy:metadata:tool_catenclean_row');
        return $collection;
    }

    /**
     * User contexts that contain cleanup requests made by this user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {tool_catenclean_run} r ON r.userid = ctx.instanceid
                 WHERE ctx.contextlevel = :userlevel
                   AND r.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'userlevel' => CONTEXT_USER,
            'userid' => $userid,
        ]);
        $rowsql = "SELECT ctx.id
                     FROM {context} ctx
                     JOIN {tool_catenclean_row} r ON r.userid = ctx.instanceid
                    WHERE ctx.contextlevel = :userlevel
                      AND r.userid = :userid";
        $contextlist->add_from_sql($rowsql, [
            'userlevel' => CONTEXT_USER,
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export cleanup requests.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_USER || (int) $context->instanceid !== (int) $userid) {
                continue;
            }
            $runs = $DB->get_records('tool_catenclean_run', ['userid' => $userid], 'id ASC');
            $rows = $DB->get_records('tool_catenclean_row', ['userid' => $userid], 'id ASC');
            if (!$runs && !$rows) {
                continue;
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'tool_category_enrol_cleanup')],
                (object) [
                    'runs' => array_values($runs ?: []),
                    'rows' => array_values($rows ?: []),
                ]
            );
        }
    }

    /**
     * Delete all plugin data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel != CONTEXT_USER) {
            return;
        }
        self::delete_user_runs((int) $context->instanceid);
    }

    /**
     * Delete one user's cleanup requests.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_USER && (int) $context->instanceid === (int) $userid) {
                self::delete_user_runs((int) $userid);
            }
        }
    }

    /**
     * Users who have cleanup requests in this context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_USER) {
            return;
        }
        $sql = "SELECT userid
                  FROM {tool_catenclean_run}
                 WHERE userid = :userid";
        $userlist->add_from_sql('userid', $sql, ['userid' => $context->instanceid]);
        $rowsql = "SELECT userid
                     FROM {tool_catenclean_row}
                    WHERE userid = :userid";
        $userlist->add_from_sql('userid', $rowsql, ['userid' => $context->instanceid]);
    }

    /**
     * Delete cleanup requests for the listed users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_USER) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            if ((int) $userid === (int) $context->instanceid) {
                self::delete_user_runs((int) $userid);
            }
        }
    }

    /**
     * Deletes runs queued by one user, and their course rows.
     *
     * @param int $userid
     */
    private static function delete_user_runs(int $userid): void {
        global $DB;

        $runids = $DB->get_fieldset_select('tool_catenclean_run', 'id', 'userid = ?', [$userid]);
        if ($runids) {
            [$insql, $params] = $DB->get_in_or_equal($runids);
            $DB->delete_records_select('tool_catenclean_item', "runid $insql", $params);
            $DB->delete_records_select('tool_catenclean_row', "runid $insql", $params);
        }
        $DB->delete_records('tool_catenclean_row', ['userid' => $userid]);
        $DB->delete_records('tool_catenclean_run', ['userid' => $userid]);
        $DB->delete_records('tool_catenclean_import', ['userid' => $userid]);
        $context = \context_user::instance($userid, IGNORE_MISSING);
        if ($context) {
            get_file_storage()->delete_area_files($context->id, 'tool_category_enrol_cleanup', 'import');
        }
    }
}
