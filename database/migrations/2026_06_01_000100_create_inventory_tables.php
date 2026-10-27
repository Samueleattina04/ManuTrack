<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Magazzino ricambi: magazzini, ricambi, giacenze per magazzino, movimenti,
 * fornitori (con codici e prezzi per ricambio) e distinta ricambi per macchina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 20)->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('contact_name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 190)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('category', 80)->nullable()->index();
            $table->string('unit', 10)->default('pz');
            $table->string('manufacturer', 120)->nullable();
            $table->string('manufacturer_code', 80)->nullable()->index();
            $table->string('description', 1000)->nullable();
            // Costo medio ponderato, aggiornato a ogni carico
            $table->decimal('unit_cost', 14, 4)->default(0);
            // Scorta minima e quantità da riordinare, sul totale dei magazzini
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->decimal('reorder_qty', 12, 2)->nullable();
            // Totale di tutti i magazzini (copia di stock_levels per elenchi e filtri veloci)
            $table->decimal('stock_total', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            // Scaffale o posizione nel magazzino (es. "A-03-2")
            $table->string('bin', 40)->nullable();
            $table->timestamps();
            $table->unique(['part_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            // carico, prelievo, reso, rettifica, trasferimento
            $table->string('type', 15)->index();
            // Con segno: positivo entra, negativo esce
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->decimal('balance_after', 12, 2);
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Le due righe di un trasferimento condividono lo stesso codice
            $table->uuid('transfer_group')->nullable()->index();
            $table->string('reference', 80)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['part_id', 'created_at']);
            $table->index(['work_order_id', 'part_id']);
        });

        Schema::create('part_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_code', 80)->nullable();
            $table->decimal('price', 14, 4)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();
            $table->unique(['part_id', 'supplier_id']);
        });

        Schema::create('asset_part', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['asset_id', 'part_id']);
        });
    }

    public function down(): void
    {
        foreach (['asset_part', 'part_supplier', 'stock_movements', 'stock_levels', 'parts', 'suppliers', 'warehouses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
