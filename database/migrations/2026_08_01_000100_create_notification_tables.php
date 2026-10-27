<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Avvisi nel centro notifiche dell'app (uno per persona)
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('title', 160);
            $table->string('body', 500)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('tag', 80)->nullable();
            $table->boolean('urgent')->default(false);
            $table->timestamp('read_at')->nullable();
            // Riepilogo email: da includere / già incluso
            $table->boolean('digest')->default(false);
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'read_at']);
            $table->index(['digest', 'emailed_at']);
        });

        // Scelte della persona per ogni evento (vuoto = predefinito del ruolo o dell'evento)
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->boolean('push')->nullable();
            $table->string('email', 10)->nullable(); // no, subito, riepilogo
            $table->timestamps();
            $table->unique(['user_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('user_notifications');
    }
};
