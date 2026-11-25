<?php

namespace Tests\Feature;

use App\Models\AttemptAnswer;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\LiveClass;
use App\Models\Setting;
use App\Models\Order;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function student(): User
    {
        return User::where('email', 'student@lms.test')->firstOrFail();
    }

    public function test_public_pages_render(): void
    {
        $course = Course::first();

        $this->get('/')
            ->assertOk()
            ->assertSee("Alom's English Center")
            ->assertSee('Choose your learning path')
            ->assertSee('Featured courses')
            ->assertSee('Latest courses')
            ->assertSee('Main navigation');
        $this->get('/courses')->assertOk();
        $this->get('/courses/'.$course->slug)->assertOk()->assertSee($course->title);
        $this->get('/ebooks')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/verify/CERT-DEMO1234')->assertOk()->assertSee('verified');
    }

    public function test_homepage_slider_renders_accessible_controls_and_active_slide(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post('/admin/sliders', [
            'title' => 'Build your English confidence',
            'subtitle' => 'Learn with expert guidance.',
            'badge' => 'Admissions open',
            'button_text' => 'Explore courses',
            'button_url' => '/courses',
            'theme' => 'emerald',
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertRedirect('/admin/sliders');

        $this->get('/')
            ->assertOk()
            ->assertSee('aria-roledescription="carousel"', false)
            ->assertSee('aria-label="Featured learning highlights"', false)
            ->assertSee('data-hero-slide', false)
            ->assertSee('data-hero-dot', false)
            ->assertSee('Build your English confidence');
    }

    public function test_homepage_dynamic_sections_and_admin_controls_render(): void
    {
        $course = Course::firstOrFail();
        $teacher = User::factory()->create([
            'role' => 'instructor',
            'is_active' => true,
            'bio' => 'Experienced English language instructor.',
            'designation' => 'Senior English Teacher',
        ]);
        $course->update(['instructor_id' => $teacher->id]);

        LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Live speaking practice',
            'platform' => 'Google Meet',
            'meeting_url' => 'https://meet.google.com/example',
            'starts_at' => now()->addDay(),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);
        Exam::create([
            'course_id' => $course->id,
            'title' => 'English progress assessment',
            'duration_minutes' => 30,
            'total_marks' => 50,
            'pass_marks' => 25,
            'starts_at' => now()->addDays(2),
            'is_published' => true,
        ]);
        Setting::set('homepage_board_results_body', 'Verified board result: 10 students achieved GPA 5.');
        Setting::set('homepage_board_results_year', '2026');

        $this->get('/')
            ->assertOk()
            ->assertSee('Upcoming live classes')
            ->assertSee('Live speaking practice')
            ->assertSee('Exams & assessments')
            ->assertSee('English progress assessment')
            ->assertSee('Meet our teachers')
            ->assertSee('Senior English Teacher')
            ->assertSee('Verified board result: 10 students achieved GPA 5.')
            ->assertSee('Join an online or classroom batch');

        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('Homepage sections & content')
            ->assertSee('homepage_show_live_classes');
    }

    public function test_registration_welcome_message_uses_application_brand(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Demo Student',
            'email' => 'new-student@example.test',
            'phone' => '01700000000',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $response->assertRedirect('/my');
        $response->assertSessionHas('success', "Welcome to ".config('app.name')."!");
        $this->assertDatabaseHas('users', [
            'email' => 'new-student@example.test',
            'role' => 'student',
        ]);
    }

    public function test_student_dashboard_and_player(): void
    {
        $user = $this->student();
        $enrollment = Enrollment::where('user_id', $user->id)->firstOrFail();
        $course = $enrollment->course;
        $lesson = $course->lessons()->first();

        $this->actingAs($user)->get('/my')->assertOk();
        $this->actingAs($user)->get('/my/my-courses')->assertOk();
        $this->actingAs($user)->get('/my/orders')->assertOk();
        $this->actingAs($user)->get('/my/profile')->assertOk();
        $this->actingAs($user)->get('/my/learn/'.$course->slug)->assertRedirect();
        $this->actingAs($user)->get('/my/learn/'.$course->slug.'/'.$lesson->id)->assertOk()->assertSee($lesson->title);

        $this->actingAs($user)
            ->post('/my/learn/'.$course->slug.'/'.$lesson->id.'/complete')
            ->assertRedirect();
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id, 'lesson_id' => $lesson->id,
        ]);
    }

    public function test_student_cannot_open_paid_course_without_enrollment(): void
    {
        $user = User::where('email', 'student6@lms.test')->firstOrFail();
        $course = Course::whereDoesntHave('enrollments', fn ($q) => $q->where('user_id', $user->id))->firstOrFail();
        // This scenario is specifically a paid course, not a course with a public preview.
        $course->lessons()->update(['is_free_preview' => false]);

        $this->actingAs($user)->get('/my/learn/'.$course->slug)->assertForbidden();
    }

    public function test_cart_checkout_and_admin_verification_grants_access(): void
    {
        $user = $this->student();
        // Seeded enrollments are randomised, so pick a paid course the student does not own yet
        // whose effective price also satisfies the FLAT500 coupon minimum (>= 2000).
        $course = Course::whereDoesntHave('enrollments', fn ($q) => $q->where('user_id', $user->id))
            ->where('price', '>', 0)
            ->where(function ($q) {
                $q->where('discount_price', '>=', 2000)
                    ->orWhere(fn ($inner) => $inner->whereNull('discount_price')->where('price', '>=', 2000));
            })
            ->orderBy('id')
            ->firstOrFail();

        // Free course enrollment
        $free = Course::where('price', 0)->firstOrFail();
        $this->actingAs($user)->post('/enroll/free/'.$free->slug)->assertRedirect();
        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $free->id]);

        // Normalise the seeded coupon so the assertion never depends on random seed data.
        Coupon::where('code', 'FLAT500')->update([
            'min_amount' => 0, 'usage_limit' => 0, 'is_active' => true,
        ]);

        // Paid course through cart + coupon
        $this->actingAs($user)->post('/cart/add/course/'.$course->id)->assertRedirect('/cart');
        $this->actingAs($user)->post('/cart/coupon', ['code' => 'FLAT500'])->assertRedirect();
        $this->actingAs($user)->get('/cart')->assertOk()->assertSee($course->title);

        $this->actingAs($user)->post('/checkout', [
            'name' => 'Test Student', 'phone' => '01700000000', 'email' => $user->email,
        ])->assertRedirect();

        $order = Order::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame(500.0, (float) $order->discount);

        // Student submits payment
        $this->actingAs($user)->post('/checkout/success/'.$order->order_no.'/pay', [
            'gateway' => 'bkash', 'sender_number' => '01700000000', 'transaction_id' => 'TRX999',
        ])->assertRedirect();

        $payment = $order->payments()->firstOrFail();

        // Admin verifies -> enrollment created
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $this->actingAs($admin)->post('/admin/payments/'.$payment->id.'/verify', ['action' => 'verify'])->assertRedirect();

        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $course->id]);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_exam_attempt_is_scored_automatically(): void
    {
        $user = $this->student();
        $enrollment = Enrollment::where('user_id', $user->id)->firstOrFail();
        $exam = Exam::where('course_id', $enrollment->course_id)->firstOrFail();

        $this->actingAs($user)->get('/my/exams')->assertOk();
        $this->actingAs($user)->get('/my/exams/'.$exam->id)->assertOk();

        $this->actingAs($user)->post('/my/exams/'.$exam->id.'/start')->assertRedirect();
        $attempt = $exam->attempts()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->get('/my/attempts/'.$attempt->id)->assertOk();

        // Answer everything correctly
        $answers = [];
        foreach ($exam->questions as $question) {
            $answers[$question->id] = $question->options->firstWhere('is_correct', true)->id;
        }

        $this->actingAs($user)->post('/my/attempts/'.$attempt->id.'/submit', ['answers' => $answers])
            ->assertRedirect('/my/attempts/'.$attempt->id.'/result');

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertEquals($exam->total_marks, $attempt->score);
        $this->assertTrue($attempt->isPassed());

        $this->actingAs($user)->get('/my/attempts/'.$attempt->id.'/result')->assertOk()->assertSee('PASSED');
    }

    public function test_admin_panel_crud(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Revenue');
        $this->actingAs($admin)->get('/admin/categories')->assertOk();
        $this->actingAs($admin)->get('/admin/categories/create')->assertOk();
        $this->actingAs($admin)->post('/admin/categories', ['name' => 'Test Cat', 'is_active' => 1])
            ->assertRedirect('/admin/categories');
        $this->assertDatabaseHas('categories', ['name' => 'Test Cat']);

        $course = Course::first();
        $this->actingAs($admin)->get('/admin/courses')->assertOk();
        $this->actingAs($admin)->get('/admin/courses/'.$course->slug.'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/courses/create')->assertOk();

        $newCourse = $this->actingAs($admin)->post('/admin/courses', [
            'title' => 'Brand New Course', 'price' => 1000, 'level' => 'beginner',
            'language' => 'Bangla', 'is_published' => 1, 'has_certificate' => 1,
        ]);
        $created = Course::where('title', 'Brand New Course')->firstOrFail();

        // Curriculum builder
        $this->actingAs($admin)->get('/admin/courses/'.$created->slug.'/sections/create')->assertOk();
        $this->actingAs($admin)->post('/admin/courses/'.$created->slug.'/sections', ['title' => 'Intro', 'position' => 1])
            ->assertRedirect();
        $section = $created->sections()->firstOrFail();

        $this->actingAs($admin)->get('/admin/lessons/create?course_id='.$created->id)->assertOk();
        $this->actingAs($admin)->post('/admin/lessons', [
            'course_id' => $created->id, 'section_id' => $section->id, 'title' => 'Lesson 1',
            'type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'is_published' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('lessons', ['title' => 'Lesson 1', 'course_id' => $created->id]);

        // Exams + questions
        $this->actingAs($admin)->post('/admin/exams', [
            'title' => 'Test Exam', 'course_id' => $created->id, 'duration_minutes' => 10,
            'is_published' => 1, 'total_marks' => 0,
        ])->assertRedirect();
        $exam = Exam::where('title', 'Test Exam')->firstOrFail();
        $this->actingAs($admin)->get('/admin/exams/'.$exam->id.'/questions')->assertOk();
        $this->actingAs($admin)->post('/admin/exams/'.$exam->id.'/questions', [
            'question' => '2 + 2 = ?', 'type' => 'mcq', 'marks' => 1,
            'options' => ['3', '4', '5', '6'], 'correct' => 1,
        ])->assertRedirect();
        $createdQuestion = Question::where('question', '2 + 2 = ?')->firstOrFail();
        $this->assertSame(4, $createdQuestion->options()->count());
        $this->assertSame('4', $createdQuestion->options()->where('is_correct', true)->value('option_text'));
        $this->assertEquals(1, $exam->fresh()->total_marks);

        // Orders / students / settings
        $this->actingAs($admin)->get('/admin/orders')->assertOk();
        $this->actingAs($admin)->get('/admin/enrollments')->assertOk();
        $this->actingAs($admin)->get('/admin/students')->assertOk();
        $this->actingAs($admin)->get('/admin/reviews')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs($admin)->post('/admin/settings', ['site_name' => 'My LMS'])->assertRedirect();
        $this->assertSame('My LMS', setting('site_name'));
    }

    public function test_student_cannot_access_admin_area(): void
    {
        $this->actingAs($this->student())->get('/admin')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertRedirect('/login');
    }
}
