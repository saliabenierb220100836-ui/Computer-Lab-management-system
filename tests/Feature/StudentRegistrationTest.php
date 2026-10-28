<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_requires_the_official_school_email_domain(): void
    {
        $academicGroup = $this->academicGroup();

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('iskolarngbayan.pup.edu.ph')
            ->assertSee($academicGroup->label)
            ->assertSee('Certificate of registration image')
            ->assertSee('name="academic_group_id"', false);

        $this->from(route('register'))->post(route('register.store'), $this->registrationData([
            'email' => 'student@example.com',
        ]))->assertRedirect(route('register'))->assertSessionHasErrors('email');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_student_registration_requires_the_exact_student_number_format(): void
    {
        $this->academicGroup();
        $this->from(route('register'))
            ->post(route('register.store'), $this->registrationData([
                'student_or_employee_id' => '2026-12345',
            ]))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('student_or_employee_id');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_student_registration_creates_a_student_and_sends_a_verification_email(): void
    {
        $this->academicGroup();
        Notification::fake();
        Storage::fake('local');

        $this->post(route('register.store'), $this->registrationData())
            ->assertRedirect(route('verification.notice'));

        $student = User::query()->where('student_or_employee_id', '2026-01234-SR-0')->firstOrFail();
        $this->assertSame('student', $student->role);
        $this->assertSame('PUP', $student->first_name);
        $this->assertSame('Student', $student->last_name);
        $this->assertSame('BSIT 3-1', $student->section);
        $this->assertNotNull($student->registration_certificate_path);
        Storage::disk('local')->assertExists($student->registration_certificate_path);
        $this->assertNull($student->email_verified_at);
        Notification::assertSentTo($student, VerifyEmail::class);
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_student_registration_requires_a_certificate_image_and_section(): void
    {
        $this->academicGroup();
        Storage::fake('local');

        $this->from(route('register'))
            ->post(route('register.store'), $this->registrationData([
                'academic_group_id' => '',
                'certificate' => null,
            ]))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['academic_group_id', 'certificate']);

        $this->assertDatabaseCount('students', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_legacy_malformed_academic_groups_cannot_be_selected_for_registration(): void
    {
        $this->academicGroup();
        $legacyGroup = AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT-3A']);

        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee('value="'.$legacyGroup->id.'"', false);

        $this->from(route('register'))
            ->post(route('register.store'), $this->registrationData([
                'academic_group_id' => $legacyGroup->id,
            ]))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('academic_group_id');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_signed_verification_link_opens_the_student_dashboard(): void
    {
        $this->academicGroup();
        Notification::fake();
        $this->post(route('register.store'), $this->registrationData());
        $student = User::query()->where('student_or_employee_id', '2026-01234-SR-0')->firstOrFail();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $student->id,
            'hash' => sha1($student->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect(route('dashboard'));

        $this->assertNotNull($student->fresh()->email_verified_at);
        $this->get(route('dashboard'))->assertOk();
    }

    private function registrationData(array $overrides = []): array
    {
        $academicGroup = $this->academicGroup();

        return array_merge([
            'first_name' => 'PUP',
            'last_name' => 'Student',
            'academic_group_id' => $academicGroup->id,
            'student_or_employee_id' => '2026-01234-SR-0',
            'email' => 'pupstudent@iskolarngbayan.pup.edu.ph',
            'certificate' => UploadedFile::fake()->createWithContent(
                'certificate.png',
                file_get_contents(public_path('images/pup-favicon.png')),
            ),
            'password' => 'Start!2026Abcd',
            'password_confirmation' => 'Start!2026Abcd',
        ], $overrides);
    }

    private function academicGroup(): AcademicGroup
    {
        return AcademicGroup::query()->firstOrCreate([
            'course' => 'BSIT',
            'section' => 'BSIT 3-1',
        ]);
    }
}
