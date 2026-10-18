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
 * Shows what will be removed and queues the adhoc cleanup after confirmation.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_category_enrol_cleanup\manager;

$categoryid = required_param('categoryid', PARAM_INT);
$includesubcats = optional_param('includesubcats', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/admin/tool/category_enrol_cleanup/confirm.php', [
    'categoryid' => $categoryid,
    'includesubcats' => $includesubcats ? 1 : 0,
]);
admin_externalpage_setup('toolcategoryenrolcleanup', '', null, $url);
$PAGE->set_title(get_string('pluginname', 'tool_category_enrol_cleanup'));

$returnurl = new moodle_url('/admin/tool/category_enrol_cleanup/index.php');

if ($confirm) {
    require_sesskey();
    $understand = optional_param('understand', 0, PARAM_BOOL);
    if ($understand) {
        if ($active = manager::get_active_run($categoryid)) {
            $reporturl = new moodle_url('/admin/tool/category_enrol_cleanup/report.php', ['id' => $active->id]);
            throw new moodle_exception('alreadyrunning', 'tool_category_enrol_cleanup', $reporturl);
        }
        $runid = manager::queue($categoryid, (bool) $includesubcats, (int) $USER->id);
        redirect(
            new moodle_url('/admin/tool/category_enrol_cleanup/report.php', ['id' => $runid]),
            get_string('queued', 'tool_category_enrol_cleanup'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    \core\notification::error(get_string('mustunderstand', 'tool_category_enrol_cleanup'));
}

$preview = manager::get_preview($categoryid, (bool) $includesubcats);

$catcontext = context_coursecat::instance($categoryid);
$categoryname = format_string($preview->categoryname, true, ['context' => $catcontext]);
$scope = $includesubcats
    ? get_string('scopewithsubcats', 'tool_category_enrol_cleanup', $categoryname)
    : get_string('scopewithoutsubcats', 'tool_category_enrol_cleanup', $categoryname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_category_enrol_cleanup'));
echo $OUTPUT->notification(get_string('warning', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_WARNING);

$summary = new html_table();
$summary->attributes['class'] = 'generaltable';
$summary->data = [
    [get_string('scope', 'tool_category_enrol_cleanup'), $scope],
    [get_string('courses', 'tool_category_enrol_cleanup'), (int) $preview->coursecount],
    [get_string('enrolments', 'tool_category_enrol_cleanup'), (int) $preview->unenrolcount],
    [get_string('distinctusers', 'tool_category_enrol_cleanup'), (int) $preview->usercount],
    [get_string('roles', 'tool_category_enrol_cleanup'), (int) $preview->rolecount],
];
echo html_writer::table($summary);

if ($preview->courses) {
    $courses = new html_table();
    $courses->head = [
        get_string('course', 'tool_category_enrol_cleanup'),
        get_string('shortname', 'tool_category_enrol_cleanup'),
    ];
    $courses->attributes['class'] = 'generaltable';
    foreach ($preview->courses as $course) {
        $coursecontext = context_course::instance($course->id, IGNORE_MISSING);
        $options = $coursecontext ? ['context' => $coursecontext] : null;
        $courses->data[] = [
            format_string($course->fullname, true, $options),
            format_string($course->shortname, true, $options),
        ];
    }
    echo html_writer::table($courses);
    $remaining = $preview->coursecount - count($preview->courses);
    if ($remaining > 0) {
        echo $OUTPUT->notification(get_string('morecourses', 'tool_category_enrol_cleanup', $remaining),
            \core\output\notification::NOTIFY_INFO);
    }
}

if ($preview->coursecount < 1) {
    echo $OUTPUT->notification(get_string('nocourses', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->single_button($returnurl, get_string('back', 'tool_category_enrol_cleanup'));
} else if ($active = manager::get_active_run($categoryid)) {
    $reporturl = new moodle_url('/admin/tool/category_enrol_cleanup/report.php', ['id' => $active->id]);
    echo $OUTPUT->notification(get_string('alreadyrunning', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->single_button($reporturl, get_string('report', 'tool_category_enrol_cleanup'));
} else {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'mt-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
    echo html_writer::div(
        html_writer::checkbox('understand', 1, false, get_string('understand', 'tool_category_enrol_cleanup'),
            ['required' => 'required']),
        'mb-3'
    );
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-danger',
        'value' => get_string('startcleanup', 'tool_category_enrol_cleanup'),
    ]);
    echo ' ';
    echo html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
