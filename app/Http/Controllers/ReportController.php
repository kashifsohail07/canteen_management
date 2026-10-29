<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Models\Vendor;
use App\Services\DailySessionService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    use ResolvesBusinessDate;

    public function __construct(
        private ReportService $reports,
        private DailySessionService $sessions,
    ) {}

    public function index(Request $request): View
    {
        $recentMonths = collect(range(0, 5))->map(fn (int $i) => now()->startOfMonth()->subMonths($i));

        return view('reports.index', [
            'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
            'today' => now(),
            'recentMonths' => $recentMonths,
        ]);
    }

    /** Daily report: html (default) or pdf. */
    public function daily(Request $request): View|Response
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'format' => ['nullable', 'in:html,pdf']]);

        $date = $this->requestedDate($request);
        $session = $this->sessions->findForDate($date) ?? abort(404, 'Is din ka koi record nahi.');
        $data = $this->reports->daily($session);
        $name = 'canteen-daily-'.$date->toDateString();

        return match ($request->query('format', 'html')) {
            'pdf' => Pdf::loadView('reports.pdf.daily', $data)->setPaper('a4')->download($name.'.pdf'),
            default => view('reports.daily', $data + ['topDate' => $date]),
        };
    }

    /** Compact daily summary (vendors with mall/payment/baqi) — html or pdf. */
    public function dailyMini(Request $request): View|Response
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'format' => ['nullable', 'in:html,pdf']]);

        $date = $this->requestedDate($request);
        $session = $this->sessions->findForDate($date) ?? abort(404, 'Is din ka koi record nahi.');
        $data = $this->reports->dailyMini($session);
        $name = 'canteen-daily-mini-'.$date->toDateString();

        return match ($request->query('format', 'html')) {
            'pdf' => Pdf::loadView('reports.pdf.daily-mini', $data)->setPaper('a4')->download($name.'.pdf'),
            default => view('reports.daily-mini', $data + ['topDate' => $date]),
        };
    }

    /** Vendor ledger report: html (default) or pdf. */
    public function vendor(Request $request, Vendor $vendor): View|Response
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'format' => ['nullable', 'in:html,pdf'],
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : null;
        $data = $this->reports->vendorLedger($vendor, $from, $to);
        $name = 'canteen-vendor-'.str($vendor->name)->slug().'-'.now()->toDateString();

        return match ($request->query('format', 'html')) {
            'pdf' => Pdf::loadView('reports.pdf.vendor', $data)->setPaper('a4')->download($name.'.pdf'),
            default => view('reports.vendor', $data),
        };
    }

    /** Monthly report: html (default) or pdf. */
    public function monthly(Request $request): View|Response
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'format' => ['nullable', 'in:html,pdf']]);

        $month = $request->filled('month') ? Carbon::createFromFormat('Y-m-d', $request->query('month').'-01') : now();
        $data = $this->reports->monthly($month->year, $month->month);
        $name = 'canteen-monthly-'.$month->format('Y-m');

        return match ($request->query('format', 'html')) {
            'pdf' => Pdf::loadView('reports.pdf.monthly', $data)->setPaper('a4', 'landscape')->download($name.'.pdf'),
            default => view('reports.monthly', $data + ['topDate' => $month]),
        };
    }

    /** Vendor balances snapshot PDF. Optional vendors[] filter. */
    public function vendors(Request $request): Response
    {
        $request->validate([
            'format' => ['nullable', 'in:pdf'],
            'vendors' => ['nullable', 'array'],
            'vendors.*' => ['integer', 'exists:vendors,id'],
            'all' => ['nullable', 'in:0,1,true,false'],
        ]);

        $wantAll = $request->boolean('all', ! $request->filled('vendors'));
        $ids = array_values(array_unique(array_map('intval', $request->input('vendors', []))));

        if (! $wantAll && $ids === []) {
            abort(422, 'Select at least one vendor for the PDF report.');
        }

        $data = $this->reports->vendorsSummary($wantAll ? null : $ids);
        $name = 'canteen-vendors-'.now()->toDateString();

        return Pdf::loadView('reports.pdf.vendors', $data)->setPaper('a4')->download($name.'.pdf');
    }
}
