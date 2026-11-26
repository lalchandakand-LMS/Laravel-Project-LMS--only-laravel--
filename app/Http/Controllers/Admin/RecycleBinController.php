<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use App\Models\Post;
use App\Models\Question;
use App\Models\Review;
use App\Models\Section;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RecycleBinController extends Controller
{
    /** Only explicitly supported soft-deletable models can be restored or purged here. */
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

    public function index(Request $request)
    {
        $type = $request->string('type')->toString();
        abort_if($type !== '' && ! array_key_exists($type, self::MODELS), 404);

        $items = collect();
        $counts = [];
        $actorIds = [];

        foreach (self::MODELS as $key => $class) {
            $query = $class::onlyTrashed()->latest('deleted_at');
            $counts[$key] = (clone $query)->count();

            if ($type !== '' && $type !== $key) {
                continue;
            }

            foreach ($query->limit(200)->get() as $record) {
                $items->push([
                    'type' => $key,
                    'label' => class_basename($class),
                    'record' => $record,
                    'deleted_at' => $record->deleted_at,
                    'deleted_by' => $record->deleted_by,
                ]);
                if ($record->deleted_by) {
                    $actorIds[] = $record->deleted_by;
                }
            }
        }

        $items = $items->sortByDesc(fn ($item) => $item['deleted_at'])->values();
        $actors = DB::table('users')->whereIn('id', array_unique($actorIds))->pluck('name', 'id');

        return view('admin.recycle-bin.index', [
            'items' => $items,
            'counts' => $counts,
            'type' => $type,
            'actors' => $actors,
            'total' => array_sum($counts),
        ]);
    }

    public function restore(string $type, int $id)
    {
        $class = $this->modelClass($type);
        $record = $class::onlyTrashed()->findOrFail($id);
        try {
            $record->restore();
        } catch (QueryException $exception) {
            report($exception);
            return back()->with('error', 'Restore blocked because a unique value is already in use. Resolve the duplicate record first.');
        }

        $this->afterRestore($record);

        return back()->with('success', class_basename($class).' restored successfully.');
    }

    public function bulkRestore(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(self::MODELS))],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $class = $this->modelClass($data['type']);
        $restored = $class::onlyTrashed()->whereIn('id', $data['ids'])->get();
        $restoredCount = 0;
        foreach ($restored as $record) {
            try {
                $record->restore();
                $this->afterRestore($record);
                $restoredCount++;
            } catch (QueryException $exception) {
                report($exception);
            }
        }

        return back()->with('success', $restoredCount.' item(s) restored. Any item with a conflicting unique value was skipped.');
    }

    public function destroy(string $type, int $id)
    {
        $class = $this->modelClass($type);
        $record = $class::onlyTrashed()->findOrFail($id);

        if ($this->hasProtectedRelations($type, $record->getKey())) {
            return back()->with('error', 'Permanent deletion blocked: this record still has linked learning, account, or transaction data. Keep it in the Recycle Bin or remove dependent records first.');
        }

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

        try {
            $record->forceDelete();
        } catch (QueryException $exception) {
            report($exception);
            return back()->with('error', 'Permanent deletion was blocked by a linked database record. The item remains safely in the Recycle Bin.');
        }

        foreach ($media as [$disk, $path]) {
            delete_stored_media($disk, $path);
        }

        return back()->with('success', class_basename($class).' permanently deleted.');
    }

    private function afterRestore($record): void
    {
        if ($record instanceof Question) {
            $exam = Exam::withTrashed()->find($record->exam_id);
            if ($exam) {
                $exam->update(['total_marks' => Question::where('exam_id', $exam->id)->sum('marks')]);
            }
        }
    }

    private function modelClass(string $type): string
    {
        abort_unless(array_key_exists($type, self::MODELS), 404);
        return self::MODELS[$type];
    }

    /** Prevent FK cascades from erasing learning history, accounts, or financial records. */
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
            'sections' => [['lessons', 'section_id']],
            'lessons' => [['lesson_progress', 'lesson_id'], ['exams', 'lesson_id'], ['assignments', 'lesson_id']],
            'exams' => [['questions', 'exam_id'], ['exam_attempts', 'exam_id']],
            'questions' => [['attempt_answers', 'question_id']],
            'ebooks' => [['ebook_purchases', 'ebook_id'], ['order_items', 'item_id']],
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
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                $query = DB::table($table)->where($column, $id);
                if ($table === 'order_items') {
                    $query->where('item_type', $type === 'ebooks' ? 'ebook' : 'course');
                }
                if ($query->exists()) {
                    return true;
                }
            }
        }

        return false;
    }
}
