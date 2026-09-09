<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule', 30)->nullable()->unique()->after('id');
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->enum('organization_type', ['internal', 'external'])->default('internal')->after('phone');
            $table->string('external_organization', 200)->nullable()->after('organization_type');
            $table->boolean('is_active')->default(true)->after('external_organization');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['matricule', 'first_name', 'last_name', 'phone',
                'organization_type', 'external_organization', 'is_active', 'last_login_at']);
        });
    }
};
