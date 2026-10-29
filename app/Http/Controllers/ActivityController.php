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
use App\Models\SaleSegment;
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

        $isSuper = (bool) $request->user()?->isSuperUser();
        $dayOpen = $activityLog->dailySession->isOpen();
        $canEditSale = $isSuper
            && $record instanceof Sale
            && ! $record->is_reversed
            && $dayOpen;
        $canDeletePurchase = $isSuper
            && $record instanceof Purchase
            && ! $record->is_reversed
            && $dayOpen;
        $canDeletePayment = $isSuper
            && $record instanceof VendorPayment
            && ! $record->is_reversed
            && $dayOpen;

        return view('activity.show', [
            'activity' => $activityLog,
            'record' => $record,
            'details' => $details,
            'purchase' => $purchase,
            'hasImage' => $hasImage,
            'topDate' => $activityLog->dailySession->business_date,
            'canEditSale' => $canEditSale,
            'canDeletePurchase' => $canDeletePurchase,
            'canDeletePayment' => $canDeletePayment,
            'segments' => $canEditSale
                ? SaleSegment::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                : collect(),
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

    /**
     * Structured detail sections for the activity page.
     *
     * @return array{meta: list<array{label: string, value: string}>, sections: list<array{title: string, hint?: string, rows: list<array{label: string, value: string, emphasis?: bool}>, items?: list<array{name: string, calc: string, total: string}>}>}
     */
    private function details(ActivityLog $activity, mixed $record): array
    {
        $meta = [];
        $addMeta = function (string $label, mixed $value) use (&$meta) {
            if ($value !== null && $value !== '') {
                $meta[] = ['label' => $label, 'value' => (string) $value];
            }
        };

        $addMeta('Time', $activity->recorded_at->format('d M Y, h:i A'));
        $addMeta('Business day', $activity->dailySession->business_date->format('D, d M Y'));
        $addMeta('Entered by', $activity->creator?->name);

        $sections = [];

        match (true) {
            $record instanceof Purchase => (function () use ($record, &$sections) {
                $credit = Money::sub($record->bill_amount, $record->paid_amount);
                $items = [];
                foreach ($record->items as $item) {
                    $qty = rtrim(rtrim((string) $item->quantity, '0'), '.');
                    $items[] = [
                        'name' => $item->item_name,
                        'calc' => $qty.' '.$item->unit.' × '.Money::format($item->rate),
                        'total' => Money::format($item->line_total),
                    ];
                }

                if ($items !== []) {
                    $sections[] = [
                        'title' => '1. Items purchased',
                        'hint' => 'Line totals below add up to the bill amount.',
                        'items' => $items,
                        'rows' => [
                            ['label' => 'Bill total', 'value' => Money::format($record->bill_amount), 'emphasis' => true],
                        ],
                    ];
                } else {
                    $sections[] = [
                        'title' => '1. This bill',
                        'rows' => [
                            ['label' => 'Vendor', 'value' => $record->vendor->name],
                            ['label' => 'Bill total', 'value' => Money::format($record->bill_amount), 'emphasis' => true],
                        ],
                    ];
                }

                $payRows = [
                    ['label' => 'Vendor', 'value' => $record->vendor->name],
                    ['label' => 'Paid now', 'value' => Money::format($record->paid_amount)],
                    ['label' => 'On credit (added to balance)', 'value' => Money::format($credit), 'emphasis' => true],
                ];
                if ($record->payment_source) {
                    $payRows[] = ['label' => 'Paid from', 'value' => Labels::source($record->payment_source)];
                }
                if ($record->notes) {
                    $payRows[] = ['label' => 'Notes', 'value' => $record->notes];
                }
                $sections[] = [
                    'title' => '2. Payment on this bill',
                    'hint' => 'On credit = bill − paid now.',
                    'rows' => $payRows,
                ];

                $sections[] = [
                    'title' => '3. Vendor balance',
                    'hint' => 'Balance after = balance before + on credit.',
                    'rows' => [
                        ['label' => 'Balance before', 'value' => Money::format($record->previous_balance)],
                        ['label' => '+ On credit from this bill', 'value' => Money::format($credit)],
                        ['label' => 'Balance after', 'value' => Money::format($record->new_balance), 'emphasis' => true],
                    ],
                ];
            })(),
            $record instanceof Sale => (function () use ($record, &$sections) {
                $sections[] = [
                    'title' => 'Sale',
                    'rows' => array_values(array_filter([
                        ['label' => 'Segment', 'value' => $record->segment->name],
                        ['label' => 'Amount', 'value' => Money::format($record->amount), 'emphasis' => true],
                        ['label' => 'Payment', 'value' => $record->payment_method === 'rent_side'
                            ? 'Rent Side (reserve)'
                            : ucfirst($record->payment_method)],
                        $record->notes ? ['label' => 'Notes', 'value' => $record->notes] : null,
                    ])),
                ];
            })(),
            $record instanceof VendorPayment => (function () use ($record, &$sections) {
                $sections[] = [
                    'title' => 'Payment',
                    'rows' => array_values(array_filter([
                        ['label' => 'Vendor', 'value' => $record->vendor->name],
                        ['label' => 'Amount paid', 'value' => Money::format($record->amount), 'emphasis' => true],
                        ['label' => 'Paid from', 'value' => Labels::source($record->payment_source)],
                        $record->notes ? ['label' => 'Notes', 'value' => $record->notes] : null,
                    ])),
                ];
                $sections[] = [
                    'title' => 'Vendor balance',
                    'hint' => 'Payment reduces what we owe.',
                    'rows' => [
                        ['label' => 'Balance before', 'value' => Money::format($record->previous_balance)],
                        ['label' => 'This payment', 'value' => '− '.Money::format($record->amount)],
                        ['label' => 'Balance after', 'value' => Money::format($record->new_balance), 'emphasis' => true],
                    ],
                ];
            })(),
            $record instanceof Expense => (function () use ($record, &$sections) {
                $sections[] = [
                    'title' => 'Expense',
                    'rows' => array_values(array_filter([
                        ['label' => 'Category', 'value' => $record->category?->name],
                        ['label' => 'Amount', 'value' => Money::format($record->amount), 'emphasis' => true],
                        ['label' => 'Paid from', 'value' => Labels::source($record->payment_source)],
                        $record->notes ? ['label' => 'Notes', 'value' => $record->notes] : null,
                    ])),
                ];
            })(),
            $record instanceof OwnerTransaction => (function () use ($record, &$sections) {
                $sections[] = [
                    'title' => 'Owner ledger',
                    'rows' => array_values(array_filter([
                        ['label' => 'Type', 'value' => Labels::ownerTransaction($record->type)],
                        ['label' => 'Amount', 'value' => Money::format($record->amount), 'emphasis' => true],
                        ['label' => 'Owner recoverable after', 'value' => Money::format($record->balance_after)],
                        $record->notes ? ['label' => 'Notes', 'value' => $record->notes] : null,
                    ])),
                ];
            })(),
            $record instanceof RentReservation => (function () use ($record, &$sections) {
                $sections[] = [
                    'title' => 'Rent reserve',
                    'rows' => array_values(array_filter([
                        ['label' => 'Amount', 'value' => Money::format($record->amount), 'emphasis' => true],
                        ['label' => 'Month', 'value' => now()->setDate($record->rentObligation->year, $record->rentObligation->month, 1)->format('F Y')],
                        $record->notes ? ['label' => 'Notes', 'value' => $record->notes] : null,
                    ])),
                ];
            })(),
            $record instanceof RentPayment => (function () use ($record, &$sections) {
                $sections[] = [
                    'title' => 'Rent payment',
                    'rows' => array_values(array_filter([
                        ['label' => 'Amount', 'value' => Money::format($record->amount), 'emphasis' => true],
                        ['label' => 'Paid from', 'value' => Labels::source($record->payment_source)],
                        ['label' => 'Month', 'value' => now()->setDate($record->rentObligation->year, $record->rentObligation->month, 1)->format('F Y')],
                        $record->notes ? ['label' => 'Notes', 'value' => $record->notes] : null,
                    ])),
                ];
            })(),
            default => (function () use ($activity, &$sections) {
                if ($activity->summary) {
                    $sections[] = [
                        'title' => 'Detail',
                        'rows' => [['label' => 'Summary', 'value' => $activity->summary]],
                    ];
                }
            })(),
        };

        if ($record && ($record->is_reversed ?? false)) {
            $sections[] = [
                'title' => 'Status',
                'rows' => [['label' => 'Status', 'value' => 'REVERSED', 'emphasis' => true]],
            ];
        }

        return ['meta' => $meta, 'sections' => $sections];
    }
}
