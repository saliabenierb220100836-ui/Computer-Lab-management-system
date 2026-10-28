<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Account
{
    protected $table = 'account_directory';

    public function getRoleAttribute(): string
    {
        $role = $this->getAttributeFromArray('role');

        return is_string($role) ? $role : static::ROLE;
    }

    public function newFromBuilder($attributes = [], $connection = null): Model
    {
        $attributes = (array) $attributes;
        $model = match ($attributes['role'] ?? null) {
            'admin' => new Administrator(),
            'instructor' => new Faculty(),
            'student' => new Student(),
            default => new static(),
        };
        $model->setRawAttributes($attributes, true);
        $model->setConnection($connection ?? $this->getConnectionName());
        $model->exists = true;
        $model->wasRecentlyCreated = false;
        $model->fireModelEvent('retrieved', false);

        return $model;
    }
}
