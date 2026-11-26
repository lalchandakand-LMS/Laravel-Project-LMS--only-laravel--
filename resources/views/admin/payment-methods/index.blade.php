@extends('layouts.admin')
@section('title', 'Payment Methods')
@section('heading', 'Payment Methods')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <section class="rounded-2xl border border-orange-200 bg-orange-50 p-5">
        <h2 class="font-bold text-orange-950">Payment configuration</h2>
        <p class="mt-1 text-sm text-orange-900">Enable the payment methods your branches may offer. API credentials can be added later when you receive them. Secrets are encrypted before they are stored and are never shown again in this form.</p>
        <p class="mt-2 text-xs font-semibold text-orange-900">Important: Saving API credentials does not activate automatic payment collection. Provider adapters, callback verification and sandbox tests are still required before Live mode can process payments.</p>
    </section>

    <form method="POST" action="{{ route('admin.payment-methods.update') }}" class="space-y-6">
        @csrf
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-bold text-slate-900">Global payment methods</h2>
                    <p class="mt-1 text-sm text-slate-500">A method must be enabled here and selected in a branch before students can submit it.</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Global control</span>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($methods as $key => $label)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
                        <input type="checkbox" name="methods[]" value="{{ $key }}" @checked(in_array($key, $enabledMethods, true)) class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                        <span class="text-sm font-semibold text-slate-800">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        @foreach ($providers as $key => $provider)
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-6 py-4">
                    <div>
                        <h2 class="font-bold text-slate-900">{{ $provider['label'] }} API configuration</h2>
                        <p class="mt-1 text-xs text-slate-500">Add the values supplied by your payment provider when you receive API access.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($provider['saved'])
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Credentials saved</span>
                        @else
                            <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-700">Awaiting API details</span>
                        @endif
                    </div>
                </div>
                <div class="grid gap-4 p-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Environment</label>
                        <select name="providers[{{ $key }}][mode]" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm md:max-w-xs">
                            <option value="sandbox" @selected($provider['mode'] === 'sandbox')>Sandbox / Test</option>
                            <option value="live" disabled>Live — locked until integration is certified</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Live mode is intentionally locked. Credentials can be stored for preparation, but automatic collection cannot be enabled until the provider adapter and callback tests pass.</p>
                        @if (($provider['configured_mode'] ?? 'sandbox') === 'live')
                            <p class="mt-2 text-xs font-semibold text-rose-700">A previous Live selection was found. Saving this form will return the stored configuration to Sandbox.</p>
                        @endif
                    </div>
                    @foreach ($provider['fields'] as $field => $label)
                        @php $secret = in_array($field, ['app_secret', 'password', 'private_key', 'api_key', 'api_secret', 'webhook_secret'], true); @endphp
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">{{ $label }}</label>
                            <input
                                name="providers[{{ $key }}][credentials][{{ $field }}]"
                                type="{{ $secret ? 'password' : 'text' }}"
                                value=""
                                autocomplete="new-password"
                                spellcheck="false"
                                placeholder="{{ $provider['saved'] ? 'Saved securely — leave blank to keep current value' : 'Enter when API details are available' }}"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                            >
                        </div>
                    @endforeach
                    @if ($provider['saved'])
                        <label class="flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 md:col-span-2">
                            <input type="checkbox" name="providers[{{ $key }}][clear_credentials]" value="1" class="rounded border-rose-300">
                            Clear all saved {{ $provider['label'] }} API credentials
                        </label>
                    @endif
                </div>
            </section>
        @endforeach

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Branch-specific account numbers and instructions remain editable under Admin → Branches.</p>
            <button type="submit" class="rounded-xl bg-orange-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-orange-700">Save payment settings</button>
        </div>
    </form>
</div>
@endsection
