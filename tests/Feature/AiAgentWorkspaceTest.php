<?php

namespace Tests\Feature;

use App\Models\AiActionProposal;
use App\Models\AiAgentRun;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAgentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai_agents.enabled' => true,
            'ai_agents.api_key' => 'test-gemini-key',
            'ai_agents.model' => 'gemini-2.5-flash',
            'ai_agents.endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);
    }

    private function geminiResponse(array $payload)
    {
        return Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => json_encode($payload)]],
                ],
            ]],
        ], 200);
    }

    public function test_student_can_use_student_agent_and_run_is_audited(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->geminiResponse([
            'message' => 'A noun names a person, place, thing or idea.',
            'actions' => [],
        ])]);

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)
            ->post(route('ai-agent.ask'), [
                'agent_type' => 'student',
                'prompt' => 'Explain nouns with examples.',
            ])
            ->assertRedirect(route('ai-agent.index'));

        $this->assertDatabaseHas('ai_agent_runs', [
            'user_id' => $student->id,
            'agent_type' => 'student',
            'status' => 'completed',
            'response' => 'A noun names a person, place, thing or idea.',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
            && $request->hasHeader('x-goog-api-key', 'test-gemini-key'));
    }

    public function test_student_cannot_select_teacher_or_admin_agent(): void
    {
        Http::fake();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)
            ->post(route('ai-agent.ask'), [
                'agent_type' => 'teacher',
                'prompt' => 'Create an assignment.',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_teacher_agent_action_waits_for_human_approval_and_creates_only_a_draft(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Teacher AI Course',
            'slug' => 'teacher-ai-course',
            'price' => 1000,
            'is_published' => true,
            'instructor_id' => $teacher->id,
        ]);

        Http::fake(['generativelanguage.googleapis.com/*' => $this->geminiResponse([
            'message' => 'I prepared an assignment draft suggestion.',
            'actions' => [[
                'type' => 'create_assignment_draft',
                'payload' => [
                    'course_id' => $course->id,
                    'title' => 'AI Draft: Daily Writing',
                    'description' => 'A short writing practice.',
                    'instructions' => 'Write a paragraph about your daily routine.',
                    'total_marks' => 20,
                    'due_at' => null,
                ],
            ]],
        ])]);

        $this->actingAs($teacher)
            ->post(route('ai-agent.ask'), [
                'agent_type' => 'teacher',
                'prompt' => 'Prepare a short writing assignment for my course.',
            ])
            ->assertRedirect(route('ai-agent.index'));

        $proposal = AiActionProposal::firstOrFail();
        $this->assertSame('pending', $proposal->status);
        $this->assertSame(0, Assignment::count());

        $this->actingAs($teacher)
            ->post(route('ai-agent.approve', $proposal))
            ->assertRedirect(route('ai-agent.index'));

        $assignment = Assignment::firstOrFail();
        $this->assertSame('AI Draft: Daily Writing', $assignment->title);
        $this->assertFalse($assignment->is_published);
        $this->assertSame('executed', $proposal->fresh()->status);
        $this->assertSame($teacher->id, $proposal->fresh()->reviewed_by);

        $this->actingAs($teacher)
            ->post(route('ai-agent.approve', $proposal))
            ->assertStatus(409);
        $this->assertSame(1, Assignment::count());
    }

    public function test_teacher_agent_cannot_propose_assignment_for_another_teachers_course(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $otherTeacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Other Teacher Course',
            'slug' => 'other-teacher-course',
            'price' => 1000,
            'is_published' => true,
            'instructor_id' => $otherTeacher->id,
        ]);

        Http::fake(['generativelanguage.googleapis.com/*' => $this->geminiResponse([
            'message' => 'Draft prepared.',
            'actions' => [[
                'type' => 'create_assignment_draft',
                'payload' => [
                    'course_id' => $course->id,
                    'title' => 'Unauthorized draft',
                    'total_marks' => 10,
                ],
            ]],
        ])]);

        $this->actingAs($teacher)
            ->post(route('ai-agent.ask'), [
                'agent_type' => 'teacher',
                'prompt' => 'Create a draft in this course.',
            ])
            ->assertRedirect(route('ai-agent.index'));

        $this->assertSame(0, AiActionProposal::count());
        $this->assertSame(0, Assignment::count());
    }

    public function test_missing_ai_configuration_is_audited_without_calling_provider(): void
    {
        config(['ai_agents.enabled' => false]);
        Http::fake();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)
            ->from(route('ai-agent.index'))
            ->post(route('ai-agent.ask'), [
                'agent_type' => 'student',
                'prompt' => 'Help me practise English.',
            ])
            ->assertRedirect(route('ai-agent.index'))
            ->assertSessionHasErrors('prompt');

        $run = AiAgentRun::where('user_id', $student->id)->firstOrFail();
        $this->assertSame('failed', $run->status);
        Http::assertNothingSent();
    }

    public function test_student_cannot_approve_teacher_action_proposal(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $run = AiAgentRun::create([
            'user_id' => $student->id,
            'agent_type' => 'teacher',
            'prompt' => 'test',
            'status' => 'completed',
        ]);
        $proposal = AiActionProposal::create([
            'ai_agent_run_id' => $run->id,
            'user_id' => $student->id,
            'action_type' => 'create_assignment_draft',
            'payload' => ['course_id' => 1, 'title' => 'test', 'total_marks' => 10],
            'status' => 'pending',
        ]);

        $this->actingAs($student)
            ->post(route('ai-agent.approve', $proposal))
            ->assertForbidden();
    }
}
