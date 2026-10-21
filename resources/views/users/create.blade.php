@extends('layouts.app')

@section('title', 'Add User')
@section('main_class', 'has-savebar')

@section('content')
<x-page-header title="Add User" sub="Naya login banao" :back="route('users.index')" />

<form method="POST" action="{{ route('users.store') }}"
      x-data="{ submitting: false, role: '{{ old('role', 'staff') }}', active: {{ old('is_active', '1') ? 'true' : 'false' }} }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf

    <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" class="form-control-x @error('name') is-invalid @enderror"
               value="{{ old('name') }}" maxlength="120" required autofocus autocomplete="name" placeholder="e.g. Ali">
        @error('name')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="email">Email (login)</label>
        <input type="email" id="email" name="email" class="form-control-x @error('email') is-invalid @enderror"
               value="{{ old('email') }}" maxlength="190" required autocomplete="username" inputmode="email"
               autocapitalize="none" placeholder="ali@canteen.local">
        @error('email')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control-x @error('password') is-invalid @enderror"
               required autocomplete="new-password" minlength="8" placeholder="Min 8 characters">
        @error('password')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control-x"
               required autocomplete="new-password" minlength="8">
    </div>

    <div class="field">
        <span class="field-label">Role</span>
        <div class="pill-group blue mb-2">
            @foreach ($roles as $role)
                <label class="m-0">
                    <input type="radio" name="role" value="{{ $role->value }}" x-model="role"
                           @checked(old('role', 'staff') === $role->value)>
                    <span class="pill">{{ $role->label() }}</span>
                </label>
            @endforeach
        </div>
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
            <input type="checkbox" name="is_active" value="1" x-model="active">
            <span>Active (login allowed)</span>
        </label>
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <i class="bi bi-check2-circle"></i> Save User
            </button>
        </div>
    </div>
</form>
@endsection
