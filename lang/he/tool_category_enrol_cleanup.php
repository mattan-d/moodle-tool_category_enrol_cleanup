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
 * Hebrew strings for category enrolment cleanup.
 *
 * @package    tool_category_enrol_cleanup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ניקוי רישומים לפי קטגוריה';
$string['privacy:metadata:tool_catenclean_run'] = 'שומר מי הפעיל ניקוי רישומים לקטגוריה ומה היו תוצאות הספירה.';
$string['privacy:metadata:tool_catenclean_run:categoryname'] = 'נתיב הקטגוריה בזמן שהניקוי הועבר לתור.';
$string['privacy:metadata:tool_catenclean_run:timecreated'] = 'מתי הניקוי הועבר לתור.';
$string['privacy:metadata:tool_catenclean_run:userid'] = 'המשתמש שהפעיל את הניקוי.';
$string['category'] = 'קטגוריה';
$string['category_help'] = 'בחרו קטגוריה מתוך עץ הקטגוריות של האתר. הניקוי חל על הקורסים בקטגוריה הזו.';
$string['selectcategory'] = 'בחירת קטגוריה';
$string['includesubcats'] = 'כולל תת-קטגוריות';
$string['includesubcats_help'] = 'כשהאפשרות מסומנת, ינוקו גם כל הקורסים בתת-הקטגוריות. כשהיא לא מסומנת, ינוקו רק קורסים שנמצאים ישירות בקטגוריה שנבחרה.';
$string['description'] = 'בחרו קטגוריה מתוך עץ הקטגוריות. הכלי יסיר את כל הרישומים ואת כל התפקידים מהקורסים בטווח, כך שהקורסים יישארו ללא רישומים. העבודה רצה כמשימת adhoc במנות קצרות ולא חוסמת את האתר.';
$string['preview'] = 'הצגת נתונים לפני הניקוי';
$string['warning'] = 'הפעולה מסירה לצמיתות את כל הרישומים ואת כל שיוכי התפקידים מהקורסים בטווח שנבחר. חשבונות המשתמשים עצמם לא נמחקים. שיטות רישום אוטומטיות (כגון cohort או meta) עלולות לרשום משתמשים מחדש בסנכרון הבא.';
$string['courses'] = 'קורסים';
$string['enrolments'] = 'רישומים';
$string['distinctusers'] = 'משתמשים שונים';
$string['roles'] = 'שיוכי תפקידים';
$string['scope'] = 'היקף';
$string['scopewithsubcats'] = '{$a} (כולל תת-קטגוריות)';
$string['scopewithoutsubcats'] = '{$a} (הקטגוריה הזו בלבד)';
$string['nocourses'] = 'אין קורסים בטווח שנבחר.';
$string['nocategories'] = 'אין קטגוריות לבחירה.';
$string['morecourses'] = 'ועוד {$a} קורסים';
$string['understand'] = 'אני מבין שהרישומים והתפקידים יוסרו ושלא ניתן לבטל את הפעולה';
$string['mustunderstand'] = 'יש לאשר שהניקוי אינו ניתן לביטול.';
$string['startcleanup'] = 'התחלת ניקוי ברקע';
$string['queued'] = 'הניקוי הועבר למשימת adhoc. הוא ירוץ דרך ה-cron במנות קצרות ולא יחסום את האתר.';
$string['alreadyrunning'] = 'כבר קיים ניקוי בתור או בריצה עבור הקטגוריה הזו.';
$string['invalidcategory'] = 'הקטגוריה אינה תקינה.';
$string['runs'] = 'ניקויים';
$string['noruns'] = 'עדיין לא בוצעו ניקויים.';
$string['report'] = 'דוח ניקוי';
$string['requestedby'] = 'בוצע על ידי';
$string['started'] = 'התחלה';
$string['finished'] = 'סיום';
$string['duration'] = 'משך';
$string['progress'] = 'התקדמות';
$string['errors'] = 'שגיאות';
$string['lasterror'] = 'שגיאה אחרונה';
$string['status'] = 'סטטוס';
$string['statusqueued'] = 'בתור';
$string['statusrunning'] = 'בריצה';
$string['statuscompleted'] = 'הושלם';
$string['statusfailed'] = 'נכשל';
$string['statuspending'] = 'ממתין';
$string['statusdone'] = 'הושלם';
$string['course'] = 'קורס';
$string['shortname'] = 'שם קצר';
$string['unenrolcount'] = 'רישומים שהוסרו';
$string['rolecount'] = 'תפקידים שהוסרו';
$string['waitingcron'] = 'ממתין לריצת cron עבור המנה הבאה.';
$string['refreshing'] = 'הדף מתרענן אוטומטית כל עוד הניקוי רץ.';
$string['resume'] = 'המשך ניקוי';
$string['deleteduser'] = 'משתמש שנמחק';
$string['taskname'] = 'ניקוי רישומים לפי קטגוריה';
$string['eventcleanupqueued'] = 'ניקוי רישומים לפי קטגוריה הועבר לתור';
$string['eventcleanupcompleted'] = 'ניקוי רישומים לפי קטגוריה הושלם';
$string['summary'] = 'קורסים: {$a->courses}, רישומים שהוסרו: {$a->unenrolments}, תפקידים שהוסרו: {$a->roles}';
$string['summaryrows'] = 'שורות: {$a->courses}, רישומים שהוסרו: {$a->unenrolments}, תפקידים שהוסרו: {$a->roles}';
$string['currentcourse'] = 'קורס נוכחי: {$a}';
$string['back'] = 'חזרה לבחירת קטגוריה';
$string['categoryheading'] = 'ניקוי לפי קטגוריה';
$string['uploadheading'] = 'הסרה מקובץ אקסל';
$string['uploadexcel'] = 'הסרת רישומים מקובץ אקסל';
$string['uploaddescription'] = 'העלו קובץ אקסל. כל שורה היא קורס ומרצה, לפי שם או לפי שם משתמש. אחרי ההעלאה ממפים את עמודות הקובץ לשדות במודל, ואז הרישום והתפקיד של המשתמש הזה מוסרים מהקורס. העיבוד רץ ברקע.';
$string['excelfile'] = 'קובץ אקסל';
$string['mappinghelp'] = 'בחרו מה כל עמודה אומרת במודל. חובה למפות עמודה לזיהוי הקורס ועמודה לזיהוי המשתמש. עמודת תפקיד היא רשות: אם ממפים אותה, יוסר רק התפקיד הזה.';
$string['samplevalue'] = 'דוגמה: {$a}';
$string['column'] = 'עמודה {$a}';
$string['mapignore'] = 'התעלמות';
$string['mapcourseshortname'] = 'קורס: שם קצר';
$string['mapcourseidnumber'] = 'קורס: מספר זיהוי';
$string['mapcoursefullname'] = 'קורס: שם מלא';
$string['mapcourseid'] = 'קורס: מזהה מספרי';
$string['mapusername'] = 'משתמש: שם משתמש';
$string['mapemail'] = 'משתמש: דוא״ל';
$string['mapuseridnumber'] = 'משתמש: מספר זיהוי';
$string['mapfullname'] = 'משתמש: שם מלא (שם המרצה)';
$string['mapfirstname'] = 'משתמש: שם פרטי';
$string['maplastname'] = 'משתמש: שם משפחה';
$string['maproleshortname'] = 'תפקיד: שם קצר';
$string['maprolename'] = 'תפקיד: שם התפקיד';
$string['mapmissingcourse'] = 'יש למפות עמודה אחת לפחות לזיהוי הקורס.';
$string['mapmissinguser'] = 'יש למפות שם משתמש, דוא״ל, מספר זיהוי, שם מלא, או שם פרטי יחד עם שם משפחה.';
$string['mapduplicate'] = 'אי אפשר למפות יותר מעמודה אחת לאותו שדה.';
$string['previewready'] = 'שורות שיוסרו';
$string['previewfailed'] = 'שורות עם שגיאה';
$string['previewskipped'] = 'שורות בלי רישום מתאים';
$string['coursenotfound'] = 'הקורס לא נמצא';
$string['usernotfound'] = 'המשתמש לא נמצא';
$string['ambiguoususer'] = 'נמצאו כמה משתמשים עם אותו שם';
$string['ambiguouscourse'] = 'נמצאו כמה קורסים עם אותו שם';
$string['norole'] = 'למשתמש אין את התפקיד הזה בקורס';
$string['notenrolled'] = 'אין רישום או תפקיד של המשתמש בקורס';
$string['rolenotfound'] = 'התפקיד לא נמצא';
$string['invalidspreadsheet'] = 'לא ניתן לקרוא את הקובץ. שמרו אותו כ-xlsx ונסו שוב.';
$string['emptyfile'] = 'הקובץ לא מכיל שורות נתונים.';
$string['toomanyrows'] = 'הקובץ מכיל יותר מ-{$a} שורות.';
$string['nofile'] = 'לא נבחר קובץ.';
$string['invalidfiletype'] = 'אפשר להעלות קובץ xlsx, xls או csv.';
$string['nothingtoqueue'] = 'אין שורות שאפשר להסיר. בדקו את מיפוי הקורס והמשתמש.';
$string['line'] = 'שורה';
$string['user'] = 'משתמש';
$string['result'] = 'תוצאה';
$string['changemapping'] = 'שינוי מיפוי';
$string['startfilecleanup'] = 'התחלת הסרה ברקע';
$string['filewarning'] = 'בכל שורה תקינה יוסרו הרישום והתפקיד של המשתמש מהקורס. שאר האנשים בקורס לא יושפעו.';
$string['statusskipped'] = 'דולג';
$string['privacy:metadata:tool_catenclean_row'] = 'שומר את המשתמש שהוסר מקורס באמצעות קובץ אקסל.';
$string['privacy:metadata:tool_catenclean_row:userid'] = 'המשתמש שהרישום שלו הוסר.';
$string['privacy:metadata:tool_catenclean_row:userlabel'] = 'ערך המשתמש כפי שהופיע בקובץ.';
$string['privacy:metadata:tool_catenclean_row:courselabel'] = 'ערך הקורס כפי שהופיע בקובץ.';
