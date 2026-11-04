@extends('layouts.app')

@section('title', 'Required Items')

@use('App\Support\Money')

@section('content')
<x-page-header title="Required Items" sub="Market list — needed → available" :back="route('dashboard')">
    <a href="{{ route('required-items.create') }}" class="btn-xl btn-sm-x btn-blue"><i class="bi bi-plus-lg"></i> Add</a>
</x-page-header>

@if ($pendingCount > 0)
    <section class="owed-card" aria-label="Needed items">
        <div class="owed-card-icon"><i class="bi bi-bag-plus"></i></div>
        <div class="owed-card-body">
            <span class="owed-card-label">Still needed</span>
            <strong class="owed-card-amount">{{ $pendingCount }}</strong>
            <small class="owed-card-meta">{{ $pendingCount === 1 ? 'item' : 'items' }} to bring from market</small>
        </div>
    </section>
@endif

<form method="GET" action="{{ route('required-items.index') }}" class="search-bar">
    <i class="bi bi-search"></i>
    <input type="search" name="q" value="{{ $q }}" class="form-control-x" placeholder="Search item or class…" autocomplete="off">
    @if ($filter !== 'pending')<input type="hidden" name="filter" value="{{ $filter }}">@endif
</form>

<div class="filter-tabs">
    <a href="{{ route('required-items.index', array_filter(['q' => $q, 'filter' => 'pending'])) }}" class="{{ $filter === 'pending' ? 'on' : '' }}">Needed</a>
    <a href="{{ route('required-items.index', array_filter(['q' => $q, 'filter' => 'available'])) }}" class="{{ $filter === 'available' ? 'on' : '' }}">Available</a>
    <a href="{{ route('required-items.index', array_filter(['q' => $q, 'filter' => 'all'])) }}" class="{{ $filter === 'all' ? 'on' : '' }}">All</a>
</div>

@if ($items->isEmpty())
    <div class="act-list">
        <div class="empty">
            <i class="bi bi-bag-check"></i>
            <div class="fw-bold text-dark">
                @if ($filter === 'available')No available items yet
                @elseif ($filter === 'all')No required items yet
                @else Nothing needed right now
                @endif
            </div>
            <div class="small text-muted mb-2">When a student asks for something (e.g. Physics book), add it here.</div>
            <a href="{{ route('required-items.create') }}" class="btn-xl btn-sm-x btn-blue mt-2">Add Required Item</a>
        </div>
    </div>
@else
    <div class="list-card">
        @foreach ($items as $item)
            <div class="list-row" style="align-items:flex-start;flex-wrap:wrap">
                <span class="vendor-avatar" style="{{ $item->isPending() ? 'background:#fff7ed;color:#ea580c' : 'background:#ecfdf5;color:#059669' }}">
                    <i class="bi {{ $item->isPending() ? 'bi-bag-plus' : 'bi-check2' }}"></i>
                </span>
                <div class="grow">
                    <div class="name">{{ $item->title }}</div>
                    <div class="d-flex flex-wrap gap-1 mt-1 mb-1">
                        @if ($item->class_name)
                            <span class="badge-soft blue">{{ $item->class_name }}</span>
                        @endif
                        <span class="badge-soft {{ $item->isPending() ? 'orange' : 'green' }}">{{ $item->status->label() }}</span>
                    </div>
                    <div class="meta">
                        @if ($item->isAvailable() && $item->quantity_received)
                            Stock {{ rtrim(rtrim(number_format((float) $item->quantity_received, 3, '.', ''), '0'), '.') }}{{ $item->unit ? ' '.$item->unit : '' }}
                            @if ($item->fulfilled_at) · {{ $item->fulfilled_at->format('d M') }} @endif
                        @elseif ($item->quantity_requested)
                            Wanted {{ rtrim(rtrim(number_format((float) $item->quantity_requested, 3, '.', ''), '0'), '.') }}{{ $item->unit ? ' '.$item->unit : '' }}
                            · Added {{ $item->created_at->format('d M') }}
                        @else
                            Added {{ $item->created_at->format('d M') }}
                            @if ($item->creator) by {{ $item->creator->name }} @endif
                        @endif
                    </div>
                    @if ($item->notes)
                        <div class="meta mt-1" style="white-space:normal">{{ $item->notes }}</div>
                    @endif
                </div>
                <div class="d-flex flex-column gap-1 align-items-end" style="min-width:72px">
                    @if ($item->isAvailable() && $item->price !== null)
                        <div class="val text-in" style="font-size:1.05rem;font-weight:800;line-height:1.15">
                            {{ Money::format($item->price) }}
                            <div class="meta" style="font-weight:600">Price</div>
                        </div>
                    @endif
                    @can('delete', $item)
                        <form method="POST" action="{{ route('required-items.destroy', $item) }}"
                              onsubmit="return confirm('Remove this item?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-xl btn-sm-x btn-ghost" style="min-height:36px;padding:0 12px;font-size:0.82rem;color:var(--pay-red)" aria-label="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    @endcan
                </div>
                @if ($item->isPending())
                    <div style="flex:1 0 100%;padding:8px 0 0 52px">
                        <a href="{{ route('required-items.fulfill', $item) }}"
                           class="btn-xl btn-sm-x btn-outline-x w-100"
                           style="min-height:40px;font-size:0.88rem">
                            <i class="bi bi-box-seam"></i> Mark as available
                        </a>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
@endif
@endsection
