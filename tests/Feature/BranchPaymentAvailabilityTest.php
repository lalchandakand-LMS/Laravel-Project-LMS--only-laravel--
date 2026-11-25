<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchPaymentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function studentWithBranch(array $methods = ['nagad']): array
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Payment Test Branch',
            'code' => 'PAY-TEST',
            'is_active' => true,
            'payment_methods' => $methods,
        ]);
        $student->update(['branch_id' => $branch->id]);

        $order = Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => 1500,
            'discount' => 0,
            'total' => 1500,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);

        return [$student, $branch, $order];
    }

    public function test_student_sees_only_methods_enabled_for_their_branch(): void
    {
        [$student, $branch, $order] = $this->studentWithBranch(['nagad', 'bank', 'cash']);

        $this->actingAs($student)
            ->get(route('checkout.success', $order->order_no))
            ->assertOk()
            ->assertSee('Nagad')
            ->assertSee('Bank transfer')
            ->assertDontSee('bKash')
            ->assertDontSee('Cash at office');

        $this->assertSame(['nagad', 'bank'], $branch->fresh()->enabledStudentPaymentMethods());
    }

    public function test_backend_rejects_disabled_method_and_accepts_enabled_method(): void
    {
        [$student, $branch, $order] = $this->studentWithBranch(['nagad']);

        $this->actingAs($student)
            ->post(route('checkout.pay', $order->order_no), [
                'gateway' => 'bkash',
                'sender_number' => '01700000000',
                'transaction_id' => 'DISABLED-TRX',
            ])
            ->assertSessionHasErrors('gateway');

        $this->assertDatabaseCount('payments', 0);

        $this->actingAs($student)
            ->post(route('checkout.pay', $order->order_no), [
                'gateway' => 'nagad',
                'amount' => 100,
                'sender_number' => '01700000000',
                'transaction_id' => 'ENABLED-TRX',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'ENABLED-TRX',
            'status' => 'pending',
        ]);
    }

    public function test_duplicate_transaction_reference_is_rejected(): void
    {
        [$student, $branch, $order] = $this->studentWithBranch(['nagad']);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'sender_number' => '01700000000',
            'transaction_id' => 'DUPLICATE-TRX',
            'amount' => 1500,
            'status' => 'pending',
        ]);

        $this->actingAs($student)
            ->post(route('checkout.pay', $order->order_no), [
                'gateway' => 'nagad',
                'sender_number' => '01700000000',
                'transaction_id' => 'DUPLICATE-TRX',
            ])
            ->assertSessionHasErrors('transaction_id');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_inactive_branch_cannot_receive_new_student_payment(): void
    {
        [$student, $branch, $order] = $this->studentWithBranch(['nagad']);
        $branch->update(['is_active' => false]);

        $this->actingAs($student)
            ->post(route('checkout.pay', $order->order_no), [
                'gateway' => 'nagad',
                'sender_number' => '01700000000',
                'transaction_id' => 'INACTIVE-BRANCH-TRX',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }
}
