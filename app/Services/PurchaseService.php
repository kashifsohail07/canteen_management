<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Enums\CashAccountCode;
use App\Enums\PaymentSource;
use App\Models\ActivityLog;
use App\Models\DailySession;
use App\Models\OwnerTransaction;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PurchaseService
{
    public function __construct(
        private DailySessionService $sessions,
        private VendorLedgerService $vendors,
        private CashLedgerService $cash,
        private OwnerLedgerService $owner,
        private ActivityLogService $activity,
        private ImageService $images,
        private AuditService $audit,
    ) {}

    /**
     * Record a purchase, either as a quick total or itemized.
     *
     * $data keys:
     *  vendor_id, entry_mode ('total'|'itemized'), bill_amount (total mode),
     *  items[] {item_name, quantity, unit, rate} (itemized mode),
     *  paid_amount (default 0), payment_source (canteen_cash|personal_pocket; required if paid > 0),
     *  notes, bill_image (UploadedFile) | bill_image_path, business_date, idempotency_key.
     */
    public function store(array $data, User $user): Purchase
    {
        if (! empty($data['idempotency_key'])
            && ($existing = Purchase::where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing->load('vendor', 'items', 'payments');
        }

        $mode = $data['entry_mode'] ?? 'total';
        if (! in_array($mode, ['total', 'itemized'], true)) {
            throw ValidationException::withMessages(['entry_mode' => 'Entry mode must be total or itemized.']);
        }

        [$billAmount, $lines] = $mode === 'itemized'
            ? $this->buildLines($data['items'] ?? [])
            : [Money::of($data['bill_amount'] ?? 0), []];

        if (! Money::isPositive($billAmount)) {
            throw ValidationException::withMessages(['bill_amount' => 'Bill amount must be greater than zero.']);
        }

        $paid = Money::of($data['paid_amount'] ?? 0);
        if (Money::isNegative($paid)) {
            throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot be negative.']);
        }
        $source = $this->resolveSource($paid, $data['payment_source'] ?? null);

        // store image before the transaction, remove it again if the transaction fails
        $imagePath = $data['bill_image_path'] ?? null;
        $storedNow = false;
        if (($data['bill_image'] ?? null) instanceof UploadedFile) {
            $imagePath = $this->images->storeInvoice($data['bill_image']);
            $storedNow = true;
        }

        try {
            return DB::transaction(function () use ($data, $user, $mode, $billAmount, $lines, $paid, $source, $imagePath) {
                $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);

                $vendor = $this->vendors->lockVendor((int) ($data['vendor_id'] ?? 0));
                if (! $vendor->is_active) {
                    throw ValidationException::withMessages(['vendor_id' => 'This vendor is inactive.']);
                }

                $previous = Money::of($vendor->current_balance);
                $afterBill = Money::add($previous, $billAmount);
                // No upper limit on $paid: paying more than is owed is an advance and simply pushes the
                // vendor balance negative (same rule as payVendor()). Cash availability is enforced by CashLedgerService.

                $now = now();
                $purchase = Purchase::create([
                    'daily_session_id' => $session->id,
                    'vendor_id' => $vendor->id,
                    'entry_mode' => $mode,
                    'bill_amount' => $billAmount,
                    'paid_amount' => $paid,
                    'payment_source' => $source?->value,
                    'previous_balance' => $previous,
                    'new_balance' => $afterBill,
                    'bill_image_path' => $imagePath,
                    'notes' => $data['notes'] ?? null,
                    'recorded_at' => $now,
                    'created_by' => $user->id,
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                ]);

                foreach ($lines as $i => $line) {
                    $purchase->items()->create($line + ['sort_order' => $i]);
                }

                $this->vendors->recordPurchaseEffect($vendor, $billAmount, [
                    'session' => $session,
                    'reference' => $purchase,
                    'notes' => 'Purchase #'.$purchase->id,
                    'user' => $user,
                    'recorded_at' => $now,
                ]);

                if (Money::isPositive($paid)) {
                    $this->applyPayment($vendor, $session, $paid, $source, $user, $purchase, $data['notes'] ?? null, $now);
                }

                $purchase->update(['new_balance' => $vendor->current_balance]);
                $vendor->forceFill(['last_used_at' => $now])->save();

                $this->logPurchaseActivity($purchase, $vendor, $session, $user, $source, $imagePath !== null);

                return $purchase->refresh()->load('vendor', 'items', 'payments');
            });
        } catch (Throwable $e) {
            if ($storedNow) {
                $this->images->delete($imagePath);
            }
            throw $e;
        }
    }

    /**
     * Standalone payment to a vendor (not tied to a new purchase).
     *
     * $data keys: vendor_id, amount, payment_source, purchase_id?, notes, business_date, idempotency_key.
     */
    public function payVendor(array $data, User $user): VendorPayment
    {
        if (! empty($data['idempotency_key'])
            && ($existing = VendorPayment::where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing->load('vendor');
        }

        $amount = Money::of($data['amount'] ?? 0);
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }
        $source = $this->resolveSource($amount, $data['payment_source'] ?? null);

        return DB::transaction(function () use ($data, $user, $amount, $source) {
            $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);
            $vendor = $this->vendors->lockVendor((int) ($data['vendor_id'] ?? 0));
            $now = now();

            // Advance payments (paying more than owed) are allowed and push balance negative.

            $payment = $this->applyPayment(
                $vendor, $session, $amount, $source, $user, null,
                $data['notes'] ?? null, $now, $data['purchase_id'] ?? null, $data['idempotency_key'] ?? null,
            );

            $vendor->forceFill(['last_used_at' => $now])->save();

            $this->activity->log(
                $session, 'vendor_payment', $vendor->name,
                $source === PaymentSource::PersonalPocket ? 'From personal pocket' : 'From canteen cash',
                $amount, $vendor->current_balance, ActivityColor::Red, $vendor, $payment, false, null, $user, $now,
            );

            return $payment->refresh()->load('vendor');
        });
    }

    /**
     * Creates the vendor_payment row and applies vendor-ledger and cash/owner effects.
     * Vendor must already be locked; runs inside the caller's transaction.
     */
    private function applyPayment(
        Vendor $vendor,
        DailySession $session,
        string $amount,
        PaymentSource $source,
        User $user,
        ?Purchase $purchase,
        ?string $notes,
        mixed $now,
        int|string|null $purchaseId = null,
        ?string $idempotencyKey = null,
    ): VendorPayment {
        $before = Money::of($vendor->current_balance);

        $payment = VendorPayment::create([
            'daily_session_id' => $session->id,
            'vendor_id' => $vendor->id,
            'purchase_id' => $purchase?->id ?? $purchaseId,
            'amount' => $amount,
            'payment_source' => $source->value,
            'previous_balance' => $before,
            'new_balance' => Money::sub($before, $amount),
            'notes' => $notes,
            'recorded_at' => $now,
            'created_by' => $user->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->vendors->recordPaymentEffect($vendor, $amount, [
            'session' => $session,
            'reference' => $payment,
            'notes' => $source === PaymentSource::PersonalPocket ? 'Paid from personal pocket' : 'Paid from canteen cash',
            'user' => $user,
            'recorded_at' => $now,
        ]);

        if ($source === PaymentSource::CanteenCash) {
            $this->cash->debit(CashAccountCode::CanteenCash, $amount, 'vendor_payment', [
                'session' => $session,
                'reference' => $payment,
                'notes' => 'Payment to '.$vendor->name,
                'user' => $user,
                'recorded_at' => $now,
            ]);
        } else {
            $this->owner->recordPersonalVendorPayment($payment->load('dailySession'), $user);
        }

        return $payment;
    }

    private function logPurchaseActivity(Purchase $purchase, Vendor $vendor, DailySession $session, User $user, ?PaymentSource $source, bool $hasImage): void
    {
        $bill = Money::of($purchase->bill_amount);
        $paid = Money::of($purchase->paid_amount);
        $unpaid = Money::sub($bill, $paid);
        $previous = Money::of($purchase->previous_balance);
        $balance = Money::of($vendor->current_balance);

        $purchase->loadMissing('items');
        $qtyLabel = $this->formatPurchaseQty($purchase);

        // Plain-text fallback (PDF / old clients): Qty → Bill → details + Balance.
        $lines = [];
        if ($qtyLabel !== null) {
            $lines[] = 'Qty '.$qtyLabel;
        }
        $lines[] = 'Bill '.Money::format($bill);
        $detailParts = ['Previous '.Money::format($previous)];
        if (Money::isPositive($paid)) {
            $detailParts[] = 'Paid '.Money::format($paid).($source === PaymentSource::PersonalPocket ? ' (personal pocket)' : '');
        }
        if (Money::isPositive($unpaid)) {
            $detailParts[] = 'On credit '.Money::format($unpaid);
        }
        $detailParts[] = 'Balance '.Money::format($balance);
        $lines[] = implode(' · ', $detailParts);

        $meta = [
            'entry_mode' => $purchase->entry_mode,
            'payment_source' => $source?->value,
            'bill' => Money::format($bill),
            'previous' => Money::format($previous),
            'balance' => Money::format($balance),
            'layout' => 'purchase_v2',
        ];
        if ($qtyLabel !== null) {
            $meta['qty_label'] = $qtyLabel;
        }
        if (Money::isPositive($paid)) {
            $meta['paid'] = Money::format($paid).($source === PaymentSource::PersonalPocket ? ' (personal pocket)' : '');
        }
        if (Money::isPositive($unpaid)) {
            $meta['on_credit'] = Money::format($unpaid);
        }

        $this->activity->log(
            $session,
            'purchase',
            $vendor->name,
            implode("\n", $lines),
            $bill,
            $paid,
            Money::isPositive($unpaid) ? ActivityColor::Orange : ActivityColor::Red,
            $vendor,
            $purchase,
            $hasImage,
            $meta,
            $user,
            $purchase->recorded_at,
        );
    }

    /**
     * Super User: delete a mistaken purchase (and any on-bill payments).
     * Reverses vendor balance + cash/pocket, drops activity feed rows.
     */
    public function deletePurchase(Purchase $purchase, string $reason, User $user): void
    {
        $this->assertCanDelete($user, $reason);

        $purchase->loadMissing('dailySession', 'vendor', 'payments');

        if ($purchase->is_reversed) {
            throw ValidationException::withMessages(['reason' => 'Yeh mall pehle hi delete / reverse ho chuka hai.']);
        }
        if ($purchase->dailySession->isClosed()) {
            throw ValidationException::withMessages(['reason' => 'Pehle din reopen karein, phir mall delete karein.']);
        }

        DB::transaction(function () use ($purchase, $reason, $user) {
            $locked = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $locked->load('dailySession', 'vendor', 'payments');

            if ($locked->is_reversed || $locked->dailySession->isClosed()) {
                throw ValidationException::withMessages(['reason' => 'Yeh mall ab delete nahi ho sakta.']);
            }

            $vendor = $this->vendors->lockVendor($locked->vendor_id);
            $now = now();
            $bill = Money::of($locked->bill_amount);

            $linkedPayments = VendorPayment::query()
                ->where('purchase_id', $locked->id)
                ->where('is_reversed', false)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            foreach ($linkedPayments as $payment) {
                $this->reversePaymentRow($payment, $vendor, $user, $reason, $now);
            }

            $this->vendors->reversePurchaseEffect($vendor, $bill, [
                'session' => $locked->dailySession,
                'reference' => $locked,
                'notes' => 'Mall delete: '.$reason,
                'user' => $user,
                'recorded_at' => $now,
            ]);

            $old = [
                'vendor_id' => $locked->vendor_id,
                'bill_amount' => $bill,
                'paid_amount' => Money::of($locked->paid_amount),
                'notes' => $locked->notes,
            ];

            $locked->update([
                'is_reversed' => true,
                'reversed_by' => $user->id,
                'reversed_at' => $now,
                'reversal_reason' => $reason,
            ]);

            ActivityLog::query()
                ->where('reference_type', 'purchase')
                ->where('reference_id', $locked->id)
                ->delete();

            foreach ($linkedPayments as $payment) {
                ActivityLog::query()
                    ->where('reference_type', 'vendor_payment')
                    ->where('reference_id', $payment->id)
                    ->delete();
            }

            $this->audit->log('purchase.deleted', $locked, $old, [
                'is_reversed' => true,
                'reversal_reason' => $reason,
            ], $reason, $user);
        });
    }

    /**
     * Super User: delete a mistaken vendor payment.
     * On-bill payments also reduce the linked purchase paid_amount.
     */
    public function deletePayment(VendorPayment $payment, string $reason, User $user): void
    {
        $this->assertCanDelete($user, $reason);

        $payment->loadMissing('dailySession', 'vendor', 'purchase');

        if ($payment->is_reversed) {
            throw ValidationException::withMessages(['reason' => 'Yeh payment pehle hi delete / reverse ho chuki hai.']);
        }
        if ($payment->dailySession->isClosed()) {
            throw ValidationException::withMessages(['reason' => 'Pehle din reopen karein, phir payment delete karein.']);
        }

        DB::transaction(function () use ($payment, $reason, $user) {
            $locked = VendorPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $locked->load('dailySession', 'vendor', 'purchase');

            if ($locked->is_reversed || $locked->dailySession->isClosed()) {
                throw ValidationException::withMessages(['reason' => 'Yeh payment ab delete nahi ho sakti.']);
            }

            $vendor = $this->vendors->lockVendor($locked->vendor_id);
            $now = now();
            $amount = Money::of($locked->amount);

            $this->reversePaymentRow($locked, $vendor, $user, $reason, $now);

            if ($locked->purchase_id && $locked->purchase && ! $locked->purchase->is_reversed) {
                $purchase = Purchase::whereKey($locked->purchase_id)->lockForUpdate()->first();
                if ($purchase && ! $purchase->is_reversed) {
                    $newPaid = Money::sub($purchase->paid_amount, $amount);
                    if (Money::isNegative($newPaid)) {
                        $newPaid = Money::zero();
                    }
                    $purchase->update([
                        'paid_amount' => $newPaid,
                        'new_balance' => $vendor->current_balance,
                    ]);
                }
            }

            ActivityLog::query()
                ->where('reference_type', 'vendor_payment')
                ->where('reference_id', $locked->id)
                ->delete();

            $this->audit->log('vendor_payment.deleted', $locked, [
                'vendor_id' => $locked->vendor_id,
                'amount' => $amount,
                'payment_source' => $locked->payment_source,
            ], [
                'is_reversed' => true,
                'reversal_reason' => $reason,
            ], $reason, $user);
        });
    }

    private function assertCanDelete(User $user, string $reason): void
    {
        if (! $user->isSuperUser()) {
            throw new AuthorizationException('Only a super user can delete this entry.');
        }

        $reason = trim($reason);
        if (strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'Delete ki wajah likhna zaroori hai.']);
        }
    }

    private function reversePaymentRow(
        VendorPayment $payment,
        Vendor $vendor,
        User $user,
        string $reason,
        mixed $now,
    ): void {
        $amount = Money::of($payment->amount);
        $source = PaymentSource::tryFrom((string) $payment->payment_source) ?? PaymentSource::CanteenCash;
        $session = $payment->dailySession;

        $ctx = [
            'session' => $session,
            'reference' => $payment,
            'notes' => 'Payment delete: '.$reason,
            'user' => $user,
            'recorded_at' => $now,
        ];

        $this->vendors->reversePaymentEffect($vendor, $amount, $ctx);

        if ($source === PaymentSource::CanteenCash) {
            $this->cash->credit(CashAccountCode::CanteenCash, $amount, 'reversal', $ctx);
        } else {
            $this->cash->debit(CashAccountCode::OwnerRecoverable, $amount, 'reversal', $ctx);
            OwnerTransaction::query()
                ->where('vendor_payment_id', $payment->id)
                ->where('is_reversed', false)
                ->update(['is_reversed' => true]);
        }

        $payment->update([
            'is_reversed' => true,
            'reversed_by' => $user->id,
            'reversed_at' => $now,
            'reversal_reason' => $reason,
        ]);
    }

    /** Sum item quantities for the activity line (e.g. "200 pcs"). Null when no items. */
    private function formatPurchaseQty(Purchase $purchase): ?string
    {
        $items = $purchase->items;
        if ($items->isEmpty()) {
            return null;
        }

        $units = $items->pluck('unit')->map(fn ($u) => $u ?: 'pcs')->unique()->values();
        if ($units->count() === 1) {
            $total = '0';
            foreach ($items as $item) {
                $total = bcadd($total, (string) $item->quantity, 3);
            }

            return $this->trimQty($total).' '.$units->first();
        }

        return $items
            ->map(fn ($item) => $this->trimQty((string) $item->quantity).' '.($item->unit ?: 'pcs'))
            ->implode(' + ');
    }

    private function trimQty(string $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, '.', ''), '0'), '.') ?: '0';
    }

    private function resolveSource(string $paid, ?string $source): ?PaymentSource
    {
        if (! Money::isPositive($paid)) {
            return null;
        }

        $enum = $source ? PaymentSource::tryFrom($source) : null;
        if (! $enum) {
            throw ValidationException::withMessages(['payment_source' => 'Choose whether payment came from canteen cash or personal pocket.']);
        }

        return $enum;
    }

    /**
     * @return array{0: string, 1: array<int, array<string, mixed>>} [total, lines]
     */
    private function buildLines(array $items): array
    {
        $lines = [];
        $total = Money::zero();

        foreach (array_values($items) as $i => $item) {
            $name = trim((string) ($item['item_name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages(["items.$i.item_name" => 'Item name is required.']);
            }

            $qty = $item['quantity'] ?? 1;
            if (! is_numeric($qty) || bccomp((string) $qty, '0', 3) <= 0) {
                throw ValidationException::withMessages(["items.$i.quantity" => 'Quantity must be greater than zero.']);
            }
            $qty = bcadd((string) $qty, '0', 3);

            $rate = Money::of($item['rate'] ?? 0);
            if (Money::isNegative($rate)) {
                throw ValidationException::withMessages(["items.$i.rate" => 'Rate cannot be negative.']);
            }

            $lineTotal = Money::mul($qty, $rate);
            $total = Money::add($total, $lineTotal);

            $lines[] = [
                'item_name' => $name,
                'quantity' => $qty,
                'unit' => $item['unit'] ?? null,
                'rate' => $rate,
                'line_total' => $lineTotal,
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one item.']);
        }

        return [$total, $lines];
    }
}
