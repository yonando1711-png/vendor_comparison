@extends('layouts.app')

@section('title', 'User Management')

@section('content')

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>User Management</h4>
        <span class="text-muted">{{ $users->count() }} user(s)</span>
    </div>

    {{-- Create user form --}}
    <div class="card mb-4">
        <div class="card-header py-2">
            <h6 class="mb-0"><i class="bi bi-person-plus me-2"></i>Add New User</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label form-label-sm fw-semibold">Name</label>
                    <input type="text" name="name"
                        class="form-control form-control-sm @error('name') is-invalid @enderror" value="{{ old('name') }}"
                        required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm fw-semibold">Email</label>
                    <input type="email" name="email"
                        class="form-control form-control-sm @error('email') is-invalid @enderror"
                        value="{{ old('email') }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Role</label>
                    <select name="role" class="form-select form-select-sm" required>
                        <option value="creator" {{ old('role') === 'creator' ? 'selected' : '' }}>Purchasing Staff</option>
                        <option value="supervisor" {{ old('role') === 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                        <option value="procurement" {{ old('role') === 'procurement' ? 'selected' : '' }}>Procurement</option>
                        <option value="manager" {{ old('role') === 'manager' ? 'selected' : '' }}>Manager</option>
                        <option value="viewer" {{ old('role') === 'viewer' ? 'selected' : '' }}>Viewer</option>
                        <option value="controller" {{ old('role') === 'controller' ? 'selected' : '' }}>Controller</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Password</label>
                    <input type="password" name="password"
                        class="form-control form-control-sm @error('password') is-invalid @enderror" required minlength="8"
                        placeholder="Min. 8 chars">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plus-circle me-1"></i>Add User
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- User list --}}
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Name</th>
                        <th>Email</th>
                        <th class="text-center">Role</th>
                        <th class="text-center">Comparisons Created</th>
                        <th class="text-center">Joined</th>
                        <th class="text-center" style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td class="ps-3 fw-semibold">
                                {{ $u->name }}
                                @if ($u->id === Auth::id())
                                    <span class="badge bg-secondary ms-1">You</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $u->email }}</td>
                            <td class="text-center">
                                <span
                                    class="badge {{ match ($u->role) {
                                        'supervisor' => 'bg-info text-dark',
                                        'procurement' => 'bg-warning text-dark',
                                        'manager' => 'bg-primary',
                                        'admin' => 'bg-dark',
                                        'viewer' => 'bg-light text-dark border',
                                        'controller' => 'bg-success',
                                        default => 'bg-secondary',
                                    } }}">{{ $u->roleBadge() }}</span>
                            </td>
                            <td class="text-center">{{ $u->comparisonsCreated()->count() }}</td>
                            <td class="text-center text-muted small">{{ $u->created_at->format('d M Y') }}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal"
                                    data-bs-target="#editModal{{ $u->id }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($u->id !== Auth::id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="d-inline"
                                        onsubmit="return confirm('Delete {{ $u->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>

                        {{-- Edit modal --}}
                        <div class="modal fade" id="editModal{{ $u->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('admin.users.update', $u) }}">
                                        @csrf @method('PUT')
                                        <div class="modal-header">
                                            <h6 class="modal-title">Edit {{ $u->name }}</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Name</label>
                                                <input type="text" name="name" class="form-control"
                                                    value="{{ $u->name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Role</label>
                                                <select name="role" class="form-select">
                                                    <option value="creator" {{ $u->role === 'creator' ? 'selected' : '' }}>
                                                        Purchasing Staff</option>
                                                    <option value="supervisor"
                                                        {{ $u->role === 'supervisor' ? 'selected' : '' }}>Supervisor
                                                    </option>
                                                    <option value="procurement"
                                                        {{ $u->role === 'procurement' ? 'selected' : '' }}>Procurement
                                                    </option>
                                                    <option value="manager"
                                                        {{ $u->role === 'manager' ? 'selected' : '' }}>Manager</option>
                                                    <option value="viewer"
                                                        {{ $u->role === 'viewer' ? 'selected' : '' }}>Viewer
                                                    </option>
                                                    <option value="controller"
                                                        {{ $u->role === 'controller' ? 'selected' : '' }}>Controller
                                                    </option>
                                                    <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>
                                                        Admin</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">New Password <span
                                                        class="text-muted small">(leave blank to keep
                                                        current)</span></label>
                                                <input type="password" name="password" class="form-control"
                                                    minlength="8">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endsection
