<?php

namespace Tests\Feature;

use App\Models\Administrator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_updates_existing_administrator_by_employee_id(): void
    {
        Administrator::query()->create([
            'name' => 'Existing Administrator',
            'email' => 'existing-admin@lab.test',
            'student_or_employee_id' => 'FA-0001-SR-0001',
            'password' => 'Existing!Password123',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('administrators', 1);
        $this->assertDatabaseHas('administrators', [
            'email' => 'admin@lab.test',
            'student_or_employee_id' => 'FA-0001-SR-0001',
        ]);
    }
}
