@extends('layouts.app')

@section('title', 'Karoseri Acknowledgement')

@push('styles')
<style>
    .teal-bg  { background: #0d9488; }
    .text-teal { color: #0d9488; }
    .border-teal { border-color: #0d9488 !important; }
    .badge-teal { background: #ccfbf1; color: #0f766e; }
    .ack-done td { opacity: .7; }
</style>
@endpush

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-eye-fill text-teal me-2"></i>Karoseri — Acknowledgement (Mengetahui)
        </h4>
        <p class="text-muted small mb-0 mt-1">
            Approved comparisons containing <strong>KAROSERI</strong> items that require your acknowledgement.
        </p>
    </div>
</div>

{{-- Stat cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center border-teal h-100">
            <div class="card-body py-3">
                <div class="display-6 fw-bold text-teal">{{ $pendingCount }}</div>
                <div class="small text-muted mt-1">Pending Acknowledgement</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-success h-100">
            <div class="card-body py-3">
                <div class="display-6 fw-bold text-success">{{ $doneCount }}</div>
                <div class="small text-muted mt-1">Acknowledged</div>
            </div>
        </div>
    </div>
</div>

@if ($comparisons->isEmpty())
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        No approved KAROSERI comparisons found.
    </div>
@else

    {{-- Search --}}
    <div class="mb-3">
        <div class="input-group input-group-sm" style="max-width:360px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="karoSearch" class="form-control" placeholder="Search PO reference or vendor…">
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="karoTable">
                    <thead>
                        <tr>
                            <th class="ps-3">PO Reference</th>
                            <th>Comparison Code</th>
                            <th>Recommended Vendor</th>
                            <th>Submitted By</th>
                            <th class="text-center">Manager Approved</th>
                            <th class="text-center">Acknowledgement</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparisons as $c)
                            <tr class="{{ $c->isAcknowledgedByController() ? 'ack-done' : '' }}"
                                data-search="{{ strtolower($c->po_name . ' ' . $c->po_vendor . ' ' . $c->selected_vendor) }}">
                                <td class="ps-3 fw-semibold">{{ $c->po_name }}</td>
                                <td class="text-muted small">{{ $c->comparison_code ?? '—' }}</td>
                                <td>{{ $c->selected_vendor ?: '—' }}</td>
                                <td>{{ $c->creator->name ?? '—' }}</td>
                                <td class="text-center small">
                                    @if ($c->manager_approved_at)
                                        <span class="text-success">
                                            <i class="bi bi-check-circle-fill me-1"></i>
                                            {{ $c->manager->name ?? '—' }}<br>
                                            <span class="text-muted">{{ $c->manager_approved_at->format('d M Y H:i') }}</span>
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center small">
                                    @if ($c->isAcknowledgedByController())
                                        <span class="text-teal">
                                            <i class="bi bi-eye-fill me-1"></i>
                                            {{ $c->controller->name ?? '—' }}<br>
                                            <span class="text-muted">{{ $c->controller_acknowledged_at->format('d M Y H:i') }}</span>
                                        </span>
                                    @else
                                        <span class="badge badge-teal">Pending</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="{{ route('comparisons.show', $c) }}"
                                            class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        @if (Auth::user()->isController() && !$c->isAcknowledgedByController())
                                            <button type="button"
                                                class="btn btn-sm btn-outline-teal"
                                                style="color:#0d9488;border-color:#0d9488;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#ackModal{{ $c->id }}">
                                                <i class="bi bi-check2-all me-1"></i>Mengetahui
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- Acknowledge modal --}}
                            @if (Auth::user()->isController() && !$c->isAcknowledgedByController())
                                <div class="modal fade" id="ackModal{{ $c->id }}" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('comparisons.acknowledge', $c) }}">
                                                @csrf
                                                <div class="modal-header">
                                                    <h6 class="modal-title">
                                                        <i class="bi bi-eye-fill text-teal me-2"></i>
                                                        Acknowledge — Mengetahui
                                                    </h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small text-muted mb-3">
                                                        You are acknowledging <strong>{{ $c->po_name }}</strong>
                                                        ({{ $c->comparison_code ?? '—' }}) — KAROSERI item comparison
                                                        approved by Manager.
                                                    </p>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Notes <span class="text-muted small">(optional)</span></label>
                                                        <textarea name="notes" class="form-control" rows="3"
                                                            placeholder="Add any notes (optional)…"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                                        data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit"
                                                        class="btn btn-sm text-white"
                                                        style="background:#0d9488;">
                                                        <i class="bi bi-check2-all me-1"></i>Confirm — Mengetahui
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('karoSearch').addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#karoTable tbody tr:not(.modal)').forEach(row => {
                if (row.dataset.search !== undefined) {
                    row.style.display = row.dataset.search.includes(q) ? '' : 'none';
                }
            });
        });
    </script>
@endif

@endsection
