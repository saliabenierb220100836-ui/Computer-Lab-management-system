<?php

namespace App\Http\Controllers;

use App\Models\AcademicGroup;
use App\Models\Account;
use App\Models\ActivitySession;
use App\Models\ClassSchedule;
use App\Models\Faculty;
use App\Models\ScheduleEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Support\AccountPasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->splitLegacyNameInput($request);
        $request->merge([
            'first_name' => is_string($request->input('first_name')) ? trim($request->input('first_name')) : $request->input('first_name'),
            'last_name' => is_string($request->input('last_name')) ? trim($request->input('last_name')) : $request->input('last_name'),
            'student_or_employee_id' => is_string($request->input('student_or_employee_id'))
                ? strtoupper(trim($request->input('student_or_employee_id')))
                : $request->input('student_or_employee_id'),
            'email' => is_string($request->input('email'))
                ? strtolower(trim($request->input('email')))
                : $request->input('email'),
        ]);
        $role = in_array($request->input('role'), ['student', 'instructor'], true)
            ? $request->input('role')
            : 'student';
        $groupRule = $role === 'student'
            ? ['required', 'integer', Rule::exists('academic_groups', 'id'), AcademicGroup::selectionRule()]
            : ['exclude'];
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'student_or_employee_id' => [
                'required',
                'string',
                'max:50',
                Rule::regex(Account::identifierPatternForRole($role)),
                'unique:account_directory,student_or_employee_id',
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                'unique:account_directory,email',
                $this->schoolDomainForRole($role),
            ],
            'role' => ['required', Rule::in(['instructor', 'student'])],
            'academic_group_id' => $groupRule,
            'password' => ['required', 'confirmed', AccountPasswordPolicy::rule()],
        ], [
            'student_or_employee_id.regex' => $role === 'student'
                ? 'Enter a student number in YYYY-#####-SR-0 format, for example 2026-01234-SR-0.'
                : 'Enter an employee number in FA-####-SR-#### format, for example FA-1234-SR-5678.',
        ]);

        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        if ($role === 'student') {
            $data['section'] = AcademicGroup::query()->findOrFail($data['academic_group_id'])->section;
        }
        $account = $role === 'instructor' ? new Faculty : new Student;
        $account->fill($data + ['email_verified_at' => now()])->save();

        return back()->with('success', 'The account has been created and verified by an administrator.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role, ['instructor', 'student'], true), 404);
        $this->splitLegacyNameInput($request);
        $request->merge([
            'first_name' => is_string($request->input('first_name')) ? trim($request->input('first_name')) : $request->input('first_name'),
            'last_name' => is_string($request->input('last_name')) ? trim($request->input('last_name')) : $request->input('last_name'),
            'student_or_employee_id' => is_string($request->input('student_or_employee_id'))
                ? strtoupper(trim($request->input('student_or_employee_id')))
                : $request->input('student_or_employee_id'),
            'email' => is_string($request->input('email'))
                ? strtolower(trim($request->input('email')))
                : $request->input('email'),
        ]);
        $role = in_array($request->input('role'), ['student', 'instructor'], true)
            ? $request->input('role')
            : $user->role;
        $groupRule = $role === 'student'
            ? ['required', 'integer', Rule::exists('academic_groups', 'id'), AcademicGroup::selectionRule()]
            : ['exclude'];
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'student_or_employee_id' => [
                'required',
                'string',
                'max:50',
                Rule::regex(Account::identifierPatternForRole($role)),
                Rule::unique('account_directory', 'student_or_employee_id')->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('account_directory', 'email')->ignore($user->id),
                $this->schoolDomainForRole($role),
            ],
            'role' => ['required', Rule::in(['instructor', 'student'])],
            'academic_group_id' => $groupRule,
        ], [
            'student_or_employee_id.regex' => $role === 'student'
                ? 'Enter a student number in YYYY-#####-SR-0 format, for example 2026-01234-SR-0.'
                : 'Enter an employee number in FA-####-SR-#### format, for example FA-1234-SR-5678.',
        ]);

        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        $data['student_or_employee_id'] = strtoupper($data['student_or_employee_id']);
        if ($role === 'student') {
            $data['section'] = AcademicGroup::query()->findOrFail($data['academic_group_id'])->section;
        }
        $destinationClass = $role === 'instructor' ? Faculty::class : Student::class;
        if (! $user instanceof $destinationClass) {
            abort_if(
                $role === 'instructor'
                    ? $user->activitySessions()->exists() || $user->scheduleEnrollments()->exists()
                    : $user->classSchedules()->exists(),
                422,
                'This account has lab history tied to its current role and cannot be changed to another account type.',
            );
            DB::transaction(function () use ($user, $destinationClass, $data): void {
                $attributes = array_merge($user->getAttributes(), $data);
                unset($attributes['role']);
                if ($destinationClass !== Student::class) {
                    unset($attributes['academic_group_id']);
                }
                $destination = new $destinationClass;
                $destination->setAttribute('id', $user->id);
                $destination->fill($attributes)->save();
                $user->forceDelete();
            });
        } else {
            $user->update($data);
        }

        return back()->with('success', "{$user->name}'s account has been updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role, ['instructor', 'student'], true), 404);
        abort_if($request->user()->is($user), 422, 'You cannot deactivate your own account.');

        DB::transaction(function () use ($user): void {
            ActivitySession::query()
                ->where('user_id', $user->id)
                ->whereNull('logout_time')
                ->update(['logout_time' => now()]);

            if ($user->role === 'instructor') {
                ClassSchedule::query()->where('instructor_id', $user->id)->delete();
            } else {
                ScheduleEnrollment::query()->where('user_id', $user->id)->delete();
            }

            $user->delete();
        });

        return back()->with('success', 'Account deactivated. Historical attendance records have been preserved.');
    }

    private function schoolDomainForRole(string $role): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($role): void {
            $domain = $role === 'student'
                ? config('app.student_email_domain')
                : 'pup.edu.ph';

            if (strtolower((string) Str::afterLast((string) $value, '@')) !== strtolower($domain)) {
                $accountType = $role === 'student' ? 'Student' : 'Employee/faculty';
                $fail("{$accountType} accounts must use an @{$domain} email address.");
            }
        };
    }

    private function splitLegacyNameInput(Request $request): void
    {
        if ($request->filled('first_name') && $request->filled('last_name')) {
            return;
        }

        $name = $request->input('name');
        if (! is_string($name)) {
            return;
        }

        $parts = preg_split('/\s+/', trim($name), 2) ?: [];
        $request->merge([
            'first_name' => $request->input('first_name') ?: ($parts[0] ?? ''),
            'last_name' => $request->input('last_name') ?: ($parts[1] ?? ''),
        ]);
    }
}
