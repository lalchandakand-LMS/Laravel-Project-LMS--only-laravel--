<?php

namespace Tests\\Feature;

use App\\Models\\Branch;
use App\\Models\\Order;
use App\\Models\\Payment;
use App\\Models\\PaymentAdjustment;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class PaymentReportFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_filter_applies_to_linked_refunds_in_report_totals_and_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Report Filter Branch',
            'code' => 'REPORT-FILTER',
            'is_active' => true,
            'payment_methods' => ['bkash', 'nagad'],
        ]);

        $bkashOrder = $this->order($student, $branch, 'BKASH');
        $bkashPayment = Payment::create([
            'order_id' => $bkashOrder->id,
            'gateway' => 'bkash',
            'transaction_id' => 'REPORT-BKASH-TXN',
            'amount' => 1000,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        PaymentAdjustment::create([
            'order_id' => $bkashOrder->id,
            'payment_id' => $bkashPayment->id,
            'created_by' => $admin->id,
            'type' => 'refund',
            'amount' => 100,
            'reference' => 'REPORT-BKASH-REFUND',
            'reason' => 'bKash partial refund',
        ]);

        $nagadOrder = $this->order($student, $branch, 'NAGAD');
        $nagadPayment = Payment::create([
            'order_id' => $nagadOrder->id,
            'gateway' => 'nagad',
            'transaction_id' => 'REPORT-NAGAD-TXN',
            'amount' => 2000,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        PaymentAdjustment::create([
            'order_id' => $nagadOrder->id,
            'payment_id' => $nagadPayment->id,
            'created_by' => $admin->id,
            'type' => 'refund',
            'amount' => 200,
            'reference' => 'REPORT-NAGAD-REFUND',
            'reason' => 'Nagad partial refund',
        ]);

        $this->actingAs($admin)->get(route('admin.payment-reports.index', ['gateway' => 'bkash']))
            ->assertOk()
            ->assertSee('REPORT-BKASH-REFUND')
            ->assertDontSee('REPORT-NAGAD-REFUND')
            ->assertViewHas('totals', function (array $totals) {
                return $totals['gross'] === 1000.0
                    && $totals['refunds'] === 100.0
                    && $totals['net'] === 900.0;
            });

        $csv = $this->get(route('admin.payment-reports.export', ['gateway' => 'bkash']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('REPORT-BKASH-REFUND', $csv);
        $this->assertStringNotContainsString('REPORT-NAGAD-REFUND', $csv);
        $this->assertStringNotContainsString('REPORT-NAGAD-TXN', $csv);
    }

    private function order(User $student, Branch $branch, string $suffix): Order
    {
        return Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => 3000,
            'discount' => 0,
            'total' => 3000,
            'status' => 'paid',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
            'note' => $suffix,
        ]);
    }
}
