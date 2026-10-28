<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_group_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('faculty')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->string('course', 80);
            $table->string('section', 60);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_group_requests');
    }
};
