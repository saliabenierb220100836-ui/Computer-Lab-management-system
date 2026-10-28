<?php

namespace App\Models;

class Administrator extends User
{
    public const ROLE = 'admin';

    protected $table = 'administrators';
}
