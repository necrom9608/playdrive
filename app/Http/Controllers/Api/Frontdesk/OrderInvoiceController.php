<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Invoicing\OrderInvoiceService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderInvoice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Frontdesk → Verkopen: factuur van een bestelling maken in WeFact.
 *
 *   POST /api/frontdesk/orders/{order}/invoice        {company_name, contact_name, vat_number, email, address, postal_code, city, country, po_number, comment}
 *   POST /api/frontdesk/orders/{order}/invoice/retry  {email?: bool}  opnieuw proberen (email = toch per e-mail i.p.v. Peppol)
 */
class OrderInvoiceController extends Controller
{
    public function __construct(private readonly OrderInvoiceService $invoices)
    {
    }

    public function store(Request $request, CurrentTenant $currentTenant, Order $order): JsonResponse
    {
        $this->authorizeOrder($currentTenant, $order);
        abort_unless(OrderInvoiceService::configured($currentTenant->tenant), 422, 'WeFact is niet ingesteld (Backoffice → Tenantinstellingen → WeFact).');

        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'vat_number'   => ['nullable', 'string', 'max:40'],
            'email'        => ['nullable', 'email', 'max:255'],
            'address'      => ['nullable', 'string', 'max:255'],
            'postal_code'  => ['nullable', 'string', 'max:20'],
            'city'         => ['nullable', 'string', 'max:255'],
            'country'      => ['nullable', 'string', 'size:2'],
            'po_number'    => ['nullable', 'string', 'max:100'],
            'comment'      => ['nullable', 'string', 'max:1000'],
        ]);

        $existing = OrderInvoice::query()->where('order_id', $order->id)->first();
        abort_if($existing?->status === OrderInvoice::STATUS_DONE, 422, 'Voor deze bestelling bestaat al een factuur.');

        $invoice = $this->invoices->prepare($order, $data, $request->attributes->get('frontdesk_user')?->id);

        if ($invoice->status === OrderInvoice::STATUS_PENDING) {
            $invoice = $this->run(fn () => $this->invoices->process($invoice));
        }

        return $this->respond($invoice);
    }

    public function retry(Request $request, CurrentTenant $currentTenant, Order $order): JsonResponse
    {
        $this->authorizeOrder($currentTenant, $order);

        $invoice = OrderInvoice::query()->where('order_id', $order->id)->firstOrFail();
        $invoice = $this->run(fn () => $this->invoices->process($invoice, $request->boolean('email')));

        return $this->respond($invoice);
    }

    // ------------------------------------------------------------------

    private function authorizeOrder(CurrentTenant $currentTenant, Order $order): void
    {
        abort_unless($currentTenant->exists() && (int) $order->tenant_id === (int) $currentTenant->id(), 404);
    }

    private function run(callable $action): OrderInvoice
    {
        try {
            return $action();
        } catch (RuntimeException $e) {
            abort(409, $e->getMessage());
        }
    }

    private function respond(OrderInvoice $invoice): JsonResponse
    {
        $message = match ($invoice->status) {
            OrderInvoice::STATUS_DONE            => 'Factuur '.$invoice->wefact_invoice_code.' verstuurd'.($invoice->channel === OrderInvoice::CHANNEL_PEPPOL ? ' via Peppol.' : ' per e-mail.'),
            OrderInvoice::STATUS_MISSING_DETAILS => $invoice->error ?: 'Er ontbreken gegevens.',
            default                              => $invoice->error ?: 'Factuur kon niet gemaakt worden.',
        };

        return response()->json([
            'message' => $message,
            'data'    => $invoice->toFrontdeskArray(),
        ], $invoice->status === OrderInvoice::STATUS_DONE ? 200 : 422);
    }
}
