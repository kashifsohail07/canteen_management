@extends('layouts.app')

@section('title', 'Profile')
@section('main_class', 'has-savebar')

@section('content')
@php($u = $user)

<x-page-header title="My Profile" sub="Apna account · {{ $u->role->label() }}" :back="route('dashboard')" />

<section class="hero-cash mb-3" style="padding:18px 16px;border-radius:20px;background:linear-gradient(145deg,#1e3a8a 0%,var(--brand-blue) 55%,#2563eb 100%);">
    <div class="label"><i class="bi bi-person-badge"></i> {{ $u->role->label() }}</div>
    <div class="amount" style="font-size:1.45rem">{{ $u->name }}</div>
    <div class="meta">
        <span class="hero-pill"><i class="bi bi-envelope"></i> {{ $u->email }}</span>
    </div>
</section>

<div class="section-title"><span>Roles kya hain?</span></div>
<div class="role-guide mb-3">
    @foreach ($roles as $role)
        <div class="role-guide-card {{ $u->role === $role ? 'is-mine' : '' }}">
            <div class="role-guide-head">
                <strong>{{ $role->label() }}</strong>
                @if ($u->role === $role)
                    <span class="badge-soft blue">Aapka role</span>
                @endif
            </div>
            <p class="role-guide-desc">{{ $role->description() }}</p>
            <ul class="role-guide-list">
                @foreach ($role->permissions() as $perm)
                    <li>{{ $perm }}</li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>

<div class="section-title"><span>Profile update</span></div>

<form method="POST" action="{{ route('profile.update') }}"
      x-data="{ submitting: false }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf
    @method('PUT')

    <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" class="form-control-x @error('name') is-invalid @enderror"
               value="{{ old('name', $u->name) }}" maxlength="120" required autocomplete="name">
        @error('name')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="email">Email (login)</label>
        <input type="email" id="email" name="email" class="form-control-x @error('email') is-invalid @enderror"
               value="{{ old('email', $u->email) }}" maxlength="190" required autocomplete="username"
               inputmode="email" autocapitalize="none">
        @error('email')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="divider-label">Security</div>

    <div class="field">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password"
               class="form-control-x @error('current_password') is-invalid @enderror"
               required autocomplete="current-password" placeholder="Save ke liye zaroori">
        @error('current_password')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="password">New password (optional)</label>
        <input type="password" id="password" name="password" class="form-control-x @error('password') is-invalid @enderror"
               autocomplete="new-password" minlength="8" placeholder="Khali = same password">
        @error('password')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="password_confirmation">Confirm new password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control-x"
               autocomplete="new-password" minlength="8">
    </div>

    <div class="field-hint mb-3">Role sirf Super User Users page se change kar sakta hai.</div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <i class="bi bi-check2-circle"></i> Save Profile
            </button>
        </div>
    </div>
</form>
@endsection
