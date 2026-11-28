@extends('layouts.admin')
@section('title', $course->exists ? 'Edit course' : 'New course')
@section('heading', $course->exists ? 'Edit: '.$course->title : 'New course')
@section('actions')
    @if ($course->exists)
        <a href="{{ route('courses.show', $course->slug) }}" target="_blank" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600">Preview</a>
    @endif
@endsection
@section('content')

<form method="POST" enctype="multipart/form-data"
      action="{{ $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store') }}"
      class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
    @csrf
    @if ($course->exists) @method('PUT') @endif

    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">Course information</h3>
            <div class="mt-4 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Title *</label>
                    <input name="title" value="{{ old('title', $course->title) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Subtitle</label>
                    <input name="subtitle" value="{{ old('subtitle', $course->subtitle) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Description</label>
                    <textarea name="description" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('description', $course->description) }}</textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">What students will learn (one per line)</label>
                        <textarea name="outcomes" rows="5" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('outcomes', implode("\n", $course->outcomes ?? [])) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Requirements</label>
                        <textarea name="requirements" rows="5" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('requirements', $course->requirements) }}</textarea>
                    </div>
                </div>
            </div>
        </section>

        @if ($course->exists)
            {{-- CURRICULUM BUILDER --}}
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="font-bold text-slate-900">Curriculum</h3>
                    <a href="{{ route('admin.courses.sections.create', $course) }}"
                       class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white">+ Add section</a>
                </div>
                <div class="divide-y divide-slate-100" data-sortable-list data-sort-url="{{ route('admin.courses.sections.reorder', $course) }}" aria-label="Course sections">
                    @forelse ($course->sections as $section)
                        <div class="p-4" draggable="true" data-sort-item data-sort-id="{{ $section->id }}">
                            <div class="flex items-center gap-2">
                                <span class="cursor-grab select-none text-slate-400" aria-hidden="true">⠿</span>
                                <p class="font-semibold text-slate-800">{{ $section->position }}. {{ $section->title }}</p>
                                <div class="ml-auto flex items-center gap-3 text-xs">
                                    <a href="{{ route('admin.lessons.create', ['course_id' => $course->id, 'section_id' => $section->id]) }}" class="font-bold text-emerald-700">+ Lesson</a>
                                    <a href="{{ route('admin.sections.edit', $section) }}" class="text-slate-500">Edit</a>
                                    <form method="POST" action="{{ route('admin.sections.destroy', $section) }}" onsubmit="return confirm('Delete section and its lessons?')">
                                        @csrf @method('DELETE')<button class="text-rose-600">Delete</button>
                                    </form>
                                </div>
                            </div>
                            <ul class="mt-2 space-y-1 text-sm" data-sortable-list data-sort-url="{{ route('admin.sections.lessons.reorder', $section) }}" aria-label="Lessons in {{ $section->title }}">
                                @foreach ($section->lessons as $lesson)
                                    <li class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2" draggable="true" data-sort-item data-sort-id="{{ $lesson->id }}">
                                        <span class="cursor-grab select-none text-slate-400" aria-hidden="true">⠿</span>
                                        <span class="text-slate-500">{{ $lesson->type === 'video' ? '▶' : ($lesson->type === 'pdf' ? '📄' : '📖') }}</span>
                                        <span class="flex-1 text-slate-700">{{ $lesson->title }}</span>
                                        @if ($lesson->is_free_preview)<span class="rounded bg-emerald-100 px-1.5 text-[10px] font-bold text-emerald-700">PREVIEW</span>@endif
                                        <span class="text-xs text-slate-500">{{ $lesson->duration_minutes }}m</span>
                                        <a href="{{ route('admin.lessons.edit', $lesson) }}" class="text-xs font-semibold text-emerald-700">Edit</a>
                                        <form method="POST" action="{{ route('admin.lessons.destroy', $lesson) }}" onsubmit="return confirm('Delete lesson?')">
                                            @csrf @method('DELETE')<button class="text-xs font-semibold text-rose-600">Del</button>
                                        </form>
                                    </li>
                                @endforeach
                                @if (! $section->lessons->count())
                                    <li class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">No lessons in this section yet.</li>
                                @endif
                            </ul>
                        </div>
                    @empty
                        <p class="p-6 text-sm text-slate-600">No sections yet — add your first section to start building the curriculum.</p>
                    @endforelse
                </div>
            </section>

            {{-- EXAMS --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900">Exams</h3>
                    <a href="{{ route('admin.exams.create') }}" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white">+ Add exam</a>
                </div>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($course->exams as $exam)
                        <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                            <span>{{ $exam->title }} <span class="text-xs text-slate-500">({{ $exam->questions()->count() }} questions)</span></span>
                            <span class="flex gap-3 text-xs">
                                <a href="{{ route('admin.exams.questions.index', $exam) }}" class="font-bold text-emerald-700">Questions</a>
                                <a href="{{ route('admin.exams.edit', $exam) }}" class="text-slate-500">Edit</a>
                            </span>
                        </li>
                    @empty
                        <li class="text-slate-500">No exams yet.</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>

    {{-- SIDEBAR --}}
    <aside class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">Publish</h3>
            <div class="mt-4 space-y-3 text-sm">
                <label class="flex items-center gap-2 font-semibold text-slate-700"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $course->is_published)) class="rounded text-emerald-700"> Published</label>
                <label class="flex items-center gap-2 font-semibold text-slate-700"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $course->is_featured)) class="rounded text-emerald-700"> Featured on homepage</label>
                <label class="flex items-center gap-2 font-semibold text-slate-700"><input type="checkbox" name="has_certificate" value="1" @checked(old('has_certificate', $course->has_certificate ?? true)) class="rounded text-emerald-700"> Certificate on completion</label>
            </div>
            <button class="mt-5 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">{{ $course->exists ? 'Save changes' : 'Create course' }}</button>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">Organisation</h3>
            <div class="mt-4 space-y-3 text-sm">
                <div>
                    <label class="mb-1 block font-semibold text-slate-700">Course categories</label>
                    <select name="category_ids[]" multiple size="7" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(in_array($category->id, old('category_ids', $selectedCategoryIds ?: ($course->category_id ? [$course->category_id] : []))))>{{ $category->parent?->name ? $category->parent->name.' / ' : '' }}{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Select one or more categories. The first selected category remains the primary category for older integrations.</p>
                    <a href="{{ route('admin.categories.index') }}" class="mt-2 inline-block text-xs font-bold text-orange-700">Manage categories & subcategories →</a>
                </div>
                @if (auth()->user()->isAdmin())
                    <div>
                        <label class="mb-1 block font-semibold text-slate-700">Instructor</label>
                        <select name="instructor_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            <option value="">— none —</option>
                            @foreach ($instructors as $instructor)
                                <option value="{{ $instructor->id }}" @selected(old('instructor_id', $course->instructor_id) == $instructor->id)>{{ $instructor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block font-semibold text-slate-700">Managed level</label>
                        <select name="level_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            <option value="">— Use legacy level —</option>
                            @foreach ($levels as $managedLevel)
                                <option value="{{ $managedLevel->id }}" @selected(old('level_id', $course->level_id) == $managedLevel->id)>{{ $managedLevel->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block font-semibold text-slate-700">Legacy level (fallback)</label>
                        <select name="level" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            @foreach (['beginner', 'intermediate', 'advanced'] as $legacyLevel)
                                <option value="{{ $legacyLevel }}" @selected(old('level', $course->level) === $legacyLevel)>{{ ucfirst($legacyLevel) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block font-semibold text-slate-700">Language</label>
                        <input name="language" value="{{ old('language', $course->language ?? 'Bangla') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">Pricing & schedule</h3>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="mb-1 block font-semibold text-slate-700">Price (৳)</label>
                    <input type="number" step="0.01" name="price" value="{{ old('price', $course->price ?? 0) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                </div>
                <div>
                    <label class="mb-1 block font-semibold text-slate-700">Discount price</label>
                    <input type="number" step="0.01" name="discount_price" value="{{ old('discount_price', $course->discount_price) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                </div>
                <div>
                    <label class="mb-1 block font-semibold text-slate-700">Duration (min)</label>
                    <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $course->duration_minutes ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                </div>
                <div>
                    <label class="mb-1 block font-semibold text-slate-700">Seat limit</label>
                    <input type="number" name="seat_limit" value="{{ old('seat_limit', $course->seat_limit) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block font-semibold text-slate-700">Student access duration (days)</label>
                    <input type="number" name="access_duration_days" min="1" max="3650" value="{{ old('access_duration_days', $course->access_duration_days) }}" placeholder="Blank means lifetime access" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                    <p class="mt-1 text-xs text-slate-500">The countdown starts when the student's payment is fully verified and course access is activated.</p>
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block font-semibold text-slate-700">Batch starts at</label>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($course->starts_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">Media & SEO</h3>
            <div class="mt-4 space-y-3 text-sm">
                @if ($course->thumbnail)
                    <img src="{{ $course->thumbnail_url }}" class="h-24 w-full rounded-lg object-cover" alt="">
                @endif
                <div>
                    <label class="mb-1 block font-semibold text-slate-700">Thumbnail</label>
                    <input type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full text-xs">
                    <label class="mt-2 block text-xs font-semibold text-slate-600">Or paste a thumbnail URL</label>
                    <input type="url" name="thumbnail_url" value="{{ old('thumbnail_url', str_starts_with((string) $course->thumbnail, 'http') ? $course->thumbnail : '') }}" placeholder="https://example.com/course-thumbnail.webp" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @if(auth()->user()->isAdmin())
                        <button type="button" data-open-media-picker data-picker-url="{{ route('admin.media.index', ['picker' => 1, 'target' => 'thumbnail_url', 'type' => 'image']) }}" class="mt-1 text-xs font-semibold text-emerald-700">Choose from Media Library →</button>
                    @endif
                </div>
                <div>
                    <label class="mb-1 block font-semibold text-slate-700">Promo video URL</label>
                    <input name="promo_video" value="{{ old('promo_video', $course->promo_video) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                </div>
                <div>
                    <label class="mb-1 block font-semibold text-slate-700">Meta title</label>
                    <input name="meta_title" value="{{ old('meta_title', $course->meta_title) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                </div>
                <div>
                    <label class="mb-1 block font-semibold text-slate-700">Meta description</label>
                    <textarea name="meta_description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('meta_description', $course->meta_description) }}</textarea>
                </div>
            </div>
        </section>
    </aside>
</form>
@if ($course->exists)
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let dragged = null;
    document.querySelectorAll('[data-sortable-list]').forEach((list) => {
        list.addEventListener('dragstart', (event) => {
            const item = event.target.closest('[data-sort-item]');
            if (!item || !list.contains(item)) return;
            dragged = item;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.sortId);
            item.classList.add('opacity-40');
        });
        list.addEventListener('dragover', (event) => {
            if (!dragged || dragged.parentElement !== list) return;
            event.preventDefault();
            const target = event.target.closest('[data-sort-item]');
            if (!target || target === dragged || target.parentElement !== list) return;
            const box = target.getBoundingClientRect();
            list.insertBefore(dragged, event.clientY > box.top + box.height / 2 ? target.nextSibling : target);
        });
        list.addEventListener('dragend', () => {
            if (!dragged || dragged.parentElement !== list) { dragged = null; return; }
            dragged.classList.remove('opacity-40');
            const ids = [...list.querySelectorAll(':scope > [data-sort-item]')].map(item => Number(item.dataset.sortId));
            dragged = null;
            fetch(list.dataset.sortUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf || ''}, body:JSON.stringify({ordered_ids:ids})})
                .then(response => { if (!response.ok) throw new Error('Save failed'); window.location.reload(); })
                .catch(() => { alert('Could not save the new order. Reloading the saved curriculum.'); window.location.reload(); });
        });
    });
})();
</script>
@endif
@push('scripts')
<script>
(() => {
    let pickerWindow = null;
    document.querySelectorAll('[data-open-media-picker]').forEach(button => button.addEventListener('click', () => {
        pickerWindow = window.open(button.dataset.pickerUrl, 'lmsMediaPicker', 'width=1200,height=850,resizable=yes,scrollbars=yes');
        if (pickerWindow) pickerWindow.focus();
    }));
    window.addEventListener('message', event => {
        if (event.origin !== window.location.origin || event.source !== pickerWindow) return;
        if (!event.data || event.data.type !== 'lms-media-selected' || typeof event.data.url !== 'string') return;
        const allowed = ['thumbnail_url', 'image_url', 'cover_url', 'avatar_url'];
        if (!allowed.includes(event.data.target)) return;
        const field = document.querySelector('[name="' + event.data.target + '"]');
        if (!field) return;
        field.value = event.data.url;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        pickerWindow = null;
    });
})();
</script>
@endpush

@endsection