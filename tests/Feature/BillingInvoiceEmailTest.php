<?php

namespace Tests\Feature;

use App\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingInvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_invoice_webhook_sends_billing_email_once(): void
    {
        config(['services.stripe.webhook_secret' => '']);

        Mail::fake();
        Http::fake([
            'https://files.stripe.com/invoices/invoice-test.pdf' => Http::response('%PDF-1.4 test invoice', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $clinic = Clinic::create([
            'name' => 'Salon Factura',
            'email' => 'billing@example.com',
            'stripe_customer_id' => 'cus_test_invoice',
        ]);

        $payload = [
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_test_paid',
                    'number' => 'SV-0001',
                    'customer' => 'cus_test_invoice',
                    'customer_email' => 'billing@example.com',
                    'amount_paid' => 2999,
                    'currency' => 'usd',
                    'invoice_pdf' => 'https://files.stripe.com/invoices/invoice-test.pdf',
                    'status_transitions' => [
                        'paid_at' => 1781730000,
                    ],
                ],
            ],
        ];

        $this->postJson('/stripe/webhook', $payload)->assertOk();
        $this->postJson('/stripe/webhook', $payload)->assertOk();

        $this->assertDatabaseHas('notifications', [
            'clinic_id' => $clinic->id,
            'channel' => 'email',
            'event' => 'billing_invoice_paid',
            'recipient' => 'billing@example.com',
            'status' => 'sent',
            'provider_message_id' => 'in_test_paid',
        ]);
        $this->assertSame(1, DB::table('notifications')
            ->where('event', 'billing_invoice_paid')
            ->where('provider_message_id', 'in_test_paid')
            ->where('status', 'sent')
            ->count());
    }
}
