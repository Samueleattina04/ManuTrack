<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impianti: ubicazioni (sito > area > linea), categorie, macchine,
 * contatori con le letture e allegati (foto e documenti).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('type', 10);
            $table->string('name', 120);
            $table->string('code', 30)->nullable();
            $table->string('description', 500)->nullable();
            // Antenati compreso se stesso, es. "/1/4/9/": per cercare un ramo intero
            $table->string('path', 255)->default('/')->index();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('icon', 40)->default('factory');
            // Forma generica per la vista 3D dello stabilimento (fase 10)
            $table->string('shape', 20)->default('box');
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->foreignId('asset_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('assets')->restrictOnDelete();
            $table->string('serial_number', 80)->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->date('installed_on')->nullable();
            $table->char('criticality', 1)->default('B');
            $table->string('status', 20)->default('operativa')->index();
            $table->timestamp('status_changed_at')->nullable();
            // Codice casuale stampato nel QR: non rivela l'id e si può rigenerare
            $table->string('qr_token', 40)->unique();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('unit', 20);
            // Contatore progressivo (ore, cicli): una lettura non può essere minore della precedente
            $table->boolean('cumulative')->default(true);
            $table->decimal('current_value', 14, 2)->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['asset_id', 'name']);
        });

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 14, 2);
            $table->timestamp('read_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 10)->default('manuale');
            // Contatore sostituito o azzerato: da qui si riparte da un valore più basso
            $table->boolean('is_reset')->default(false);
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['meter_id', 'read_at']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('kind', 12);
            $table->string('disk', 20);
            $table->string('path', 255);
            $table->string('original_name', 200);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->json('variants')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('meters');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('locations');
    }
};
