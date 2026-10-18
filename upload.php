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
 * Upload a spreadsheet, map its columns, and queue enrolment removal.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use tool_category_enrol_cleanup\form\mapping_form;
use tool_category_enrol_cleanup\form\upload_form;
use tool_category_enrol_cleanup\importer;
use tool_category_enrol_cleanup\manager;

$importid = optional_param('importid', 0, PARAM_INT);
$stage = optional_param('stage', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/admin/tool/category_enrol_cleanup/upload.php', $importid ? ['importid' => $importid] : []);
admin_externalpage_setup('toolcategoryenrolcleanupupload', '', null, $url);
$PAGE->set_title(get_string('uploadexcel', 'tool_category_enrol_cleanup'));

$indexurl = new moodle_url('/admin/tool/category_enrol_cleanup/index.php');

if ($importid && $confirm) {
    require_sesskey();
    $import = importer::get_owned($importid, (int) $USER->id);
    $runid = importer::queue($import, (int) $USER->id);
    redirect(
        new moodle_url('/admin/tool/category_enrol_cleanup/report.php', ['id' => $runid]),
        get_string('queued', 'tool_category_enrol_cleanup'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('uploadexcel', 'tool_category_enrol_cleanup'));
echo $OUTPUT->notification(get_string('uploaddescription', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_INFO);

if (!$importid) {
    $form = new upload_form($url);
    if ($form->is_cancelled()) {
        redirect($indexurl);
    }
    if ($data = $form->get_data()) {
        $newid = importer::store_upload((int) $data->excelfile, (int) $USER->id);
        redirect(new moodle_url($url, ['importid' => $newid]));
    }
    $form->display();
} else {
    $import = importer::get_owned($importid, (int) $USER->id);
    $sheet = importer::sheet_preview($import);
    $formurl = new moodle_url($url, ['importid' => $importid]);
    $form = new mapping_form($formurl, [
        'importid' => $importid,
        'headers' => $sheet['headers'],
        'samples' => $sheet['samples'],
    ]);
    if ($form->is_cancelled()) {
        importer::delete_import($importid, (int) $USER->id);
        redirect(new moodle_url('/admin/tool/category_enrol_cleanup/upload.php'));
    }
    if ($data = $form->get_data()) {
        $mapping = [];
        foreach ($sheet['headers'] as $index => $unused) {
            $key = 'map' . $index;
            $mapping[] = $data->{$key} ?? '';
        }
        importer::save_mapping($importid, $mapping);
        redirect(new moodle_url($formurl, ['stage' => 'preview']));
    }

    if ($stage === 'preview' && !empty($import->mapping)) {
        $mapping = json_decode($import->mapping, true) ?: [];
        $preview = importer::preview($import, $mapping);
        echo $OUTPUT->notification(get_string('filewarning', 'tool_category_enrol_cleanup'), \core\output\notification::NOTIFY_WARNING);
        $summary = new html_table();
        $summary->attributes['class'] = 'generaltable';
        $summary->data = [
            [get_string('excelfile', 'tool_category_enrol_cleanup'), s($import->filename)],
            [get_string('previewready', 'tool_category_enrol_cleanup'), (int) $preview->ready],
            [get_string('previewfailed', 'tool_category_enrol_cleanup'), (int) $preview->failed],
            [get_string('previewskipped', 'tool_category_enrol_cleanup'), (int) $preview->skipped],
        ];
        echo html_writer::table($summary);

        if ($preview->rows) {
            $table = new html_table();
            $table->head = [
                get_string('line', 'tool_category_enrol_cleanup'),
                get_string('course', 'tool_category_enrol_cleanup'),
                get_string('user', 'tool_category_enrol_cleanup'),
                get_string('status', 'tool_category_enrol_cleanup'),
                get_string('result', 'tool_category_enrol_cleanup'),
            ];
            $table->attributes['class'] = 'generaltable';
            foreach ($preview->rows as $row) {
                $table->data[] = [
                    (int) $row->line,
                    s($row->courselabel),
                    s($row->userlabel),
                    manager::status_label($row->status),
                    s($row->message),
                ];
            }
            echo html_writer::table($table);
            if ($preview->total > count($preview->rows)) {
                echo $OUTPUT->notification(get_string('morecourses', 'tool_category_enrol_cleanup',
                    $preview->total - count($preview->rows)), \core\output\notification::NOTIFY_INFO);
            }
        }

        echo html_writer::div(
            html_writer::link(new moodle_url($formurl), get_string('changemapping', 'tool_category_enrol_cleanup')),
            'mb-3'
        );
        if ($preview->ready > 0) {
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'class' => 'btn btn-danger',
                'value' => get_string('startfilecleanup', 'tool_category_enrol_cleanup'),
            ]);
            echo html_writer::end_tag('form');
        }
    } else {
        $form->display();
    }
}

echo $OUTPUT->footer();
