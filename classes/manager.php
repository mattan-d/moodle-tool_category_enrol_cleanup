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
 * Queues and performs category enrolment cleanup.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_category_enrol_cleanup;

use tool_category_enrol_cleanup\task\cleanup_task;

defined('MOODLE_INTERNAL') || die();

/**
 * Removes enrolments and role assignments from courses in a category.
 *
 * Work is stored in tool_catenclean_run / tool_catenclean_item and advanced in short slices
 * so a single adhoc invocation cannot hold cron for the whole category.
 */
class manager {

    /** @var string Run is waiting for cron. */
    public const STATUS_QUEUED = 'queued';

    /** @var string Run is in progress. */
    public const STATUS_RUNNING = 'running';

    /** @var string Run finished. */
    public const STATUS_COMPLETED = 'completed';

    /** @var string Run stopped because of an error. */
    public const STATUS_FAILED = 'failed';

    /** @var string Course has not been cleaned yet. */
    public const COURSE_PENDING = 'pending';

    /** @var string Course cleanup finished. */
    public const COURSE_DONE = 'done';

    /** @var string Course cleanup stopped. */
    public const COURSE_FAILED = 'failed';

    /** @var int Enrolment rows handled before the clock is checked again. */
    public const USER_BATCH = 40;

    /** @var int Seconds one adhoc slice may run before it schedules the next slice. */
    public const MAX_RUNTIME = 15;

    /** @var int Courses listed on the confirmation page. */
    public const PREVIEW_COURSES = 30;

    /**
     * Counts courses, enrolments and roles that a cleanup would touch.
     *
     * @param int $categoryid
     * @param bool $includesubcats
     * @return \stdClass
     */
    public static function get_preview(int $categoryid, bool $includesubcats): \stdClass {
        global $DB;

        $category = self::require_category($categoryid);
        [$from, $params] = self::course_from_sql($categoryid, $includesubcats);

        $coursecount = $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $unenrolcount = $DB->count_records_sql(
            "SELECT COUNT(ue.id)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid IN (SELECT c.id $from)",
            $params
        );
        $usercount = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid IN (SELECT c.id $from)",
            $params
        );

        $roleparams = $params;
        $roleparams['courselevel'] = CONTEXT_COURSE;
        $childmatch = $DB->sql_concat('cctx.path', "'/%'");
        $rolecount = $DB->count_records_sql(
            "SELECT COUNT(ra.id)
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
               JOIN {context} cctx ON cctx.contextlevel = :courselevel
                AND (ctx.id = cctx.id OR ctx.path LIKE $childmatch)
              WHERE cctx.instanceid IN (SELECT c.id $from)",
            $roleparams
        );

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname $from ORDER BY c.sortorder, c.id",
            $params,
            0,
            self::PREVIEW_COURSES
        );

        return (object) [
            'categoryid' => $categoryid,
            'categoryname' => self::category_path_name($category),
            'includesubcats' => $includesubcats,
            'coursecount' => (int) $coursecount,
            'unenrolcount' => (int) $unenrolcount,
            'usercount' => (int) $usercount,
            'rolecount' => (int) $rolecount,
            'courses' => $courses,
        ];
    }

    /**
     * Stores a run and queues the first adhoc slice.
     *
     * @param int $categoryid
     * @param bool $includesubcats
     * @param int $userid
     * @return int Run id
     */
    public static function queue(int $categoryid, bool $includesubcats, int $userid): int {
        global $DB;

        $category = self::require_category($categoryid);
        if ($existing = self::get_active_run($categoryid)) {
            throw new \moodle_exception('alreadyrunning', 'tool_category_enrol_cleanup',
                new \moodle_url('/admin/tool/category_enrol_cleanup/report.php', ['id' => $existing->id]));
        }

        $now = time();
        $run = (object) [
            'categoryid' => $categoryid,
            'categoryname' => \core_text::substr(self::category_path_name($category), 0, 1333),
            'includesubcats' => $includesubcats ? 1 : 0,
            'userid' => $userid,
            'status' => self::STATUS_QUEUED,
            'snapshotted' => 0,
            'timecreated' => $now,
            'timestarted' => 0,
            'timefinished' => 0,
            'coursecount' => 0,
            'coursesdone' => 0,
            'unenrolcount' => 0,
            'rolecount' => 0,
            'errorcount' => 0,
        ];
        $runid = (int) $DB->insert_record('tool_catenclean_run', $run);

        cleanup_task::queue_for_run($runid, $userid);

        $event = \tool_category_enrol_cleanup\event\cleanup_queued::create([
            'context' => \context_coursecat::instance($categoryid),
            'objectid' => $runid,
            'other' => [
                'categoryid' => $categoryid,
                'includesubcats' => $includesubcats ? 1 : 0,
            ],
        ]);
        $event->trigger();

        return $runid;
    }

    /**
     * Advances one run until the time budget is spent or the run is finished.
     *
     * @param int $runid
     * @param int $maxruntime Seconds
     * @return bool True when the run is finished (completed or failed)
     */
    public static function process(int $runid, int $maxruntime = self::MAX_RUNTIME): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/enrollib.php');

        $run = self::get_run($runid);
        if ($run->status === self::STATUS_COMPLETED || $run->status === self::STATUS_FAILED) {
            return true;
        }

        $now = time();
        if ($run->status !== self::STATUS_RUNNING) {
            $DB->set_field('tool_catenclean_run', 'status', self::STATUS_RUNNING, ['id' => $run->id]);
            $run->status = self::STATUS_RUNNING;
        }
        if (empty($run->timestarted)) {
            $DB->set_field('tool_catenclean_run', 'timestarted', $now, ['id' => $run->id]);
            $run->timestarted = $now;
        }

        if (empty($run->snapshotted)) {
            if (!$DB->record_exists('course_categories', ['id' => $run->categoryid])) {
                self::mark_failed($runid, new \moodle_exception('invalidcategory', 'tool_category_enrol_cleanup'));
                return true;
            }
            self::snapshot_courses($run);
        }

        $started = time();
        while ((time() - $started) < $maxruntime) {
            $item = self::next_item((int) $run->id);
            if (!$item) {
                self::complete_run($run);
                return true;
            }
            $done = self::process_course($run, $item, $started, $maxruntime);
            self::refresh_totals($run);
            if (!$done) {
                return false;
            }
        }

        return false;
    }

    /**
     * Active queued or running cleanup for a category, if any.
     *
     * @param int $categoryid
     * @return \stdClass|null
     */
    public static function get_active_run(int $categoryid): ?\stdClass {
        global $DB;

        $runs = $DB->get_records_select(
            'tool_catenclean_run',
            'categoryid = :categoryid AND status IN (:queued, :running)',
            [
                'categoryid' => $categoryid,
                'queued' => self::STATUS_QUEUED,
                'running' => self::STATUS_RUNNING,
            ],
            'id DESC',
            '*',
            0,
            1
        );
        if (!$runs) {
            return null;
        }
        return reset($runs);
    }

    /**
     * Loads a run.
     *
     * @param int $runid
     * @return \stdClass
     */
    public static function get_run(int $runid): \stdClass {
        global $DB;
        return $DB->get_record('tool_catenclean_run', ['id' => $runid], '*', MUST_EXIST);
    }

    /**
     * Records a recoverable error without changing the run status.
     *
     * @param int $runid
     * @param \Throwable $e
     */
    public static function note_error(int $runid, \Throwable $e): void {
        global $DB;

        $run = $DB->get_record('tool_catenclean_run', ['id' => $runid]);
        if (!$run) {
            return;
        }
        $message = \core_text::substr($e->getMessage(), 0, 1000);
        $DB->set_field('tool_catenclean_run', 'lasterror', $message, ['id' => $runid]);
        $DB->set_field('tool_catenclean_run', 'errorcount', ((int) $run->errorcount) + 1, ['id' => $runid]);
    }

    /**
     * Marks a run as failed.
     *
     * @param int $runid
     * @param \Throwable $e
     */
    public static function mark_failed(int $runid, \Throwable $e): void {
        global $DB;

        $run = $DB->get_record('tool_catenclean_run', ['id' => $runid]);
        if (!$run || $run->status === self::STATUS_COMPLETED) {
            return;
        }
        self::note_error($runid, $e);
        $DB->set_field('tool_catenclean_run', 'status', self::STATUS_FAILED, ['id' => $runid]);
        $DB->set_field('tool_catenclean_run', 'timefinished', time(), ['id' => $runid]);
    }

    /**
     * Queues another slice when the previous task is gone.
     *
     * @param int $runid
     * @param int $userid
     */
    public static function resume(int $runid, int $userid): void {
        global $DB;

        $run = self::get_run($runid);
        if ($run->status === self::STATUS_COMPLETED || cleanup_task::is_queued($runid)) {
            return;
        }
        if ($run->status === self::STATUS_FAILED) {
            $DB->set_field('tool_catenclean_run', 'status', self::STATUS_RUNNING, ['id' => $runid]);
            $DB->set_field('tool_catenclean_run', 'timefinished', 0, ['id' => $runid]);
        }
        cleanup_task::queue_for_run($runid, $userid);
    }

    /**
     * Localised status label.
     *
     * @param string $status
     * @return string
     */
    public static function status_label(string $status): string {
        $key = 'status' . $status;
        if (get_string_manager()->string_exists($key, 'tool_category_enrol_cleanup')) {
            return get_string($key, 'tool_category_enrol_cleanup');
        }
        return $status;
    }

    /**
     * Formats a duration in minutes and seconds.
     *
     * @param int $seconds
     * @return string
     */
    public static function format_duration(int $seconds): string {
        if ($seconds < 0) {
            $seconds = 0;
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remain = $seconds % 60;
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remain);
        }
        return sprintf('%d:%02d', $minutes, $remain);
    }

    /**
     * @param int $categoryid
     * @return \core_course_category
     */
    private static function require_category(int $categoryid): \core_course_category {
        if ($categoryid <= 0) {
            throw new \moodle_exception('invalidcategory', 'tool_category_enrol_cleanup');
        }
        $category = \core_course_category::get($categoryid, MUST_EXIST, true);
        if (!$category || !$category->id) {
            throw new \moodle_exception('invalidcategory', 'tool_category_enrol_cleanup');
        }
        return $category;
    }

    /**
     * Plain "Parent / Child" path, stored so the report still makes sense if the category is renamed.
     *
     * @param \core_course_category $category
     * @return string
     */
    private static function category_path_name(\core_course_category $category): string {
        global $DB;

        $ids = array_merge($category->get_parents(), [$category->id]);
        $ids = array_filter($ids);
        if (!$ids) {
            return (string) $category->name;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $names = $DB->get_records_select_menu('course_categories', "id $insql", $params, '', 'id, name');
        $parts = [];
        foreach ($ids as $id) {
            if (isset($names[$id])) {
                $parts[] = $names[$id];
            }
        }
        return implode(' / ', $parts);
    }

    /**
     * FROM/WHERE fragment selecting courses. The course alias is c.
     *
     * @param int $categoryid
     * @param bool $includesubcats
     * @return array{0:string,1:array}
     */
    private static function course_from_sql(int $categoryid, bool $includesubcats): array {
        $params = [
            'siteid' => SITEID,
            'categoryid' => $categoryid,
        ];
        if ($includesubcats) {
            $context = \context_coursecat::instance($categoryid);
            $params['catlevel'] = CONTEXT_COURSECAT;
            $params['path'] = $context->path . '/%';
            $from = "FROM {course} c
                     JOIN {course_categories} cc ON cc.id = c.category
                     JOIN {context} catctx ON catctx.instanceid = cc.id AND catctx.contextlevel = :catlevel
                    WHERE c.id <> :siteid
                      AND (cc.id = :categoryid OR catctx.path LIKE :path)";
        } else {
            $from = "FROM {course} c
                    WHERE c.id <> :siteid
                      AND c.category = :categoryid";
        }
        return [$from, $params];
    }

    /**
     * Stores the course list once, so later category edits do not change the run.
     *
     * @param \stdClass $run
     */
    private static function snapshot_courses(\stdClass $run): void {
        global $DB;

        if (!empty($run->snapshotted)) {
            return;
        }

        [$from, $params] = self::course_from_sql((int) $run->categoryid, !empty($run->includesubcats));
        $ids = $DB->get_fieldset_sql("SELECT c.id $from ORDER BY c.sortorder, c.id", $params);

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('tool_catenclean_item', ['runid' => $run->id]);

        $now = time();
        foreach (array_chunk($ids, 200) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $courses = $DB->get_records_select('course', "id $insql", $inparams, '', 'id, fullname, shortname');
            $batch = [];
            foreach ($chunk as $courseid) {
                if (!isset($courses[$courseid])) {
                    continue;
                }
                $course = $courses[$courseid];
                $batch[] = (object) [
                    'runid' => $run->id,
                    'courseid' => $course->id,
                    'fullname' => \core_text::substr((string) $course->fullname, 0, 254),
                    'shortname' => \core_text::substr((string) $course->shortname, 0, 255),
                    'status' => self::COURSE_PENDING,
                    'unenrolcount' => 0,
                    'rolecount' => 0,
                    'timemodified' => $now,
                ];
            }
            if ($batch) {
                $DB->insert_records('tool_catenclean_item', $batch);
            }
        }

        $count = count($ids);
        $DB->set_field('tool_catenclean_run', 'coursecount', $count, ['id' => $run->id]);
        $DB->set_field('tool_catenclean_run', 'snapshotted', 1, ['id' => $run->id]);
        $transaction->allow_commit();

        $run->coursecount = $count;
        $run->snapshotted = 1;
    }

    /**
     * Next course that still needs work.
     *
     * @param int $runid
     * @return \stdClass|null
     */
    private static function next_item(int $runid): ?\stdClass {
        global $DB;

        $items = $DB->get_records_select(
            'tool_catenclean_item',
            'runid = :runid AND status = :pending',
            ['runid' => $runid, 'pending' => self::COURSE_PENDING],
            'id ASC',
            '*',
            0,
            1
        );
        if (!$items) {
            return null;
        }
        return reset($items);
    }

    /**
     * Unenrols a batch of users, then removes any roles that were not tied to an enrolment.
     *
     * @param \stdClass $run
     * @param \stdClass $item
     * @param int $startedat
     * @param int $maxruntime
     * @return bool True when this course is finished
     */
    private static function process_course(\stdClass $run, \stdClass $item, int $startedat, int $maxruntime): bool {
        global $DB;

        $course = $DB->get_record('course', ['id' => $item->courseid], 'id', IGNORE_MISSING);
        $context = $course ? \context_course::instance($item->courseid, IGNORE_MISSING) : false;
        if (!$course || !$context) {
            self::fail_item($run, $item, 'Course no longer exists');
            return true;
        }

        $plugins = [];
        $instances = [];

        while ((time() - $startedat) < $maxruntime) {
            $batch = $DB->get_records_sql(
                "SELECT ue.id, ue.userid, ue.enrolid, e.enrol
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                  WHERE e.courseid = :courseid
               ORDER BY ue.id",
                ['courseid' => $item->courseid],
                0,
                self::USER_BATCH
            );
            if (!$batch) {
                try {
                    self::remove_remaining_roles($item, $context);
                    $item->status = self::COURSE_DONE;
                } catch (\Throwable $e) {
                    $item->status = self::COURSE_FAILED;
                    $item->errormessage = \core_text::substr($e->getMessage(), 0, 500);
                    self::bump_run_error($run, $item->errormessage);
                }
                $item->timemodified = time();
                $DB->update_record('tool_catenclean_item', $item);
                return true;
            }

            foreach ($batch as $ue) {
                if ((time() - $startedat) >= $maxruntime) {
                    $item->timemodified = time();
                    $DB->update_record('tool_catenclean_item', $item);
                    return false;
                }
                self::unenrol_one($run, $item, $ue, $context, $plugins, $instances);
            }
        }

        $item->timemodified = time();
        $DB->update_record('tool_catenclean_item', $item);
        return false;
    }

    /**
     * Unenrols one user through the enrolment plugin, with a direct delete if the plugin fails.
     *
     * @param \stdClass $run
     * @param \stdClass $item
     * @param \stdClass $ue
     * @param \context $context
     * @param array $plugins
     * @param array $instances
     */
    private static function unenrol_one(
        \stdClass $run,
        \stdClass $item,
        \stdClass $ue,
        \context $context,
        array &$plugins,
        array &$instances
    ): void {
        global $DB;

        $before = $DB->count_records('role_assignments', [
            'userid' => $ue->userid,
            'contextid' => $context->id,
        ]);

        $enrol = (string) $ue->enrol;
        if (!array_key_exists($enrol, $plugins)) {
            $plugins[$enrol] = enrol_get_plugin($enrol);
        }
        if (!array_key_exists($ue->enrolid, $instances)) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', ['id' => $ue->enrolid]);
        }

        try {
            if ($plugins[$enrol] && $instances[$ue->enrolid]) {
                $plugins[$enrol]->unenrol_user($instances[$ue->enrolid], $ue->userid);
            } else {
                self::force_remove_enrolment($ue, $context);
            }
        } catch (\Throwable $e) {
            try {
                self::force_remove_enrolment($ue, $context);
            } catch (\Throwable $forceerror) {
                $DB->delete_records('user_enrolments', ['id' => $ue->id]);
                $e = $forceerror;
            }
            $message = \core_text::substr($e->getMessage(), 0, 500);
            $item->errormessage = $message;
            self::bump_run_error($run, $message);
        }

        if ($DB->record_exists('user_enrolments', ['id' => $ue->id])) {
            // The plugin returned without deleting the row. Remove it so the slice cannot loop on it.
            try {
                self::force_remove_enrolment($ue, $context);
            } catch (\Throwable $e) {
                $DB->delete_records('user_enrolments', ['id' => $ue->id]);
                $message = \core_text::substr($e->getMessage(), 0, 500);
                $item->errormessage = $message;
                self::bump_run_error($run, $message);
            }
        }

        $after = $DB->count_records('role_assignments', [
            'userid' => $ue->userid,
            'contextid' => $context->id,
        ]);
        $item->unenrolcount = (int) $item->unenrolcount + 1;
        $item->rolecount = (int) $item->rolecount + max(0, $before - $after);
        $item->timemodified = time();
        $DB->update_record('tool_catenclean_item', $item);
    }

    /**
     * Deletes one enrolment and the course roles that belong with a final enrolment.
     *
     * @param \stdClass $ue
     * @param \context $context
     */
    private static function force_remove_enrolment(\stdClass $ue, \context $context): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $userid = (int) $ue->userid;
        $courseid = (int) $context->instanceid;
        if (!empty($ue->enrol) && !empty($ue->enrolid)) {
            role_unassign_all([
                'userid' => $userid,
                'contextid' => $context->id,
                'component' => 'enrol_' . $ue->enrol,
                'itemid' => $ue->enrolid,
            ]);
        }
        $DB->delete_records('user_enrolments', ['id' => $ue->id]);

        $sql = "SELECT 1
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid AND e.courseid = :courseid";
        if (!$DB->record_exists_sql($sql, ['userid' => $userid, 'courseid' => $courseid])) {
            role_unassign_all(['userid' => $userid, 'contextid' => $context->id]);
            groups_delete_group_members($courseid, $userid);
            grade_user_unenrol($courseid, $userid);
            $DB->delete_records('user_lastaccess', ['userid' => $userid, 'courseid' => $courseid]);
        }
        mark_user_dirty($userid);
    }

    /**
     * Removes role assignments left in the course and its activities.
     *
     * @param \stdClass $item
     * @param \context $context
     */
    private static function remove_remaining_roles(\stdClass $item, \context $context): void {
        global $DB;

        $count = $DB->count_records_sql(
            "SELECT COUNT(ra.id)
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ctx.id = :contextid OR ctx.path LIKE :path",
            [
                'contextid' => $context->id,
                'path' => $context->path . '/%',
            ]
        );
        if ($count > 0) {
            role_unassign_all(['contextid' => $context->id], true);
            $item->rolecount = (int) $item->rolecount + (int) $count;
        }
    }

    /**
     * @param \stdClass $run
     * @param \stdClass $item
     * @param string $message
     */
    private static function fail_item(\stdClass $run, \stdClass $item, string $message): void {
        global $DB;

        $item->status = self::COURSE_FAILED;
        $item->errormessage = \core_text::substr($message, 0, 500);
        $item->timemodified = time();
        $DB->update_record('tool_catenclean_item', $item);
        self::bump_run_error($run, $item->errormessage);
    }

    /**
     * @param \stdClass $run
     * @param string $message
     */
    private static function bump_run_error(\stdClass $run, string $message): void {
        global $DB;

        $run->errorcount = (int) $run->errorcount + 1;
        $run->lasterror = \core_text::substr($message, 0, 1000);
        $DB->set_field('tool_catenclean_run', 'errorcount', $run->errorcount, ['id' => $run->id]);
        $DB->set_field('tool_catenclean_run', 'lasterror', $run->lasterror, ['id' => $run->id]);
    }

    /**
     * Copies per-course counters onto the run.
     *
     * @param \stdClass $run
     */
    private static function refresh_totals(\stdClass $run): void {
        global $DB;

        $totals = $DB->get_record_sql(
            "SELECT COALESCE(SUM(unenrolcount), 0) AS unenrolcount,
                    COALESCE(SUM(rolecount), 0) AS rolecount,
                    COALESCE(SUM(CASE WHEN status = :done OR status = :failed THEN 1 ELSE 0 END), 0) AS coursesdone
               FROM {tool_catenclean_item}
              WHERE runid = :runid",
            [
                'runid' => $run->id,
                'done' => self::COURSE_DONE,
                'failed' => self::COURSE_FAILED,
            ]
        );
        $run->unenrolcount = (int) $totals->unenrolcount;
        $run->rolecount = (int) $totals->rolecount;
        $run->coursesdone = (int) $totals->coursesdone;
        $DB->set_field('tool_catenclean_run', 'unenrolcount', $run->unenrolcount, ['id' => $run->id]);
        $DB->set_field('tool_catenclean_run', 'rolecount', $run->rolecount, ['id' => $run->id]);
        $DB->set_field('tool_catenclean_run', 'coursesdone', $run->coursesdone, ['id' => $run->id]);
    }

    /**
     * Marks the run complete and logs it.
     *
     * @param \stdClass $run
     */
    private static function complete_run(\stdClass $run): void {
        global $DB;

        self::refresh_totals($run);
        $finished = time();
        $DB->set_field('tool_catenclean_run', 'status', self::STATUS_COMPLETED, ['id' => $run->id]);
        $DB->set_field('tool_catenclean_run', 'timefinished', $finished, ['id' => $run->id]);
        $run->status = self::STATUS_COMPLETED;
        $run->timefinished = $finished;

        $context = \context_coursecat::instance($run->categoryid, IGNORE_MISSING);
        if (!$context) {
            $context = \context_system::instance();
        }
        $event = \tool_category_enrol_cleanup\event\cleanup_completed::create([
            'context' => $context,
            'objectid' => $run->id,
            'other' => [
                'coursecount' => (int) $run->coursecount,
                'unenrolcount' => (int) $run->unenrolcount,
                'rolecount' => (int) $run->rolecount,
            ],
        ]);
        $event->trigger();
    }
}
