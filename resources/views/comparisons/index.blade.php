@extends('layouts.app')

@section('title', 'Approval Dashboard')

@section('content')

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-check2-square me-2"></i>Vendor Comparison Approvals</h4>
    </div>

    {{-- Stats cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card text-center h-100" style="border-color:#7c3aed">
                <div class="card-body py-3">
                    <div class="display-6 fw-bold" style="color:#7c3aed">{{ $stats['pending_procurement'] }}</div>
                    <div class="small text-muted mt-1">Pending Procurement</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center border-warning h-100">
                <div class="card-body py-3">
                    <div class="display-6 fw-bold text-warning">{{ $stats['pending_supervisor'] }}</div>
                    <div class="small text-muted mt-1">Pending Supervisor</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center border-info h-100">
                <div class="card-body py-3">
                    <div class="display-6 fw-bold text-info">{{ $stats['pending_manager'] }}</div>
                    <div class="small text-muted mt-1">Pending Manager</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-success h-100">
                <div class="card-body py-3">
                    <div class="display-6 fw-bold text-success">{{ $stats['approved'] }}</div>
                    <div class="small text-muted mt-1">Approved</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-danger h-100">
                <div class="card-body py-3">
                    <div class="display-6 fw-bold text-danger">{{ $stats['rejected'] }}</div>
                    <div class="small text-muted mt-1">Rejected</div>
                </div>
            </div>
        </div>
    </div>

    @if ($comparisons->isEmpty())
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            No comparisons submitted yet.
            @if (Auth::user()->isCreator())
                Go to <a href="{{ route('rfq.index') }}">Comparison List</a> and click <strong>Compare</strong> to submit one.
            @endif
        </div>
    @else
        {{-- Search bar --}}
        <div class="mb-3">
            <div class="input-group input-group-sm" style="max-width:360px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="cmpSearch" class="form-control" placeholder="Search PO reference or vendor…">
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="cmpTable">
                        <thead>
                            <tr>
                                <th class="ps-3">PO Reference</th>
                                <th>Vendor (RFQ)</th>
                                <th>Recommended Vendor</th>
                                <th>Submitted By</th>
                                <th>Submitted At</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Supervisor</th>
                                <th class="text-center">Manager</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($comparisons as $c)
                                <tr
                                    data-search="{{ strtolower($c->po_name . ' ' . $c->po_vendor . ' ' . $c->selected_vendor) }}">
                                    <td class="ps-3 fw-semibold">{{ $c->po_name }}</td>
                                    <td class="text-muted small">{{ $c->po_vendor ?: '—' }}</td>
                                    <td>{{ $c->selected_vendor ?: '—' }}</td>
                                    <td>{{ $c->creator->name ?? '—' }}</td>
                                    <td class="text-muted small">
                                        {{ $c->created_at->format('d M Y H:i') }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $c->statusBadgeClass() }}"
                                            @if($c->status === 'pending_procurement') style="background:#7c3aed" @endif>
                                            {{ $c->statusLabel() }}
                                        </span>
                                        @if ($c->isRejected())
                                            <div class="text-danger small mt-1"
                                                style="max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                                                title="{{ $c->rejection_reason }}">
                                                <i
                                                    class="bi bi-exclamation-circle me-1"></i>{{ Str::limit($c->rejection_reason, 30) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($c->supervisor_approved_at)
                                            <span class="text-success small">
                                                <i class="bi bi-check-circle-fill me-1"></i>
                                                {{ $c->supervisor->name ?? '—' }}<br>
                                                <span
                                                    class="text-muted">{{ $c->supervisor_approved_at->format('d M Y') }}</span>
                                            </span>
                                        @elseif($c->isRejected() && $c->rejectedBy && $c->rejectedBy->isSupervisor())
                                            <span class="text-danger small"><i
                                                    class="bi bi-x-circle-fill me-1"></i>Rejected</span>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($c->manager_approved_at)
                                            <span class="text-success small">
                                                <i class="bi bi-check-circle-fill me-1"></i>
                                                {{ $c->manager->name ?? '—' }}<br>
                                                <span
                                                    class="text-muted">{{ $c->manager_approved_at->format('d M Y') }}</span>
                                            </span>
                                        @elseif($c->isRejected() && $c->rejectedBy && $c->rejectedBy->isManager())
                                            <span class="text-danger small"><i
                                                    class="bi bi-x-circle-fill me-1"></i>Rejected</span>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('comparisons.show', $c) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>View
                                            @if (
                                                (Auth::user()->isProcurement() && $c->isPendingProcurement()) ||
                                                (Auth::user()->isSupervisor() && $c->isPendingSupervisor()) ||
                                                (Auth::user()->isManager() && $c->isPendingManager()))
                                                <span class="badge bg-warning text-dark ms-1">Action</span>
                                            @endif
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            document.getElementById('cmpSearch').addEventListener('input', function() {
                const q = this.value.toLowerCase();
                document.querySelectorAll('#cmpTable tbody tr').forEach(row => {
                    row.style.display = row.dataset.search.includes(q) ? '' : 'none';
                });
            });
        </script>
    @endif

@endsection
