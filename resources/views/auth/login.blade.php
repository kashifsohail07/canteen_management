<!doctype html>
<html lang="en">
<head>
    @include('layouts.partials.head')
</head>
<body>
<div class="login-wrap" x-data="{ show: false, busy: false }">
    <div class="text-center text-white mb-5 pb-2">
        <div class="small fw-semibold opacity-75 text-uppercase" style="letter-spacing:2px">School Canteen</div>
    </div>

    <div class="login-card">
        <div class="login-logo"><i class="bi bi-wallet2"></i></div>
        <h1 class="login-title">Canteen Hisab</h1>
        <p class="login-sub">Cash, vendor aur rent ka poora hisab — ek jagah</p>

        @if ($errors->any())
            <div class="flash flash-error" role="alert">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" @submit="busy = true" novalidate>
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" class="form-control-x @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email"
                       autocapitalize="none" placeholder="aap@canteen.local">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="position-relative">
                    <input id="password" name="password" :type="show ? 'text' : 'password'" class="form-control-x pe-5"
                           required autocomplete="current-password" placeholder="••••••••">
                    <button type="button" class="btn position-absolute top-50 end-0 translate-middle-y me-1 text-muted"
                            @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'">
                        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                    </button>
                </div>
            </div>

            <label class="check-x mb-3">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <span>Mujhe yaad rakhein</span>
            </label>

            <button type="submit" class="btn-xl btn-save w-100" :class="{ disabled: busy }">
                <span x-show="!busy"><i class="bi bi-box-arrow-in-right me-1"></i> Login Karein</span>
                <span x-show="busy" x-cloak class="busy-inline"><span class="spinner-dot"></span> Ruk jayein…</span>
            </button>
        </form>
    </div>

    <p class="text-center text-white-50 small mt-4 mb-0">© {{ date('Y') }} Canteen Hisab</p>
</div>
</body>
</html>
