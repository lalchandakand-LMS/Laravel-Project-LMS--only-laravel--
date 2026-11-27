@extends(auth()->user()->isStudent() ? 'layouts.student' : ((auth()->user()->isInstructor() || auth()->user()->isGuardian() || auth()->user()->isBranchManager()) ? 'layouts.portal' : 'layouts.admin'))
@section('title', 'AI Agent Workspace')
@section('heading', 'AI Agent Workspace')
@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
        <p class="font-bold">AI assistance with human-controlled actions</p>
        <p class="mt-1">AI may prepare role-specific drafts. Nothing is submitted, published, sent or changed until you review and approve it. Do not include passwords, payment PINs, OTPs or sensitive personal information.</p>
    </div>

    @unless ($configured)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            AI Agents are not configured yet. Add <code>GEMINI_API_KEY</code> and set <code>AI_AGENTS_ENABLED=true</code> in the server environment, then clear the Laravel config cache.
        </div>
    @endunless

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-extrabold text-slate-900">Ask an AI agent</h2>
        <form method="POST" action="{{ route('ai-agent.ask') }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label for="agent_type" class="mb-1 block text-sm font-semibold text-slate-700">Choose agent</label>
                <select id="agent_type" name="agent_type" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    @foreach ($availableAgents as $agent)
                        <option value="{{ $agent }}" @selected(old('agent_type') === $agent)>{{ ucfirst($agent) }} Agent</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="prompt" class="mb-1 block text-sm font-semibold text-slate-700">Your question or task</label>
                <textarea id="prompt" name="prompt" rows="5" maxlength="{{ (int) config('ai_agents.max_prompt_length', 4000) }}" required
                          class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                          placeholder="Ask for help or request a draft action that your role is allowed to perform...">{{ old('prompt') }}</textarea>
                @error('prompt')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <button @disabled(! $configured) class="rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Send to AI agent</button>
        </form>
    </section>

    <section class="space-y-4">
        <h2 class="text-lg font-extrabold text-slate-900">Recent agent activity</h2>
        @forelse ($runs as $run)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">{{ ucfirst($run->agent_type) }} Agent</span>
                    <span class="text-xs font-semibold text-slate-500">{{ ucfirst($run->status) }} · {{ $run->created_at->format('d M Y, h:i A') }}</span>
                </div>
                <p class="mt-4 whitespace-pre-line text-sm font-semibold text-slate-800">{{ $run->prompt }}</p>
                @if ($run->response)
                    <div class="mt-3 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-700">{{ $run->response }}</div>
                @elseif ($run->status === 'failed')
                    <p class="mt-3 text-sm text-rose-600">The AI request could not be completed. Check the configuration and try again.</p>
                @endif

                @foreach ($run->proposals as $proposal)
                    @php
                        $payload = $proposal->payload ?? [];
                        $actionLabel = match ($proposal->action_type) {
                            'create_assignment_draft' => 'Create assignment draft',
                            'create_complaint_draft' => 'Submit complaint',
                            'create_announcement_draft' => 'Create announcement draft',
                            default => 'Unsupported action',
                        };
                    @endphp
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-bold text-amber-950">Human approval required: {{ $actionLabel }}</p>
                            <span class="text-xs font-bold uppercase text-amber-800">{{ $proposal->status }}</span>
                        </div>
                        <p class="mt-2 text-sm font-semibold text-amber-950">{{ $payload['title'] ?? $payload['subject'] ?? 'Draft action' }}</p>
                        @if ($proposal->action_type === 'create_assignment_draft')
                            <p class="mt-1 text-xs text-amber-800">Course ID: {{ $payload['course_id'] ?? '—' }} · Marks: {{ $payload['total_marks'] ?? '—' }}</p>
                        @elseif ($proposal->action_type === 'create_complaint_draft')
                            <p class="mt-1 text-xs text-amber-800">Category: {{ ucfirst($payload['category'] ?? 'other') }}</p>
                        @elseif ($proposal->action_type === 'create_announcement_draft')
                            <p class="mt-1 text-xs text-amber-800">Audience: {{ ucfirst($payload['audience_role'] ?? 'all applicable users') }} · Priority: {{ ucfirst($payload['priority'] ?? 'general') }} · Branch: {{ $payload['branch_id'] ?? 'All branches' }}</p>
                        @endif
                        @if (! empty($payload['body']) || ! empty($payload['description']) || ! empty($payload['instructions']))
                            <p class="mt-2 whitespace-pre-line text-sm text-amber-950">{{ $payload['body'] ?? $payload['description'] ?? $payload['instructions'] }}</p>
                        @endif
                        @if ($proposal->status === 'pending')
                            <div class="mt-3 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('ai-agent.approve', $proposal) }}">
                                    @csrf
                                    <button class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Approve & execute draft</button>
                                </form>
                                <form method="POST" action="{{ route('ai-agent.reject', $proposal) }}">
                                    @csrf
                                    <button class="rounded-lg border border-amber-300 px-4 py-2 text-xs font-bold text-amber-900">Reject</button>
                                </form>
                            </div>
                        @elseif ($proposal->status === 'executed')
                            <p class="mt-2 text-xs font-semibold text-emerald-800">
                                {{ $proposal->result['message'] ?? 'Approved action completed.' }}
                                @if (! empty($proposal->result['tracking_code'])) · Tracking: {{ $proposal->result['tracking_code'] }} @endif
                                @if (! empty($proposal->result['record_id'])) · Record #{{ $proposal->result['record_id'] }} @endif
                            </p>
                        @elseif ($proposal->status === 'rejected')
                            <p class="mt-2 text-xs text-amber-800">This suggestion was rejected and no LMS record was created.</p>
                        @endif
                    </div>
                @endforeach
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No AI agent activity yet.</div>
        @endforelse
    </section>
</div>
@endsection
