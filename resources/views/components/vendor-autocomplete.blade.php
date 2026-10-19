{{-- Alpine typeahead. Dispatches `vendor-selected` (bubbles) so the parent form can show balances. --}}
@props(['vendor' => null, 'focus' => null, 'name' => 'vendor_id', 'label' => 'Vendor'])
@php
    $vendor ??= old($name) ? \App\Models\Vendor::find(old($name)) : null;
    $selected = $vendor ? [
        'id' => $vendor->id,
        'name' => $vendor->name,
        'balance' => \App\Support\Money::of($vendor->current_balance),
        'balance_formatted' => \App\Support\Money::format($vendor->current_balance),
        'phone' => $vendor->phone,
    ] : null;
@endphp
<div class="field" x-data="vendorAutocomplete(@js(['url' => route('vendors.search', [], false), 'focus' => $focus, 'selected' => $selected]))"
     @click.outside="open = false" @keydown.escape="open = false">
    <label for="vendor_q">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" :value="selected ? selected.id : ''" value="{{ old($name, $vendor?->id) }}">

    {{-- chosen vendor --}}
    <div class="vendor-selected" x-show="selected" x-cloak>
        <div class="vendor-avatar" x-text="selected ? selected.name.charAt(0).toUpperCase() : ''"></div>
        <div class="flex-grow-1 min-w-0">
            <div class="fw-bold text-truncate" x-text="selected && selected.name"></div>
            <div class="small" :class="selected && parseFloat(selected.balance) > 0 ? 'text-owed' : 'text-adv'"
                 x-text="selected && (parseFloat(selected.balance) === 0 ? 'Koi baqaya nahi' : (parseFloat(selected.balance) > 0 ? fmt(selected.balance) + ' dena hai' : fmt(Math.abs(selected.balance)) + ' advance'))"></div>
        </div>
        <button type="button" class="btn-xl btn-sm-x btn-ghost" @click="clear()">Badlein</button>
    </div>

    {{-- search box --}}
    <div class="ac" x-show="!selected">
        <div class="ac-input-wrap">
            <i class="bi bi-search"></i>
            <input id="vendor_q" x-ref="q" type="text" class="form-control-x" placeholder="Vendor ka naam likhein…"
                   autocomplete="off" autocapitalize="words" enterkeyhint="search"
                   x-model="query" @focus="onFocus()" @input="onInput()"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                   @keydown.enter="enter($event)"
                   role="combobox" aria-expanded="false" :aria-expanded="open.toString()" aria-autocomplete="list">
        </div>
        <ul class="ac-list" x-show="open" x-cloak x-ref="list" role="listbox">
            <template x-for="(v, i) in items" :key="v.id">
                <li class="ac-item" :class="{ active: i === active }" role="option" @mouseenter="active = i" @click="choose(v)">
                    <div class="min-w-0">
                        <div class="name text-truncate" x-text="v.name"></div>
                        <div class="phone" x-show="v.phone" x-text="v.phone"></div>
                    </div>
                    <div class="bal" :class="parseFloat(v.balance) > 0 ? 'text-owed' : (parseFloat(v.balance) < 0 ? 'text-adv' : 'text-muted')" x-text="v.balance_formatted"></div>
                </li>
            </template>
            <li class="ac-empty" x-show="!loading && items.length === 0">
                Koi vendor nahi mila. <a href="{{ route('vendors.create') }}">Naya vendor banayein</a>
            </li>
            <li class="ac-empty" x-show="loading && items.length === 0">Dhoond rahe hain…</li>
        </ul>
    </div>
    @error($name)<div class="field-error">{{ $message }}</div>@enderror
</div>
