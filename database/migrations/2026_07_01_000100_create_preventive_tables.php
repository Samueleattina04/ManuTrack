<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            // tempo: ogni N giorni/settimane/mesi/anni; contatore: ogni N unità del contatore
            $table->string('trigger', 10)->default('tempo');
            $table->string('schedule_mode', 10)->default('fisso'); // fisso, ultima
            $table->unsignedSmallInteger('interval_value')->nullable();
            $table->string('interval_unit', 10)->nullable();
            $table->date('start_on')->nullable(); // prima scadenza: le date fisse si contano da qui
            $table->date('next_due_on')->nullable();
            $table->unsignedSmallInteger('lead_days')->default(7);
            $table->foreignId('meter_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('meter_interval', 14, 2)->nullable();
            $table->decimal('meter_lead', 14, 2)->default(0);
            $table->decimal('next_meter_due', 14, 2)->nullable();
            $table->string('priority', 10)->default('media');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->foreignId('checklist_template_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('current_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('last_done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'next_due_on']);
        });

        Schema::create('maintenance_plan_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->unique(['maintenance_plan_id', 'part_id']);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('maintenance_plan_id')->nullable()->after('location_id')->constrained()->nullOnDelete();
            // Scadenza prevista dal piano: serve a misurare il rispetto dei piani
            $table->date('plan_due_on')->nullable()->after('maintenance_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_plan_id');
            $table->dropColumn('plan_due_on');
        });
        Schema::dropIfExists('maintenance_plan_parts');
        Schema::dropIfExists('maintenance_plans');
    }
};
