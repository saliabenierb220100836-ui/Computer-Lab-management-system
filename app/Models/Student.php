<?php

namespace App\Models;

class Student extends User
{
    public const ROLE = 'student';

    protected $table = 'students';
}
