<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DailySession;
use App\Models\Expense;
use App\Models\OwnerTransaction;
use App\Models\Purchase;
use App\Models\RentPayment;
use App\Models\RentReservation;
use App\Models\Sale;
use App\Models\VendorPayment;
use App\Services\ImageService;
use App\Support\Labels;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    /** Single activity entry with its linked record (and bill photo for purchases). */
    public function show(Request $request, ActivityLog $activityLog, ImageService $images): View|JsonResponse
    {
        $activityLog->load('vendor', 'creator:id,name', 'dailySession');

        $record = $this->resolveRecord($activityLog);
        $details = $this->details($activityLog, $record);

        $purchase = $record instanceof Purchase ? $record : null;
        $hasImage = $purchase && $images->exists($purchase->bill_image_path);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'activity' => [
                    'id' => $activityLog->id,
                    'type' => $activityLog->activity_type,
                    'title' => $activityLog->title,
                    'summary' => $activityLog->summary,
                    'amount' => $activityLog->amount,
                    'recorded_at' => $activityLog->recorded_at->toIso8601String(),
                ],
                'details' => $details,
                'image_url' => $hasImage ? route('invoices.show', $purchase) : null,
            ]);
        }

        return view('activity.show', [
            'activity' => $activityLog,
            'record' => $record,
            'details' => $details,
            'purchase' => $purchase,
            'hasImage' => $hasImage,
            'topDate' => $activityLog->dailySession->business_date,
        ]);
    }

    private function resolveRecord(ActivityLog $activity): mixed
    {
        $id = $activity->reference_id;
        if (! $id) {
            return null;
        }

        return match ($activity->reference_type) {
            'purchase' => Purchase::with('vendor', 'items', 'creator:id,name')->find($id),
            'sale' => Sale::with('segment')->find($id),
            'vendor_payment' => VendorPayment::with('vendor')->find($id),
            'expense' => Expense::with('category')->find($id),
            'owner_transaction' => OwnerTransaction::find($id),
            'rent_reservation' => RentReservation::with('rentObligation')->find($id),
            'rent_payment' => RentPayment::with('rentObligation')->find($id),
            'daily_session' => DailySession::find($id),
            default => null,
        };
    }

    /** @return array<int, array{label: string, value: string}> */
    private function details(ActivityLog $activity, mixed $record): array
    {
        $rows = [];
        $add = function (string $label, mixed $value) use (&$rows) {
            if ($value !== null && $value !== '') {
                $rows[] = ['label' => $label, 'value' => (string) $value];
            }
        };

        $add('Waqt', $activity->recorded_at->format('d M Y, h:i A'));
        $add('Din', $activity->dailySession->business_date->format('D, d M Y'));
        $add('Entry karne wala', $activity->creator?->name);

        match (true) {
            $record instanceof Purchase => (function () use ($record, $add) {
                $add('Vendor', $record->vendor->name);
                $add('Bill', Money::format($record->bill_amount));
                $add('Paid', Money::format($record->paid_amount));
                $add('Udhaar', Money::format(Money::sub($record->bill_amount, $record->paid_amount)));
                $add('Payment source', $record->payment_source ? Labels::source($record->payment_source) : null);
                $add('Pehla balance', Money::format($record->previous_balance));
                $add('Naya balance', Money::format($record->new_balance));
                foreach ($record->items as $item) {
                    $add('Item: '.$item->item_name, rtrim(rtrim((string) $item->quantity, '0'), '.').' '.$item->unit.' × '.Money::format($item->rate).' = '.Money::format($item->line_total));
                }
                $add('Notes', $record->notes);
            })(),
            $record instanceof Sale => (function () use ($record, $add) {
                $add('Segment', $record->segment->name);
                $add('Amount', Money::format($record->amount));
                $add('Payment', ucfirst($record->payment_method));
                $add('Notes', $record->notes);
            })(),
            $record instanceof VendorPayment => (function () use ($record, $add) {
                $add('Vendor', $record->vendor->name);
                $add('Amount', Money::format($record->amount));
                $add('Source', Labels::source($record->payment_source));
                $add('Pehla balance', Money::format($record->previous_balance));
                $add('Naya balance', Money::format($record->new_balance));
                $add('Notes', $record->notes);
            })(),
            $record instanceof Expense => (function () use ($record, $add) {
                $add('Category', $record->category?->name);
                $add('Amount', Money::format($record->amount));
                $add('Source', Labels::source($record->payment_source));
                $add('Notes', $record->notes);
            })(),
            $record instanceof OwnerTransaction => (function () use ($record, $add) {
                $add('Qism', Labels::ownerTransaction($record->type));
                $add('Amount', Money::format($record->amount));
                $add('Owner ka baqaya', Money::format($record->balance_after));
                $add('Notes', $record->notes);
            })(),
            $record instanceof RentReservation => (function () use ($record, $add) {
                $add('Amount', Money::format($record->amount));
                $add('Mahina', now()->setDate($record->rentObligation->year, $record->rentObligation->month, 1)->format('F Y'));
                $add('Notes', $record->notes);
            })(),
            $record instanceof RentPayment => (function () use ($record, $add) {
                $add('Amount', Money::format($record->amount));
                $add('Source', Labels::source($record->payment_source));
                $add('Mahina', now()->setDate($record->rentObligation->year, $record->rentObligation->month, 1)->format('F Y'));
                $add('Notes', $record->notes);
            })(),
            default => $add('Detail', $activity->summary),
        };

        if ($record && ($record->is_reversed ?? false)) {
            $add('Status', 'REVERSED');
        }

        return $rows;
    }
}
