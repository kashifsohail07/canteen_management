<?php

namespace App\Http\Controllers;

use App\Enums\RequiredItemStatus;
use App\Http\Requests\FulfillRequiredItemRequest;
use App\Http\Requests\StoreRequiredItemRequest;
use App\Models\RequiredItem;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RequiredItemController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RequiredItem::class);

        $filter = $request->query('filter', 'pending');
        if (! in_array($filter, ['pending', 'available', 'all'], true)) {
            $filter = 'pending';
        }

        $q = trim((string) $request->query('q', ''));

        $items = RequiredItem::query()
            ->with(['creator:id,name', 'fulfiller:id,name'])
            ->when($filter === 'pending', fn ($query) => $query->where('status', RequiredItemStatus::Pending))
            ->when($filter === 'available', fn ($query) => $query->where('status', RequiredItemStatus::Available))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', '%'.$q.'%')
                        ->orWhere('class_name', 'like', '%'.$q.'%')
                        ->orWhere('notes', 'like', '%'.$q.'%');
                });
            })
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(40)
            ->withQueryString();

        $pendingCount = RequiredItem::where('status', RequiredItemStatus::Pending)->count();

        return view('required-items.index', [
            'items' => $items,
            'filter' => $filter,
            'q' => $q,
            'pendingCount' => $pendingCount,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', RequiredItem::class);

        return view('required-items.create');
    }

    public function store(StoreRequiredItemRequest $request): RedirectResponse
    {
        $data = $request->validated();

        RequiredItem::create([
            'title' => trim($data['title']),
            'class_name' => isset($data['class_name']) && trim((string) $data['class_name']) !== ''
                ? trim($data['class_name'])
                : null,
            'notes' => $data['notes'] ?? null,
            'quantity_requested' => isset($data['quantity_requested']) && $data['quantity_requested'] !== ''
                ? $data['quantity_requested']
                : null,
            'unit' => $data['unit'] ?? null,
            'status' => RequiredItemStatus::Pending,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('required-items.index', ['filter' => 'pending'])
            ->with('success', 'Required item added.');
    }

    public function fulfillForm(RequiredItem $requiredItem): View
    {
        Gate::authorize('fulfill', $requiredItem);

        return view('required-items.fulfill', ['item' => $requiredItem]);
    }

    public function fulfill(FulfillRequiredItemRequest $request, RequiredItem $requiredItem): RedirectResponse
    {
        $data = $request->validated();

        $requiredItem->update([
            'status' => RequiredItemStatus::Available,
            'quantity_received' => $data['quantity_received'],
            'price' => Money::of($data['price']),
            'notes' => array_key_exists('notes', $data) && $data['notes'] !== null && $data['notes'] !== ''
                ? $data['notes']
                : $requiredItem->notes,
            'fulfilled_at' => now(),
            'fulfilled_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('required-items.index', ['filter' => 'available'])
            ->with('success', 'Marked available: '.$requiredItem->title);
    }

    public function destroy(RequiredItem $requiredItem): RedirectResponse
    {
        Gate::authorize('delete', $requiredItem);

        $title = $requiredItem->title;
        $requiredItem->delete();

        return redirect()
            ->route('required-items.index')
            ->with('success', 'Removed: '.$title);
    }
}
