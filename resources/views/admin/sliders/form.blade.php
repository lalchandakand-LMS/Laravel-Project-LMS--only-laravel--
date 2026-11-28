@extends('layouts.admin')
@section('title', $slider->exists ? 'Edit slide' : 'New slide')
@section('heading', $slider->exists ? 'Edit slide' : 'New slide')
@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $slider->exists ? route('admin.sliders.update', $slider) : route('admin.sliders.store') }}"
      class="max-w-3xl space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($slider->exists) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Badge (small label)</label>
            <input name="badge" value="{{ old('badge', $slider->badge) }}" placeholder="Admission 2025" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Colour theme</label>
            <select name="theme" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                @foreach ($themes as $value => $label)
                    <option value="{{ $value }}" @selected(old('theme', $slider->theme) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1 block text-sm font-semibold text-slate-700">Headline</label>
            <input name="title" value="{{ old('title', $slider->title) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1 block text-sm font-semibold text-slate-700">Sub headline</label>
            <textarea name="subtitle" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('subtitle', $slider->subtitle) }}</textarea>
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Button text</label>
            <input name="button_text" value="{{ old('button_text', $slider->button_text) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Button link</label>
            <input name="button_url" value="{{ old('button_url', $slider->button_url) }}" placeholder="/courses" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Sort order</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $slider->sort_order) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Banner image (recommended 1920 × 720; optional if using an image URL)</label>
            <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <label class="mt-2 block text-xs font-semibold text-slate-600">Or paste an image URL</label>
            <input type="url" name="image_url" value="{{ old('image_url', str_starts_with((string) $slider->image, 'http') ? $slider->image : '') }}" placeholder="https://example.com/learning-banner.webp" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button type="button" data-open-media-picker data-picker-url="{{ route('admin.media.index', ['picker' => 1, 'target' => 'image_url', 'type' => 'image']) }}" class="mt-1 text-xs font-semibold text-emerald-700">Choose from Media Library →</button>
            @if ($slider->imageUrl())
                <img src="{{ $slider->imageUrl() }}" class="mt-2 aspect-[16/5] w-full rounded-lg border border-slate-200 object-cover" alt="Current banner preview">
            @endif
            <p class="mt-1 text-xs text-slate-500">Use a ready-made promotional banner (JPG, PNG, WebP or GIF, max 4 MB). The public slider displays the image as the complete banner card without overlay headline or CTA text.</p>
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $slider->exists ? $slider->is_active : true)) class="rounded text-emerald-700"> Publish this slide
    </label>

    <div class="flex gap-2">
        <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save slide</button>
        <a href="{{ route('admin.sliders.index') }}" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600">Cancel</a>
    </div>
</form>
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