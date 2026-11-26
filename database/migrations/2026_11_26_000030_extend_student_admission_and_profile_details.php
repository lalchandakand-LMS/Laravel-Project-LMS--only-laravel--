<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coaching_admissions', function (Blueprint $table) {
            $table->date('student_date_of_birth')->nullable();
            $table->string('student_gender', 30)->nullable();
            $table->string('school_name', 180)->nullable();
            $table->string('academic_class', 80)->nullable();
            $table->string('academic_roll', 60)->nullable();
            $table->string('academic_session', 40)->nullable();
            $table->string('academic_group', 80)->nullable();
            $table->string('present_address', 500)->nullable();
            $table->string('permanent_address', 500)->nullable();
            $table->string('quota_type', 80)->nullable();
            $table->text('quota_notes')->nullable();
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('guardian_name', 160)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('guardian_email')->nullable();
            $table->string('quota_type', 80)->nullable();
            $table->text('quota_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn(['guardian_name', 'guardian_phone', 'guardian_email', 'quota_type', 'quota_notes']);
        });

        Schema::table('coaching_admissions', function (Blueprint $table) {
            $table->dropColumn([
                'student_date_of_birth', 'student_gender', 'school_name', 'academic_class',
                'academic_roll', 'academic_session', 'academic_group', 'present_address',
                'permanent_address', 'quota_type', 'quota_notes',
            ]);
        });
    }
};
