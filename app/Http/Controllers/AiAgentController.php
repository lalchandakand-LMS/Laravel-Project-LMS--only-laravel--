<?php

namespace App\Http\Controllers;

use App\Models\AiActionProposal;
use App\Models\AiAgentRun;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Branch;
use App\Models\Complaint;
use App\Models\ComplaintEvent;
use App\Models\Course;
use App\Models\User;
use App\Services\GeminiAgentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AiAgentController extends Controller
{
    private const AGENTS = ['student', 'teacher', 'admin', 'support', 'learning'];

    private const ACTIONS_BY_ROLE = [
        'student' => ['create_complaint_draft'],
        'guardian' => ['create_complaint_draft'],
        'instructor' => ['create_assignment_draft'],
        'branch_manager' => ['create_announcement_draft'],
        'admin' => ['create_assignment_draft', 'create_announcement_draft'],
    ];

    public function index()
    {
        $user = auth()->user();
        $runs = AiAgentRun::with('proposals')
            ->where('user_id', $user->id)
            ->latest()->take(15)->get();

        return view('ai-agents.index', [
            'runs' => $runs,
            'availableAgents' => $this->availableAgents($user->role),
            'configured' => (bool) config('ai_agents.enabled') && filled(config('ai_agents.api_key')),
        ]);
    }

    public function ask(Request $request, GeminiAgentClient $client)
    {
        $user = $request->user();
        $data = $request->validate([
            'agent_type' => 'required|in:'.implode(',', self::AGENTS),
            'prompt' => 'required|string|max:'.(int) config('ai_agents.max_prompt_length', 4000),
        ]);

        abort_unless(in_array($data['agent_type'], $this->availableAgents($user->role), true), 403);

        $run = AiAgentRun::create([
            'user_id' => $user->id,
            'agent_type' => $data['agent_type'],
            'prompt' => $data['prompt'],
            'status' => 'processing',
            'provider' => 'gemini',
            'model' => config('ai_agents.model'),
        ]);

        try {
            $result = $client->generate($data['agent_type'], $data['prompt'], $user->role);
            $run->update([
                'response' => $result['message'],
                'status' => 'completed',
                'model' => $result['model'] ?? config('ai_agents.model'),
            ]);

            foreach ($result['actions'] as $action) {
                $this->storeSafeProposal($run, $user, $action);
            }

            return redirect()->route('ai-agent.index')->with('success', 'AI response received. Review any suggested actions before approving them.');
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'metadata' => ['error' => $exception->getMessage()],
            ]);

            return redirect()->route('ai-agent.index')
                ->withErrors(['prompt' => $exception->getMessage()]);
        }
    }

    public function approve(AiActionProposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_if($proposal->status !== 'pending', 409, 'This proposal has already been reviewed.');

        $result = DB::transaction(function () use ($proposal) {
            $locked = AiActionProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'pending', 409, 'This proposal has already been reviewed.');

            $user = auth()->user();
            $payload = $locked->payload;

            $result = match ($locked->action_type) {
                'create_assignment_draft' => $this->executeAssignmentDraft($payload, $user),
                'create_complaint_draft' => $this->executeComplaintDraft($payload, $user),
                'create_announcement_draft' => $this->executeAnnouncementDraft($payload, $user),
                default => abort(422, 'This AI action type is not supported.'),
            };

            $locked->update([
                'status' => 'executed',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'executed_at' => now(),
                'result' => $result,
            ]);

            return $result;
        });

        return redirect()->route('ai-agent.index')->with('success', $result['message']);
    }

    public function reject(AiActionProposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_if($proposal->status !== 'pending', 409, 'This proposal has already been reviewed.');

        DB::transaction(function () use ($proposal) {
            $locked = AiActionProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'pending', 409, 'This proposal has already been reviewed.');

            $locked->update([
                'status' => 'rejected',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        });

        return redirect()->route('ai-agent.index')->with('success', 'AI action proposal rejected.');
    }

    private function availableAgents(string $role): array
    {
        return match ($role) {
            'admin' => self::AGENTS,
            'instructor' => ['teacher', 'learning', 'support'],
            'student' => ['student', 'learning', 'support'],
            'guardian', 'branch_manager' => ['support', 'learning'],
            default => [],
        };
    }

    private function storeSafeProposal(AiAgentRun $run, User $user, mixed $action): void
    {
        if (! is_array($action) || ! is_string($action['type'] ?? null)
            || ! in_array($action['type'], self::ACTIONS_BY_ROLE[$user->role] ?? [], true)
            || ! is_array($action['payload'] ?? null)) {
            return;
        }

        $type = $action['type'];
        $validator = match ($type) {
            'create_assignment_draft' => Validator::make($action['payload'], [
                'course_id' => 'required|integer|exists:courses,id',
                'title' => 'required|string|max:180',
                'description' => 'nullable|string|max:10000',
                'instructions' => 'nullable|string|max:20000',
                'due_at' => 'nullable|date|after:now',
                'total_marks' => 'required|numeric|min:1|max:100000',
            ]),
            'create_complaint_draft' => Validator::make($action['payload'], [
                'category' => 'required|in:teacher,class,course,payment,administration,technical,other',
                'subject' => 'required|string|max:180',
                'body' => 'required|string|min:10|max:10000',
            ]),
            'create_announcement_draft' => Validator::make($action['payload'], [
                'title' => 'required|string|max:180',
                'body' => 'required|string|max:30000',
                'audience_role' => 'nullable|in:student,guardian,instructor,branch_manager',
                'branch_id' => 'nullable|integer|exists:branches,id',
                'priority' => 'nullable|in:general,important,urgent',
            ]),
            default => null,
        };

        if (! $validator || $validator->fails()) {
            return;
        }

        $payload = $validator->validated();

        if ($type === 'create_assignment_draft') {
            $course = Course::find($payload['course_id']);
            if (! $course || (! $user->isAdmin() && (int) $course->instructor_id !== (int) $user->id)) {
                return;
            }
        }

        if ($type === 'create_announcement_draft') {
            if ($user->isBranchManager()) {
                $branch = Branch::forManager($user);
                if (! $branch || ! $branch->is_active) return;
                $payload['branch_id'] = $branch->id;
                $payload['audience_role'] = $payload['audience_role'] ?? 'student';
            } elseif ($user->isAdmin() && ! empty($payload['branch_id'])) {
                if (! Branch::whereKey($payload['branch_id'])->exists()) return;
            }
            $payload['priority'] = $payload['priority'] ?? 'general';
        }

        $run->proposals()->create([
            'user_id' => $user->id,
            'action_type' => $type,
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    private function executeAssignmentDraft(array $payload, User $user): array
    {
        abort_unless($user->isAdmin() || $user->isInstructor(), 403);
        $validator = Validator::make($payload, [
            'course_id' => 'required|integer|exists:courses,id',
            'title' => 'required|string|max:180',
            'description' => 'nullable|string|max:10000',
            'instructions' => 'nullable|string|max:20000',
            'due_at' => 'nullable|date|after:now',
            'total_marks' => 'required|numeric|min:1|max:100000',
        ]);
        if ($validator->fails()) throw new ValidationException($validator);

        $course = Course::findOrFail($payload['course_id']);
        abort_unless($user->isAdmin() || (int) $course->instructor_id === (int) $user->id, 403);

        $assignment = Assignment::create([
            'course_id' => $course->id,
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'instructions' => $payload['instructions'] ?? null,
            'due_at' => $payload['due_at'] ?? null,
            'total_marks' => $payload['total_marks'],
            'is_published' => false,
        ]);

        return ['record_id' => $assignment->id, 'record_type' => 'assignment', 'title' => $assignment->title,
            'message' => 'Approved: unpublished assignment draft created.'];
    }

    private function executeComplaintDraft(array $payload, User $user): array
    {
        abort_unless($user->isStudent() || $user->isGuardian(), 403);
        $validator = Validator::make($payload, [
            'category' => 'required|in:teacher,class,course,payment,administration,technical,other',
            'subject' => 'required|string|max:180',
            'body' => 'required|string|min:10|max:10000',
        ]);
        if ($validator->fails()) throw new ValidationException($validator);

        $complaint = Complaint::create([
            'tracking_code' => 'AEC-'.now()->format('ym').'-'.Str::upper(Str::random(8)),
            'user_id' => $user->id,
            'branch_id' => $user->branch_id,
            'category' => $payload['category'],
            'subject' => $payload['subject'],
            'body' => $payload['body'],
            'status' => 'submitted',
        ]);

        ComplaintEvent::create([
            'complaint_id' => $complaint->id,
            'created_by' => $user->id,
            'event_type' => 'submitted',
            'description' => 'Complaint submitted from an AI-prepared draft after user approval.',
        ]);

        return ['record_id' => $complaint->id, 'record_type' => 'complaint', 'title' => $complaint->subject,
            'tracking_code' => $complaint->tracking_code, 'message' => 'Approved: complaint submitted. Your tracking code is '.$complaint->tracking_code.'.'];
    }

    private function executeAnnouncementDraft(array $payload, User $user): array
    {
        abort_unless($user->isAdmin() || $user->isBranchManager(), 403);
        $validator = Validator::make($payload, [
            'title' => 'required|string|max:180',
            'body' => 'required|string|max:30000',
            'audience_role' => 'nullable|in:student,guardian,instructor,branch_manager',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'priority' => 'nullable|in:general,important,urgent',
        ]);
        if ($validator->fails()) throw new ValidationException($validator);

        $managedBranch = $user->isBranchManager() ? Branch::forManager($user) : null;
        $branchId = $user->isBranchManager() ? $managedBranch?->id : ($payload['branch_id'] ?? null);
        abort_if($user->isBranchManager() && (! $managedBranch || ! $managedBranch->is_active), 422, 'Your branch account is not linked to an active branch.');

        $announcement = Announcement::create([
            'branch_id' => $branchId,
            'title' => $payload['title'],
            'body' => $payload['body'],
            'audience_role' => $payload['audience_role'] ?? null,
            'priority' => $payload['priority'] ?? 'general',
            'is_published' => false,
            'is_pinned' => false,
        ]);

        return ['record_id' => $announcement->id, 'record_type' => 'announcement', 'title' => $announcement->title,
            'message' => 'Approved: unpublished announcement draft created. Review and publish it from the Notices manager.'];
    }

    private function authorizeProposal(AiActionProposal $proposal): void
    {
        $user = auth()->user();
        $isOwner = $proposal->user_id === $user->id;
        $isAdminReview = $user->isAdmin() && $proposal->action_type !== 'create_complaint_draft';
        abort_unless($isOwner || $isAdminReview, 403);
        // Include soft-deleted creators so their original role remains available
        // for authorization. A hard-deleted/missing creator is denied safely.
        $creatorRole = $proposal->user()->withTrashed()->value('role');
        abort_unless($creatorRole !== null
            && in_array($proposal->action_type, self::ACTIONS_BY_ROLE[$creatorRole] ?? [], true), 403);
    }
}
