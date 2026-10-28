<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 11 (GDPR): esportazioni complete dei dati e persone anonimizzate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // in_coda, in_corso, pronto, errore
            $table->string('status', 12)->default('in_coda');
            $table->string('path', 255)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('tables_count')->nullable();
            $table->unsignedInteger('files_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('deactivated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
        Schema::dropIfExists('data_exports');
    }
};
