<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orario di lavoro (docs/KPI.md §2.9): calendari con orari settimanali validi da una data,
 * eccezioni per giorno (festività, chiusure, orari speciali) e assegnazioni datate ad
 * azienda, ubicazione e macchina. Tutto ha una data di inizio: i mesi passati non cambiano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Orario settimanale valido da una data (fino alla versione successiva)
        Schema::create('work_calendar_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_calendar_id')->constrained()->cascadeOnDelete();
            $table->date('valid_from');
            $table->timestamps();
            $table->unique(['work_calendar_id', 'valid_from']);
        });

        // Turni: giorno della settimana (1 = lunedì) e orari locali "HH:MM"; fine <= inizio = finisce il giorno dopo
        Schema::create('work_calendar_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_calendar_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->char('start', 5);
            $table->char('end', 5);
            $table->index(['work_calendar_schedule_id', 'weekday']);
        });

        // Eccezioni per data: chiuso, orario speciale, oppure "lavorativo" (festività proposta ma tolta)
        Schema::create('work_calendar_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_calendar_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('kind', 12);
            $table->string('label', 80)->nullable();
            $table->string('source', 12)->default('manuale');
            $table->json('hours')->nullable();
            $table->timestamps();
            $table->unique(['work_calendar_id', 'date']);
        });

        // Chi usa quale calendario e da quando. Calendario vuoto = eredita dal livello sopra (in cima: 24 ore su 24)
        Schema::create('work_calendar_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 12);
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('work_calendar_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('valid_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['scope', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_calendar_assignments');
        Schema::dropIfExists('work_calendar_exceptions');
        Schema::dropIfExists('work_calendar_shifts');
        Schema::dropIfExists('work_calendar_schedules');
        Schema::dropIfExists('work_calendars');
    }
};
