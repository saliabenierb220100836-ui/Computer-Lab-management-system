<?php

namespace App\Http\Controllers;

use App\Models\AcademicGroup;
use App\Models\Account;
use App\Models\Faculty;
use App\Models\Student;
use App\Support\AccountPasswordPolicy;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'emailDomain' => config('app.student_email_domain'),
            'academicGroups' => AcademicGroup::query()
                ->orderBy('course')
                ->orderBy('section')
                ->get()
                ->filter(fn (AcademicGroup $academicGroup) => $academicGroup->hasValidSectionFormat())
                ->values(),
        ]);
    }

    public function createFaculty(): View
    {
        return view('auth.register-faculty');
    }

    public function storeFaculty(Request $request): RedirectResponse
    {
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
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'student_or_employee_id' => [
                'required',
                'string',
                'max:50',
                Rule::regex(Account::EMPLOYEE_NUMBER_PATTERN),
                'unique:account_directory,student_or_employee_id',
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                'unique:account_directory,email',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (strtolower((string) Str::afterLast($value, '@')) !== 'pup.edu.ph') {
                        $fail('Faculty accounts must use an official @pup.edu.ph email address.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', AccountPasswordPolicy::rule()],
        ], [
            'student_or_employee_id.regex' => 'Enter an employee number in FA-####-SR-#### format, for example FA-1234-SR-5678.',
        ]);

        $faculty = Faculty::query()->create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'student_or_employee_id' => $validated['student_or_employee_id'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        event(new Registered($faculty));
        Auth::login($faculty);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('success', 'Your faculty account is ready. Verify your @pup.edu.ph email to continue.');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'student_or_employee_id' => is_string($request->input('student_or_employee_id'))
                ? strtoupper(trim($request->input('student_or_employee_id')))
                : $request->input('student_or_employee_id'),
            'email' => is_string($request->input('email'))
                ? strtolower(trim($request->input('email')))
                : $request->input('email'),
        ]);
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'academic_group_id' => ['required', 'integer', 'exists:academic_groups,id', AcademicGroup::selectionRule()],
            'student_or_employee_id' => [
                'required',
                'string',
                'max:50',
                Rule::regex(Account::STUDENT_NUMBER_PATTERN),
                'unique:account_directory,student_or_employee_id',
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                'unique:account_directory,email',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (strtolower((string) Str::afterLast($value, '@')) !== strtolower(config('app.student_email_domain'))) {
                        $fail('Use your official @'.config('app.student_email_domain').' student email address.');
                    }
                },
            ],
            'certificate' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'password' => ['required', 'confirmed', AccountPasswordPolicy::rule()],
        ], [
            'student_or_employee_id.regex' => 'Enter your student number in YYYY-#####-SR-0 format, for example 2026-01234-SR-0.',
            'certificate.max' => 'The certificate image must not exceed 10 MB.',
            'certificate.uploaded' => 'The certificate image must not exceed 10 MB.',
        ]);

        $user = DB::transaction(function () use ($validated): Student {
            $academicGroup = AcademicGroup::query()->findOrFail($validated['academic_group_id']);
            $user = Student::query()->create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'section' => $academicGroup->section,
                'academic_group_id' => $academicGroup->id,
                'student_or_employee_id' => strtoupper($validated['student_or_employee_id']),
                'email' => strtolower($validated['email']),
                'password' => $validated['password'],
            ]);
            $certificatePath = $validated['certificate']->storeAs(
                'registration-certificates',
                $validated['student_or_employee_id'].'.'.$validated['certificate']->extension(),
                'local',
            );
            if (! $certificatePath) {
                abort(500, 'Unable to save the registration certificate image.');
            }
            $user->update(['registration_certificate_path' => $certificatePath]);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('success', 'Your account is ready. Verify your school email to continue.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'A new verification link has been sent to your school email.');
    }
}
