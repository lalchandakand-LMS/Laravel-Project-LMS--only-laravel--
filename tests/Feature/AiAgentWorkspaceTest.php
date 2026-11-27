<?php

namespace Tests\Feature;

use App\Models\AiActionProposal;
use App\Models\AiAgentRun;
use App\Models\Assignment;
use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Complaint;
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

    public function test_student_and_guardian_can_create_and_approve_complaint_drafts(): void
    {
        foreach (['student', 'guardian'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $payload = [
                'message' => 'Complaint draft ready.',
                'actions' => [[
                    'type' => 'create_complaint_draft',
                    'payload' => [
                        'category' => $role === 'student' ? 'class' : 'administration',
                        'subject' => $role === 'student' ? 'Class schedule issue' : 'Guardian meeting request',
                        'body' => 'The class schedule conflicts with the published teacher announcement and needs review.',
                    ],
                ]],
            ];
            Http::fake(['generativelanguage.googleapis.com/*' => $this->geminiResponse($payload)]);

            $this->actingAs($user)->post(route('ai-agent.ask'), [
                'agent_type' => $role === 'student' ? 'student' : 'support',
                'prompt' => 'Prepare a complaint draft.',
            ])->assertRedirect(route('ai-agent.index'));

            $proposal = AiActionProposal::where('user_id', $user->id)->firstOrFail();
            $this->assertSame('create_complaint_draft', $proposal->action_type);
            $this->assertSame('pending', $proposal->status);
            $this->assertSame(Complaint::where('user_id', '!=', $user->id)->count(), Complaint::count());

            $this->actingAs($user)->post(route('ai-agent.approve', $proposal))
                ->assertRedirect(route('ai-agent.index'));

            $complaint = Complaint::where('user_id', $user->id)->firstOrFail();
            $this->assertSame('submitted', $complaint->status);
            $this->assertDatabaseHas('complaint_events', [
                'complaint_id' => $complaint->id, 'created_by' => $user->id, 'event_type' => 'submitted',
            ]);
            $this->assertSame('executed', $proposal->fresh()->status);
        }
    }

    public function test_branch_manager_can_only_create_announcement_drafts_for_their_own_branch(): void
    {
        $branch = Branch::create(['name' => 'North AI Branch', 'code' => 'NORTH-AI', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'South AI Branch', 'code' => 'SOUTH-AI', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id, 'is_active' => true]);

        Http::fake(['generativelanguage.googleapis.com/*' => $this->geminiResponse(['message' => 'Branch notice draft ready.', 'actions' => [[
            'type' => 'create_announcement_draft',
            'payload' => [
                'title' => 'Branch schedule update',
                'body' => 'The branch class schedule will be updated next week.',
                'audience_role' => 'student',
                'branch_id' => $otherBranch->id,
                'priority' => 'important',
            ],
        ]]])]);

        $this->actingAs($manager)->post(route('ai-agent.ask'), [
            'agent_type' => 'support', 'prompt' => 'Prepare a notice for my branch students.',
        ])->assertRedirect(route('ai-agent.index'));

        $proposal = AiActionProposal::where('user_id', $manager->id)->firstOrFail();
        $this->assertSame($branch->id, $proposal->payload['branch_id']);
        $this->assertSame(0, Announcement::count());

        $this->actingAs($manager)->post(route('ai-agent.approve', $proposal))
            ->assertRedirect(route('ai-agent.index'));

        $announcement = Announcement::firstOrFail();
        $this->assertSame($branch->id, $announcement->branch_id);
        $this->assertFalse($announcement->is_published);
        $this->assertSame('executed', $proposal->fresh()->status);
    }

    public function test_admin_can_create_an_announcement_draft_without_auto_publishing(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Http::fake(['generativelanguage.googleapis.com/*' => $this->geminiResponse(['message' => 'Announcement draft ready.', 'actions' => [[
            'type' => 'create_announcement_draft',
            'payload' => ['title' => 'Exam registration reminder', 'body' => 'Please review the examination registration dates.', 'audience_role' => 'student', 'priority' => 'important'],
        ]]])]);

        $this->actingAs($admin)->post(route('ai-agent.ask'), [
            'agent_type' => 'admin', 'prompt' => 'Prepare an announcement for students.',
        ])->assertRedirect(route('ai-agent.index'));

        $proposal = AiActionProposal::where('user_id', $admin->id)->firstOrFail();
        $this->assertSame('create_announcement_draft', $proposal->action_type);

        $this->actingAs($admin)->post(route('ai-agent.approve', $proposal))
            ->assertRedirect(route('ai-agent.index'));

        $announcement = Announcement::firstOrFail();
        $this->assertFalse($announcement->is_published);
        $this->assertSame('student', $announcement->audience_role);
    }

    public function test_student_cannot_smuggle_an_action_reserved_for_another_role(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Http::fake(['generativelanguage.googleapis.com/*' => $this->geminiResponse(['message' => 'No action available.', 'actions' => [[
            'type' => 'create_assignment_draft',
            'payload' => ['course_id' => 1, 'title' => 'Unauthorized assignment', 'total_marks' => 10],
        ]]])]);

        $this->actingAs($student)->post(route('ai-agent.ask'), [
            'agent_type' => 'student', 'prompt' => 'Create an assignment.',
        ])->assertRedirect(route('ai-agent.index'));

        $this->assertSame(0, AiActionProposal::count());
        $this->assertSame(0, Assignment::count());
    }

    public function test_user_cannot_approve_another_users_action_proposal(): void
    {
        $owner = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $run = AiAgentRun::create(['user_id' => $owner->id, 'agent_type' => 'student', 'prompt' => 'Prepare complaint', 'status' => 'completed']);
        $proposal = AiActionProposal::create([
            'ai_agent_run_id' => $run->id, 'user_id' => $owner->id, 'action_type' => 'create_complaint_draft',
            'payload' => ['category' => 'other', 'subject' => 'Private request', 'body' => 'This private request belongs to another student account.'],
            'status' => 'pending',
        ]);

        $this->actingAs($other)->post(route('ai-agent.approve', $proposal))->assertForbidden();
        $this->assertSame('pending', $proposal->fresh()->status);
        $this->assertSame(0, Complaint::count());
    }


    public function test_admin_cannot_approve_or_reject_a_proposal_after_its_owner_is_soft_deleted(): void
    {
        $owner = User::factory()->create([
            'role' => 'branch_manager',
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $run = AiAgentRun::create([
            'user_id' => $owner->id,
            'agent_type' => 'support',
            'prompt' => 'Prepare a branch announcement.',
            'status' => 'completed',
        ]);
        $proposal = AiActionProposal::create([
            'ai_agent_run_id' => $run->id,
            'user_id' => $owner->id,
            'action_type' => 'create_announcement_draft',
            'payload' => [
                'title' => 'Branch closure notice',
                'body' => 'The branch will be closed for a public holiday.',
            ],
            'status' => 'pending',
        ]);

        $owner->delete();

        $this->actingAs($admin)
            ->post(route('ai-agent.approve', $proposal))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('ai-agent.reject', $proposal))
            ->assertForbidden();

        $this->assertSame('pending', $proposal->fresh()->status);
        $this->assertSame(0, Announcement::count());
    }

}
