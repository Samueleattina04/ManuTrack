<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambi di stato fatti fuori dal ciclo normale ("Correggi stato" dell'amministratore).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_status_changes', function (Blueprint $table) {
            $table->boolean('is_correction')->default(false)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_status_changes', function (Blueprint $table) {
            $table->dropColumn('is_correction');
        });
    }
};
