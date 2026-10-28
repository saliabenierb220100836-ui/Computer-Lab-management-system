<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course', 'section'])]
class AcademicGroup extends Model
{
    public static function sectionPatternForCourse(string $course): string
    {
        return '/^'.preg_quote(strtoupper(trim($course)), '/').' [0-9]+-[0-9]+$/';
    }

    public function hasValidSectionFormat(): bool
    {
        return preg_match(self::sectionPatternForCourse($this->course), $this->section) === 1;
    }

    public static function selectionRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $academicGroup = static::query()->find($value);
            if ($academicGroup && ! $academicGroup->hasValidSectionFormat()) {
                $fail('Select a course and section using the format COURSE #-#, such as BSIT 3-1.');
            }
        };
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function getLabelAttribute(): string
    {
        return "{$this->course} · {$this->section}";
    }
}
