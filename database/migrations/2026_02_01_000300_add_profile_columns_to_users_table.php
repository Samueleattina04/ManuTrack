<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 120)->nullable()->after('email');
            $table->string('phone', 40)->nullable()->after('job_title');
            $table->string('locale', 10)->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('locale')->index();
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('deactivated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'phone', 'locale', 'is_active', 'deactivated_at', 'last_login_at']);
        });
    }
};
