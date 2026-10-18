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
 * Choose a category and review previous cleanup runs.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use tool_category_enrol_cleanup\form\cleanup_form;
use tool_category_enrol_cleanup\manager;

$baseurl = new moodle_url('/admin/tool/category_enrol_cleanup/index.php');
admin_externalpage_setup('toolcategoryenrolcleanup', '', null, $baseurl);

$PAGE->set_title(get_string('pluginname', 'tool_category_enrol_cleanup'));

$form = new cleanup_form($baseurl);
if ($data = $form->get_data()) {
    redirect(new moodle_url('/admin/tool/category_enrol_cleanup/confirm.php', [
        'categoryid' => $data->categoryid,
        'includesubcats' => empty($data->includesubcats) ? 0 : 1,
    ]));
}

$userfields = \core_user\fields::for_name()->get_sql('u', true, '', 'requesterid', true);
$sql = "SELECT r.* {$userfields->selects}
          FROM {tool_catenclean_run} r
     LEFT JOIN {user} u ON u.id = r.userid
               {$userfields->joins}
      ORDER BY r.id DESC";
$runs = $DB->get_records_sql($sql, $userfields->params, 0, 30);

$active = false;
foreach ($runs as $run) {
    if ($run->status === manager::STATUS_QUEUED || $run->status === manager::STATUS_RUNNING) {
        $active = true;
        break;
    }
}
if ($active) {
    $PAGE->set_periodic_refresh_delay(5);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_category_enrol_cleanup'));
echo $OUTPUT->notification(get_string('description', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_INFO);

echo $OUTPUT->heading(get_string('uploadheading', 'tool_category_enrol_cleanup'), 3);
echo $OUTPUT->notification(get_string('uploaddescription', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_INFO);
echo $OUTPUT->single_button(
    new moodle_url('/admin/tool/category_enrol_cleanup/upload.php'),
    get_string('uploadexcel', 'tool_category_enrol_cleanup')
);

echo $OUTPUT->heading(get_string('categoryheading', 'tool_category_enrol_cleanup'), 3);
$form->display();

echo $OUTPUT->heading(get_string('runs', 'tool_category_enrol_cleanup'), 3);
if ($active) {
    echo $OUTPUT->notification(get_string('refreshing', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_INFO);
}

if (!$runs) {
    echo $OUTPUT->notification(get_string('noruns', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('category', 'tool_category_enrol_cleanup'),
        get_string('status', 'tool_category_enrol_cleanup'),
        get_string('progress', 'tool_category_enrol_cleanup'),
        get_string('unenrolcount', 'tool_category_enrol_cleanup'),
        get_string('rolecount', 'tool_category_enrol_cleanup'),
        get_string('requestedby', 'tool_category_enrol_cleanup'),
        get_string('started', 'tool_category_enrol_cleanup'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($runs as $run) {
        $reporturl = new moodle_url('/admin/tool/category_enrol_cleanup/report.php', ['id' => $run->id]);
        $name = html_writer::link($reporturl, format_string($run->categoryname));
        $who = !empty($run->firstname) ? fullname($run) : get_string('deleteduser', 'tool_category_enrol_cleanup');
        $started = !empty($run->timestarted) ? userdate($run->timestarted) : '-';
        $table->data[] = [
            $name,
            manager::status_label($run->status),
            (int) $run->coursesdone . ' / ' . (int) $run->coursecount,
            (int) $run->unenrolcount,
            (int) $run->rolecount,
            s($who),
            $started,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
