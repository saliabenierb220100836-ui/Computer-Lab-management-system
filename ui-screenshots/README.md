# PUP - SRComLab UI Reference Screenshots

These screenshots are a visual reference for the current Laravel/Blade app, not finished design mockups. Capture content uses demo accounts and fictional student records only.

Open the image files in numeric order to follow common user journeys from sign-in through role-specific actions. Files marked `full` capture the complete page; `step` captures an open form or workflow.

| Screenshot | Page or action |
|---|---|
| `01-login-full.png` | Sign-in page |
| `02-registration-full.png` | Student registration page and required number/email fields |
| `03-registration-validation.png` | Registration validation for an incorrectly formatted student number |
| `04-email-verification-full.png` | Newly registered student’s email-verification page |
| `05-admin-dashboard-full.png` | Administrator dashboard, map, accounts, and maintenance |
| `06-admin-create-account-step.png` | Expanded create-account form |
| `07-admin-edit-account-step.png` | Expanded account-edit form |
| `08-instructor-dashboard-full.png` | Instructor dashboard, classes, roster, and attendance |
| `09-instructor-class-roster-step.png` | Expanded class roster with student numbers, check-in state, and import action |
| `10-instructor-import-csv-step.png` | Example class roster selected in the CSV import control |
| `11-instructor-roster-imported-step.png` | Roster import result |
| `12-student-dashboard-full.png` | Student map and dashboard |
| `13-student-session-active-step.png` | Student checked in to a reserved workstation |
| `14-student-report-fault-step.png` | Expanded fault report form |
| `15-student-fault-reported-step.png` | Fault submission confirmation |
| `16-admin-maintenance-queue-step.png` | Admin maintenance ticket update controls |
| `17-admin-ticket-update-selected.png` | Admin selected a ticket status and priority |
| `18-admin-ticket-updated-step.png` | Updated maintenance ticket in the queue |
| `19-student-mobile-dashboard.png` | Student dashboard at a mobile viewport |

The instructor roster CSV upload screenshot uses the included `sample-3rd-year-it-roster.csv`. Its example names and identifiers are fictional. To successfully import it, matching verified student accounts must already exist in the database.

The set covers representative page layouts and key role-specific workflows; it is not a capture of every possible form state or validation error.
