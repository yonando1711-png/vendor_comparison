@extends('layouts.app')

@section('title', 'CLVP – ' . $comparison->po_name)

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('comparisons.index') }}">Approvals</a></li>
            <li class="breadcrumb-item active">{{ $comparison->po_name }}</li>
        </ol>
    </nav>

    {{-- Prominent rejection notice for creators --}}
    @if ($comparison->isRejected() && Auth::user()->isCreator())
        <div class="alert alert-danger d-flex gap-3 align-items-start mb-4">
            <i class="bi bi-x-octagon-fill fs-4 flex-shrink-0 mt-1"></i>
            <div>
                <h6 class="mb-1 fw-bold">This CLVP was rejected</h6>
                <div class="mb-1">
                    Rejected by <strong>{{ $comparison->rejectedBy->name ?? '—' }}</strong>
                    on <strong>{{ $comparison->rejected_at?->format('d M Y H:i') }}</strong>
                </div>
                <div class="fst-italic">"{{ $comparison->rejection_reason }}"</div>
                <div class="mt-2">
                    <a href="{{ route('rfq.show', $comparison->po_id) }}" class="btn btn-sm btn-danger">
                        <i class="bi bi-pencil me-1"></i>Submit New CLVP for this RFQ
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-4" id="clvpTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabApproval">
                <i class="bi bi-diagram-3 me-1"></i>Approval Workflow
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabClvp">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Dokumen CLVP
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLog">
                <i class="bi bi-journal-text me-1"></i>Audit Log
                <span class="badge bg-secondary ms-1">{{ $comparison->logs->count() }}</span>
            </button>
        </li>
    </ul>

    <div class="tab-content">

        {{-- ════════════════════════════════════════════════════════ --}}
        {{-- TAB 1: APPROVAL WORKFLOW                                 --}}
        {{-- ════════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade show active" id="tabApproval">
            <div class="row g-4">

                {{-- ─── Left column: approval workflow card ─────────────────── --}}
                <div class="col-lg-4">

                    {{-- Status card --}}
                    <div class="card mb-3">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Approval Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-semibold">{{ $comparison->po_name }}</span>
                                <span class="badge {{ $comparison->statusBadgeClass() }} fs-6">
                                    {{ $comparison->statusLabel() }}
                                </span>
                            </div>

                            {{-- Edit button for creator while pending supervisor --}}
                            @if ($comparison->isEditableBy(Auth::user()))
                                <div class="mb-3">
                                    <a href="{{ route('comparisons.edit', $comparison) }}"
                                        class="btn btn-outline-warning btn-sm w-100">
                                        <i class="bi bi-pencil me-1"></i>Edit CLVP (pending supervisor)
                                    </a>
                                </div>
                            @endif

                            {{-- Timeline --}}
                            <ul class="list-unstyled mb-0">
                                {{-- Step 1: Submission --}}
                                <li class="d-flex gap-3 mb-3">
                                    <div class="text-center" style="width:28px">
                                        <span class="badge bg-success rounded-circle p-2">
                                            <i class="bi bi-check-lg"></i>
                                        </span>
                                        <div style="width:2px;height:30px;background:#e5e7eb;margin:4px auto"></div>
                                    </div>
                                    <div>
                                        <div class="fw-semibold small">Submitted</div>
                                        <div class="text-muted small">{{ $comparison->creator->name ?? '—' }}</div>
                                        <div class="text-muted small">{{ $comparison->created_at->format('d M Y H:i') }}
                                        </div>
                                        @if ($comparison->notes)
                                            <div class="mt-1 p-2 bg-light rounded small fst-italic">
                                                {{ $comparison->notes }}
                                            </div>
                                        @endif
                                    </div>
                                </li>

                                {{-- Step 2: Supervisor --}}
                                <li class="d-flex gap-3 mb-3">
                                    <div class="text-center" style="width:28px">
                                        @if ($comparison->supervisor_approved_at)
                                            <span class="badge bg-success rounded-circle p-2"><i
                                                    class="bi bi-check-lg"></i></span>
                                        @elseif($comparison->isRejected() && $comparison->rejectedBy?->isSupervisor())
                                            <span class="badge bg-danger rounded-circle p-2"><i
                                                    class="bi bi-x-lg"></i></span>
                                        @elseif($comparison->isPendingSupervisor())
                                            <span class="badge bg-warning rounded-circle p-2"><i
                                                    class="bi bi-hourglass-split"></i></span>
                                        @else
                                            <span class="badge bg-secondary rounded-circle p-2"><i
                                                    class="bi bi-dash"></i></span>
                                        @endif
                                        <div style="width:2px;height:30px;background:#e5e7eb;margin:4px auto"></div>
                                    </div>
                                    <div>
                                        <div class="fw-semibold small">Supervisor Approval</div>
                                        @if ($comparison->supervisor_approved_at)
                                            <div class="text-muted small">{{ $comparison->supervisor->name ?? '—' }}</div>
                                            <div class="text-muted small">
                                                {{ $comparison->supervisor_approved_at->format('d M Y H:i') }}</div>
                                            @if ($comparison->supervisor_notes)
                                                <div class="mt-1 p-2 bg-light rounded small fst-italic">
                                                    {{ $comparison->supervisor_notes }}</div>
                                            @endif
                                        @elseif($comparison->isPendingSupervisor())
                                            <div class="text-warning small">Waiting for approval…</div>
                                        @else
                                            <div class="text-muted small">—</div>
                                        @endif
                                    </div>
                                </li>

                                {{-- Step 3: Manager --}}
                                <li class="d-flex gap-3">
                                    <div class="text-center" style="width:28px">
                                        @if ($comparison->manager_approved_at)
                                            <span class="badge bg-success rounded-circle p-2"><i
                                                    class="bi bi-check-lg"></i></span>
                                        @elseif($comparison->isRejected() && $comparison->rejectedBy?->isManager())
                                            <span class="badge bg-danger rounded-circle p-2"><i
                                                    class="bi bi-x-lg"></i></span>
                                        @elseif($comparison->isPendingManager())
                                            <span class="badge bg-warning rounded-circle p-2"><i
                                                    class="bi bi-hourglass-split"></i></span>
                                        @else
                                            <span class="badge bg-secondary rounded-circle p-2"><i
                                                    class="bi bi-dash"></i></span>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="fw-semibold small">Manager Approval</div>
                                        @if ($comparison->manager_approved_at)
                                            <div class="text-muted small">{{ $comparison->manager->name ?? '—' }}</div>
                                            <div class="text-muted small">
                                                {{ $comparison->manager_approved_at->format('d M Y H:i') }}</div>
                                            @if ($comparison->manager_notes)
                                                <div class="mt-1 p-2 bg-light rounded small fst-italic">
                                                    {{ $comparison->manager_notes }}</div>
                                            @endif
                                        @elseif($comparison->isPendingManager())
                                            <div class="text-warning small">Waiting for approval…</div>
                                        @else
                                            <div class="text-muted small">—</div>
                                        @endif
                                    </div>
                                </li>
                            </ul>

                            {{-- Rejection info --}}
                            @if ($comparison->isRejected())
                                <div class="alert alert-danger mt-3 mb-0 py-2 small">
                                    <strong><i class="bi bi-x-circle me-1"></i>Rejected</strong>
                                    by {{ $comparison->rejectedBy->name ?? '—' }}
                                    on {{ $comparison->rejected_at?->format('d M Y H:i') }}<br>
                                    <em>{{ $comparison->rejection_reason }}</em>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Action card (supervisor / manager) --}}
                    @if (
                        (Auth::user()->isSupervisor() && $comparison->isPendingSupervisor()) ||
                            (Auth::user()->isManager() && $comparison->isPendingManager()))
                        <div class="card border-warning">
                            <div class="card-header py-2" style="background:#fefce8; border-color:#fde047">
                                <h6 class="mb-0 text-warning"><i class="bi bi-pencil-square me-2"></i>Your Action Required
                                </h6>
                            </div>
                            <div class="card-body">
                                {{-- Approve --}}
                                <form method="POST" action="{{ route('comparisons.approve', $comparison) }}"
                                    class="mb-3">
                                    @csrf
                                    <label class="form-label fw-semibold small">Approval Notes (optional)</label>
                                    <textarea name="notes" class="form-control form-control-sm mb-2" rows="2" placeholder="Add a comment…"></textarea>
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-check-circle me-2"></i>
                                        @if (Auth::user()->isSupervisor())
                                            Approve (Send to Manager)
                                        @else
                                            Approve (Final Approval)
                                        @endif
                                    </button>
                                </form>

                                {{-- Reject --}}
                                <form method="POST" action="{{ route('comparisons.reject', $comparison) }}">
                                    @csrf
                                    <label class="form-label fw-semibold small text-danger">Rejection Reason <span
                                            class="text-danger">*</span></label>
                                    <textarea name="rejection_reason" class="form-control form-control-sm mb-2" rows="2"
                                        placeholder="State the reason for rejection…" required></textarea>
                                    <button type="submit" class="btn btn-outline-danger w-100"
                                        onclick="return confirm('Are you sure you want to reject this comparison?')">
                                        <i class="bi bi-x-circle me-2"></i>Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- Odoo Integration hidden --}}
                    @if (false)
                        <div class="card mt-3 {{ $comparison->odoo_synced_at ? 'border-success' : 'border-primary' }}">
                            <div
                                class="card-header py-2 {{ $comparison->odoo_synced_at ? 'bg-success' : 'bg-primary' }} text-white">
                                <h6 class="mb-0"><i class="bi bi-cloud me-2"></i>Odoo Integration</h6>
                            </div>
                            <div class="card-body">
                                @if ($comparison->odoo_synced_at)
                                    <div class="d-flex align-items-center gap-2 text-success">
                                        <i class="bi bi-cloud-check-fill fs-4"></i>
                                        <div>
                                            <div class="fw-semibold small">Posted to Odoo</div>
                                            <div class="text-muted small">
                                                {{ $comparison->odoo_synced_at->format('d M Y H:i') }}</div>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-muted small mb-3">Attach the CLVP PDF to the Odoo RFQ and post an
                                        approval note to its chatter.</p>
                                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal"
                                        data-bs-target="#odooPostModal">
                                        <i class="bi bi-cloud-upload me-2"></i>Post to Odoo
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif {{-- /Odoo Integration hidden --}}
                </div>

                {{-- ─── Right column: RFQ + vendor comparison data ────────── --}}
                <div class="col-lg-8">

                    {{-- RFQ Summary --}}
                    @if ($rfq)
                        <div class="card mb-4">
                            <div class="card-header py-2">
                                <h6 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>{{ $rfq['name'] }}</h6>
                            </div>
                            <div class="card-body py-2">
                                <div class="row g-2 small">
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted">Vendor</div>
                                        <div class="fw-semibold">
                                            {{ is_array($rfq['partner_id']) ? $rfq['partner_id'][1] : '—' }}
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted">Source</div>
                                        <div class="fw-semibold">{{ $rfq['origin'] ?: '—' }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted">Recommended Vendor</div>
                                        <div class="fw-semibold text-primary">{{ $comparison->selected_vendor ?: '—' }}
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted">Total</div>
                                        <div class="fw-semibold">
                                            {{ is_array($rfq['currency_id']) ? $rfq['currency_id'][1] : 'IDR' }}
                                            {{ number_format($rfq['amount_total'], 0, ',', '.') }}
                                        </div>
                                    </div>
                                </div>
                                @if ($comparison->notes)
                                    <div class="mt-2 p-2 bg-light rounded small">
                                        <strong>Notes:</strong> {{ $comparison->notes }}
                                    </div>
                                @endif

                                {{-- Vendors selected for this comparison --}}
                                @if (!empty($comparison->vendors))
                                    <div class="mt-2">
                                        <div class="text-muted small fw-semibold mb-1">Vendors Selected for Comparison
                                            ({{ count($comparison->vendors) }}):</div>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach ($comparison->vendors as $v)
                                                <span
                                                    class="badge {{ $v['name'] === $comparison->selected_vendor ? 'bg-success' : 'bg-light text-dark border' }}">
                                                    @if ($v['name'] === $comparison->selected_vendor)
                                                        <i class="bi bi-check-circle-fill me-1"></i>
                                                    @endif
                                                    {{ $v['name'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                        <div class="text-muted small mt-1">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>Green = recommended
                                            vendor
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Per-product vendor comparison (condensed) --}}
                        @php $currency = is_array($rfq['currency_id']) ? $rfq['currency_id'][1] : 'IDR'; @endphp
                        @foreach ($rfq['lines'] as $line)
                            @php
                                if (!is_array($line['product_id'])) {
                                    continue;
                                }
                                $productId = $line['product_id'][0];
                                $productName = $line['product_id'][1];
                                $uom = is_array($line['product_uom']) ? $line['product_uom'][1] : '';
                                $rfqVendorId = is_array($rfq['partner_id']) ? $rfq['partner_id'][0] : null;
                                $vendorRows = $history[$productId] ?? [];
                                $allPrices = array_column(array_values($vendorRows), 'price_unit');
                                $allPrices[] = $line['price_unit'];
                                $allPrices = array_filter($allPrices, fn($p) => $p > 0);
                                $bestPrice = !empty($allPrices) ? min($allPrices) : null;
                                $worstPrice = !empty($allPrices) ? max($allPrices) : null;
                                $rfqVendorNm = is_array($rfq['partner_id']) ? $rfq['partner_id'][1] : '—';
                                $otherRows = array_values(
                                    array_filter(array_values($vendorRows), fn($r) => $r['vendor_id'] !== $rfqVendorId),
                                );
                                $byDate = $otherRows;
                                usort($byDate, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
                                $mostRecentRow = $byDate[0] ?? null;
                                $byPrice = $otherRows;
                                usort($byPrice, fn($a, $b) => $a['price_unit'] <=> $b['price_unit']);
                                $cheapRows = array_slice(
                                    array_values(
                                        array_filter(
                                            $byPrice,
                                            fn($r) => !$mostRecentRow ||
                                                $r['vendor_id'] !== $mostRecentRow['vendor_id'],
                                        ),
                                    ),
                                    0,
                                    3,
                                );
                                $totalHistoryCount = count($otherRows);
                            @endphp
                            <div class="card mb-3">
                                <div class="card-header py-2 d-flex align-items-center gap-2 flex-wrap">
                                    <i class="bi bi-box-seam text-muted"></i>
                                    <span class="badge bg-secondary">{{ $productName }}</span>
                                    <span class="fw-semibold">{{ $line['name'] }}</span>
                                    <span class="badge bg-light text-dark border">{{ $line['product_qty'] }}
                                        {{ $uom }}</span>
                                    <span class="ms-auto text-muted small">
                                        RFQ Unit Price:&nbsp;
                                        <strong @class([
                                            'text-success' =>
                                                $bestPrice !== null &&
                                                $line['price_unit'] == $bestPrice &&
                                                count($allPrices) > 1,
                                            'text-danger' =>
                                                $worstPrice !== null &&
                                                $line['price_unit'] == $worstPrice &&
                                                $bestPrice !== $worstPrice &&
                                                count($allPrices) > 1,
                                        ])>
                                            {{ $currency }} {{ number_format($line['price_unit'], 2, ',', '.') }}
                                        </strong>
                                    </span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="ps-3">Vendor</th>
                                                    <th class="text-center">Unit Price</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-center">UoM</th>
                                                    <th>Last PO</th>
                                                    <th>Last Purchase Date</th>
                                                    <th class="text-center">Note</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $isCurrentBest =
                                                        $bestPrice !== null &&
                                                        $line['price_unit'] == $bestPrice &&
                                                        count($allPrices) > 1;
                                                    $isCurrentWorst =
                                                        $worstPrice !== null &&
                                                        $line['price_unit'] == $worstPrice &&
                                                        $bestPrice !== $worstPrice;
                                                    $currentClass = 'price-current';
                                                    if ($isCurrentBest) {
                                                        $currentClass = 'price-best';
                                                    } elseif ($isCurrentWorst) {
                                                        $currentClass = 'price-worst';
                                                    }
                                                @endphp
                                                <tr class="{{ $currentClass }}">
                                                    <td class="ps-3 fw-semibold">
                                                        <i
                                                            class="bi bi-star-fill text-warning me-1"></i>{{ $rfqVendorNm }}
                                                    </td>
                                                    <td class="text-center fw-bold">{{ $currency }}
                                                        {{ number_format($line['price_unit'], 2, ',', '.') }}</td>
                                                    <td class="text-center">{{ $line['product_qty'] }}</td>
                                                    <td class="text-center">{{ $uom }}</td>
                                                    <td class="text-muted">{{ $rfq['name'] }}</td>
                                                    <td class="text-muted">
                                                        {{ \Carbon\Carbon::parse($rfq['date_order'])->format('d M Y') }}
                                                    </td>
                                                    <td class="text-center"><span
                                                            class="badge bg-warning text-dark">Current RFQ</span></td>
                                                </tr>

                                                @if ($mostRecentRow)
                                                    @php $isBest = $bestPrice !== null && $mostRecentRow['price_unit'] == $bestPrice; @endphp
                                                    <tr class="table-info" style="border-left:3px solid #0d6efd;">
                                                        <td class="ps-3 fw-semibold">{{ $mostRecentRow['vendor_name'] }}
                                                        </td>
                                                        <td class="text-center fw-semibold">
                                                            {{ $currency }}
                                                            {{ number_format($mostRecentRow['price_unit'], 2, ',', '.') }}
                                                            @if ($isBest)
                                                                <i class="bi bi-check-circle-fill text-success ms-1"
                                                                    title="Best Price"></i>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">{{ $mostRecentRow['product_qty'] }}</td>
                                                        <td class="text-center">{{ $mostRecentRow['uom'] }}</td>
                                                        <td class="text-muted small">{{ $mostRecentRow['po_name'] }}</td>
                                                        <td class="fw-semibold">
                                                            {{ \Carbon\Carbon::parse($mostRecentRow['date'])->format('d M Y H:i') }}
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-primary">Latest Purchase</span>
                                                            @if ($isBest)
                                                                <span class="badge bg-success">Best Price</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endif

                                                @foreach ($cheapRows as $row)
                                                    @php
                                                        $isBest =
                                                            $bestPrice !== null && $row['price_unit'] == $bestPrice;
                                                        $isWorst =
                                                            $worstPrice !== null &&
                                                            $row['price_unit'] == $worstPrice &&
                                                            $bestPrice !== $worstPrice;
                                                    @endphp
                                                    <tr
                                                        class="{{ $isBest ? 'price-best' : ($isWorst ? 'price-worst' : '') }}">
                                                        <td class="ps-3">{{ $row['vendor_name'] }}</td>
                                                        <td class="text-center">
                                                            {{ $currency }}
                                                            {{ number_format($row['price_unit'], 2, ',', '.') }}
                                                            @if ($isBest)
                                                                <i class="bi bi-check-circle-fill text-success ms-1"></i>
                                                            @elseif ($isWorst)
                                                                <i class="bi bi-arrow-up-circle-fill text-danger ms-1"></i>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">{{ $row['product_qty'] }}</td>
                                                        <td class="text-center">{{ $row['uom'] }}</td>
                                                        <td class="text-muted small">{{ $row['po_name'] }}</td>
                                                        <td class="text-muted">
                                                            {{ \Carbon\Carbon::parse($row['date'])->format('d M Y H:i') }}
                                                        </td>
                                                        <td class="text-center">
                                                            @if ($isBest)
                                                                <span class="badge bg-success">Best Price</span>
                                                            @elseif ($isWorst)
                                                                <span class="badge bg-danger">Highest</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach

                                                @if (!$mostRecentRow && empty($cheapRows))
                                                    <tr>
                                                        <td colspan="7" class="text-center text-muted py-3 small">
                                                            <i class="bi bi-clock-history me-1"></i>No purchase history
                                                            from other vendors.
                                                        </td>
                                                    </tr>
                                                @elseif ($totalHistoryCount > 4)
                                                    <tr>
                                                        <td colspan="7"
                                                            class="text-center text-muted py-2 small fst-italic">
                                                            Showing latest purchase + 3 cheapest of
                                                            {{ $totalHistoryCount }} vendors in history.
                                                        </td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="alert alert-warning">Could not load RFQ data from Odoo.</div>
                    @endif

                </div>{{-- end right column --}}
            </div>{{-- end row --}}
        </div>{{-- end tabApproval --}}

        {{-- ════════════════════════════════════════════════════════ --}}
        {{-- TAB 2: CLVP DOCUMENT                                    --}}
        {{-- ════════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="tabClvp">

            <div class="d-flex justify-content-end mb-3 gap-2">
                <a href="{{ route('comparisons.pdf', $comparison) }}" target="_blank" class="btn btn-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Download PDF
                </a>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>

            {{-- Post to Odoo modal hidden --}}
            @if (false)
                <div class="modal fade" id="odooPostModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('comparisons.odoo-post', $comparison) }}"
                                id="odooPostForm">
                                @csrf
                                <div class="modal-header">
                                    <h6 class="modal-title">
                                        <i class="bi bi-cloud-upload me-2 text-primary"></i>Post to Odoo —
                                        {{ $comparison->po_name }}
                                    </h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted small mb-3">
                                        This will attach the CLVP PDF to the Odoo RFQ and post a log note to its chatter.
                                        The note will include the recommended vendor, approval details, and your message
                                        below.
                                    </p>
                                    <div class="bg-light border rounded p-3 mb-3 small">
                                        <div class="fw-semibold mb-1 text-muted">Auto-generated summary:</div>
                                        <div>✅ CLVP Approved — Recommended:
                                            <strong>{{ $comparison->selected_vendor }}</strong>
                                        </div>
                                        <div>Approved by: <strong>{{ $comparison->manager->name ?? '—' }}</strong>
                                            on {{ $comparison->manager_approved_at?->format('d M Y') }}</div>
                                        <div>Submitted by: <strong>{{ $comparison->creator->name ?? '—' }}</strong></div>
                                    </div>
                                    <label class="form-label fw-semibold">Additional note <span
                                            class="text-muted fw-normal">(optional)</span></label>
                                    <textarea name="note" id="odooNoteInput" class="form-control" rows="4"
                                        placeholder="Write any extra context to include in the Odoo log note, e.g. 'PO to be issued by end of week' or special instructions…"></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" id="odooPostBtn" class="btn btn-primary btn-sm">
                                        <i class="bi bi-cloud-upload me-1"></i>Post to Odoo
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <script>
                    document.getElementById('odooPostForm').addEventListener('submit', function() {
                        const btn = document.getElementById('odooPostBtn');
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Posting…';
                    });
                </script>
            @endif {{-- /Post to Odoo modal hidden --}}

            {{-- ── CLVP Document ── --}}
            <div id="clvpDocument" class="bg-white border p-4"
                style="font-family: Arial, sans-serif; font-size:12px; color:#000;">

                {{-- Header --}}
                <div class="text-center mb-3">
                    <div style="font-size:15px; font-weight:bold; letter-spacing:1px;">
                        COMPARISON LOCAL VENDOR PRICE ( CLVP )
                    </div>
                </div>

                <div class="d-flex align-items-start mb-3 gap-4">
                    {{-- Logo area --}}
                    <div style="min-width:90px;">
                        <img src="{{ asset('logo.png') }}" style="height:55px; max-width:140px; object-fit:contain;">
                    </div>

                    {{-- Category checkboxes --}}
                    <div class="d-flex gap-4 align-items-center" style="font-size:12px;">
                        @php
                            $catMap = [
                                'unit_baru' => 'Unit Baru',
                                'aksesoris' => 'Aksesoris Mobil',
                                'sparepart' => 'Sparepart',
                                'umum' => 'Umum',
                            ];
                        @endphp
                        @foreach ($catMap as $val => $lbl)
                            <span style="display:inline-flex; align-items:center; gap:4px;">
                                <span
                                    style="display:inline-block; width:14px; height:14px; border:1.5px solid #000; text-align:center; line-height:12px; font-size:11px; font-weight:bold;">
                                    {{ $comparison->category === $val ? 'V' : '' }}
                                </span>
                                {{ $lbl }}
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- CLVP Table --}}
                @php
                    $vendors = $comparison->vendors ?? [];
                    $vpRows = $comparison->vendor_prices ?? [];
                    $currency = 'Rp';
                @endphp

                <table style="width:100%; border-collapse:collapse; font-size:11px;">
                    <thead>
                        {{-- Row 1: fixed headers + "MITRA BISNIS" spanning vendor cols --}}
                        <tr>
                            <th rowspan="2"
                                style="border:1px solid #000; padding:4px 6px; text-align:center; width:28px;">No</th>
                            <th rowspan="2" style="border:1px solid #000; padding:4px 6px; text-align:center;">Nama
                                Barang</th>
                            <th rowspan="2"
                                style="border:1px solid #000; padding:4px 6px; text-align:center; width:60px;">Kode Barang
                            </th>
                            <th rowspan="2"
                                style="border:1px solid #000; padding:4px 6px; text-align:center; width:36px;">Qty</th>
                            <th rowspan="2"
                                style="border:1px solid #000; padding:4px 6px; text-align:center; width:36px;">UoM</th>
                            <th rowspan="2"
                                style="border:1px solid #000; padding:4px 6px; text-align:center; width:90px;">Pricelist
                                Original</th>
                            @if (!empty($vendors))
                                <th colspan="{{ count($vendors) }}"
                                    style="border:1px solid #000; padding:4px 6px; text-align:center; background:#f0f0f0;">
                                    MITRA BISNIS
                                </th>
                            @endif
                        </tr>
                        {{-- Row 2: individual vendor names + PIC/TELP --}}
                        <tr>
                            @foreach ($vendors as $vi => $v)
                                @php
                                    $isRec = ($v['name'] ?? '') === $comparison->selected_vendor;
                                @endphp
                                <th
                                    style="border:1px solid #000; padding:4px 6px; text-align:center; min-width:110px;
                                {{ $isRec ? 'background:#d4edda;' : '' }}">
                                    <div style="font-weight:bold;">{{ $v['name'] ?? '—' }}</div>
                                    @if (!empty($v['pic']))
                                        <div style="font-weight:normal; font-size:10px;">PIC : {{ $v['pic'] }}</div>
                                    @endif
                                    @if (!empty($v['phone']))
                                        <div style="font-weight:normal; font-size:10px;">TELP : {{ $v['phone'] }}</div>
                                    @endif
                                    @if ($isRec)
                                        <div style="font-size:9px; color:#155724; font-weight:bold;">✓ Rekomendasi</div>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Build ordered RFQ line index for product name lookup --}}
                        @php
                            $rfqProductLines = [];
                            if (!empty($rfq['lines'])) {
                                $li = 0;
                                foreach ($rfq['lines'] as $l) {
                                    if (!is_array($l['product_id'])) {
                                        continue;
                                    }
                                    $rfqProductLines[$li++] = $l;
                                }
                            }
                        @endphp
                        {{-- Product rows --}}
                        @foreach ($vpRows as $ri => $row)
                            @php
                                $rl = $rfqProductLines[$ri] ?? null;
                                $pCode = $rl ? $rl['product_code'] ?? '' : $row['product_code'] ?? '';
                                $pName = $rl ? $rl['name'] : $row['product_name'] ?? '';
                            @endphp
                            <tr>
                                <td style="border:1px solid #000; padding:4px 6px; text-align:center;">{{ $ri + 1 }}
                                </td>
                                <td style="border:1px solid #000; padding:4px 6px;">
                                    @if (!empty($pCode))
                                        <span
                                            style="background:#6c757d;color:#fff;padding:1px 4px;border-radius:3px;font-size:8px;margin-right:3px;">{{ $pCode }}</span>
                                    @endif
                                    {{ $pName }}
                                </td>
                                <td
                                    style="border:1px solid #000; padding:4px 6px; text-align:center; color:#888; font-size:10px;">
                                    {{ $pCode }}
                                </td>
                                <td style="border:1px solid #000; padding:4px 6px; text-align:center;">
                                    {{ $row['qty'] ?? '' }}</td>
                                <td style="border:1px solid #000; padding:4px 6px; text-align:center;">
                                    {{ $row['uom'] ?? '' }}</td>
                                <td style="border:1px solid #000; padding:4px 6px; text-align:right;">
                                    {{ number_format($row['pricelist_original'] ?? 0, 0, ',', '.') }}
                                </td>
                                @foreach ($vendors as $vi => $v)
                                    @php
                                        $price = $row['prices'][$vi] ?? null;
                                        $isRec = ($v['name'] ?? '') === $comparison->selected_vendor;
                                        preg_match('/[\d.]+/', $v['discount'] ?? '', $dm);
                                        $dRate = isset($dm[0]) ? (float) $dm[0] / 100 : 0;
                                        $discountedPrice = $price && $dRate > 0 ? (float) $price * (1 - $dRate) : null;
                                    @endphp
                                    <td
                                        style="border:1px solid #000; padding:4px 6px; text-align:right; {{ $isRec ? 'background:#f0fff4;' : '' }}">
                                        @if ($price === null || $price === '' || $price == 0)
                                            <span style="color:#888; font-style:italic;">Tidak jual</span>
                                        @else
                                            @php $displayPrice = $dRate > 0 ? (float)$price * (1 - $dRate) : (float)$price; @endphp
                                            {{ $currency }}{{ number_format($displayPrice, 0, ',', '.') }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        {{-- Disc row: right after last product, before spacers --}}
                        @if (collect($vendors)->filter(fn($v) => !empty($v['discount']))->count() > 0)
                            <tr>
                                <td style="border:1px solid #000; padding:4px 6px;"></td>
                                <td style="border:1px solid #000;"></td>
                                <td style="border:1px solid #000;"></td>
                                <td style="border:1px solid #000;"></td>
                                <td style="border:1px solid #000;"></td>
                                <td style="border:1px solid #000;"></td>
                                @foreach ($vendors as $v)
                                    @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                                    <td
                                        style="border:1px solid #000; padding:4px 6px; text-align:center; font-size:10px; font-weight:bold; color:#c0392b; {{ $isRec ? 'background:#f0fff4;' : '' }}">
                                        {{ !empty($v['discount']) ? 'Disc ' . $v['discount'] : '' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endif

                        {{-- Empty spacer rows for aesthetic (like original form) --}}
                        @for ($i = 0; $i < max(0, 6 - count($vpRows)); $i++)
                            <tr>
                                <td style="border:1px solid #000; padding:4px 6px;">&nbsp;</td>
                                <td style="border:1px solid #000;"></td>
                                <td style="border:1px solid #000;"></td>
                                <td style="border:1px solid #000;"></td>
                                <td style="border:1px solid #000;"></td>
                                <td style="border:1px solid #000;"></td>
                                @foreach ($vendors as $v)
                                    <td style="border:1px solid #000;"></td>
                                @endforeach
                            </tr>
                        @endfor

                        {{-- TOTAL row --}}
                        <tr style="font-weight:bold; background:#f9f9f9;">
                            <td colspan="5"
                                style="border:1px solid #000; padding:4px 6px; text-align:right; font-weight:bold;">TOTAL
                            </td>
                            <td style="border:1px solid #000; padding:4px 6px; text-align:right;">
                                @php $origTotal = array_sum(array_column($vpRows, 'pricelist_original')); @endphp
                                {{ $currency }}{{ number_format($origTotal, 0, ',', '.') }}
                            </td>
                            @foreach ($vendors as $vi => $v)
                                @php
                                    $vTotal = 0;
                                    $vTotalDisc = 0;
                                    preg_match('/[\d.]+/', $v['discount'] ?? '', $dm);
                                    $dRate = isset($dm[0]) ? (float) $dm[0] / 100 : 0;
                                    foreach ($vpRows as $row) {
                                        $p = (float) ($row['prices'][$vi] ?? 0);
                                        $vTotal += $p;
                                        $vTotalDisc += $p * (1 - $dRate);
                                    }
                                    $isRec = ($v['name'] ?? '') === $comparison->selected_vendor;
                                @endphp
                                <td
                                    style="border:1px solid #000; padding:4px 6px; text-align:right; font-weight:bold; {{ $isRec ? 'background:#f0fff4;' : '' }}">
                                    {{ $currency }}{{ number_format($vTotalDisc, 0, ',', '.') }}
                                </td>
                            @endforeach
                        </tr>

                        {{-- Availability row --}}
                        <tr>
                            <td colspan="6" style="border:1px solid #000; padding:3px 6px;"></td>
                            @foreach ($vendors as $v)
                                @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                                <td
                                    style="border:1px solid #000; padding:3px 6px; font-size:10px; {{ $isRec ? 'background:#f0fff4;' : '' }}">
                                    @php
                                        $isReady = ($v['availability'] ?? '') === 'ready' || !empty($v['ready']);
                                        $isIndent = ($v['availability'] ?? '') === 'indent' || !empty($v['indent']);
                                    @endphp
                                    <div style="display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                                        <span
                                            style="display:inline-block; width:12px; height:12px; border:1px solid #000; text-align:center; line-height:11px; font-size:10px;">
                                            {{ $isReady ? 'V' : '' }}
                                        </span> Ready
                                    </div>
                                    <div style="display:flex; align-items:center; gap:4px;">
                                        <span
                                            style="display:inline-block; width:12px; height:12px; border:1px solid #000; text-align:center; line-height:11px; font-size:10px;">
                                            {{ $isIndent ? 'V' : '' }}
                                        </span> Indent / Kosong
                                    </div>
                                    @if (!empty($v['indent_duration']))
                                        <div style="font-size:9px; color:#555; margin-top:2px; padding-left:16px;">
                                            {{ $v['indent_duration'] }}
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>

                        {{-- Delivery time --}}
                        <tr>
                            <td colspan="6" style="border:1px solid #000; padding:3px 6px;"></td>
                            @foreach ($vendors as $v)
                                @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                                <td
                                    style="border:1px solid #000; padding:3px 6px; font-size:10px; {{ $isRec ? 'background:#f0fff4;' : '' }}">
                                    {{ $v['delivery_time'] ?? '' }}
                                </td>
                            @endforeach
                        </tr>

                        {{-- Tax info --}}
                        <tr>
                            <td colspan="6" style="border:1px solid #000; padding:3px 6px;"></td>
                            @foreach ($vendors as $v)
                                @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                                <td
                                    style="border:1px solid #000; padding:3px 6px; font-size:10px; {{ $isRec ? 'background:#f0fff4;' : '' }}">
                                    {{ $v['tax_info'] ?? '' }}
                                </td>
                            @endforeach
                        </tr>

                        {{-- Payment terms --}}
                        <tr>
                            <td colspan="6" style="border:1px solid #000; padding:3px 6px;"></td>
                            @foreach ($vendors as $v)
                                @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                                <td
                                    style="border:1px solid #000; padding:3px 6px; font-size:10px; {{ $isRec ? 'background:#f0fff4;' : '' }}">
                                    @php
                                        $top = $v['term_of_payment'] ?? '';
                                        if (is_numeric(trim($top))) {
                                            $top .= ' Hari';
                                        }
                                    @endphp
                                    {{ $top }}
                                </td>
                            @endforeach
                        </tr>

                        {{-- Payment method --}}
                        @if (collect($vendors)->where('payment_method', '!=', '')->count() > 0)
                            <tr>
                                <td colspan="6" style="border:1px solid #000; padding:3px 6px;"></td>
                                @foreach ($vendors as $v)
                                    @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                                    <td
                                        style="border:1px solid #000; padding:3px 6px; font-size:10px; {{ $isRec ? 'background:#f0fff4;' : '' }}">
                                        {{ $v['payment_method'] ?? '' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endif
                    </tbody>
                </table>

                {{-- Footer --}}
                <div class="mt-3 d-flex justify-content-between align-items-start">
                    <div style="font-size:11px;">
                        <strong>NOTES : {{ $comparison->po_name }}</strong>
                        @if ($comparison->notes)
                            <div class="text-muted mt-1">{{ $comparison->notes }}</div>
                        @endif
                    </div>
                    <div style="font-size:11px; text-align:right;">
                        Tgl {{ $comparison->created_at->format('d/m/y') }}<br>
                        Dibuat oleh,<br><br><br><br><br>
                        ({{ $comparison->creator->name ?? '—' }})
                    </div>
                </div>

                {{-- If approved, show approval signatures --}}
                {{-- @if ($comparison->isApproved())
                    <div class="mt-2 d-flex gap-5" style="font-size:11px;">
                        <div style="text-align:center;">
                            Disetujui Supervisor,<br><br><br>
                            ({{ $comparison->supervisor->name ?? '—' }})<br>
                            <small>{{ $comparison->supervisor_approved_at?->format('d/m/Y') }}</small>
                        </div>
                        <div style="text-align:center;">
                            Disetujui Manager,<br><br><br>
                            ({{ $comparison->manager->name ?? '—' }})<br>
                            <small>{{ $comparison->manager_approved_at?->format('d/m/Y') }}</small>
                        </div>
                    </div>
                @endif --}}
            </div>{{-- end clvpDocument --}}

        </div>{{-- end tabClvp --}}

        {{-- ════════════════════════════════════════════════════════ --}}
        {{-- TAB 3: AUDIT LOG                                         --}}
        {{-- ════════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="tabLog">
            <div class="card" style="max-width:640px;">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Activity Log</h6>
                </div>
                <div class="card-body p-0">
                    @if ($comparison->logs->isEmpty())
                        <div class="p-3 text-muted small">No activity recorded yet.</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($comparison->logs as $log)
                                <li class="list-group-item py-3">
                                    <div class="d-flex gap-3 align-items-start">
                                        <i class="bi {{ $log->actionIcon() }} fs-5 mt-1 flex-shrink-0"></i>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold">{{ $log->actionLabel() }}</div>
                                            <div class="text-muted small">
                                                by {{ $log->user->name ?? 'System' }}
                                                &middot; {{ $log->created_at->format('d M Y H:i') }}
                                                ({{ $log->created_at->diffForHumans() }})
                                            </div>
                                            @if ($log->notes)
                                                <div class="mt-1 p-2 bg-light rounded small fst-italic">
                                                    {{ $log->notes }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>{{-- end tabLog --}}

    </div>{{-- end tab-content --}}

    <div class="mt-3">
        <a href="{{ route('comparisons.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Approvals
        </a>
    </div>

    <style>
        @media print {
            body * {
                visibility: hidden !important;
            }

            #clvpDocument,
            #clvpDocument * {
                visibility: visible !important;
            }

            #clvpDocument {
                position: fixed;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 10px;
                border: none !important;
            }
        }
    </style>

@endsection
