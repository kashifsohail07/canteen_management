<?php

namespace Database\Seeders;

use App\Enums\CashAccountCode;
use App\Enums\UserRole;
use App\Models\CashAccount;
use App\Models\ExpenseCategory;
use App\Models\SaleSegment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\SettingService;
use App\Services\VendorLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@canteen.local'],
            ['name' => 'Admin', 'password' => 'password', 'role' => UserRole::Admin, 'is_active' => true, 'email_verified_at' => now()],
        );

        User::updateOrCreate(
            ['email' => 'staff@canteen.local'],
            ['name' => 'Staff', 'password' => 'password', 'role' => UserRole::Staff, 'is_active' => true, 'email_verified_at' => now()],
        );

        foreach (CashAccountCode::cases() as $code) {
            CashAccount::firstOrCreate(['code' => $code->value], ['name' => $code->label(), 'balance' => '0.00']);
        }

        foreach (['First Break', 'Second Break', 'School Closing / Off Time', 'Other Sale'] as $i => $name) {
            SaleSegment::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i + 1, 'is_active' => true],
            );
        }

        $settings = app(SettingService::class);
        $settings->set('monthly_rent_target', '71000');
        $settings->set('daily_rent_suggest', '5000');
        $settings->set('currency', 'PKR');

        ExpenseCategory::firstOrCreate(['name' => 'Misc'], ['is_active' => true]);

        // SAMPLE vendors for demo/testing only - delete or replace with real vendors before going live
        $vendors = app(VendorLedgerService::class);
        foreach ([
            'Samosay Supplier' => '14000',
            'Pepsi' => '0',
            'Biscuits' => '5000',
            'Fruit Gala' => '7300',
        ] as $name => $opening) {
            if (Vendor::where('name', $name)->exists()) {
                continue;
            }

            $vendors->createVendor([
                'name' => $name,
                'notes' => 'SAMPLE vendor - demo data',
                'opening_balance' => $opening,
            ], $admin);
        }
    }
}
