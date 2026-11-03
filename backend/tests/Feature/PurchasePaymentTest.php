<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class PurchasePaymentTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private const PERMISSIONS = [
        'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update',
        'purchase_orders.confirm', 'purchase_orders.receive',
        'payments.view', 'payments.create',
        'suppliers.view', 'products.view',
    ];

    private PurchaseOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 50,
            'cost_price' => 50,
            'tax_rate' => 20,
        ]);

        $this->actingWithPermissions(self::PERMISSIONS);

        $response = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 50],
            ],
        ])->assertCreated();

        $this->order = PurchaseOrder::query()->findOrFail($response->json('data.id'));
    }

    private function confirm(): void
    {
        $this->postJson("/api/v1/purchase-orders/{$this->order->id}/confirm")->assertOk();
    }

    public function test_a_new_order_is_fully_unpaid(): void
    {
        $this->assertSame(600.0, (float) $this->order->balance_due);
        $this->assertSame(0.0, (float) $this->order->amount_paid);
    }

    public function test_a_partial_payment_reduces_the_balance(): void
    {
        $this->confirm();

        $this->postJson("/api/v1/purchase-orders/{$this->order->id}/payments", [
            'amount' => 200,
            'method' => 'bank_transfer',
        ])
            ->assertCreated()
            ->assertJsonPath('data.direction', 'out')
            ->assertJsonPath('data.method', 'bank_transfer');

        $order = $this->order->fresh();
        $this->assertSame(200.0, (float) $order->amount_paid);
        $this->assertSame(400.0, (float) $order->balance_due);
    }

    public function test_a_full_payment_clears_the_balance(): void
    {
        $this->confirm();

        $this->postJson("/api/v1/purchase-orders/{$this->order->id}/payments", ['amount' => 600])
            ->assertCreated();

        $order = $this->order->fresh();
        $this->assertSame(600.0, (float) $order->amount_paid);
        $this->assertSame(0.0, (float) $order->balance_due);
    }

    public function test_an_overpayment_is_rejected(): void
    {
        $this->confirm();

        $this->postJson("/api/v1/purchase-orders/{$this->order->id}/payments", ['amount' => 601])
            ->assertStatus(422);

        $this->assertSame(0.0, (float) $this->order->fresh()->amount_paid);
    }

    public function test_a_draft_order_cannot_be_paid(): void
    {
        $this->postJson("/api/v1/purchase-orders/{$this->order->id}/payments", ['amount' => 100])
            ->assertStatus(422);
    }

    public function test_payments_are_booked_as_outgoing_and_listed_on_the_order(): void
    {
        $this->confirm();

        $this->postJson("/api/v1/purchase-orders/{$this->order->id}/payments", [
            'amount' => 150,
            'method' => 'check',
            'reference_number' => 'CHQ-991',
        ])->assertCreated();

        $payment = Payment::query()->sole();

        $this->assertSame('out', $payment->direction->value);
        $this->assertSame(150.0, (float) $payment->amount);
        $this->assertSame('CHQ-991', $payment->reference_number);
        $this->assertStringStartsWith('PAY-', $payment->reference);

        $this->getJson("/api/v1/purchase-orders/{$this->order->id}")
            ->assertOk()
            ->assertJsonPath('data.amount_paid', 150)
            ->assertJsonPath('data.balance_due', 450)
            ->assertJsonCount(1, 'data.payments');
    }
}
