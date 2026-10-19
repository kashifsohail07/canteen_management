<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplaceInvoiceRequest;
use App\Models\ActivityLog;
use App\Models\Purchase;
use App\Services\AuditService;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InvoiceController extends Controller
{
    public function __construct(
        private ImageService $images,
        private AuditService $audit,
    ) {}

    /** Stream the private bill photo (login required). */
    public function show(Purchase $purchase): StreamedResponse
    {
        abort_unless($this->images->exists($purchase->bill_image_path), 404, 'Is bill ki photo maujood nahi.');

        return Storage::disk(ImageService::DISK)->response(
            $purchase->bill_image_path,
            'bill-'.$purchase->id.'.'.pathinfo($purchase->bill_image_path, PATHINFO_EXTENSION),
            [
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    /** Replace (or add) the bill photo of a purchase. */
    public function replace(ReplaceInvoiceRequest $request, Purchase $purchase): RedirectResponse|JsonResponse
    {
        $old = $purchase->bill_image_path;
        $new = $this->images->storeInvoice($request->file('bill_image'));

        try {
            DB::transaction(function () use ($purchase, $new, $old, $request) {
                $purchase->update(['bill_image_path' => $new]);

                ActivityLog::where('reference_type', 'purchase')
                    ->where('reference_id', $purchase->id)
                    ->update(['has_image' => true]);

                $this->audit->log(
                    $old ? 'purchase.invoice_replaced' : 'purchase.invoice_added',
                    $purchase,
                    ['bill_image_path' => $old],
                    ['bill_image_path' => $new],
                    null,
                    $request->user(),
                );
            });
        } catch (Throwable $e) {
            $this->images->delete($new);
            throw $e;
        }

        $this->images->delete($old);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Bill ki photo update ho gayi.', 'url' => route('invoices.show', $purchase)]);
        }

        $activity = ActivityLog::where('reference_type', 'purchase')->where('reference_id', $purchase->id)->value('id');

        return redirect()
            ->to($activity ? route('activity.show', $activity) : route('vendors.show', $purchase->vendor_id))
            ->with('success', 'Bill ki photo update ho gayi.');
    }
}
