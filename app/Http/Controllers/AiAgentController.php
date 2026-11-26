<?php

namespace App\Http\Controllers;

use App\Models\AiActionProposal;
use App\Models\AiAgentRun;
use App\Models\Assignment;
use App\Models\Course;
use App\Services\GeminiAgentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AiAgentController extends Controller
{
    private const AGENTS = ['student', 'teacher', 'admin', 'support', 'learning'];

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
            $result = $client->generate($data['agent_type'], $data['prompt']);
            $run->update([
                'response' => $result['message'],
                'status' => 'completed',
                'model' => $result['model'] ?? config('ai_agents.model'),
            ]);

            if ($data['agent_type'] === 'teacher' && ($user->isInstructor() || $user->isAdmin())) {
                foreach ($result['actions'] as $action) {
                    $this->storeSafeProposal($run, $user, $action);
                }
            }

            return redirect()->route('ai-agent.index')->with('success', 'AI response received.');
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
        abort_unless($proposal->action_type === 'create_assignment_draft', 422);

        $payload = $proposal->payload;
        $validator = Validator::make($payload, [
            'course_id' => 'required|integer|exists:courses,id',
            'title' => 'required|string|max:180',
            'description' => 'nullable|string|max:10000',
            'instructions' => 'nullable|string|max:20000',
            'due_at' => 'nullable|date',
            'total_marks' => 'required|numeric|min:1|max:100000',
        ]);
        abort_if($validator->fails(), 422, 'The proposed assignment data is invalid.');

        $course = Course::findOrFail($payload['course_id']);
        abort_unless(auth()->user()->isAdmin() || (int) $course->instructor_id === (int) auth()->id(), 403);

        DB::transaction(function () use ($proposal, $payload, $course) {
            $assignment = Assignment::create([
                'course_id' => $course->id,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'instructions' => $payload['instructions'] ?? null,
                'due_at' => $payload['due_at'] ?? null,
                'total_marks' => $payload['total_marks'],
                'is_published' => false,
            ]);

            $proposal->update([
                'status' => 'executed',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'executed_at' => now(),
                'result' => ['assignment_id' => $assignment->id, 'title' => $assignment->title],
            ]);
        });

        return redirect()->route('ai-agent.index')->with('success', 'Approved action executed. An unpublished assignment draft was created.');
    }

    public function reject(AiActionProposal $proposal)
    {
        $this->authorizeProposal($proposal);
        abort_if($proposal->status !== 'pending', 409, 'This proposal has already been reviewed.');

        $proposal->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

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

    private function storeSafeProposal(AiAgentRun $run, $user, mixed $action): void
    {
        if (! is_array($action) || ($action['type'] ?? null) !== 'create_assignment_draft'
            || ! is_array($action['payload'] ?? null)) {
            return;
        }

        $validator = Validator::make($action['payload'], [
            'course_id' => 'required|integer|exists:courses,id',
            'title' => 'required|string|max:180',
            'description' => 'nullable|string|max:10000',
            'instructions' => 'nullable|string|max:20000',
            'due_at' => 'nullable|date',
            'total_marks' => 'required|numeric|min:1|max:100000',
        ]);

        if ($validator->fails()) {
            return;
        }

        $course = Course::find($action['payload']['course_id']);
        if (! $course || (! $user->isAdmin() && (int) $course->instructor_id !== (int) $user->id)) {
            return;
        }

        $run->proposals()->create([
            'user_id' => $user->id,
            'action_type' => 'create_assignment_draft',
            'payload' => $validator->validated(),
            'status' => 'pending',
        ]);
    }

    private function authorizeProposal(AiActionProposal $proposal): void
    {
        $user = auth()->user();
        abort_unless($user->isInstructor() || $user->isAdmin(), 403);
        abort_unless($proposal->user_id === $user->id || $user->isAdmin(), 403);
    }
}
