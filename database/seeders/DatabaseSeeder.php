<?php

namespace Database\Seeders;

use App\Models\Administrator;
use App\Models\Terminal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = config('app.admin_password');

        if (! is_string($adminPassword) || preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,16}$/D', $adminPassword) !== 1) {
            throw new InvalidArgumentException('Set ADMIN_PASSWORD in .env to a 12–16 character password with uppercase, lowercase, a number, and a special character before seeding.');
        }

        $administrator = Administrator::withTrashed()->updateOrCreate(
            ['student_or_employee_id' => 'FA-0001-SR-0001'],
            [
                'name' => 'Lab Administrator',
                'email' => config('app.admin_email'),
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ],
        );

        if ($administrator->trashed()) {
            $administrator->restore();
        }

        for ($number = 1; $number <= 40; $number++) {
            $column = (($number - 1) % 8) + 1;
            $row = intdiv($number - 1, 8) + 1;
            Terminal::query()->updateOrCreate(
                ['pc_name' => sprintf('PC-%02d', $number)],
                ['grid_x' => $column, 'grid_y' => $row, 'status' => 'available', 'specs_description' => 'Standard lab workstation'],
            );
        }
    }
}
