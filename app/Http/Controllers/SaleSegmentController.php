<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleSegmentRequest;
use App\Http\Requests\UpdateSaleSegmentRequest;
use App\Models\SaleSegment;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SaleSegmentController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(): View
    {
        return view('segments.index', [
            'segments' => SaleSegment::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(StoreSaleSegmentRequest $request): RedirectResponse
    {
        $name = trim($request->validated('name'));

        $segment = DB::transaction(function () use ($name, $request) {
            $max = (int) SaleSegment::max('sort_order');
            $segment = SaleSegment::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'sort_order' => $max + 1,
                'is_active' => true,
            ]);

            $this->audit->log('sale_segment.created', $segment, null, [
                'name' => $segment->name,
                'slug' => $segment->slug,
            ], null, $request->user());

            return $segment;
        });

        return redirect()->route('segments.index')
            ->with('success', 'Segment add ho gaya: '.$segment->name);
    }

    public function update(UpdateSaleSegmentRequest $request, SaleSegment $segment): RedirectResponse
    {
        $data = $request->validated();
        $old = $segment->only(['name', 'is_active', 'sort_order']);

        $segment->fill([
            'name' => trim($data['name']),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        if ($segment->isDirty('name')) {
            $segment->slug = $this->uniqueSlug($segment->name, $segment->id);
        }

        $segment->save();

        $this->audit->log(
            'sale_segment.updated',
            $segment,
            $old,
            $segment->only(['name', 'is_active', 'sort_order']),
            null,
            $request->user(),
        );

        return redirect()->route('segments.index')
            ->with('success', 'Segment update ho gaya: '.$segment->name);
    }

    public function move(Request $request, SaleSegment $segment): RedirectResponse
    {
        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        DB::transaction(function () use ($segment, $direction) {
            $ordered = SaleSegment::orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $index = $ordered->search(fn (SaleSegment $s) => $s->id === $segment->id);
            if ($index === false) {
                return;
            }

            $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swapWith < 0 || $swapWith >= $ordered->count()) {
                return;
            }

            $a = $ordered[$index];
            $b = $ordered[$swapWith];
            $tmp = $a->sort_order;
            $a->sort_order = $b->sort_order;
            $b->sort_order = $tmp;
            // if sort_orders were equal, force distinct positions
            if ($a->sort_order === $b->sort_order) {
                $a->sort_order = $index + 1;
                $b->sort_order = $swapWith + 1;
            }
            $a->save();
            $b->save();

            // normalize 1..n
            SaleSegment::orderBy('sort_order')->orderBy('id')->get()
                ->each(function (SaleSegment $s, int $i) {
                    if ($s->sort_order !== $i + 1) {
                        $s->forceFill(['sort_order' => $i + 1])->save();
                    }
                });
        });

        return redirect()->route('segments.index');
    }

    public function destroy(Request $request, SaleSegment $segment): RedirectResponse
    {
        // Never hard-delete if sales exist — deactivate instead.
        if ($segment->sales()->exists()) {
            $segment->update(['is_active' => false]);
            $this->audit->log('sale_segment.deactivated', $segment, ['is_active' => true], ['is_active' => false], 'Has sales; deactivated instead of delete', $request->user());

            return redirect()->route('segments.index')
                ->with('success', $segment->name.' pe sales hain — delete nahi, inactive kar diya.');
        }

        $name = $segment->name;
        $this->audit->log('sale_segment.deleted', $segment, $segment->toArray(), null, null, $request->user());
        $segment->delete();

        return redirect()->route('segments.index')
            ->with('success', 'Segment delete: '.$name);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name) ?: 'segment';
        $base = $slug;
        $n = 2;
        while (
            SaleSegment::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
