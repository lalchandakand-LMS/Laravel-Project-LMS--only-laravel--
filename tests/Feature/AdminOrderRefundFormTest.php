<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderRefundFormTest extends TestCase
{
    use RefreshDatabase;

    private function orderForAdmin(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Refund Form Branch',
            'code' => 'REFUND-FORM',
            'is_active' => true,
            'payment_methods' => ['nagad'],
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'status' => 'paid',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
        ]);

        return [$admin, $student, $order];
    }

    public function test_refund_form_caps_amount_at_cash_received_not_internal_credit(): void
    {
        [$admin, $student, $order] = $this->orderForAdmin();

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'REFUND-FORM-CASH-500',
            'amount' => 500,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        PaymentAdjustment::create([
            'order_id' => $order->id,
            'created_by' => $admin->id,
            'type' => 'credit',
            'amount' => 500,
            'reference' => 'REFUND-FORM-CREDIT-500',
            'reason' => 'Internal credit is not refundable cash.',
        ]);

        $this->assertSame(1000.0, $order->fresh()->amountPaid());
        $this->assertSame(500.0, $order->fresh()->refundableAmount());

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order->order_no))
            ->assertOk()
            ->assertSee('Record refund')
            ->assertSee('max="500"')
            ->assertDontSee('max="1000"');
    }

    public function test_refund_form_is_hidden_when_order_has_only_internal_credit_and_no_cash_receipt(): void
    {
        [$admin, $student, $order] = $this->orderForAdmin();

        PaymentAdjustment::create([
            'order_id' => $order->id,
            'created_by' => $admin->id,
            'type' => 'credit',
            'amount' => 500,
            'reference' => 'REFUND-FORM-CREDIT-ONLY',
            'reason' => 'Internal credit is not refundable cash.',
        ]);

        $this->assertSame(500.0, $order->fresh()->amountPaid());
        $this->assertSame(0.0, $order->fresh()->refundableAmount());

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order->order_no))
            ->assertOk()
            ->assertDontSee('Record refund');
    }
}
