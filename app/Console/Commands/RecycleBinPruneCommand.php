<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\FlashSale;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\LiveClass;
use App\Models\Level;
use App\Models\MenuItem;
use App\Models\Question;
use App\Models\Post;
use App\Models\Review;
use App\Models\Section;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RecycleBinPruneCommand extends Command
{
    protected $signature = 'recycle-bin:prune {--days= : Override the configured recycle-bin retention period}';
    protected $description = 'Permanently remove expired recycle-bin records only when no protected data depends on them';

    private const MODELS = [
        'users' => User::class,
        'courses' => Course::class,
        'categories' => Category::class,
        'sections' => Section::class,
        'lessons' => Lesson::class,
        'exams' => Exam::class,
        'questions' => Question::class,
        'ebooks' => Ebook::class,
        'live-classes' => LiveClass::class,
        'announcements' => Announcement::class,
        'reviews' => Review::class,
        'sliders' => Slider::class,
        'menu-items' => MenuItem::class,
        'coupons' => Coupon::class,
        'posts' => Post::class,
        'flash-sales' => FlashSale::class,
        'branches' => Branch::class,
        'batches' => Batch::class,
        'levels' => Level::class,
    ];

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('lms.recycle_bin_retention_days', 30));
        $days = max(1, min(3650, $days));
        $cutoff = now()->subDays($days);
        $deleted = 0;
        $protected = 0;
        $failed = 0;

        foreach (self::MODELS as $type => $class) {
            $class::onlyTrashed()->where('deleted_at', '<=', $cutoff)->chunkById(100, function ($records) use ($type, &$deleted, &$protected, &$failed) {
                foreach ($records as $record) {
                    if ($this->hasProtectedRelations($type, (int) $record->getKey())) {
                        $protected++;
                        continue;
                    }

                    try {
                        $record->forceDelete();
                        $this->deleteRecordMedia($record);
                        $deleted++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                    }
                }
            });
        }

        $this->info("Recycle Bin retention: {$days} days. Permanently removed: {$deleted}. Protected and retained: {$protected}. Failed safely: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function deleteRecordMedia($record): void
    {
        $media = match (true) {
            $record instanceof User => [['public', $record->avatar]],
            $record instanceof Course => [['public', $record->thumbnail]],
            $record instanceof Category => [['public', $record->image]],
            $record instanceof Lesson => [['local', $record->attachment]],
            $record instanceof Slider => [['public', $record->image]],
            $record instanceof Ebook => [['public', $record->cover], ['local', $record->file]],
            $record instanceof Post => [['public', $record->thumbnail]],
            default => [],
        };

        foreach ($media as [$disk, $path]) {
            delete_stored_media($disk, $path);
        }
    }

    /** Never cascade-delete accounts, financial records, learning history or exam attempts. */
    private function hasProtectedRelations(string $type, int $id): bool
    {
        $checks = match ($type) {
            'users' => [
                ['orders', 'user_id'], ['enrollments', 'user_id'], ['lesson_progress', 'user_id'],
                ['exam_attempts', 'user_id'], ['certificates', 'user_id'], ['ebook_purchases', 'user_id'],
                ['reviews', 'user_id'], ['courses', 'instructor_id'], ['branches', 'manager_id'],
                ['guardian_students', 'guardian_id'], ['guardian_students', 'student_id'],
            ],
            'courses' => [
                ['sections', 'course_id'], ['lessons', 'course_id'], ['enrollments', 'course_id'],
                ['exams', 'course_id'], ['reviews', 'course_id'], ['announcements', 'course_id'],
                ['certificates', 'course_id'], ['live_classes', 'course_id'], ['order_items', 'item_id'],
                ['assignments', 'course_id'], ['batches', 'course_id'], ['online_batch_enrollments', 'course_id'],
            ],
            'categories' => [['categories', 'parent_id'], ['courses', 'category_id'], ['posts', 'category_id']],
            'sections' => [['lessons', 'section_id']],
            'lessons' => [['lesson_progress', 'lesson_id'], ['exams', 'lesson_id'], ['assignments', 'lesson_id']],
            'exams' => [['questions', 'exam_id'], ['exam_attempts', 'exam_id']],
            'questions' => [['attempt_answers', 'question_id']],
            'ebooks' => [['ebook_purchases', 'ebook_id'], ['order_items', 'item_id']],
            'coupons' => [['orders', 'coupon_id']],
            'branches' => [
                ['users', 'branch_id'], ['orders', 'branch_id'], ['batches', 'branch_id'],
                ['coaching_batches', 'branch_id'], ['coaching_admissions', 'branch_id'],
                ['student_fee_invoices', 'branch_id'], ['fee_plans', 'branch_id'],
            ],
            'batches' => [['enrollments', 'batch_id'], ['online_batch_enrollments', 'batch_id'], ['live_classes', 'batch_id'], ['attendance_sessions', 'batch_id']],
            'levels' => [['courses', 'level_id'], ['batches', 'level_id'], ['coaching_batches', 'level_id']],
            'menu-items' => [['menu_items', 'parent_id']],
            default => [],
        };

        foreach ($checks as [$table, $column]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table)->where($column, $id);
            if ($table === 'order_items') {
                $query->where('item_type', $type === 'ebooks' ? 'ebook' : 'course');
            }
            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }
}
