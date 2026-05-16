@extends('layouts.app')

@section('title', 'Approval Dashboard')

@section('content')

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-check2-square me-2"></i>Vendor Comparison Approvals</h4>
        <span class="text-muted">{{ $comparisons->count() }} comparison(s)</span>
    </div>

    @if ($comparisons->isEmpty())
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            No comparisons submitted yet.
            @if (Auth::user()->isCreator())
                Go to <a href="{{ route('rfq.index') }}">RFQ List</a> and click <strong>Compare</strong> to submit one.
            @endif
        </div>
    @else
        <div class="card">
            <div class="card-header py-3">
                <h5><i class="bi bi-table me-2"></i>All Comparisons</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
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
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $c->po_name }}</td>
                                    <td class="text-muted small">{{ $c->po_vendor ?: '—' }}</td>
                                    <td>{{ $c->selected_vendor ?: '—' }}</td>
                                    <td>{{ $c->creator->name ?? '—' }}</td>
                                    <td class="text-muted small">
                                        {{ $c->created_at->format('d M Y H:i') }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $c->statusBadgeClass() }}">
                                            {{ $c->statusLabel() }}
                                        </span>
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
                                                (Auth::user()->isSupervisor() && $c->isPendingSupervisor()) ||
                                                    (Auth::user()->isManager() && $c->isPendingManager()))
                                                <span class="badge bg-warning text-dark ms-1">Action Needed</span>
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
    @endif

@endsection
