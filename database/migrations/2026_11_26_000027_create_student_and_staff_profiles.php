<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('student_code', 40)->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 30)->nullable();
            $table->string('school_name', 180)->nullable();
            $table->string('academic_class', 80)->nullable();
            $table->string('academic_roll', 60)->nullable();
            $table->string('academic_session', 40)->nullable();
            $table->string('academic_group', 80)->nullable();
            $table->string('present_address', 500)->nullable();
            $table->string('permanent_address', 500)->nullable();
            $table->string('emergency_contact_name', 160)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_code', 40)->nullable()->unique();
            $table->string('department', 120)->nullable();
            $table->string('designation', 120)->nullable();
            $table->string('employment_type', 30)->nullable();
            $table->date('joining_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->unsignedSmallInteger('experience_years')->nullable();
            $table->string('highest_qualification', 180)->nullable();
            $table->string('institution', 180)->nullable();
            $table->string('employment_status', 24)->default('active')->index();
            $table->text('professional_bio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('student_profiles');
    }
};
