<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Models\Vendor;
use App\Services\DailySessionService;
use App\Services\ReportService;
use App\Support\Labels;
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

    /** Daily report: html (default), pdf or csv. */
    public function daily(Request $request): View|Response
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'format' => ['nullable', 'in:html,pdf,csv']]);

        $date = $this->requestedDate($request);
        $session = $this->sessions->findForDate($date) ?? abort(404, 'Is din ka koi record nahi.');
        $data = $this->reports->daily($session);
        $name = 'canteen-daily-'.$date->toDateString();

        return match ($request->query('format', 'html')) {
            'pdf' => Pdf::loadView('reports.pdf.daily', $data)->setPaper('a4')->download($name.'.pdf'),
            'csv' => $this->csv($name.'.csv', $this->dailyCsv($data)),
            default => view('reports.daily', $data + ['topDate' => $date]),
        };
    }

    /** Vendor ledger report: html (default), pdf or csv. */
    public function vendor(Request $request, Vendor $vendor): View|Response
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'format' => ['nullable', 'in:html,pdf,csv'],
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : null;
        $data = $this->reports->vendorLedger($vendor, $from, $to);
        $name = 'canteen-vendor-'.str($vendor->name)->slug().'-'.now()->toDateString();

        return match ($request->query('format', 'html')) {
            'pdf' => Pdf::loadView('reports.pdf.vendor', $data)->setPaper('a4')->download($name.'.pdf'),
            'csv' => $this->csv($name.'.csv', $this->vendorCsv($data)),
            default => view('reports.vendor', $data),
        };
    }

    /** Monthly report: html (default), pdf or csv. */
    public function monthly(Request $request): View|Response
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'format' => ['nullable', 'in:html,pdf,csv']]);

        $month = $request->filled('month') ? Carbon::createFromFormat('Y-m-d', $request->query('month').'-01') : now();
        $data = $this->reports->monthly($month->year, $month->month);
        $name = 'canteen-monthly-'.$month->format('Y-m');

        return match ($request->query('format', 'html')) {
            'pdf' => Pdf::loadView('reports.pdf.monthly', $data)->setPaper('a4', 'landscape')->download($name.'.pdf'),
            'csv' => $this->csv($name.'.csv', $this->monthlyCsv($data)),
            default => view('reports.monthly', $data + ['topDate' => $month]),
        };
    }

    /** @param  array<int, array<int, mixed>>  $rows */
    private function csv(string $filename, array $rows): Response
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<int, array<int, mixed>> */
    private function dailyCsv(array $d): array
    {
        $s = $d['summary'];
        $rows = [
            ['Canteen Hisab – Daily Report', $d['date']->format('Y-m-d')],
            ['Status', $s['status']],
            [],
            ['Summary'],
            ['Opening cash', $s['opening_canteen_cash']],
            ['Total cash in', $s['total_cash_in']],
            ['Total cash out', $s['total_cash_out']],
            ['Expected closing cash', $s['expected_closing_cash']],
            ['Counted cash', $s['actual_counted_cash']],
            ['Difference', $s['cash_difference']],
            ['Difference reason', $s['difference_reason']],
            ['Sales', $s['sales_total']],
            ['Purchases (bills)', $s['purchases_total']],
            ['Vendor payments', $s['vendor_payments_total']],
            ['Expenses', $s['expenses_total']],
            ['Rent reserved', $s['rent_reserved']],
            ['Rent paid', $s['rent_paid']],
            ['Vendors outstanding', $s['vendors_outstanding']],
            [],
            ['Activity'],
            ['Time', 'Type', 'Title', 'Details', 'Amount', 'Other amount'],
        ];
        foreach ($d['activity'] as $a) {
            $rows[] = [$a->recorded_at->format('H:i'), $a->activity_type, $a->title, $a->summary, $a->amount, $a->secondary_amount];
        }

        return $rows;
    }

    /** @return array<int, array<int, mixed>> */
    private function vendorCsv(array $d): array
    {
        $rows = [
            ['Canteen Hisab – Vendor Ledger', $d['vendor']->name],
            ['From', $d['from']?->toDateString() ?? 'start', 'To', $d['to']?->toDateString() ?? 'today'],
            [],
            ['Date', 'Type', 'Notes', 'Bill / Debit', 'Payment / Credit', 'Balance'],
            ['', 'Opening', '', '', '', $d['opening']],
        ];
        foreach ($d['entries'] as $e) {
            $rows[] = [$e->recorded_at->format('Y-m-d H:i'), Labels::ledgerEntry($e->entry_type), $e->notes, $e->debit, $e->credit, $e->balance_after];
        }
        $rows[] = ['', 'Totals', '', $d['total_debit'], $d['total_credit'], $d['closing']];

        return $rows;
    }

    /** @return array<int, array<int, mixed>> */
    private function monthlyCsv(array $d): array
    {
        $rows = [
            ['Canteen Hisab – Monthly Report', $d['month_label'] ?? $d['month']->format('F Y')],
            ['Days with sales', $d['days_with_sales'] ?? 0, 'Days recorded', $d['days_recorded'] ?? 0, 'Days in month', $d['month']->daysInMonth],
            [],
            ['Date', 'Status', 'Sales', 'Bills', 'Paid on bill', 'Vendor payments', 'Expenses', 'Rent reserved', 'Rent paid', 'Owner deposits', 'Closing cash', 'Difference'],
        ];
        foreach ($d['rows'] as $r) {
            $rows[] = [$r['date']->format('Y-m-d'), $r['status'], $r['sales'], $r['bills'], $r['paid_on_bill'], $r['vendor_payments'], $r['expenses'], $r['rent_reserved'], $r['rent_paid'], $r['owner_deposits'], $r['closing_cash'], $r['difference']];
        }
        $t = $d['totals'];
        $rows[] = ['Total', '', $t['sales'], $t['bills'], $t['paid_on_bill'], $t['vendor_payments'], $t['expenses'], $t['rent_reserved'], $t['rent_paid'], $t['owner_deposits'], '', ''];
        $rows[] = [];
        $rows[] = ['Sales by segment'];
        foreach ($d['segments'] as $seg) {
            $rows[] = [$seg['name'], $seg['total'], $seg['count']];
        }
        $rows[] = [];
        $rows[] = ['Vendors', 'Bills', 'Paid', 'Balance now'];
        foreach ($d['vendors'] as $v) {
            $rows[] = [$v['name'], $v['bills'], $v['paid'], $v['balance']];
        }

        return $rows;
    }
}
