<?php

namespace App\Http\Controllers;

use App\Services\StripeBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request, StripeBillingService $billing): View
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic, 404);

        $billingError = null;
        $invoices = collect();
        $subscriptionSummary = [
            'purchased_at' => null,
            'renews_at' => $clinic->subscription_renews_at,
            'status' => $clinic->subscription_status,
        ];

        try {
            $invoices = $billing->invoicesForClinic($clinic);
            $subscriptionSummary = $billing->subscriptionSummary($clinic);
        } catch (\Throwable $exception) {
            $billingError = $exception->getMessage();
        }

        return view('billing.index', [
            'clinic' => $clinic->load('plan'),
            'invoices' => $invoices,
            'subscriptionSummary' => $subscriptionSummary,
            'billingError' => $billingError,
        ]);
    }

    public function pdf(Request $request, string $invoice, StripeBillingService $billing): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic, 404);

        try {
            return redirect()->away($billing->invoicePdfUrl($clinic, $invoice));
        } catch (\Throwable $exception) {
            return redirect('/facturacion')->with('billing_error', $exception->getMessage());
        }
    }
}
