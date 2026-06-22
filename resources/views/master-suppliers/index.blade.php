@extends('layouts.app')

@section('title', 'Master Supplier')

@section('content')
    <h5 class="mb-3"><i class="bi bi-building me-2"></i>Master Supplier</h5>
    <p class="text-muted small mb-3">
        Kelola supplier lokal yang belum terdaftar di Odoo. Supplier ini dapat digunakan dalam perbandingan harga.
    </p>

    @if ($canManage)
        {{-- Add Supplier Form --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-plus-circle me-2"></i>Tambah Supplier Baru</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('master-suppliers.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Supplier <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Telepon</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">HP</label>
                            <input type="text" name="mobile" class="form-control" value="{{ old('mobile') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Alamat (Jalan)</label>
                            <input type="text" name="street" class="form-control" value="{{ old('street') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Alamat Lanjutan</label>
                            <input type="text" name="street2" class="form-control" value="{{ old('street2') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kota</label>
                            <input type="text" name="city" class="form-control" value="{{ old('city') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>Simpan Supplier
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Supplier List --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-list me-2"></i>Daftar Supplier</h5>
            <span class="badge bg-secondary">{{ $suppliers->count() }} supplier</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Kontak</th>
                            <th>Alamat</th>
                            <th>Catatan</th>
                            <th>Status</th>
                            @if ($canManage)
                                <th style="width:120px">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suppliers as $s)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $s->name }}</div>
                                    <div class="text-muted small">Dibuat: {{ $s->creator?->name ?? '—' }}</div>
                                </td>
                                <td>
                                    @if ($s->phone)
                                        <div class="small"><i class="bi bi-telephone me-1"></i>{{ $s->phone }}</div>
                                    @endif
                                    @if ($s->mobile)
                                        <div class="small"><i class="bi bi-phone me-1"></i>{{ $s->mobile }}</div>
                                    @endif
                                    @if ($s->email)
                                        <div class="small"><i class="bi bi-envelope me-1"></i>{{ $s->email }}</div>
                                    @endif
                                    @if (!$s->phone && !$s->mobile && !$s->email)
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($s->street || $s->street2 || $s->city)
                                        <div class="small">
                                            {{ $s->street }}{{ $s->street2 ? ', ' . $s->street2 : '' }}{{ $s->city ? ', ' . $s->city : '' }}
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-muted small">{{ Str::limit($s->notes, 60) ?: '—' }}</span>
                                </td>
                                <td>
                                    @if ($s->is_active)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                            data-bs-target="#editModal{{ $s->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('master-suppliers.destroy', $s) }}"
                                            class="d-inline" onsubmit="return confirm('Hapus supplier ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 6 : 5 }}" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Belum ada supplier.
                                    @if ($canManage)
                                        Tambahkan supplier baru di atas.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Edit Modals --}}
    @if ($canManage)
        @foreach ($suppliers as $s)
        <div class="modal fade" id="editModal{{ $s->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('master-suppliers.update', $s) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Supplier — {{ $s->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Nama Supplier <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required value="{{ $s->name }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ $s->email }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Telepon</label>
                                    <input type="text" name="phone" class="form-control" value="{{ $s->phone }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">HP</label>
                                    <input type="text" name="mobile" class="form-control" value="{{ $s->mobile }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Alamat (Jalan)</label>
                                    <input type="text" name="street" class="form-control" value="{{ $s->street }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Alamat Lanjutan</label>
                                    <input type="text" name="street2" class="form-control" value="{{ $s->street2 }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Kota</label>
                                    <input type="text" name="city" class="form-control" value="{{ $s->city }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Catatan</label>
                                    <textarea name="notes" class="form-control" rows="2">{{ $s->notes }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                            id="isActive{{ $s->id }}" {{ $s->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label" for="isActive{{ $s->id }}">
                                            Aktif (bisa dipilih dalam perbandingan)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    @endif
@endsection
