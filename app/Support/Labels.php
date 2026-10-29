<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Human friendly (Roman Urdu / English) labels for internal type codes. */
class Labels
{
    /** cash_transactions.transaction_type */
    public static function cashTransaction(string $type): string
    {
        return match ($type) {
            'sale' => 'Cash Sale',
            'owner_deposit' => 'Owner Ne Cash Diya',
            'owner_repayment' => 'Owner Ko Wapis Kiya',
            'vendor_payment' => 'Vendor Payment',
            'customer_payment' => 'Customer Payment',
            'expense' => 'Kharcha',
            'rent_reserve_transfer' => 'Rent Side Rakha',
            'rent_payment' => 'Rent Diya',
            'closing_adjustment' => 'Closing Adjustment',
            'reversal' => 'Reversal',
            default => Str::headline($type),
        };
    }

    /** owner_transactions.type */
    public static function ownerTransaction(string $type): string
    {
        return match ($type) {
            'cash_deposit' => 'Cash Deposit',
            'repayment' => 'Wapsi (Repayment)',
            'vendor_payment' => 'Vendor Payment (Pocket)',
            'expense' => 'Kharcha Apni Jeb Se',
            'rent_payment' => 'Rent Apni Jeb Se',
            default => Str::headline($type),
        };
    }

    /** vendor_ledger_entries.entry_type */
    public static function ledgerEntry(string $type): string
    {
        return match ($type) {
            'opening_balance' => 'Opening Balance',
            'purchase' => 'Maal / Bill',
            'udhaar' => 'Udhaar',
            'payment' => 'Payment',
            'adjustment' => 'Adjustment',
            'reversal' => 'Reversal / Delete',
            default => Str::headline($type),
        };
    }

    public static function source(?string $source): string
    {
        return match ($source) {
            'canteen_cash' => 'Canteen Cash',
            'personal_pocket' => 'Personal Pocket',
            'rent_reserve' => 'Rent Reserve',
            null, '' => '-',
            default => Str::headline($source),
        };
    }

    public static function activityIcon(string $type): string
    {
        return match ($type) {
            'sale' => 'bi-cash-coin',
            'purchase' => 'bi-basket2-fill',
            'vendor_payment' => 'bi-send-fill',
            'customer_udhaar' => 'bi-journal-plus',
            'customer_payment' => 'bi-person-check-fill',
            'expense' => 'bi-receipt',
            'owner_deposit' => 'bi-box-arrow-in-down',
            'owner_repayment' => 'bi-box-arrow-up',
            'rent_reserve' => 'bi-house-lock-fill',
            'rent_payment' => 'bi-house-check-fill',
            'rent_skip' => 'bi-skip-forward-fill',
            'day_closed' => 'bi-lock-fill',
            'day_reopened' => 'bi-unlock-fill',
            'vendor_balance_adjust', 'customer_balance_adjust' => 'bi-sliders',
            default => 'bi-circle-fill',
        };
    }

    /** '+' for money coming in, '−' for money going out, '' when neutral. */
    public static function activitySign(string $type): string
    {
        return match ($type) {
            'sale', 'owner_deposit', 'customer_payment' => '+',
            'vendor_payment', 'expense', 'rent_payment', 'owner_repayment' => '−',
            default => '',
        };
    }

    /** Feed card type pill: [label, badge color]. */
    public static function activityPill(string $type): array
    {
        return match ($type) {
            'purchase' => ['Purchase', 'orange'],
            'sale' => ['Sale', 'green'],
            'vendor_payment' => ['Payment', 'red'],
            'customer_udhaar' => ['Udhaar', 'orange'],
            'customer_payment' => ['Pay', 'green'],
            'expense' => ['Expense', 'red'],
            'owner_deposit' => ['Deposit', 'blue'],
            'owner_repayment' => ['Wapsi', 'blue'],
            'rent_reserve' => ['Rent Side', 'blue'],
            'rent_payment' => ['Rent', 'blue'],
            'rent_skip' => ['Skipped', 'blue'],
            'day_closed' => ['Closed', 'red'],
            'day_reopened' => ['Reopened', 'green'],
            default => [Str::headline($type), 'blue'],
        };
    }

    /** Label above the main amount on feed cards. */
    public static function activityAmountLabel(string $type): string
    {
        return match ($type) {
            'purchase' => 'Total',
            'vendor_payment', 'rent_payment', 'expense', 'owner_repayment' => 'Paid',
            'sale', 'owner_deposit', 'customer_payment', 'rent_reserve' => 'Amount',
            'customer_udhaar' => 'Udhaar',
            default => 'Amount',
        };
    }
}
