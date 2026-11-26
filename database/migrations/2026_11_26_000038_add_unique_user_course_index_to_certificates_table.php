<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        $duplicates = DB::table('certificates')
            ->select('user_id', 'course_id')
            ->groupBy('user_id', 'course_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add the unique user/course certificate index: duplicate certificates already exist. Review and reconcile duplicate student/course certificates before retrying this migration.'
            );
        }

        Schema::table('certificates', function (Blueprint $table) {
            $table->unique(['user_id', 'course_id'], 'certificates_user_course_unique');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique('certificates_user_course_unique');
        });
    }
};
