<?php

namespace Database\Seeders;

use App\Models\AcademicGroup;
use App\Models\Administrator;
use App\Models\ClassSchedule;
use App\Models\Faculty;
use App\Models\ScheduleEnrollment;
use App\Models\Student;
use App\Models\Terminal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $academicGroup = AcademicGroup::query()->firstOrCreate([
            'course' => 'BSIT',
            'section' => 'BSIT 3-1',
        ]);
        foreach ([
            [Administrator::class, ['name' => 'Lab Administrator', 'email' => 'admin@lab.test', 'student_or_employee_id' => 'FA-0001-SR-0001']],
            [Faculty::class, ['first_name' => 'Jordan', 'last_name' => 'Rivera', 'name' => 'Jordan Rivera', 'email' => 'instructor@pup.edu.ph', 'student_or_employee_id' => 'FA-1234-SR-5678']],
            [Student::class, ['first_name' => 'Alex', 'last_name' => 'Morgan', 'name' => 'Alex Morgan', 'email' => 'alex.morgan@iskolarngbayan.pup.edu.ph', 'student_or_employee_id' => '2024-01234-SR-0', 'academic_group_id' => $academicGroup->id, 'section' => $academicGroup->section]],
            [Student::class, ['first_name' => 'Taylor', 'last_name' => 'Reed', 'name' => 'Taylor Reed', 'email' => 'taylor.reed@iskolarngbayan.pup.edu.ph', 'student_or_employee_id' => '2024-01235-SR-0', 'academic_group_id' => $academicGroup->id, 'section' => $academicGroup->section]],
        ] as [$modelClass, $account]) {
            $user = $modelClass::withTrashed()->updateOrCreate(
                ['student_or_employee_id' => $account['student_or_employee_id']],
                [...$account, 'password' => Hash::make('C0mputer!Lab2026'), 'email_verified_at' => now()],
            );
            if ($user->trashed()) {
                $user->restore();
            }
        }

        for ($number = 1; $number <= 40; $number++) {
            $column = (($number - 1) % 8) + 1;
            $row = intdiv($number - 1, 8) + 1;
            Terminal::query()->updateOrCreate(
                ['pc_name' => sprintf('PC-%02d', $number)],
                ['grid_x' => $column, 'grid_y' => $row, 'status' => 'available', 'specs_description' => 'Standard lab workstation'],
            );
        }

        $instructor = Faculty::query()->where('student_or_employee_id', 'FA-1234-SR-5678')->firstOrFail();
        $student = Student::query()->where('student_or_employee_id', '2024-01234-SR-0')->firstOrFail();
        $schedule = ClassSchedule::query()->updateOrCreate(
            ['instructor_id' => $instructor->id, 'subject_code' => 'CS 201', 'section' => $academicGroup->section],
            [
                'academic_group_id' => $academicGroup->id,
                'room_number' => 'Lab 01',
                'starts_at' => now()->addDay()->setTime(9, 0),
                'ends_at' => now()->addDay()->setTime(10, 30),
            ],
        );
        ScheduleEnrollment::query()->updateOrCreate(
            ['schedule_id' => $schedule->id, 'user_id' => $student->id],
            ['terminal_id' => null],
        );
    }
}
