<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manutenzione: segnalazioni, ordini di lavoro e quello che vi ruota attorno
 * (assegnatari, storico degli stati, commenti, checklist, tempi, fermi,
 * codici guasto) e la numerazione progressiva per anno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last')->default(0);
            $table->unique(['name', 'year']);
        });

        Schema::create('failure_codes', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10); // problema, causa, rimedio
            $table->string('code', 20);
            $table->string('label', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['kind', 'code']);
        });

        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('label', 255);
            $table->string('kind', 10)->default('spunta'); // spunta, valore
            $table->string('unit', 20)->nullable();
            $table->boolean('required')->default(true);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('type', 20)->default('correttivo');
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('priority', 10)->default('media');
            $table->string('status', 20)->default('aperto')->index();
            $table->string('waiting_reason', 20)->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('planned_start_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedSmallInteger('reopened_count')->default(0);
            $table->foreignId('failure_problem_id')->nullable()->constrained('failure_codes')->nullOnDelete();
            $table->foreignId('failure_cause_id')->nullable()->constrained('failure_codes')->nullOnDelete();
            $table->foreignId('failure_remedy_id')->nullable()->constrained('failure_codes')->nullOnDelete();
            $table->text('completion_notes')->nullable();
            $table->decimal('external_cost', 12, 2)->nullable();
            $table->decimal('labor_rate', 8, 2)->nullable(); // costo orario al momento della chiusura
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['asset_id', 'status']);
        });

        Schema::create('work_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->boolean('machine_stopped')->default(false);
            $table->string('priority', 10)->default('media');
            $table->string('status', 12)->default('nuova')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('work_requests')->nullOnDelete();
            $table->string('triage_note', 500)->nullable();
            $table->foreignId('triaged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('triaged_at')->nullable();
            // Identificativo creato dal telefono: un invio ripetuto (rete instabile, coda offline) non duplica
            $table->uuid('client_uuid')->nullable()->unique();
            // Quando è stata scritta sul telefono (può precedere l'invio se mancava la rete)
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('work_order_assignees', function (Blueprint $table) {
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['work_order_id', 'user_id']);
        });

        Schema::create('work_order_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('from', 20)->nullable();
            $table->string('to', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('work_order_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('work_order_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('label', 255);
            $table->string('kind', 10)->default('spunta');
            $table->string('unit', 20)->nullable();
            $table->boolean('required')->default(true);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('value', 60)->nullable();
            $table->string('note', 255)->nullable();
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            // Vuoto finché il cronometro è acceso
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'ended_at']);
        });

        Schema::create('downtime_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        foreach (['downtime_events', 'time_entries', 'work_order_checklist_items', 'work_order_comments', 'work_order_status_changes', 'work_order_assignees', 'work_requests', 'work_orders', 'checklist_template_items', 'checklist_templates', 'failure_codes', 'sequences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
