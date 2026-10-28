# PUP - SRComLab — Computer Lab Management

A Laravel 13, Blade, and Tailwind application for computer-lab access, student accounts, course rosters, attendance, and maintenance.

## Account database structure

Administrator, faculty, and student accounts are stored separately in the `administrators`, `faculty`, and `students` tables. The read-only `account_directory` database view combines those records for authentication, account lookup, and reporting; account changes are written to the role-specific tables.

> **Existing database warning:** Migration `2026_10_28_000007_split_accounts_by_role` intentionally removes existing accounts and linked class schedules, enrollments, attendance/session history, maintenance tickets, password-reset tokens, and active web sessions before creating the separate role tables. Terminals are retained. Back up your database before applying this migration. On a fresh install, continue with the normal migration steps below.

## Run locally

```powershell
composer install
npm install
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`. The seed data provides:

| Role | Email | Campus ID | Password |
|---|---|---|---|
| Lab administrator | `admin@lab.test` | `FA-0001-SR-0001` | `C0mputer!Lab2026` |
| Instructor | `instructor@pup.edu.ph` | `FA-1234-SR-5678` | `C0mputer!Lab2026` |
| Student | `alex.morgan@iskolarngbayan.pup.edu.ph` | `2024-01234-SR-0` | `C0mputer!Lab2026` |

These are demo credentials only. Change or remove them before deploying.

The storage link exposes uploaded public assets, including user profile photos.

## UI screenshot references

The [UI screenshot folder](./ui-screenshots/) contains screenshots of login, registration and email verification, and the admin, instructor, and student dashboards with their key actions. See its [screenshot index](./ui-screenshots/README.md). Screenshots use a separate temporary database populated with fictional demonstration records.

## Student registration and email

- Students may register at `/register` with a unique student number and an `@iskolarngbayan.pup.edu.ph` email address.
- Sections must match their course code and use the `COURSE #-#` format (for example, `BSIT 3-1`). Students must select a course/section from the academic-group list. Administrators manage this list from **Courses & sections**, where they can create, edit, and delete unused groups or import a CSV with `course,section` columns. Instructors can only select existing groups when scheduling or updating classes; if a section is missing, they can request it for admin review. Each class and student account references the same `academic_groups` record, preventing cross-course or cross-section roster assignment. Existing student accounts without a group must be assigned one by an administrator before they can join a class.
- Instructors who cannot find a required course and section may submit a request from **Classes & schedules**. Administrators review pending requests from **Courses & sections** and can approve them into the catalog or reject them with an optional note; instructors can see the request status.
- Registration also requires the student's section and a JPG, PNG, or WebP certificate-of-registration image (up to 5 MB). The certificate is stored privately and is visible only to instructors/admins authorized for a class roster containing that student.
- Student numbers must match `YYYY-#####-SR-0` (example: `2026-01234-SR-0`). Instructor/employee numbers must match `FA-####-SR-####` (example: `FA-1234-SR-5678`). Admin account forms enforce the matching format for each selected account role.
- Admin-created or updated employee/faculty accounts must use an `@pup.edu.ph` email address; student accounts use the configured official student email domain.
- The default is configured through `STUDENT_EMAIL_DOMAIN` in `.env`; configure it to the correct official student domain before deployment.
- Students must verify ownership of that address before they can use the dashboard, check in, or be added to a course roster.
- Faculty can create an instructor account from the faculty registration link on the sign-in page. Registration requires an employee number and an official `@pup.edu.ph` webmail address, which must be verified before entering the workspace.
- Administrator, faculty, and student tables store `first_name` and `last_name` separately; existing full names are backfilled during migration and the combined name remains available for display.
- All registrations and password changes/resets require a 12–16 character password containing at least one uppercase letter, one lowercase letter, one number, and one special character. The same password format is required at sign-in.
- Locally, `MAIL_MAILER=log` records verification messages in `storage/logs/laravel.log`. Configure SMTP or another delivery service in `.env` for real school email delivery.
- Accounts created by an administrator are treated as verified by that administrator. Student accounts created or updated by an administrator must still use the configured official student email domain.
- Verified users can change their password from **Change password** after signing in. The form requires the current password and a confirmed new password.
- Users who cannot sign in can request a password-reset link using their registered school email from the sign-in page. The emailed link verifies access to that inbox and allows them to choose a new password; reset links expire after 60 minutes.

## Account and course management

- Administrators can create student/instructor accounts, edit their account details, and deactivate accounts in **User account management** on the dashboard. They cannot change passwords for existing users.
- The account management page separates faculty and student records into their own tabs; administrator accounts are not listed there.
- Deactivation is a soft delete: users cannot sign in, active sessions are checked out, affected class rosters are updated, and historical attendance remains available.
- Instructors can access only their own course rosters and attendance identities. Their **Session log** lists completed classes only; open a completed class to see its attendance summary and check-in records. Open a class's **Students** page to assign students, import the roster, and view each enrolled student's profile, section, school email, class attendance, and registration-certificate image.
- Instructors can add an individual verified student by student number, assign or change an available station for enrolled students, and remove students from their own rosters. When an enrolled student checks into an available station during an active class without a station assignment, that station is assigned to them on the class roster automatically.
- Instructors can import an official class roster as CSV with a `student_number` or `student_or_employee_id` column. Each upload synchronizes that class roster to the CSV only: existing roster entries absent from the file are removed, and only matching, verified student accounts listed in the file are enrolled. CSV numbers without a registered account are reported to the instructor; registered but unverified, inactive, or non-school-email accounts are reported separately. Attendance history is retained, and any active class sessions for students removed from the roster are checked out.

### Sample third-year IT roster CSV

Download and copy the [sample-3rd-year-it-roster.csv](./sample-3rd-year-it-roster.csv). It contains 40 **fictional example records** for BS Information Technology, year 3, section BSIT 3-1, using the requested student-number format. The numbers and names are examples only, not real PUP student records.

To upload the roster:

1. Make sure each actual student already has an account registered with their student number and verified official school email. The import links existing accounts; it does not create accounts or verify enrollment against PUP’s SIS.
2. Sign in as the instructor who owns the course schedule.
3. If necessary, create a class schedule. Find it under **Classes & schedules**.
4. Open that class's **Students** page, choose the roster CSV under **Import official course roster**, then select **Import roster**.
5. The system matches the `student_number` column against verified student accounts, enrolls matching records, and reports unmatched or unverified numbers. Existing accounts must use the exact same student number.

The sample is a format/template example; importing it only enrolls records if matching student accounts already exist in your database. For a real class, replace the fictional rows with the official roster and keep the `student_number` header.

**Enrollment source:** the roster CSV is the in-app record of the official roster supplied by the instructor. No external PUP student-information-system connection is configured; an uploaded roster is not independently checked against SIS.

## Access rules

| Action | Lab administrator | Instructor | Student |
|---|---|---|---|
| View lab map and session activity | All stations and logs | All station statuses; own class roster/logs | Station status; own logs |
| Manage accounts, stations, and maintenance | Full access | — | — |
| Create schedules and manage enrolled students | Full access | Own classes | — |
| Check in to a station | — | — | Available station or assigned class seat |
| Report a fault | Yes | Yes | Yes |
| Export session log | All sessions | Own class sessions | Own sessions |

Role middleware protects the server-side routes as well as the dashboard UI. Successful sign-in rotates the session ID; repeated login failures are rate-limited.

## Features

- Seeded 8-by-5 interactive floor map with available, occupied, reserved, and maintenance states; the map, occupancy counts, and recent log poll every 15 seconds while the dashboard is visible.
- Database-backed check-in/check-out and session history; students may only hold one active session.
- Administrators can force-release an occupied station from the floor map; this checks out its active session and marks the station available. Stations flagged for maintenance cannot be force-released as available.
- Instructor schedules with overlap checks across the computer lab, manual class start without waiting for attendance, cancellation of future classes without attendance records, verified student enrollment, station assignments, and official roster CSV import.
- Instructors can dismiss a started class to save its duration and a final attendance summary. Students without a class check-in are marked absent; check-ins more than 15 minutes after the class start are marked late.
- Cancelling or dismissing a class automatically checks out any active sessions linked to it. Students can check themselves out from the active-session banner on any workspace page.
- Fault reports and an admin maintenance queue with priority and status updates.
- Role-scoped CSV exports with spreadsheet-formula protection.
- Scheduled automatic check-out for class sessions; run Laravel's scheduler in production (`php artisan schedule:work`, or configure `php artisan schedule:run` in cron).

## Validate

```powershell
php artisan test
npm run build
```
