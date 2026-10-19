<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Enums\CashAccountCode;
use App\Enums\PaymentSource;
use App\Models\DailySession;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Support\Money;
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
                $session, 'vendor_payment', 'Paid '.$vendor->name,
                ($source === PaymentSource::PersonalPocket ? 'From personal pocket' : 'From canteen cash')
                    .' · Balance '.Money::format($vendor->current_balance),
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

        $parts = ['Bill '.Money::format($bill)];
        if (Money::isPositive($paid)) {
            $parts[] = 'Paid '.Money::format($paid).($source === PaymentSource::PersonalPocket ? ' (personal pocket)' : '');
        }
        if (Money::isPositive($unpaid)) {
            $parts[] = 'On credit '.Money::format($unpaid);
        }
        $parts[] = 'Vendor balance '.Money::format($vendor->current_balance);

        $this->activity->log(
            $session,
            'purchase',
            'Purchase from '.$vendor->name,
            implode(' · ', $parts),
            $bill,
            $paid,
            Money::isPositive($unpaid) ? ActivityColor::Orange : ActivityColor::Red,
            $vendor,
            $purchase,
            $hasImage,
            ['entry_mode' => $purchase->entry_mode, 'payment_source' => $source?->value],
            $user,
            $purchase->recorded_at,
        );
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
