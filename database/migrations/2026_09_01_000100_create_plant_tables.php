<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 10: pianta dello stabilimento (gemello digitale semplificato) e modello 3D delle macchine.
 * Coordinate in metri: x verso destra, z verso il basso della pianta, origine nell'angolo in alto a sinistra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            // Ubicazione rappresentata (sito, reparto...): le sue macchine sono quelle che si possono posizionare
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('width', 6, 2);
            $table->decimal('depth', 6, 2);
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('layout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_layout_id')->constrained()->cascadeOnDelete();
            // Una macchina compare in una sola pianta
            $table->foreignId('asset_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            // Zona disegnata sul pavimento (una linea, un'area): sotto-ubicazione o solo etichetta
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label', 80)->nullable();
            // Centro, dimensioni e rotazione (gradi, intorno al centro)
            $table->decimal('x', 7, 2);
            $table->decimal('z', 7, 2);
            $table->decimal('w', 6, 2);
            $table->decimal('d', 6, 2);
            $table->decimal('h', 5, 2)->nullable();
            $table->unsignedSmallInteger('rotation')->default(0);
            $table->timestamps();
            $table->index(['plant_layout_id', 'location_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->string('model_path', 255)->nullable()->after('notes');
            $table->string('model_name', 150)->nullable()->after('model_path');
            $table->unsignedInteger('model_size')->nullable()->after('model_name');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['model_path', 'model_name', 'model_size']);
        });
        Schema::dropIfExists('layout_items');
        Schema::dropIfExists('plant_layouts');
    }
};
