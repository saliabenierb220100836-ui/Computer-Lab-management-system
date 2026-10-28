<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'first_name', 'last_name', 'email', 'password', 'student_or_employee_id', 'email_verified_at', 'profile_photo_path', 'section', 'academic_group_id', 'registration_certificate_path'])]
#[Hidden(['password', 'remember_token'])]
abstract class Account extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    public const STUDENT_NUMBER_PATTERN = '/^\d{4}-\d{5}-SR-0$/';

    public const EMPLOYEE_NUMBER_PATTERN = '/^FA-\d{4}-SR-\d{4}$/';

    protected $keyType = 'int';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Account $account): void {
            if ($account->getKey() !== null) {
                return;
            }

            $account->setAttribute('id', DB::transaction(function (): int {
                $sequence = DB::table('account_sequences')->where('id', 1)->lockForUpdate()->first();
                $nextId = $sequence->next_id + 1;
                DB::table('account_sequences')->where('id', 1)->update(['next_id' => $nextId]);

                return $nextId;
            }));
        });
    }

    public function getRoleAttribute(): string
    {
        return static::ROLE;
    }

    public function getNameAttribute(mixed $value): string
    {
        $firstName = trim((string) ($this->attributes['first_name'] ?? ''));
        $lastName = trim((string) ($this->attributes['last_name'] ?? ''));
        $fullName = trim($firstName.' '.$lastName);

        return $fullName !== '' ? $fullName : (string) $value;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value;
        if (array_key_exists('first_name', $this->attributes) || array_key_exists('last_name', $this->attributes)) {
            return;
        }

        $parts = preg_split('/\s+/', trim((string) $value), 2) ?: [];
        $this->attributes['first_name'] = $parts[0] ?? '';
        $this->attributes['last_name'] = $parts[1] ?? '';
    }

    public function hasOfficialStudentEmail(): bool
    {
        return strcasecmp((string) strrchr($this->email, '@'), '@'.config('app.student_email_domain')) === 0;
    }

    public static function identifierPatternForRole(string $role): string
    {
        return $role === 'student' ? self::STUDENT_NUMBER_PATTERN : self::EMPLOYEE_NUMBER_PATTERN;
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'instructor_id');
    }

    public function academicGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicGroup::class);
    }

    public function activitySessions(): HasMany
    {
        return $this->hasMany(ActivitySession::class, 'user_id');
    }

    public function scheduleEnrollments(): HasMany
    {
        return $this->hasMany(ScheduleEnrollment::class, 'user_id');
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class, 'reported_by');
    }
}
