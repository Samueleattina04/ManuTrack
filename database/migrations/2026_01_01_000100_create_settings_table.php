<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 120)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('system_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 40)->index();
            $table->string('previous_version', 40)->nullable();
            $table->string('status', 20)->index();
            $table->string('trigger', 20);
            $table->string('backup_file')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_versions');
        Schema::dropIfExists('settings');
    }
};
