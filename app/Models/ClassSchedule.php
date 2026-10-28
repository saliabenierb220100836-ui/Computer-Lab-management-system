<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['instructor_id', 'subject_code', 'section', 'academic_group_id', 'room_number', 'starts_at', 'ends_at', 'dismissed_at'])]
class ClassSchedule extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'dismissed_at' => 'datetime'];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id')->withTrashed();
    }

    public function academicGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicGroup::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ScheduleEnrollment::class, 'schedule_id');
    }

    public function activitySessions(): HasMany
    {
        return $this->hasMany(ActivitySession::class, 'schedule_id');
    }
}
