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
        Schema::create('calendars', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('name')->nullable();
            $table->json('working_days');
            $table->json('weekend_days');
            $table->smallInteger('working_hours_per_day')->default(8);
            $table->json('holidays')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendars');
    }
};
