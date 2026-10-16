<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resource Chart part 3: allocation percent per assignment (1–100,
     * default 100 = a full working day). Feeds the workload calculator.
     */
    public function up(): void
    {
        Schema::table('assignments', static function (Blueprint $table): void {
            $table->unsignedTinyInteger('allocation')->default(100)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', static function (Blueprint $table): void {
            $table->dropColumn('allocation');
        });
    }
};
