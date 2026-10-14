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
 * Adhoc task that cleans enrolments in short slices.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_category_enrol_cleanup\task;

use tool_category_enrol_cleanup\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Background cleanup. Each run stops after a few seconds and queues the next slice.
 */
class cleanup_task extends \core\task\adhoc_task {

    /**
     * Name shown in the scheduled task UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskname', 'tool_category_enrol_cleanup');
    }

    /**
     * Only one cleanup slice runs at a time, so a large category cannot saturate cron workers.
     *
     * @return int
     */
    protected function get_default_concurrency_limit(): int {
        return 1;
    }

    /**
     * Process one slice of a cleanup run.
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $runid = (int)($data->runid ?? 0);
        if ($runid <= 0) {
            mtrace('Category enrolment cleanup is missing a run id.');
            return;
        }

        mtrace('Category enrolment cleanup run ' . $runid);

        try {
            $finished = manager::process($runid, manager::MAX_RUNTIME);
        } catch (\Throwable $e) {
            mtrace('Category enrolment cleanup failed: ' . $e->getMessage());
            if ($this->get_attempts_available() <= 1) {
                manager::mark_failed($runid, $e);
                return;
            }
            manager::note_error($runid, $e);
            throw $e;
        }

        $run = $DB->get_record('tool_catenclean_run', ['id' => $runid]);
        if ($run) {
            mtrace("status={$run->status} courses={$run->coursesdone}/{$run->coursecount} " .
                "unenrolments={$run->unenrolcount} roles={$run->rolecount}");
        }

        if (!$finished) {
            mtrace('Time limit reached, queueing the next slice.');
            self::queue_for_run($runid, (int)$this->get_userid());
        }
    }

    /**
     * Queue a slice for a run.
     *
     * @param int $runid
     * @param int $userid User the task runs as. Zero runs as the cron user.
     */
    public static function queue_for_run(int $runid, int $userid): void {
        $task = new self();
        $task->set_component('tool_category_enrol_cleanup');
        $task->set_custom_data(['runid' => $runid]);
        if ($userid > 0) {
            $task->set_userid($userid);
        }
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Whether a slice for this run is already waiting or running.
     *
     * @param int $runid
     * @return bool
     */
    public static function is_queued(int $runid): bool {
        global $DB;

        $classname = \core\task\manager::get_canonical_class_name(self::class);
        return $DB->record_exists('task_adhoc', [
            'classname' => $classname,
            'customdata' => json_encode(['runid' => $runid]),
        ]);
    }
}
