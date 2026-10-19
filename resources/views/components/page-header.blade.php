@props(['title', 'sub' => null, 'back' => null])
<div class="page-head">
    @if ($back)
        <a href="{{ $back }}" class="back-btn" aria-label="Wapis"><i class="bi bi-arrow-left"></i></a>
    @endif
    <div class="flex-grow-1">
        <h1>{{ $title }}</h1>
        @if ($sub)<div class="sub">{{ $sub }}</div>@endif
    </div>
    {{ $slot }}
</div>
