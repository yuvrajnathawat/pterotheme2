<?php

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
        Schema::table('games', function (Blueprint $table) {
            if (!Schema::hasColumn('games', 'split_limit')) {
                $table->integer('split_limit')->default(0)->after('is_hourly');
            }
            $table->integer('proxy_limit')->nullable()->default(null)->after('is_hourly');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            if (Schema::hasColumn('games', 'proxy_limit')) {
                $table->dropColumn('proxy_limit');
            }
        });
    }
};
