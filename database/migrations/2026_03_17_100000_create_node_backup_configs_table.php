<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('node_backup_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('node_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('schedule_type', 20)->default('interval'); // interval, fixed, both
            $table->unsignedTinyInteger('interval_value')->nullable();
            $table->string('interval_unit', 10)->nullable(); // hours, days, weeks, months
            $table->string('fixed_time', 5)->nullable(); // HH:MM
            $table->unsignedInteger('max_file_size_mb')->default(0); // 0 = unlimited
            $table->unsignedSmallInteger('retention_max_count')->default(0); // 0 = unlimited
            $table->unsignedSmallInteger('retention_max_days')->default(0); // 0 = unlimited
            $table->boolean('whitelist_mode')->default(false);
            $table->longText('storage_backends')->nullable(); // encrypted JSON
            $table->text('discord_webhook_url')->nullable(); // encrypted, overrides global
            $table->timestamps();

            $table->foreign('node_id')->references('id')->on('nodes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_backup_configs');
    }
};
