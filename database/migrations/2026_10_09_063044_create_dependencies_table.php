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
        Schema::create('dependencies', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('predecessor_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('successor_id')->constrained('tasks')->cascadeOnDelete();
            $table->enum('type', ['FS', 'SS'])->default('FS');
            $table->integer('lag')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['predecessor_id', 'successor_id']);
            $table->index('predecessor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dependencies');
    }
};
