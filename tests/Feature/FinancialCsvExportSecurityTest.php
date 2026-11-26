<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialCsvExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_and_payment_exports_neutralize_spreadsheet_formulas(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'name' => '-ADMIN-NAME',
        ]);
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        $branch = Branch::create([
            'name' => '@SUM(1,1)',
            'code' => 'CSV-SAFE',
            'is_active' => true,
            'payment_methods' => ['nagad', 'cash'],
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'status' => 'pending',
            'name' => '=HYPERLINK("https://example.test","Student")',
            'phone' => '+CMD|/C calc',
            'email' => 'student@example.test',
        ]);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => '=WEBSERVICE("https://example.test")',
            'amount' => 1000,
            'status' => 'rejected',
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'rejection_reason' => '+RUN-COMMAND',
        ]);
        PaymentAdjustment::create([
            'order_id' => $order->id,
            'created_by' => $admin->id,
            'type' => 'refund',
            'amount' => 100,
            'reference' => '@REFUND-REFERENCE',
            'reason' => '=FORMULA-REASON',
        ]);

        $orderCsv = $this->actingAs($admin)->get(route('admin.orders.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $orderCsv);
        $this->assertStringContainsString("'+CMD", $orderCsv);
        $this->assertStringContainsString("'@SUM", $orderCsv);

        $paymentCsv = $this->get(route('admin.payment-reports.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString("'@SUM", $paymentCsv);
        $this->assertStringContainsString("'=WEBSERVICE", $paymentCsv);
        $this->assertStringContainsString("'+RUN-COMMAND", $paymentCsv);
        $this->assertStringContainsString("'-ADMIN-NAME", $paymentCsv);
        $this->assertStringContainsString("'@REFUND-REFERENCE", $paymentCsv);
        $this->assertStringContainsString("'=FORMULA-REASON", $paymentCsv);
    }
}
