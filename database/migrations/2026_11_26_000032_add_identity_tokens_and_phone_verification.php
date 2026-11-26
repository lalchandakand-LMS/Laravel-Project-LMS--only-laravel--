<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('id_token', 64)->nullable()->unique()->after('student_code');
        });

        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->string('id_token', 64)->nullable()->unique()->after('employee_code');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropUnique(['id_token']);
            $table->dropColumn('id_token');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropUnique(['id_token']);
            $table->dropColumn('id_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
    }
};
