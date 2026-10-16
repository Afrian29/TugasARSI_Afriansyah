<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_input')->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->enum('priority', ['Low', 'Medium', 'High', 'Critical'])->default('Medium');
            $table->unsignedInteger('percent_complete')->default(0);
            $table->enum('task_type', ['Task', 'Milestone', 'Parent'])->default('Task');
            $table->date('effective_start')->nullable();
            $table->date('effective_end')->nullable();
            $table->date('early_start')->nullable();
            $table->date('early_finish')->nullable();
            $table->date('late_start')->nullable();
            $table->date('late_finish')->nullable();
            $table->unsignedInteger('slack')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'parent_id', 'order']);
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
