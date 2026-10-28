<?php

namespace App\Models;

class Faculty extends User
{
    public const ROLE = 'instructor';

    protected $table = 'faculty';
}
