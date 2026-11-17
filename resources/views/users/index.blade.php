@extends('layouts.app')

@section('page_title', 'User & Access Management')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark">User Role & Access Management</h4>
        <p class="text-muted small mb-0">Control accounts, permissions, and roles (Admin vs Standard User).</p>
    </div>
    <button type="button" class="btn btn-emerald btn-sm px-3 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-person-plus me-1"></i> Add New User
    </button>
</div>

<!-- Filters Card -->
<div class="card mb-4 border-0 shadow-sm" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name or email..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select form-select-sm">
                    <option value="">All Roles</option>
                    <option value="admin" {{ request('role') == 'admin' ? 'selected' : '' }}>Admin (Full Control)</option>
                    <option value="user" {{ request('role') == 'user' ? 'selected' : '' }}>User (Own Documents & Invoices)</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive / Suspended</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-emerald w-100">Filter</button>
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-light border" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0" style="border: 1px solid var(--slate-200) !important;">
    <div class="card-header bg-white py-3">
        <span class="fw-bold fs-6 text-dark"><i class="bi bi-people text-success me-2"></i> System User Directory ({{ $users->total() }})</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-secondary">
                <tr>
                    <th>User</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Phone</th>
                    <th>Registered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $u)
                    <tr>
                        <td class="fw-semibold text-dark">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; background-color: var(--emerald-50); color: var(--emerald-700); font-size: 0.8rem;">
                                    {{ strtoupper(substr($u->name, 0, 1)) }}
                                </div>
                                <span>{{ $u->name }}</span>
                                @if(auth()->id() === $u->id)
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">You</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-muted">{{ $u->email }}</td>
                        <td>
                            @if($u->role === 'admin')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle text-uppercase" style="font-size: 0.7rem;">
                                    <i class="bi bi-shield-check me-1"></i> Admin
                                </span>
                            @else
                                <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.7rem;">
                                    <i class="bi bi-person me-1"></i> User
                                </span>
                            @endif
                        </td>
                        <td><x-badge-status :status="$u->status" /></td>
                        <td class="text-muted">{{ $u->phone ?? '-' }}</td>
                        <td class="text-muted">{{ $u->created_at->format('M d, Y') }}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-light border p-1 px-2" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $u->id }}" title="Edit User">
                                <i class="bi bi-pencil"></i>
                            </button>

                            @if(auth()->id() !== $u->id)
                                <form action="{{ route('users.destroy', $u) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete user account for {{ $u->email }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger p-1 px-2" title="Delete User">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>

                    <!-- Edit User Modal -->
                    <div class="modal fade" id="editUserModal{{ $u->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content border-0 shadow">
                                <div class="modal-header">
                                    <h6 class="modal-title fw-bold text-dark">Edit User: {{ $u->name }}</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form action="{{ route('users.update', $u) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-body p-4">
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold text-secondary">Full Name</label>
                                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $u->name }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold text-secondary">Email Address</label>
                                            <input type="email" name="email" class="form-control form-control-sm" value="{{ $u->email }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold text-secondary">Assigned Role</label>
                                            <select name="role" class="form-select form-select-sm" required {{ auth()->id() === $u->id ? 'disabled' : '' }}>
                                                <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>Admin (Full Privileges)</option>
                                                <option value="user" {{ $u->role === 'user' ? 'selected' : '' }}>User (Own Documents & Invoices)</option>
                                            </select>
                                            @if(auth()->id() === $u->id)
                                                <input type="hidden" name="role" value="admin">
                                            @endif
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold text-secondary">Account Status</label>
                                            <select name="status" class="form-select form-select-sm" required {{ auth()->id() === $u->id ? 'disabled' : '' }}>
                                                <option value="active" {{ $u->status === 'active' ? 'selected' : '' }}>Active</option>
                                                <option value="inactive" {{ $u->status === 'inactive' ? 'selected' : '' }}>Inactive / Deactivated</option>
                                            </select>
                                            @if(auth()->id() === $u->id)
                                                <input type="hidden" name="status" value="active">
                                            @endif
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold text-secondary">Phone Number</label>
                                            <input type="text" name="phone" class="form-control form-control-sm" value="{{ $u->phone }}">
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label small fw-semibold text-secondary">Reset Password (leave blank to keep current)</label>
                                            <input type="password" name="password" class="form-control form-control-sm" placeholder="••••••••">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-sm btn-emerald rounded-pill px-4">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $users->links() }}
        </div>
    @endif
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-dark">Create New User Account</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Full Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" required placeholder="John Doe">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Email Address</label>
                        <input type="email" name="email" class="form-control form-control-sm" required placeholder="user@company.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Initial Password</label>
                        <input type="password" name="password" class="form-control form-control-sm" required placeholder="Minimum 8 characters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Role Selection</label>
                        <select name="role" class="form-select form-select-sm" required>
                            <option value="user" selected>User (Upload, Process, Match & Export Own Documents)</option>
                            <option value="admin">Admin (Manage Users, System Settings, AI & Full History)</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-secondary">Phone Number (Optional)</label>
                        <input type="text" name="phone" class="form-control form-control-sm" placeholder="+1 (555) 010-9922">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-emerald rounded-pill px-4">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
