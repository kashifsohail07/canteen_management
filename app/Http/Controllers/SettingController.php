<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\RentObligation;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private AuditService $audit,
    ) {}

    public function edit(): View
    {
        return view('settings.edit', [
            'monthlyRent' => $this->settings->getDecimal('monthly_rent_target', 71000),
            'dailySuggest' => $this->settings->getDecimal('daily_rent_suggest', 5000),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $old = [
                'monthly_rent_target' => $this->settings->getDecimal('monthly_rent_target', 71000),
                'daily_rent_suggest' => $this->settings->getDecimal('daily_rent_suggest', 5000),
            ];
            $new = [
                'monthly_rent_target' => Money::of($data['monthly_rent_target']),
                'daily_rent_suggest' => Money::of($data['daily_rent_suggest']),
            ];

            foreach ($new as $key => $value) {
                $this->settings->set($key, $value);
            }

            if ($old['monthly_rent_target'] !== $new['monthly_rent_target']) {
                RentObligation::where('year', now()->year)->where('month', now()->month)
                    ->update(['target_amount' => $new['monthly_rent_target']]);
            }

            if ($old !== $new) {
                $setting = Setting::where('key', 'monthly_rent_target')->first();
                $this->audit->log('settings.updated', $setting, $old, $new, null, $request->user());
            }
        });

        return redirect()->route('settings.edit')->with('success', 'Settings save ho gayi.');
    }
}
