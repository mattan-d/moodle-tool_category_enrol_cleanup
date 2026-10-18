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
 * Reads a spreadsheet and removes one user's enrolment from each course row.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_category_enrol_cleanup;

defined('MOODLE_INTERNAL') || die();

/**
 * Excel upload, column mapping, and row resolution.
 */
class importer {

    /** @var int Maximum data rows accepted from one file. */
    public const MAX_ROWS = 20000;

    /** @var string[] Course columns. Exactly one must be mapped. */
    public const COURSE_FIELDS = ['courseid', 'courseshortname', 'courseidnumber', 'coursefullname'];

    /** @var string[] User columns that identify a person on their own. */
    public const USER_FIELDS = ['username', 'email', 'useridnumber', 'fullname'];

    /**
     * Options for the mapping dropdown.
     *
     * @return array
     */
    public static function field_options(): array {
        $fields = [
            'ignore',
            'courseshortname',
            'courseidnumber',
            'coursefullname',
            'courseid',
            'username',
            'email',
            'useridnumber',
            'fullname',
            'firstname',
            'lastname',
            'roleshortname',
            'rolename',
        ];
        $options = [];
        foreach ($fields as $field) {
            $options[$field === 'ignore' ? '' : $field] = get_string('map' . $field, 'tool_category_enrol_cleanup');
        }
        return $options;
    }

    /**
     * Suggests a Moodle field from a column heading.
     *
     * @param string $header
     * @return string
     */
    public static function guess(string $header): string {
        $header = self::normalize($header);
        $map = [
            'courseshortname' => ['shortname', 'short name', 'שם קצר'],
            'courseidnumber' => ['course idnumber', 'מספר קורס', 'קוד קורס'],
            'courseid' => ['course id', 'courseid', 'מזהה קורס'],
            'coursefullname' => ['course fullname', 'course name', 'שם הקורס', 'שם קורס', 'קורס'],
            'username' => ['username', 'user name', 'שם משתמש', 'שם המשתמש'],
            'email' => ['email', 'e mail', 'אימייל', 'דואל', 'דואר אלקטרוני', 'מייל'],
            'useridnumber' => ['user idnumber', 'idnumber', 'מספר זהות', 'תעודת זהות', 'תז'],
            'roleshortname' => ['role shortname', 'קוד תפקיד'],
            'rolename' => ['role', 'role name', 'תפקיד'],
            'firstname' => ['firstname', 'first name', 'שם פרטי'],
            'lastname' => ['lastname', 'last name', 'שם משפחה'],
            'fullname' => ['fullname', 'full name', 'lecturer', 'teacher', 'מרצה', 'שם המרצה', 'שם מרצה', 'שם'],
        ];
        foreach ($map as $field => $needles) {
            foreach ($needles as $needle) {
                if ($header === self::normalize($needle)) {
                    return $field;
                }
            }
        }
        return '';
    }

    /**
     * Stores an uploaded spreadsheet for the current user.
     *
     * @param int $draftid Draft file area item id
     * @param int $userid
     * @return int Import id
     */
    public static function store_upload(int $draftid, int $userid): int {
        global $DB;

        $fs = get_file_storage();
        $context = \context_user::instance($userid);
        $drafts = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false);
        if (!$drafts) {
            throw new \moodle_exception('nofile', 'tool_category_enrol_cleanup');
        }
        $draft = reset($drafts);
        $filename = clean_param($draft->get_filename(), PARAM_FILE);
        $extension = \core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            throw new \moodle_exception('invalidfiletype', 'tool_category_enrol_cleanup');
        }

        $importid = $DB->insert_record('tool_catenclean_import', (object) [
            'userid' => $userid,
            'filename' => \core_text::substr($filename, 0, 255),
            'timecreated' => time(),
        ]);
        $stored = $fs->create_file_from_storedfile([
            'contextid' => $context->id,
            'component' => 'tool_category_enrol_cleanup',
            'filearea' => 'import',
            'itemid' => $importid,
            'filepath' => '/',
            'filename' => $filename,
        ], $draft);

        try {
            $parsed = self::read_stored_file($stored);
        } catch (\Throwable $e) {
            self::delete_import($importid, $userid);
            throw new \moodle_exception('invalidspreadsheet', 'tool_category_enrol_cleanup');
        }
        if (!$parsed['headers'] || !$parsed['rows']) {
            self::delete_import($importid, $userid);
            throw new \moodle_exception('emptyfile', 'tool_category_enrol_cleanup');
        }
        return (int) $importid;
    }

    /**
     * Loads an import owned by the user.
     *
     * @param int $importid
     * @param int $userid
     * @return \stdClass
     */
    public static function get_owned(int $importid, int $userid): \stdClass {
        global $DB;

        $import = $DB->get_record('tool_catenclean_import', ['id' => $importid, 'userid' => $userid], '*', MUST_EXIST);
        return $import;
    }

    /**
     * Reads headings and a few sample cells.
     *
     * @param \stdClass $import
     * @return array{headers: array, samples: array}
     */
    public static function sheet_preview(\stdClass $import): array {
        $parsed = self::read_import($import);
        $samples = $parsed['rows'] ? $parsed['rows'][0] : [];
        return [
            'headers' => $parsed['headers'],
            'samples' => $samples,
        ];
    }

    /**
     * Saves the column mapping.
     *
     * @param int $importid
     * @param array $mapping List of field keys aligned with columns
     */
    public static function save_mapping(int $importid, array $mapping): void {
        global $DB;

        $clean = [];
        $allowed = array_keys(self::field_options());
        foreach ($mapping as $field) {
            $field = (string) $field;
            $clean[] = in_array($field, $allowed, true) ? $field : '';
        }
        $DB->set_field('tool_catenclean_import', 'mapping', json_encode($clean), ['id' => $importid]);
    }

    /**
     * Resolves rows without changing enrolments.
     *
     * @param \stdClass $import
     * @param array $mapping
     * @return \stdClass
     */
    public static function preview(\stdClass $import, array $mapping): \stdClass {
        $parsed = self::read_import($import);
        return self::resolve_rows($parsed['rows'], $mapping, 30);
    }

    /**
     * Creates a background run from the mapped file.
     *
     * @param \stdClass $import
     * @param int $userid User who queued the run
     * @return int Run id
     */
    public static function queue(\stdClass $import, int $userid): int {
        global $DB;

        $mapping = json_decode($import->mapping ?? '', true);
        if (!is_array($mapping)) {
            throw new \moodle_exception('mapmissingcourse', 'tool_category_enrol_cleanup');
        }
        $parsed = self::read_import($import);
        $resolved = self::resolve_rows($parsed['rows'], $mapping, 0);
        if ($resolved->ready < 1) {
            throw new \moodle_exception('nothingtoqueue', 'tool_category_enrol_cleanup');
        }

        $now = time();
        $runid = $DB->insert_record('tool_catenclean_run', (object) [
            'categoryid' => 0,
            'source' => manager::SOURCE_FILE,
            'categoryname' => \core_text::substr($import->filename, 0, 1333),
            'includesubcats' => 0,
            'userid' => $userid,
            'status' => manager::STATUS_QUEUED,
            'snapshotted' => 1,
            'timecreated' => $now,
            'timestarted' => 0,
            'timefinished' => 0,
            'coursecount' => $resolved->total,
            'coursesdone' => $resolved->total - $resolved->ready,
            'unenrolcount' => 0,
            'rolecount' => 0,
            'errorcount' => $resolved->failed,
        ]);

        $batch = [];
        foreach ($resolved->all as $row) {
            $batch[] = (object) [
                'runid' => $runid,
                'linenum' => $row->line,
                'courseid' => (int) $row->courseid,
                'userid' => (int) $row->userid,
                'roleid' => (int) $row->roleid,
                'courselabel' => \core_text::substr($row->courselabel, 0, 255),
                'userlabel' => \core_text::substr($row->userlabel, 0, 255),
                'status' => $row->status,
                'unenrolcount' => 0,
                'rolecount' => 0,
                'message' => $row->message,
                'timemodified' => $now,
            ];
            if (count($batch) >= 200) {
                $DB->insert_records('tool_catenclean_row', $batch);
                $batch = [];
            }
        }
        if ($batch) {
            $DB->insert_records('tool_catenclean_row', $batch);
        }

        \tool_category_enrol_cleanup\task\cleanup_task::queue_for_run((int) $runid, $userid);
        self::delete_import((int) $import->id, $userid);

        $event = \tool_category_enrol_cleanup\event\cleanup_queued::create([
            'context' => \context_system::instance(),
            'objectid' => $runid,
            'other' => [
                'categoryid' => 0,
                'includesubcats' => 0,
            ],
        ]);
        $event->trigger();

        return (int) $runid;
    }

    /**
     * Deletes a stored upload and its file.
     *
     * @param int $importid
     * @param int $userid
     */
    public static function delete_import(int $importid, int $userid): void {
        global $DB;

        $context = \context_user::instance($userid, IGNORE_MISSING);
        if ($context) {
            get_file_storage()->delete_area_files($context->id, 'tool_category_enrol_cleanup', 'import', $importid);
        }
        $DB->delete_records('tool_catenclean_import', ['id' => $importid, 'userid' => $userid]);
    }

    /**
     * @param array $rows
     * @param array $mapping
     * @param int $samples Maximum resolved rows to keep for display. 0 keeps all, which queue() needs.
     * @return \stdClass
     */
    private static function resolve_rows(array $rows, array $mapping, int $samples): \stdClass {
        $ready = 0;
        $failed = 0;
        $skipped = 0;
        $shown = [];
        $all = [];
        $line = 1;
        foreach ($rows as $cells) {
            $line++;
            if (self::row_is_empty($cells)) {
                continue;
            }
            $resolved = self::resolve($cells, $mapping);
            $resolved->line = $line;
            if ($resolved->status === manager::COURSE_PENDING) {
                $ready++;
            } else if ($resolved->status === manager::COURSE_SKIPPED) {
                $skipped++;
            } else {
                $failed++;
            }
            if ($samples === 0 || count($shown) < $samples) {
                $shown[] = $resolved;
            }
            if ($samples === 0) {
                $all[] = $resolved;
            }
        }
        if ($samples === 0) {
            $shown = array_slice($all, 0, 30);
        }
        return (object) [
            'ready' => $ready,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => $ready + $failed + $skipped,
            'rows' => $shown,
            'all' => $all,
        ];
    }

    /**
     * Resolves one data row to a course and a user.
     *
     * @param array $cells
     * @param array $mapping
     * @return \stdClass
     */
    private static function resolve(array $cells, array $mapping): \stdClass {
        $values = [];
        foreach ($mapping as $index => $field) {
            if ($field === '' || $field === null) {
                continue;
            }
            $cell = trim((string) ($cells[$index] ?? ''));
            if ($cell === '') {
                continue;
            }
            $values[$field] = $cell;
        }

        $result = (object) [
            'line' => 0,
            'courseid' => 0,
            'userid' => 0,
            'roleid' => 0,
            'courselabel' => self::first_value($values, self::COURSE_FIELDS),
            'userlabel' => self::user_label($values),
            'status' => manager::COURSE_FAILED,
            'message' => '',
        ];

        $course = self::find_course($values);
        if (!empty($course->error)) {
            $result->message = $course->error;
            return $result;
        }
        $result->courseid = (int) $course->id;
        $result->courselabel = $course->label;

        $user = self::find_user($values);
        if (!empty($user->error)) {
            $result->message = $user->error;
            return $result;
        }
        $result->userid = (int) $user->id;
        $result->userlabel = $user->label;

        $roleid = 0;
        if (!empty($values['roleshortname']) || !empty($values['rolename'])) {
            $role = self::find_role($values['roleshortname'] ?? '', $values['rolename'] ?? '');
            if (!empty($role->error)) {
                $result->message = $role->error;
                return $result;
            }
            $roleid = (int) $role->id;
            $result->roleid = $roleid;
        }

        if (!self::user_has_membership($result->courseid, $result->userid, $roleid)) {
            $result->status = manager::COURSE_SKIPPED;
            $result->message = get_string($roleid ? 'norole' : 'notenrolled', 'tool_category_enrol_cleanup');
            return $result;
        }

        $result->status = manager::COURSE_PENDING;
        $result->message = '';
        return $result;
    }

    /**
     * @param array $values
     * @return \stdClass
     */
    private static function find_course(array $values): \stdClass {
        global $DB;

        if (!empty($values['courseid'])) {
            $course = $DB->get_record('course', ['id' => (int) $values['courseid']], 'id, fullname, shortname');
            if (!$course || (int) $course->id === (int) SITEID) {
                return (object) ['error' => get_string('coursenotfound', 'tool_category_enrol_cleanup')];
            }
            return (object) ['id' => $course->id, 'label' => $course->shortname];
        }
        if (!empty($values['courseshortname'])) {
            $course = $DB->get_record('course', ['shortname' => $values['courseshortname']], 'id, shortname');
            if (!$course || (int) $course->id === (int) SITEID) {
                return (object) ['error' => get_string('coursenotfound', 'tool_category_enrol_cleanup')];
            }
            return (object) ['id' => $course->id, 'label' => $course->shortname];
        }
        if (!empty($values['courseidnumber'])) {
            $courses = $DB->get_records('course', ['idnumber' => $values['courseidnumber']], '', 'id, shortname');
            $courses = array_filter($courses, function($course) {
                return (int) $course->id !== (int) SITEID;
            });
            if (count($courses) > 1) {
                return (object) ['error' => get_string('ambiguouscourse', 'tool_category_enrol_cleanup')];
            }
            if (!$courses) {
                return (object) ['error' => get_string('coursenotfound', 'tool_category_enrol_cleanup')];
            }
            $course = reset($courses);
            return (object) ['id' => $course->id, 'label' => $course->shortname];
        }
        if (!empty($values['coursefullname'])) {
            $courses = $DB->get_records_sql(
                "SELECT id, shortname
                   FROM {course}
                  WHERE id <> :siteid AND " . $DB->sql_equal('fullname', ':fullname', false),
                ['siteid' => SITEID, 'fullname' => $values['coursefullname']]
            );
            if (count($courses) > 1) {
                return (object) ['error' => get_string('ambiguouscourse', 'tool_category_enrol_cleanup')];
            }
            if (!$courses) {
                return (object) ['error' => get_string('coursenotfound', 'tool_category_enrol_cleanup')];
            }
            $course = reset($courses);
            return (object) ['id' => $course->id, 'label' => $course->shortname];
        }
        return (object) ['error' => get_string('coursenotfound', 'tool_category_enrol_cleanup')];
    }

    /**
     * @param array $values
     * @return \stdClass
     */
    private static function find_user(array $values): \stdClass {
        global $DB;

        $guestid = guest_user()->id;
        if (!empty($values['username'])) {
            $user = $DB->get_record_select(
                'user',
                'deleted = 0 AND id <> :guest AND ' . $DB->sql_equal('username', ':username', false),
                ['guest' => $guestid, 'username' => $values['username']],
                'id, firstname, lastname, username'
            );
            if (!$user) {
                return (object) ['error' => get_string('usernotfound', 'tool_category_enrol_cleanup')];
            }
            return (object) ['id' => $user->id, 'label' => $user->username];
        }
        if (!empty($values['email'])) {
            $users = $DB->get_records_select(
                'user',
                'deleted = 0 AND id <> :guest AND ' . $DB->sql_equal('email', ':email', false),
                ['guest' => $guestid, 'email' => $values['email']],
                '',
                'id, username'
            );
            if (count($users) > 1) {
                return (object) ['error' => get_string('ambiguoususer', 'tool_category_enrol_cleanup')];
            }
            if (!$users) {
                return (object) ['error' => get_string('usernotfound', 'tool_category_enrol_cleanup')];
            }
            $user = reset($users);
            return (object) ['id' => $user->id, 'label' => $user->username];
        }
        if (!empty($values['useridnumber'])) {
            $users = $DB->get_records_select(
                'user',
                'deleted = 0 AND id <> :guest AND idnumber = :idnumber',
                ['guest' => $guestid, 'idnumber' => $values['useridnumber']],
                '',
                'id, username'
            );
            if (count($users) > 1) {
                return (object) ['error' => get_string('ambiguoususer', 'tool_category_enrol_cleanup')];
            }
            if (!$users) {
                return (object) ['error' => get_string('usernotfound', 'tool_category_enrol_cleanup')];
            }
            $user = reset($users);
            return (object) ['id' => $user->id, 'label' => $user->username];
        }

        $name = '';
        if (!empty($values['firstname']) || !empty($values['lastname'])) {
            $name = trim(($values['firstname'] ?? '') . ' ' . ($values['lastname'] ?? ''));
        } else if (!empty($values['fullname'])) {
            $name = $values['fullname'];
        }
        if ($name === '') {
            return (object) ['error' => get_string('usernotfound', 'tool_category_enrol_cleanup')];
        }
        $users = self::find_users_by_name($name, $guestid);
        if (count($users) > 1) {
            return (object) ['error' => get_string('ambiguoususer', 'tool_category_enrol_cleanup')];
        }
        if (!$users) {
            return (object) ['error' => get_string('usernotfound', 'tool_category_enrol_cleanup')];
        }
        $user = reset($users);
        return (object) ['id' => $user->id, 'label' => fullname($user)];
    }

    /**
     * @param string $name
     * @param int $guestid
     * @return array
     */
    private static function find_users_by_name(string $name, int $guestid): array {
        global $DB;

        $name = self::normalize($name);
        $forward = $DB->sql_concat('firstname', "' '", 'lastname');
        $reverse = $DB->sql_concat('lastname', "' '", 'firstname');
        $users = $DB->get_records_sql(
            "SELECT id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, username
               FROM {user}
              WHERE deleted = 0 AND id <> :guest
                AND (" . $DB->sql_equal($forward, ':name1', false) . "
                  OR " . $DB->sql_equal($reverse, ':name2', false) . ")",
            ['guest' => $guestid, 'name1' => $name, 'name2' => $name]
        );
        if ($users) {
            return $users;
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 2) {
            return [];
        }
        $first = $parts[0];
        $last = $parts[count($parts) - 1];
        $rest = implode(' ', array_slice($parts, 1));
        return $DB->get_records_sql(
            "SELECT id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, username
               FROM {user}
              WHERE deleted = 0 AND id <> :guest
                AND ((" . $DB->sql_equal('firstname', ':first', false) . "
                    AND " . $DB->sql_equal('lastname', ':rest', false) . ")
                  OR (" . $DB->sql_equal('firstname', ':first2', false) . "
                    AND " . $DB->sql_equal('lastname', ':last', false) . "))",
            [
                'guest' => $guestid,
                'first' => $first,
                'rest' => $rest,
                'first2' => $first,
                'last' => $last,
            ]
        );
    }

    /**
     * @param string $shortname
     * @param string $name
     * @return \stdClass
     */
    private static function find_role(string $shortname, string $name): \stdClass {
        global $DB;

        if ($shortname !== '') {
            $role = $DB->get_record('role', ['shortname' => \core_text::strtolower(trim($shortname))], 'id, shortname, name');
            if (!$role) {
                return (object) ['error' => get_string('rolenotfound', 'tool_category_enrol_cleanup')];
            }
            return (object) ['id' => $role->id];
        }

        $needle = self::normalize($name);
        $roles = $DB->get_records('role', null, '', 'id, shortname, name');
        $matches = [];
        foreach ($roles as $role) {
            $local = self::normalize(role_get_name($role));
            if ($local === $needle || self::normalize($role->shortname) === $needle || self::normalize((string) $role->name) === $needle) {
                $matches[$role->id] = $role;
            }
        }
        if (count($matches) !== 1) {
            return (object) ['error' => get_string('rolenotfound', 'tool_category_enrol_cleanup')];
        }
        $role = reset($matches);
        return (object) ['id' => $role->id];
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @param int $roleid
     * @return bool
     */
    private static function user_has_membership(int $courseid, int $userid, int $roleid): bool {
        global $DB;

        $context = \context_course::instance($courseid);
        if ($roleid > 0) {
            return $DB->record_exists('role_assignments', [
                'contextid' => $context->id,
                'userid' => $userid,
                'roleid' => $roleid,
            ]);
        }
        $enrolled = $DB->record_exists_sql(
            "SELECT 1
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid AND ue.userid = :userid",
            ['courseid' => $courseid, 'userid' => $userid]
        );
        if ($enrolled) {
            return true;
        }
        return $DB->record_exists('role_assignments', [
            'contextid' => $context->id,
            'userid' => $userid,
        ]);
    }

    /**
     * @param \stored_file $file
     * @return array{headers: array, rows: array}
     */
    private static function read_stored_file(\stored_file $file): array {
        $extension = \core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $temp = make_request_directory() . '/import.' . $extension;
        $file->copy_content_to($temp);
        return self::read_path($temp);
    }

    /**
     * @param \stdClass $import
     * @return array{headers: array, rows: array}
     */
    private static function read_import(\stdClass $import): array {
        $context = \context_user::instance($import->userid);
        $files = get_file_storage()->get_area_files(
            $context->id,
            'tool_category_enrol_cleanup',
            'import',
            $import->id,
            'id',
            false
        );
        if (!$files) {
            throw new \moodle_exception('invalidspreadsheet', 'tool_category_enrol_cleanup');
        }
        return self::read_stored_file(reset($files));
    }

    /**
     * @param string $path
     * @return array{headers: array, rows: array}
     */
    private static function read_path(string $path): array {
        global $CFG;
        require_once($CFG->libdir . '/excellib.class.php');

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray('', true, true, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $headers = [];
        $rows = [];
        $started = false;
        foreach ($matrix as $line) {
            $line = array_map(function($cell) {
                return trim((string) $cell);
            }, $line);
            if (!$started) {
                if (self::row_is_empty($line)) {
                    continue;
                }
                $headers = self::trim_trailing_empty($line);
                $started = true;
                continue;
            }
            $line = array_slice($line, 0, count($headers));
            if (self::row_is_empty($line)) {
                continue;
            }
            $rows[] = $line;
            if (count($rows) > self::MAX_ROWS) {
                throw new \moodle_exception('toomanyrows', 'tool_category_enrol_cleanup', '', self::MAX_ROWS);
            }
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param array $cells
     * @return bool
     */
    private static function row_is_empty(array $cells): bool {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $line
     * @return array
     */
    private static function trim_trailing_empty(array $line): array {
        while ($line && trim((string) end($line)) === '') {
            array_pop($line);
        }
        return array_values($line);
    }

    /**
     * @param array $values
     * @param array $keys
     * @return string
     */
    private static function first_value(array $values, array $keys): string {
        foreach ($keys as $key) {
            if (!empty($values[$key])) {
                return $values[$key];
            }
        }
        return '';
    }

    /**
     * @param array $values
     * @return string
     */
    private static function user_label(array $values): string {
        if (!empty($values['username'])) {
            return $values['username'];
        }
        if (!empty($values['email'])) {
            return $values['email'];
        }
        if (!empty($values['useridnumber'])) {
            return $values['useridnumber'];
        }
        if (!empty($values['fullname'])) {
            return $values['fullname'];
        }
        return trim(($values['firstname'] ?? '') . ' ' . ($values['lastname'] ?? ''));
    }

    /**
     * @param string $value
     * @return string
     */
    private static function normalize(string $value): string {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = str_replace(['"', "'", '״', '׳'], '', $value);
        return \core_text::strtolower($value);
    }
}
