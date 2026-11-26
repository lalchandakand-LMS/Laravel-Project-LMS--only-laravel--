<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRevenueAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_orders_revenue_excludes_credit_transfers_but_subtracts_refunds(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Revenue Test Branch',
            'code' => 'REV-TEST',
            'is_active' => true,
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
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'bkash',
            'transaction_id' => 'REV-TEST-PAYMENT',
            'sender_number' => '01712345678',
            'amount' => 1000,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        PaymentAdjustment::create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'created_by' => $admin->id,
            'type' => 'refund',
            'amount' => 100,
            'reference' => 'REV-TEST-REFUND',
            'reason' => 'Partial refund for accounting regression test.',
        ]);
        PaymentAdjustment::create([
            'order_id' => $order->id,
            'created_by' => $admin->id,
            'type' => 'credit',
            'amount' => 200,
            'reference' => 'REV-TEST-CREDIT',
            'reason' => 'Internal credit transfer; not a new cash receipt.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertViewHas('totals', fn (array $totals) => $totals['revenue'] === 900.0);
    }
}
