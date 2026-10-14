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
 * Progress and final counts for one cleanup run.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_category_enrol_cleanup\manager;
use tool_category_enrol_cleanup\task\cleanup_task;

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$resume = optional_param('resume', 0, PARAM_BOOL);
$perpage = 50;

$url = new moodle_url('/admin/tool/category_enrol_cleanup/report.php', ['id' => $id]);
admin_externalpage_setup('toolcategoryenrolcleanup', '', null, $url);
$PAGE->set_title(get_string('report', 'tool_category_enrol_cleanup'));

$run = manager::get_run($id);

if ($resume) {
    require_sesskey();
    manager::resume($id, (int) $USER->id);
    redirect($url, get_string('queued', 'tool_category_enrol_cleanup'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$busy = $run->status === manager::STATUS_QUEUED || $run->status === manager::STATUS_RUNNING;
if ($busy) {
    $PAGE->set_periodic_refresh_delay(5);
}

$user = $DB->get_record('user', ['id' => $run->userid]);
$who = $user ? fullname($user) : get_string('deleteduser', 'tool_category_enrol_cleanup');
$end = !empty($run->timefinished) ? (int) $run->timefinished : ($busy && !empty($run->timestarted) ? time() : 0);
$duration = !empty($run->timestarted) && $end ? manager::format_duration($end - (int) $run->timestarted) : '-';
$percent = (int) $run->coursecount > 0
    ? (int) round(((int) $run->coursesdone / (int) $run->coursecount) * 100)
    : ($run->status === manager::STATUS_COMPLETED ? 100 : 0);

$current = null;
if ($busy) {
    $pending = $DB->get_records_select(
        'tool_catenclean_item',
        'runid = :runid AND status = :pending',
        ['runid' => $run->id, 'pending' => manager::COURSE_PENDING],
        'id ASC',
        'id, fullname',
        0,
        1
    );
    $current = $pending ? reset($pending) : null;
}

$itemcount = $DB->count_records('tool_catenclean_item', ['runid' => $run->id]);
$items = $DB->get_records(
    'tool_catenclean_item',
    ['runid' => $run->id],
    'id ASC',
    '*',
    $page * $perpage,
    $perpage
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'tool_category_enrol_cleanup'));

$note = \core\output\notification::NOTIFY_INFO;
if ($run->status === manager::STATUS_COMPLETED) {
    $note = \core\output\notification::NOTIFY_SUCCESS;
} else if ($run->status === manager::STATUS_FAILED) {
    $note = \core\output\notification::NOTIFY_ERROR;
}
echo $OUTPUT->notification(
    get_string('summary', 'tool_category_enrol_cleanup', (object) [
        'courses' => (int) $run->coursesdone . ' / ' . (int) $run->coursecount,
        'unenrolments' => (int) $run->unenrolcount,
        'roles' => (int) $run->rolecount,
    ]),
    $note
);

echo html_writer::div(
    html_writer::div('', 'progress-bar', [
        'role' => 'progressbar',
        'style' => 'width: ' . $percent . '%',
        'aria-valuenow' => $percent,
        'aria-valuemin' => 0,
        'aria-valuemax' => 100,
    ]),
    'progress mb-3'
);

$summary = new html_table();
$summary->attributes['class'] = 'generaltable';
$summary->data = [
    [get_string('category', 'tool_category_enrol_cleanup'), format_string($run->categoryname)],
    [
        get_string('includesubcats', 'tool_category_enrol_cleanup'),
        !empty($run->includesubcats) ? get_string('yes') : get_string('no'),
    ],
    [get_string('status', 'tool_category_enrol_cleanup'), manager::status_label($run->status)],
    [get_string('progress', 'tool_category_enrol_cleanup'), (int) $run->coursesdone . ' / ' . (int) $run->coursecount],
    [get_string('unenrolcount', 'tool_category_enrol_cleanup'), (int) $run->unenrolcount],
    [get_string('rolecount', 'tool_category_enrol_cleanup'), (int) $run->rolecount],
    [get_string('errors', 'tool_category_enrol_cleanup'), (int) $run->errorcount],
    [get_string('requestedby', 'tool_category_enrol_cleanup'), s($who)],
    [get_string('started', 'tool_category_enrol_cleanup'), !empty($run->timestarted) ? userdate($run->timestarted) : '-'],
    [get_string('finished', 'tool_category_enrol_cleanup'), !empty($run->timefinished) ? userdate($run->timefinished) : '-'],
    [get_string('duration', 'tool_category_enrol_cleanup'), $duration],
];
echo html_writer::table($summary);

if (!empty($run->lasterror)) {
    echo $OUTPUT->notification(
        get_string('lasterror', 'tool_category_enrol_cleanup') . ': ' . s($run->lasterror),
        \core\output\notification::NOTIFY_WARNING
    );
}
if ($current) {
    echo $OUTPUT->notification(
        get_string('currentcourse', 'tool_category_enrol_cleanup', format_string($current->fullname)),
        \core\output\notification::NOTIFY_INFO
    );
}
if ($busy) {
    $queued = cleanup_task::is_queued((int) $run->id);
    if ($queued) {
        echo $OUTPUT->notification(get_string('waitingcron', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_INFO);
        echo $OUTPUT->notification(get_string('refreshing', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_INFO);
    }
}

if ($run->status !== manager::STATUS_COMPLETED && !cleanup_task::is_queued((int) $run->id)) {
    $resumeurl = new moodle_url($url, ['resume' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($resumeurl, get_string('resume', 'tool_category_enrol_cleanup'), 'post');
}

if ($items) {
    $table = new html_table();
    $table->head = [
        get_string('course', 'tool_category_enrol_cleanup'),
        get_string('shortname', 'tool_category_enrol_cleanup'),
        get_string('status', 'tool_category_enrol_cleanup'),
        get_string('unenrolcount', 'tool_category_enrol_cleanup'),
        get_string('rolecount', 'tool_category_enrol_cleanup'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($items as $item) {
        $courseurl = new moodle_url('/user/index.php', ['id' => $item->courseid]);
        $coursename = html_writer::link($courseurl, format_string($item->fullname));
        $table->data[] = [
            $coursename,
            s($item->shortname),
            manager::status_label($item->status),
            (int) $item->unenrolcount,
            (int) $item->rolecount,
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($itemcount, $page, $perpage, $url);
}

echo $OUTPUT->single_button(
    new moodle_url('/admin/tool/category_enrol_cleanup/index.php'),
    get_string('back', 'tool_category_enrol_cleanup')
);
echo $OUTPUT->footer();
