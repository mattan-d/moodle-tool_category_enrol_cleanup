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
 * English strings for category enrolment cleanup.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Category enrolment cleanup';
$string['privacy:metadata:tool_catenclean_run'] = 'Stores who queued a category enrolment cleanup and the resulting counts.';
$string['privacy:metadata:tool_catenclean_run:categoryname'] = 'The category path at the time the cleanup was queued.';
$string['privacy:metadata:tool_catenclean_run:timecreated'] = 'When the cleanup was queued.';
$string['privacy:metadata:tool_catenclean_run:userid'] = 'The user who queued the cleanup.';
$string['category'] = 'Category';
$string['category_help'] = 'Choose a category from the site category tree. The cleanup applies to courses in that category.';
$string['selectcategory'] = 'Choose a category';
$string['includesubcats'] = 'Include subcategories';
$string['includesubcats_help'] = 'When enabled, courses in every subcategory are cleaned as well. When disabled, only courses placed directly in the selected category are cleaned.';
$string['description'] = 'Choose a category from the category tree. The tool then removes every enrolment and every role assignment from the courses in that scope, so the courses are left with no enrolments. The work runs as an adhoc task and does not block the site.';
$string['preview'] = 'Show data before cleanup';
$string['warning'] = 'This permanently removes every enrolment and every role assignment from the courses in the selected scope. User accounts are not deleted. Automatic enrolment methods (such as cohort or meta) can enrol people again on their next sync.';
$string['courses'] = 'Courses';
$string['enrolments'] = 'Enrolments';
$string['distinctusers'] = 'Distinct users';
$string['roles'] = 'Role assignments';
$string['scope'] = 'Scope';
$string['scopewithsubcats'] = '{$a} (including subcategories)';
$string['scopewithoutsubcats'] = '{$a} (this category only)';
$string['nocourses'] = 'There are no courses in the selected scope.';
$string['nocategories'] = 'There are no categories to choose from.';
$string['morecourses'] = 'and {$a} more courses';
$string['understand'] = 'I understand that enrolments and roles will be removed and that this cannot be undone';
$string['mustunderstand'] = 'Confirm that you understand the cleanup cannot be undone.';
$string['startcleanup'] = 'Start background cleanup';
$string['queued'] = 'The cleanup was queued as an adhoc task. It will run via cron in short slices and will not block the site.';
$string['alreadyrunning'] = 'A cleanup for this category is already queued or running.';
$string['invalidcategory'] = 'The category is not valid.';
$string['runs'] = 'Cleanup runs';
$string['noruns'] = 'No cleanups have been run yet.';
$string['report'] = 'Cleanup report';
$string['requestedby'] = 'Requested by';
$string['started'] = 'Started';
$string['finished'] = 'Finished';
$string['duration'] = 'Duration';
$string['progress'] = 'Progress';
$string['errors'] = 'Errors';
$string['lasterror'] = 'Last error';
$string['status'] = 'Status';
$string['statusqueued'] = 'Queued';
$string['statusrunning'] = 'Running';
$string['statuscompleted'] = 'Completed';
$string['statusfailed'] = 'Failed';
$string['statuspending'] = 'Pending';
$string['statusdone'] = 'Done';
$string['course'] = 'Course';
$string['shortname'] = 'Short name';
$string['unenrolcount'] = 'Enrolments removed';
$string['rolecount'] = 'Roles removed';
$string['waitingcron'] = 'Waiting for cron to run the next slice.';
$string['refreshing'] = 'This page refreshes automatically while the cleanup is running.';
$string['resume'] = 'Resume cleanup';
$string['deleteduser'] = 'Deleted user';
$string['taskname'] = 'Category enrolment cleanup';
$string['eventcleanupqueued'] = 'Category enrolment cleanup queued';
$string['eventcleanupcompleted'] = 'Category enrolment cleanup completed';
$string['summary'] = 'Courses: {$a->courses}, enrolments removed: {$a->unenrolments}, roles removed: {$a->roles}';
$string['summaryrows'] = 'Rows: {$a->courses}, enrolments removed: {$a->unenrolments}, roles removed: {$a->roles}';
$string['currentcourse'] = 'Current course: {$a}';
$string['back'] = 'Back to category selection';
$string['categoryheading'] = 'Cleanup by category';
$string['uploadheading'] = 'Removal from an Excel file';
$string['uploadexcel'] = 'Remove enrolments from an Excel file';
$string['uploaddescription'] = 'Upload an Excel file. Each row is a course and a lecturer, identified by name or username. After upload, map the file columns to Moodle fields. The tool then removes that user\'s enrolment and role from the course. The work runs in the background.';
$string['excelfile'] = 'Excel file';
$string['mappinghelp'] = 'Choose what each column means in Moodle. Map one column to the course and one to the user. A role column is optional: when it is mapped, only that role is removed.';
$string['samplevalue'] = 'Example: {$a}';
$string['column'] = 'Column {$a}';
$string['mapignore'] = 'Ignore';
$string['mapcourseshortname'] = 'Course: short name';
$string['mapcourseidnumber'] = 'Course: ID number';
$string['mapcoursefullname'] = 'Course: full name';
$string['mapcourseid'] = 'Course: numeric id';
$string['mapusername'] = 'User: username';
$string['mapemail'] = 'User: email';
$string['mapuseridnumber'] = 'User: ID number';
$string['mapfullname'] = 'User: full name (lecturer name)';
$string['mapfirstname'] = 'User: first name';
$string['maplastname'] = 'User: last name';
$string['maproleshortname'] = 'Role: short name';
$string['maprolename'] = 'Role: role name';
$string['mapmissingcourse'] = 'Map one column to a course field.';
$string['mapmissinguser'] = 'Map a username, email, ID number, full name, or both first and last name.';
$string['mapduplicate'] = 'Each Moodle field can be mapped from only one column.';
$string['previewready'] = 'Rows that will be removed';
$string['previewfailed'] = 'Rows with an error';
$string['previewskipped'] = 'Rows with no matching enrolment';
$string['coursenotfound'] = 'Course not found';
$string['usernotfound'] = 'User not found';
$string['ambiguoususer'] = 'More than one user has this name';
$string['ambiguouscourse'] = 'More than one course has this name';
$string['norole'] = 'The user does not have this role in the course';
$string['notenrolled'] = 'The user has no enrolment or role in the course';
$string['rolenotfound'] = 'Role not found';
$string['invalidspreadsheet'] = 'The file could not be read. Save it as xlsx and try again.';
$string['emptyfile'] = 'The file has no data rows.';
$string['toomanyrows'] = 'The file has more than {$a} rows.';
$string['nofile'] = 'No file was selected.';
$string['invalidfiletype'] = 'Upload an xlsx, xls or csv file.';
$string['nothingtoqueue'] = 'No rows can be removed. Check the course and user mapping.';
$string['line'] = 'Line';
$string['user'] = 'User';
$string['result'] = 'Result';
$string['changemapping'] = 'Change mapping';
$string['startfilecleanup'] = 'Start background removal';
$string['filewarning'] = 'For each valid row, that user\'s enrolment and role are removed from the course. Other people in the course are left as they are.';
$string['statusskipped'] = 'Skipped';
$string['privacy:metadata:tool_catenclean_row'] = 'Stores the user who was removed from a course by a spreadsheet cleanup.';
$string['privacy:metadata:tool_catenclean_row:userid'] = 'The user whose enrolment was removed.';
$string['privacy:metadata:tool_catenclean_row:userlabel'] = 'The user value from the spreadsheet.';
$string['privacy:metadata:tool_catenclean_row:courselabel'] = 'The course value from the spreadsheet.';
