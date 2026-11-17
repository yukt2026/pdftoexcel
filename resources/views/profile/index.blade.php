@extends('layouts.app')

@section('page_title', 'User Profile & Security')

@section('content')
<div class="row g-4">
    <!-- User Overview Card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-body text-center p-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 fw-bold" style="width: 72px; height: 72px; background-color: var(--emerald-50); color: var(--emerald-700); font-size: 1.8rem;">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <h5 class="fw-bold text-dark mb-1">{{ $user->name }}</h5>
                <div class="text-muted small mb-3">{{ $user->email }}</div>
                <div>
                    <span class="badge bg-emerald-50 text-emerald-700 border border-success-subtle text-uppercase px-3 py-1 rounded-pill" style="background-color: var(--emerald-50); color: var(--emerald-700); font-size: 0.75rem;">
                        {{ $user->role === 'admin' ? 'Administrator' : 'Standard User' }}
                    </span>
                    <x-badge-status :status="$user->status" />
                </div>
            </div>
            <div class="card-footer bg-light p-3 small text-muted">
                <div class="d-flex justify-content-between mb-1">
                    <span>Member Since:</span>
                    <span class="fw-semibold text-dark">{{ $user->created_at->format('M d, Y') }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Phone Contact:</span>
                    <span class="fw-semibold text-dark">{{ $user->phone ?? 'Not set' }}</span>
                </div>
            </div>
        </div>

        <!-- Session Security Card -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                <i class="bi bi-shield-lock me-1 text-success"></i> Session Management
            </div>
            <div class="card-body p-3">
                <p class="text-muted small mb-3">
                    If you suspect unauthorized activity, you can terminate all other active browser sessions across your devices.
                </p>
                <form action="{{ route('profile.logoutOtherDevices') }}" method="POST" onsubmit="return confirm('Log out of all other active browser sessions?');">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Confirm Current Password</label>
                        <input type="password" name="password" class="form-control form-control-sm" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100 rounded-pill">
                        <i class="bi bi-box-arrow-right me-1"></i> Log Out Other Sessions
                    </button>
                </form>
            </div>
        </div>

        <!-- Personal Activity Log -->
        <div class="card border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3 fw-bold small text-uppercase text-dark">
                <i class="bi bi-clock-history me-1 text-primary"></i> My Recent Operations
            </div>
            <ul class="list-group list-group-flush small">
                @forelse($recentActivities as $act)
                    <li class="list-group-item py-2 px-3">
                        <div class="text-truncate fw-medium text-dark">{{ $act->description }}</div>
                        <div class="text-muted" style="font-size: 0.7rem;">{{ $act->created_at->diffForHumans() }}</div>
                    </li>
                @empty
                    <li class="list-group-item text-muted text-center py-3">No activity logged yet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <!-- Forms Column -->
    <div class="col-lg-8">
        <!-- 1. Profile Information -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3">
                <span class="fw-bold fs-6 text-dark"><i class="bi bi-person me-2 text-success"></i> Account Profile Information</span>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Full Name</label>
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email', $user->email) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Phone Number</label>
                            <input type="text" name="phone" class="form-control form-control-sm" value="{{ old('phone', $user->phone) }}" placeholder="+1 555-0100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Assigned Role</label>
                            <input type="text" class="form-control form-control-sm bg-light text-uppercase fw-semibold" value="{{ $user->role }}" readonly disabled>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-emerald btn-sm px-4 rounded-pill shadow-sm fw-semibold">
                            <i class="bi bi-check2 me-1"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. Change Password -->
        <div class="card border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
            <div class="card-header bg-white py-3">
                <span class="fw-bold fs-6 text-dark"><i class="bi bi-key me-2 text-warning"></i> Change Password</span>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('profile.password') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Current Password</label>
                            <input type="password" name="current_password" class="form-control form-control-sm" required placeholder="••••••••">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">New Password</label>
                            <input type="password" name="password" class="form-control form-control-sm" required placeholder="Min. 8 characters">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                            <input type="password" name="password_confirmation" class="form-control form-control-sm" required placeholder="••••••••">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-emerald btn-sm px-4 rounded-pill shadow-sm fw-semibold">
                            <i class="bi bi-shield-check me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
