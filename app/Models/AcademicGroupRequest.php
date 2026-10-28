<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['requested_by', 'reviewed_by', 'course', 'section', 'reason', 'status', 'review_note', 'reviewed_at'])]
class AcademicGroupRequest extends Model
{
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'reviewed_by');
    }
}
