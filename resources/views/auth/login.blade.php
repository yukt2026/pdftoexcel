@extends('layouts.auth')

@section('content')
<div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center text-white rounded-3 p-3 mb-3 shadow-sm" style="width: 52px; height: 52px; background: linear-gradient(135deg, var(--primary-teal, #0F766E), #14B8A6);">
        <i class="bi bi-file-earmark-spreadsheet-fill fs-3"></i>
    </div>
    <h4 class="fw-bold mb-1" style="color: var(--primary-navy, #172554); letter-spacing: -0.02em;">AI Invoice to Excel</h4>
    <p class="text-muted small">Sign in to your automated extraction workspace</p>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show small py-2 mb-3" role="alert" style="background-color: #F0FDF4; border-color: #BBF7D0; color: #166534;">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show small py-2 mb-3" role="alert" style="background-color: #FEF2F2; border-color: #FECACA; color: #991B1B;">
        <i class="bi bi-exclamation-triangle me-1"></i> {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('login') }}">
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-secondary">Email Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" name="email" id="email" class="form-control border-start-0 ps-0" value="{{ old('email', 'admin@invoiceexcel.test') }}" required autofocus placeholder="name@company.com">
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" name="password" id="password" class="form-control border-start-0 ps-0" value="password" required placeholder="••••••••">
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" checked>
            <label class="form-check-label small text-muted" for="remember">Remember me</label>
        </div>
        <a href="{{ route('password.request') }}" class="small text-decoration-none fw-semibold" style="color: var(--primary-teal, #0F766E);">Forgot Password?</a>
    </div>

    <button type="submit" class="btn btn-teal w-100 py-2 fw-semibold shadow-sm mb-3">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Dashboard
    </button>

    <div class="p-3 bg-light rounded-3 text-center small text-muted border">
        <div class="fw-semibold text-dark mb-1">Pre-configured Demo Accounts:</div>
        <div><strong>Admin:</strong> admin@invoiceexcel.test / password</div>
        <div><strong>User:</strong> user@invoiceexcel.test / password</div>
    </div>
</form>
@endsection
