<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Http\Requests\CloseDayRequest;
use App\Http\Requests\ReopenDayRequest;
use App\Services\DailyClosingService;
use App\Services\DailySessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClosingController extends Controller
{
    use ResolvesBusinessDate;

    public function __construct(
        private DailyClosingService $closing,
        private DailySessionService $sessions,
    ) {}

    public function show(Request $request): View
    {
        $date = $this->requestedDate($request);
        $session = $this->sessionForView($date, $request->user()) ?? abort(404, 'Is din ka koi record nahi.');

        $checklist = $session->isOpen()
            ? $this->closing->checklistGaps($session)
            : ['segments' => [], 'rent_missing' => false, 'daily_rent_suggest' => '0'];

        return view('closing.show', [
            'session' => $session,
            'summary' => $this->closing->summarize($session),
            'checklist' => $checklist,
            'date' => $date,
            'topDate' => $date,
            'canClose' => $request->user()->can('close', $session),
            'canReopen' => $request->user()->can('reopen', $session),
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function close(CloseDayRequest $request): RedirectResponse
    {
        $date = $request->validated('business_date');
        $session = $this->sessions->findForDate($date) ?? abort(404);

        if ($session->isClosed()) {
            throw ValidationException::withMessages(['business_date' => 'Yeh din pehle se close ho chuka hai.']);
        }
        Gate::authorize('close', $session);

        $this->closing->close(
            $session,
            $request->validated('actual_counted_cash'),
            $request->validated('difference_reason'),
            $request->user(),
            $request->checklistInput(),
        );

        return redirect()
            ->route('closing.show', ['date' => $date])
            ->with('success', 'Din close ho gaya. Shukriya!');
    }

    public function reopen(ReopenDayRequest $request): RedirectResponse
    {
        $date = $request->validated('business_date');
        $session = $this->sessions->findForDate($date) ?? abort(404);

        if ($session->isOpen()) {
            throw ValidationException::withMessages(['business_date' => 'Yeh din pehle se khula hai.']);
        }
        Gate::authorize('reopen', $session);

        $this->closing->reopen($session, $request->user(), $request->validated('reason'));

        return redirect()
            ->route('closing.show', ['date' => $date])
            ->with('success', 'Din dobara khol diya gaya.');
    }
}
