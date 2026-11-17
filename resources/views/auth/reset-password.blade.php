@extends('layouts.auth')

@section('content')
<div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center text-white rounded-3 p-3 mb-3 shadow-sm" style="width: 52px; height: 52px; background: var(--primary-teal, #0F766E);">
        <i class="bi bi-shield-lock-fill fs-3"></i>
    </div>
    <h4 class="fw-bold mb-1 text-dark">Set New Password</h4>
    <p class="text-muted small">Choose a secure password for your account</p>
</div>

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show small py-2 mb-3" role="alert">
        {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('password.update') }}">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-secondary">Email Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" name="email" id="email" class="form-control border-start-0 ps-0" value="{{ old('email', $email) }}" required autofocus placeholder="name@company.com">
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label small fw-semibold text-secondary">New Password</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" name="password" id="password" class="form-control border-start-0 ps-0" required placeholder="Minimum 8 characters">
        </div>
    </div>

    <div class="mb-4">
        <label for="password_confirmation" class="form-label small fw-semibold text-secondary">Confirm New Password</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock-fill text-muted"></i></span>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control border-start-0 ps-0" required placeholder="••••••••">
        </div>
    </div>

    <button type="submit" class="btn w-100 py-2 fw-semibold shadow-sm mb-3 text-white" style="background: var(--primary-teal, #0F766E); border-color: var(--primary-teal, #0F766E);">
        <i class="bi bi-check2 me-1"></i> Reset Password
    </button>

    <div class="text-center mt-3">
        <a href="{{ route('login') }}" class="small text-decoration-none fw-semibold" style="color: var(--primary-teal, #0F766E);">
            <i class="bi bi-arrow-left me-1"></i> Return to Sign In
        </a>
    </div>
</form>
@endsection
