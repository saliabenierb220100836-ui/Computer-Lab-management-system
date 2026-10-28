<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->string('pc_name')->unique();
            $table->unsignedSmallInteger('grid_x');
            $table->unsignedSmallInteger('grid_y');
            $table->string('status')->default('available');
            $table->string('specs_description')->nullable();
            $table->timestamps();
            $table->unique(['grid_x', 'grid_y']);
        });

        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_code');
            $table->string('section');
            $table->string('room_number')->default('Lab 01');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->index(['room_number', 'starts_at', 'ends_at']);
        });

        Schema::create('schedule_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('class_schedules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->nullOnDelete();
            $table->timestamps();
            $table->unique(['schedule_id', 'user_id']);
            $table->unique(['schedule_id', 'terminal_id']);
        });

        Schema::create('activity_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('terminal_id')->constrained('terminals')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->string('purpose')->default('Open lab');
            $table->dateTime('login_time');
            $table->dateTime('logout_time')->nullable();
            $table->timestamps();
            $table->index(['terminal_id', 'logout_time']);
            $table->index(['user_id', 'logout_time']);
        });

        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('terminals')->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('issue_type');
            $table->text('description');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
        Schema::dropIfExists('activity_sessions');
        Schema::dropIfExists('schedule_enrollments');
        Schema::dropIfExists('class_schedules');
        Schema::dropIfExists('terminals');
    }
};
