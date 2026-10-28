<?php

use App\Http\Controllers\AcademicGroupRequestController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\AdminAcademicGroupController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseRosterController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/register/faculty', [RegistrationController::class, 'createFaculty'])->name('register.faculty');
    Route::post('/register/faculty', [RegistrationController::class, 'storeFaculty'])
        ->middleware('throttle:5,1')
        ->name('register.faculty.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [RegistrationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [LabController::class, 'dashboard'])->name('dashboard');
    Route::get('/account/password', [AccountPasswordController::class, 'edit'])->name('password.change');
    Route::put('/account/password', [AccountPasswordController::class, 'update'])->name('password.change.update');
    Route::post('/profile/photo', [ProfilePhotoController::class, 'store'])->name('profile.photo.store');
    Route::get('/workspace/{page}', [LabController::class, 'dashboard'])
        ->where('page', 'floor-map|classes|session-log|active-users|fault-reports|maintenance|accounts|academic-groups')
        ->name('workspace.page');
    Route::get('/sessions/export', [LabController::class, 'export'])->name('sessions.export');

    Route::middleware('role:admin,student')->group(function () {
        Route::post('/terminals/{terminal}/check-in', [LabController::class, 'checkIn'])
            ->middleware('role:student')
            ->name('terminals.check-in');
        Route::post('/sessions/check-out', [LabController::class, 'checkOut'])->name('sessions.check-out');
    });

    Route::middleware('role:admin,instructor')->group(function () {
        Route::post('/schedules', [LabController::class, 'createSchedule'])->name('schedules.store');
        Route::post('/schedules/{schedule}/start', [LabController::class, 'startSchedule'])->name('schedules.start');
        Route::post('/schedules/{schedule}/dismiss', [LabController::class, 'dismissSchedule'])->name('schedules.dismiss');
        Route::delete('/schedules/{schedule}', [LabController::class, 'cancelSchedule'])->name('schedules.cancel');
        Route::get('/schedules/{schedule}/attendance', [LabController::class, 'showScheduleAttendance'])->name('schedules.attendance.show');
        Route::get('/schedules/{schedule}/attendance/export', [LabController::class, 'exportScheduleAttendance'])->name('schedules.attendance.export');
        Route::get('/schedules/{schedule}/students', [CourseRosterController::class, 'index'])->name('schedules.students.index');
        Route::get('/schedules/{schedule}/students/{student}', [CourseRosterController::class, 'show'])->name('schedules.students.show');
        Route::get('/schedules/{schedule}/students/{student}/certificate', [CourseRosterController::class, 'certificate'])->name('schedules.students.certificate');
        Route::post('/schedules/{schedule}/students', [CourseRosterController::class, 'store'])->name('schedules.students.store');
        Route::post('/schedules/{schedule}/students/import', [CourseRosterController::class, 'import'])->name('schedules.students.import');
        Route::patch('/schedules/{schedule}/academic-group', [CourseRosterController::class, 'updateAcademicGroup'])->name('schedules.academic-group.update');
        Route::patch('/schedules/{schedule}/students/{enrollment}/station', [CourseRosterController::class, 'updateStation'])->name('schedules.students.station.update');
        Route::delete('/schedules/{schedule}/students/{enrollment}', [CourseRosterController::class, 'destroy'])->name('schedules.students.destroy');
        Route::post('/academic-group-requests', [AcademicGroupRequestController::class, 'store'])
            ->middleware('role:instructor')
            ->name('academic-group-requests.store');
    });

    Route::middleware('role:admin,student,instructor')->group(function () {
        Route::post('/tickets', [LabController::class, 'createTicket'])->name('tickets.store');
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('/admin/academic-group-requests/{academicGroupRequest}/approve', [AcademicGroupRequestController::class, 'approve'])
            ->name('admin.academic-group-requests.approve');
        Route::post('/admin/academic-group-requests/{academicGroupRequest}/reject', [AcademicGroupRequestController::class, 'reject'])
            ->name('admin.academic-group-requests.reject');
        Route::get('/admin/academic-groups/template', [AdminAcademicGroupController::class, 'template'])
            ->name('admin.academic-groups.template');
        Route::post('/admin/academic-groups/import', [AdminAcademicGroupController::class, 'import'])
            ->name('admin.academic-groups.import');
        Route::post('/admin/academic-groups', [AdminAcademicGroupController::class, 'store'])
            ->name('admin.academic-groups.store');
        Route::patch('/admin/academic-groups/{academicGroup}', [AdminAcademicGroupController::class, 'update'])
            ->name('admin.academic-groups.update');
        Route::delete('/admin/academic-groups/{academicGroup}', [AdminAcademicGroupController::class, 'destroy'])
            ->name('admin.academic-groups.destroy');
        Route::get('/admin/session-logs/{date}', [LabController::class, 'showAdminDailySessions'])
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('admin.session-logs.show');
        Route::get('/admin/session-logs/{date}/export', [LabController::class, 'exportAdminDailySessions'])
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('admin.session-logs.export');
        Route::patch('/terminals/{terminal}', [LabController::class, 'updateTerminal'])->name('terminals.update');
        Route::post('/terminals/{terminal}/force-release', [LabController::class, 'forceReleaseTerminal'])->name('terminals.force-release');
        Route::patch('/tickets/{ticket}', [LabController::class, 'updateTicket'])->name('tickets.update');
        Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::patch('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    });
});
