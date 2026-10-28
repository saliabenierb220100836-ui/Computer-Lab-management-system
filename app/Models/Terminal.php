<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['pc_name', 'grid_x', 'grid_y', 'status', 'specs_description'])]
class Terminal extends Model
{
    public function activitySessions(): HasMany
    {
        return $this->hasMany(ActivitySession::class);
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(ActivitySession::class)->whereNull('logout_time');
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class);
    }

    public function activeReservation(): HasOne
    {
        return $this->hasOne(ScheduleEnrollment::class, 'terminal_id')
            ->whereHas('schedule', fn ($query) => $query
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->whereNull('dismissed_at'));
    }
}
