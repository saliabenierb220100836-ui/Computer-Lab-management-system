<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\Terminal;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_only_the_admin_and_terminal_inventory(): void
    {
        config()->set('app.admin_password', 'Seed!Admin123');
        Administrator::query()->create([
            'name' => 'Existing Administrator',
            'email' => 'existing-admin@lab.test',
            'student_or_employee_id' => 'FA-0001-SR-0001',
            'password' => 'Existing!Password123',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('administrators', 1);
        $this->assertDatabaseHas('administrators', [
            'email' => 'ComLabAdmin@pup.edu.ph',
            'student_or_employee_id' => 'FA-0001-SR-0001',
        ]);
        $this->assertSame(0, DB::table('faculty')->count());
        $this->assertSame(0, DB::table('students')->count());
        $this->assertSame(0, DB::table('academic_groups')->count());
        $this->assertSame(0, DB::table('class_schedules')->count());
        $this->assertSame(40, Terminal::query()->count());
        $this->assertTrue(Hash::check('Seed!Admin123', Administrator::query()->sole()->password));
    }

    public function test_seeder_requires_a_strong_admin_password(): void
    {
        config()->set('app.admin_password', null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Set ADMIN_PASSWORD in .env');

        $this->seed(DatabaseSeeder::class);
    }
}
