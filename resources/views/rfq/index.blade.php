@extends('layouts.app')

@section('title', 'RFQ List')

@section('content')

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2"></i>Request For Quotation (RFQ)</h4>
        <div class="d-flex align-items-center gap-3">
            @if ($cachedAt)
                <span class="text-muted small">
                    <i class="bi bi-clock me-1"></i>Last synced: {{ $cachedAt->diffForHumans() }}
                </span>
            @endif
            <form method="POST" action="{{ route('rfq.refresh') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh from Odoo
                </button>
            </form>
        </div>
    </div>

    {{-- Odoo connection error --}}
    @if ($odooError)
        <div class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-wifi-off fs-5"></i>
            <div>
                <strong>Cannot reach Odoo.</strong> Showing cached data if available.<br>
                <small class="text-muted">{{ $odooError }}</small>
            </div>
        </div>
    @endif

    @if (empty($rfqs) && !$odooError)
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>No RFQs found in the system.
        </div>
    @endif

    @if (!empty($rfqs))
        {{-- Search & Filter bar --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="rfqSearch" class="form-control"
                                placeholder="Search PO reference, vendor, source document…">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="rfqStatusFilter" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            <option value="draft">RFQ</option>
                            <option value="sent">RFQ Sent</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="rfqClvpFilter" class="form-select form-select-sm">
                            <option value="">All CLVPs</option>
                            <option value="has">Has CLVP</option>
                            <option value="none">No CLVP</option>
                        </select>
                    </div>
                    <div class="col-md-1 text-end">
                        <span id="rfqCount" class="text-muted small">{{ count($rfqs) }} records</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="rfqTable">
                        <thead>
                            <tr>
                                <th class="ps-3" style="width:160px">PO Reference</th>
                                <th>Vendor</th>
                                <th>Source Document</th>
                                <th>Buyer</th>
                                <th class="text-center">Lines</th>
                                <th>Order Deadline</th>
                                <th class="text-end">Amount Total</th>
                                <th class="text-center" style="width:80px">Status</th>
                                <th class="text-center" style="width:130px">CLVP</th>
                                <th class="text-center" style="width:100px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rfqs as $rfq)
                                @php
                                    $clvp = $existingComparisons[$rfq['id']] ?? null;
                                    $vendorName = is_array($rfq['partner_id']) ? $rfq['partner_id'][1] : '';
                                    $currency = is_array($rfq['currency_id']) ? $rfq['currency_id'][1] : 'IDR';
                                @endphp
                                @if (($rfq['amount_total'] ?? 0) <= 250000)
                                    @continue
                                @endif
                                <tr data-rfq-name="{{ strtolower($rfq['name']) }}"
                                    data-vendor="{{ strtolower($vendorName) }}"
                                    data-origin="{{ strtolower($rfq['origin'] ?? '') }}" data-state="{{ $rfq['state'] }}"
                                    data-clvp="{{ $clvp ? 'has' : 'none' }}">
                                    <td class="ps-3 fw-semibold">{{ $rfq['name'] }}</td>
                                    <td>{{ $vendorName ?: '—' }}</td>
                                    <td class="text-muted">{{ $rfq['origin'] ?: '—' }}</td>
                                    <td class="text-muted">
                                        {{ is_array($rfq['user_id']) ? $rfq['user_id'][1] : '—' }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary rounded-pill">
                                            {{ count($rfq['order_line']) }}
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        {{ $rfq['date_order'] ? \Illuminate\Support\Carbon::parse($rfq['date_order'])->format('d M Y H:i') : '—' }}
                                    </td>
                                    <td class="text-end fw-semibold">
                                        {{ $currency }} {{ number_format($rfq['amount_total'], 0, ',', '.') }}
                                    </td>
                                    <td class="text-center">
                                        @if ($rfq['state'] === 'sent')
                                            <span class="badge badge-sent">RFQ Sent</span>
                                        @else
                                            <span class="badge badge-rfq">RFQ</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($clvp)
                                            <a href="{{ route('comparisons.show', $clvp->id) }}"
                                                class="badge text-decoration-none {{ $clvp->statusBadgeClass() }}">
                                                {{ $clvp->statusLabel() }}
                                            </a>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('rfq.show', $rfq['id']) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-search me-1"></i>Compare
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

    <script>
        (function() {
            const searchEl = document.getElementById('rfqSearch');
            const statusEl = document.getElementById('rfqStatusFilter');
            const clvpEl = document.getElementById('rfqClvpFilter');
            const countEl = document.getElementById('rfqCount');
            const rows = document.querySelectorAll('#rfqTable tbody tr');

            function filterTable() {
                const q = searchEl.value.toLowerCase().trim();
                const status = statusEl.value;
                const clvp = clvpEl.value;
                let visible = 0;

                rows.forEach(row => {
                    const matchQ = !q ||
                        row.dataset.rfqName.includes(q) ||
                        row.dataset.vendor.includes(q) ||
                        row.dataset.origin.includes(q);
                    const matchStatus = !status || row.dataset.state === status;
                    const matchClvp = !clvp || row.dataset.clvp === clvp;

                    const show = matchQ && matchStatus && matchClvp;
                    row.style.display = show ? '' : 'none';
                    if (show) visible++;
                });

                countEl.textContent = visible + ' record' + (visible !== 1 ? 's' : '');
            }

            searchEl.addEventListener('input', filterTable);
            statusEl.addEventListener('change', filterTable);
            clvpEl.addEventListener('change', filterTable);
        })();
    </script>

@endsection
