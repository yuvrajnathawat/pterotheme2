<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('node_backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('node_id');
            $table->unsignedInteger('server_id')->nullable(); // null = node config archive
            $table->char('run_id', 36); // UUID groups records per run
            $table->string('type', 10)->default('auto'); // manual, auto
            $table->boolean('is_node_archive')->default(false);
            $table->string('status', 20)->default('pending'); // pending, running, completed, failed
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->text('storage_paths')->nullable(); // JSON: {s3:"path", sftp:"path", ...}
            $table->string('checksum', 128)->nullable(); // sha256:abc...
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['node_id', 'run_id']);
            $table->index(['server_id', 'status']);
            $table->foreign('node_id')->references('id')->on('nodes')->onDelete('cascade');
            $table->foreign('server_id')->references('id')->on('servers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_backups');
    }
};
