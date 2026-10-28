<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['terminal_id', 'reported_by', 'issue_type', 'description', 'priority', 'status'])]
class MaintenanceTicket extends Model
{
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by')->withTrashed();
    }
}
