@extends('layouts.app')

@section('title', 'Edit User')
@section('main_class', 'has-savebar')

@section('content')
<x-page-header title="Edit User" :sub="$user->email" :back="route('users.index')" />

@if ($isSelf)
    <div class="flash flash-success mb-3" role="status">
        <i class="bi bi-person-check-fill"></i>
        <div>Ye aapka apna account hai. Role / inactive change locked hai.</div>
    </div>
@elseif ($lastSuper)
    <div class="flash flash-error mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>Last active Super User — role ya inactive change nahi hoga.</div>
    </div>
@endif

<form method="POST" action="{{ route('users.update', $user) }}"
      x-data="{
          submitting: false,
          role: '{{ old('role', $user->role->value) }}',
          active: {{ old('is_active', $user->is_active ? '1' : '0') ? 'true' : 'false' }}
      }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf
    @method('PUT')

    <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" class="form-control-x @error('name') is-invalid @enderror"
               value="{{ old('name', $user->name) }}" maxlength="120" required autocomplete="name">
        @error('name')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="email">Email (login)</label>
        <input type="email" id="email" name="email" class="form-control-x @error('email') is-invalid @enderror"
               value="{{ old('email', $user->email) }}" maxlength="190" required autocomplete="username"
               inputmode="email" autocapitalize="none">
        @error('email')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="divider-label">Password (optional)</div>
    <div class="field">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" class="form-control-x @error('password') is-invalid @enderror"
               autocomplete="new-password" minlength="8" placeholder="Khali chhoro agar change nahi">
        @error('password')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="password_confirmation">Confirm new password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control-x"
               autocomplete="new-password" minlength="8">
    </div>

    <div class="field">
        <span class="field-label">Role</span>
        <div class="pill-group blue">
            @foreach ($roles as $role)
                <label class="m-0">
                    <input type="radio" name="role" value="{{ $role->value }}" x-model="role"
                           @checked(old('role', $user->role->value) === $role->value)
                           @disabled($isSelf || $lastSuper)>
                    <span class="pill">{{ $role->label() }}</span>
                </label>
            @endforeach
        </div>
        @if ($isSelf || $lastSuper)
            <input type="hidden" name="role" value="{{ $user->role->value }}">
        @endif
        @error('role')<div class="field-error">{{ $message }}</div>@enderror
        @foreach ($roles as $role)
            <div class="field-hint mb-1" x-show="role === '{{ $role->value }}'}" x-cloak>
                {{ $role->description() }}
            </div>
        @endforeach
    </div>

    <div class="card-x mb-3">
        <label class="check-x mb-0">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" x-model="active"
                   @disabled($isSelf || $lastSuper)>
            <span>Active (login allowed)</span>
        </label>
        @if ($isSelf || $lastSuper)
            <input type="hidden" name="is_active" value="1">
        @endif
        @error('is_active')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <i class="bi bi-check2-circle"></i> Save Changes
            </button>
        </div>
    </div>
</form>

@unless ($isSelf || $lastSuper)
    <form method="POST" action="{{ route('users.toggle-active', $user) }}" class="mt-3"
          onsubmit="return confirm('{{ $user->is_active ? 'Is user ko inactive karna hai?' : 'Is user ko active karna hai?' }}')">
        @csrf
        <button type="submit" class="btn-xl btn-ghost w-100">
            <i class="bi {{ $user->is_active ? 'bi-person-x' : 'bi-person-check' }}"></i>
            {{ $user->is_active ? 'Deactivate User' : 'Activate User' }}
        </button>
    </form>
@endunless
@endsection
