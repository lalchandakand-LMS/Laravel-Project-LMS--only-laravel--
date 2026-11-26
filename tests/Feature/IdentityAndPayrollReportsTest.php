<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\StaffPayroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdentityAndPayrollReportsTest extends TestCase
{
    use RefreshDatabase;

    private function configureSms(): void
    {
        config([
            'live_classes.sms.endpoint' => 'https://sms.example.test/send',
            'live_classes.sms.username' => 'test-user',
            'live_classes.sms.api_key' => 'test-key',
            'live_classes.sms.sender_name' => 'ALOMS',
        ]);
        Http::fake(['https://sms.example.test/send' => Http::response([
            'statusCode' => '200',
            'status' => 'success',
            'trxnId' => 'SMS-TEST-1',
        ], 200)]);
    }

    public function test_user_can_verify_saved_bangladesh_mobile_with_one_time_sms_code(): void
    {
        $student = User::factory()->create(['role' => 'student', 'phone' => '01712345678']);
        $this->configureSms();

        $this->actingAs($student)->post(route('account.phone.send'))->assertRedirect();
        $otp = null;
        Http::assertSent(function (HttpRequest $request) use (&$otp) {
            preg_match('/code is ([0-9]{6})/', $request['message'], $matches);
            $otp = $matches[1] ?? null;
            return $request['mobileNumber'] === '8801712345678' && $otp !== null;
        });
        $this->assertNotNull($otp);

        $this->post(route('account.phone.verify'), ['otp' => $otp])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull($student->fresh()->phone_verified_at);
        $this->assertFalse(Cache::has('phone-verification:'.$student->id));
    }

    public function test_phone_otp_is_limited_and_invalid_attempts_do_not_verify_the_account(): void
    {
        $student = User::factory()->create(['role' => 'student', 'phone' => '01712345678']);
        $this->configureSms();

        $this->actingAs($student)->post(route('account.phone.send'))->assertRedirect();
        $actualOtp = null;
        Http::assertSent(function (HttpRequest $request) use (&$actualOtp) {
            preg_match('/code is ([0-9]{6})/', $request['message'], $matches);
            $actualOtp = $matches[1] ?? null;
            return $actualOtp !== null;
        });
        $wrongOtp = $actualOtp === '999999' ? '888888' : '999999';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('account.phone.verify'), ['otp' => $wrongOtp])->assertSessionHasErrors('otp');
        }

        $this->post(route('account.phone.verify'), ['otp' => $wrongOtp])->assertSessionHasErrors('otp');
        $this->assertNull($student->fresh()->phone_verified_at);
        $this->assertFalse(Cache::has('phone-verification:'.$student->id));
    }

    public function test_changing_a_saved_phone_number_clears_its_verified_status(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'phone' => '01712345678',
            'phone_verified_at' => now(),
        ]);

        $this->actingAs($student)->put(route('student.profile.update'), [
            'name' => $student->name,
            'phone' => '01812345678',
            'bio' => '',
        ])->assertRedirect();

        $this->assertNull($student->fresh()->phone_verified_at);
    }

    public function test_admin_can_print_qr_identity_card_and_public_page_exposes_only_minimal_identity_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create([
            'role' => 'student',
            'phone' => '01712345678',
            'email' => 'private.student@example.com',
        ]);
        $student->studentProfile()->create(['student_code' => 'AEC-QR-001', 'status' => 'active']);

        $this->actingAs($admin)->get(route('admin.users.id-card', $student))
            ->assertOk()->assertSee('identity-qr')->assertSee('AEC-QR-001');

        $token = $student->fresh()->studentProfile->id_token;
        $this->assertNotEmpty($token);

        $this->get(route('identity.verify', $token))
            ->assertOk()->assertSee($student->name)->assertSee('AEC-QR-001')
            ->assertDontSee('01712345678')->assertDontSee('private.student@example.com');
    }

    public function test_payroll_report_filters_by_month_branch_and_status_and_exports_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'instructor', 'branch_id' => $branch->id]);
        $teacherProfile = $teacher->staffProfile()->create(['employee_code' => 'AEC-RPT-001']);
        $otherTeacher = User::factory()->create(['role' => 'instructor', 'branch_id' => $otherBranch->id]);
        $otherProfile = $otherTeacher->staffProfile()->create(['employee_code' => 'AEC-RPT-002']);

        StaffPayroll::create([
            'staff_profile_id' => $teacherProfile->id, 'payroll_month' => '2026-11',
            'basic_salary' => 30000, 'allowances' => 5000, 'deductions' => 2000,
            'net_salary' => 33000, 'status' => 'paid',
        ]);
        StaffPayroll::create([
            'staff_profile_id' => $otherProfile->id, 'payroll_month' => '2026-11',
            'basic_salary' => 20000, 'allowances' => 0, 'deductions' => 0,
            'net_salary' => 20000, 'status' => 'paid',
        ]);
        StaffPayroll::create([
            'staff_profile_id' => $teacherProfile->id, 'payroll_month' => '2026-10',
            'basic_salary' => 30000, 'allowances' => 0, 'deductions' => 0,
            'net_salary' => 30000, 'status' => 'draft',
        ]);

        $query = ['start_month' => '2026-11', 'end_month' => '2026-11', 'branch_id' => $branch->id, 'status' => 'paid'];
        $this->actingAs($admin)->get(route('admin.staff-payroll.report', $query))
            ->assertOk()->assertSee('33,000.00')->assertSee('AEC-RPT-001')->assertDontSee('AEC-RPT-002');

        $this->get(route('admin.staff-payroll.report.export', $query))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('AEC-RPT-001')->assertDontSee('AEC-RPT-002');
    }

    public function test_non_admin_cannot_access_payroll_reports(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $this->actingAs($teacher)->get(route('admin.staff-payroll.report'))->assertForbidden();
    }
}
