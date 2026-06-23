@extends('layouts.app')

@section('title', $rfq ? 'Comparison – ' . $rfq['name'] : 'Comparison')

@section('content')

    @if ($error)
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ $error }}
        </div>
        <a href="{{ route('rfq.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Comparison List
        </a>
    @elseif($rfq)
        {{-- ── Breadcrumb ── --}}
        @if (request('from'))
            <a href="{{ route('rfq.show', request('from')) }}" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="bi bi-arrow-left me-1"></i>Back to {{ request('from_name', 'previous RFQ') }}
            </a>
        @endif
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('rfq.index') }}">Comparison List</a></li>
                <li class="breadcrumb-item active">{{ $rfq['name'] }}</li>
            </ol>
        </nav>

        {{-- ── RFQ Header Card ── --}}
        <div class="card mb-4">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <h5><i class="bi bi-file-earmark-text me-2"></i>{{ $rfq['name'] }}</h5>
                <span
                    class="badge fs-6 {{ match ($rfq['state'] ?? '') {
                        'sent' => 'badge-sent',
                        'purchase' => 'bg-success',
                        'done' => 'bg-secondary',
                        'cancel' => 'bg-danger',
                        default => 'badge-rfq',
                    } }}">
                    {{ match ($rfq['state'] ?? '') {
                        'sent' => 'RFQ Sent',
                        'purchase' => 'Purchase Order',
                        'done' => 'Locked',
                        'cancel' => 'Cancelled',
                        default => 'RFQ',
                    } }}
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted small">Source Document</div>
                        <div class="fw-semibold">{{ $rfq['origin'] ?: '—' }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted small">Order Deadline</div>
                        <div class="fw-semibold">
                            {{ $rfq['date_order'] ? \Illuminate\Support\Carbon::parse($rfq['date_order'])->format('d M Y H:i') : '—' }}
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted small">Total Amount</div>
                        <div class="fw-semibold fs-5">
                            @php
                                $currency = is_array($rfq['currency_id']) ? $rfq['currency_id'][1] : 'IDR';
                            @endphp
                            {{ $currency }} {{ number_format($rfq['amount_total'], 0, ',', '.') }}
                        </div>
                    </div>
                    @if (is_array($rfq['user_id']))
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-muted small">Buyer</div>
                            <div class="fw-semibold">{{ $rfq['user_id'][1] }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Approval submission panel (creators only) ── --}}
        @auth
            @php
                // $existingComparison is passed from RfqController; $comparison from ComparisonController::edit()
                $existing = $existingComparison ?? ($comparison ?? null);
                $isEditMode = isset($comparison) && $comparison !== null;
                $isRejectedPrefill = !$isEditMode && $existing && $existing->isRejected();
                $prefillSource = $isEditMode ? $comparison : ($isRejectedPrefill ? $existing : null);
                $draftKey = 'clvp_draft_' . $rfq['id'];
                $isActiveRfq = in_array($rfq['state'] ?? '', ['draft', 'sent']);
            @endphp

            @if (!$isActiveRfq && !$isEditMode)
                {{-- This is a confirmed / locked / cancelled PO — show read-only notice --}}
                <div class="alert alert-secondary d-flex align-items-center gap-3 mb-4">
                    <i class="bi bi-lock-fill fs-4 text-muted"></i>
                    <div>
                        <strong>
                            @if (($rfq['state'] ?? '') === 'cancel')
                                Cancelled Purchase Order
                            @else
                                Confirmed Purchase Order
                            @endif
                        </strong>
                        — This record is shown as <em>historical reference only</em>.
                        CLVP submission is only available for active RFQs.
                        @if ($existing)
                            <a href="{{ route('comparisons.show', $existing) }}" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="bi bi-eye me-1"></i>View CLVP
                            </a>
                        @endif
                    </div>
                </div>
            @else
                @if ($existing && !$isEditMode)
                    <div
                        class="alert d-flex align-items-center gap-3 mb-4
                    {{ $existing->isApproved() ? 'alert-success' : ($existing->isRejected() ? 'alert-danger' : ($existing->isCancelled() ? 'alert-warning' : 'alert-info')) }}">
                        <i
                            class="bi {{ $existing->isApproved() ? 'bi-patch-check-fill' : ($existing->isRejected() ? 'bi-x-circle-fill' : ($existing->isCancelled() ? 'bi-slash-circle-fill' : 'bi-hourglass-split')) }} fs-4"></i>
                        <div>
                            <strong>
                                @if ($existing->isRejected())
                                    CLVP was rejected.
                                @elseif ($existing->isCancelled())
                                    CLVP was cancelled.
                                @else
                                    Comparison already submitted.
                                @endif
                            </strong>
                            Status: <span
                                class="badge {{ $existing->statusBadgeClass() }}">{{ $existing->statusLabel() }}</span>
                            @if ($existing->isRejected())
                                <div class="mt-1 small fst-italic">"{{ $existing->rejection_reason }}"</div>
                            @endif
                            <a href="{{ route('comparisons.show', $existing) }}" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="bi bi-eye me-1"></i>View Approval Details
                            </a>
                        </div>
                    </div>
                @endif

                @if (Auth::user()->isCreator() && (!$existing || $isEditMode || $existing->isRejected() || $existing->isCancelled()))
                    {{-- ════════════════════════════════════════════════════════
                     DATA CALON VENDOR — CLVP Input Form
                ════════════════════════════════════════════════════════ --}}
                    <div class="card mb-4 border-primary">
                        <div class="card-header py-2 d-flex align-items-center justify-content-between"
                            style="background:#eff6ff; border-color:#bfdbfe;">
                            <h6 class="mb-0 text-primary">
                                <i class="bi bi-table me-2"></i>
                                @if ($isEditMode)
                                    Edit CLVP — {{ $comparison->po_name }}
                                @else
                                    Data Calon Vendor — Comparison Local Vendor Price (CLVP)
                                @endif
                            </h6>
                            <span class="badge bg-secondary" id="vendorCountBadge">0 vendor</span>
                        </div>
                        <div class="card-body">

                            @if ($errors->any())
                                <div class="alert alert-danger py-2 mb-3">
                                    <ul class="mb-0 ps-3">
                                        @foreach ($errors->all() as $e)
                                            <li>{{ $e }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form method="POST"
                                action="{{ $isEditMode ? route('comparisons.update', $comparison) : route('comparisons.store') }}"
                                id="clvpForm">
                                @csrf
                                @if ($isEditMode)
                                    @method('PUT')
                                @endif
                                <input type="hidden" name="po_id" value="{{ $rfq['id'] }}">
                                <input type="hidden" name="po_name" value="{{ $rfq['name'] }}">
                                <input type="hidden" name="po_vendor"
                                    value="{{ is_array($rfq['partner_id']) ? $rfq['partner_id'][1] : '' }}">

                                {{-- ── Category ── --}}
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Kategori Pengadaan</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        @foreach (['unit_baru' => 'Unit Baru', 'aksesoris' => 'Aksesoris Mobil', 'sparepart' => 'Sparepart', 'umum' => 'Umum'] as $val => $lbl)
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="category"
                                                    id="cat_{{ $val }}" value="{{ $val }}"
                                                    onchange="if(typeof onCategoryChange==='function') onCategoryChange()"
                                                    {{ old('category', $prefillSource ? $prefillSource->category ?? 'umum' : 'umum') === $val ? 'checked' : '' }}>
                                                <label class="form-check-label"
                                                    for="cat_{{ $val }}">{{ $lbl }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- ── Vendor Cards ── --}}
                                <div id="vendorCardsContainer" class="mb-4"></div>

                                <div class="d-flex gap-2 mb-4">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="addVendorBtn"
                                        onclick="addVendorCard()">
                                        <i class="bi bi-plus-circle me-1"></i>+ Tambah Vendor
                                    </button>
                                    <span class="text-muted small align-self-center">Minimal 3, maksimal 10 vendor</span>
                                </div>

                                {{-- ── Price Matrix ── --}}
                                <div id="priceMatrixSection" class="mb-4" style="display:none">
                                    <h6 class="fw-semibold mb-2">
                                        <i class="bi bi-grid-3x3-gap me-1"></i>Harga per Item per Vendor
                                    </h6>
                                    @php
                                        $totalLines = count($rfq['lines'] ?? []);
                                        $productLines = count(
                                            array_filter($rfq['lines'] ?? [], fn($l) => is_array($l['product_id'])),
                                        );
                                        $skipped = $totalLines - $productLines;
                                    @endphp
                                    @if ($skipped > 0)
                                        <div class="alert alert-info py-1 px-2 mb-2 small">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Menampilkan <strong>{{ $productLines }}</strong> dari
                                            <strong>{{ $totalLines }}</strong> baris Odoo.
                                            {{ $skipped }} baris lainnya adalah <em>section/catatan</em> tanpa produk,
                                            dilewati otomatis.
                                        </div>
                                    @endif
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm align-middle" id="priceMatrix">
                                            <thead class="table-light">
                                                <tr id="priceMatrixHeader">
                                                    <th class="text-center" style="width:40px">No</th>
                                                    <th>Nama Barang</th>
                                                    <th class="text-center" style="width:60px">Qty</th>
                                                    <th class="text-center" style="width:60px">UoM</th>
                                                    <th class="text-center" style="width:120px">Pricelist Ori (Rp)<div class="fw-normal text-muted" style="font-size:.7rem">Opsional</div></th>
                                                    {{-- vendor columns injected by JS --}}
                                                </tr>
                                            </thead>
                                            <tbody id="priceMatrixBody">
                                                @php $lineIdx = 0; @endphp
                                                @foreach ($rfq['lines'] as $line)
                                                    @php
                                                        if (!is_array($line['product_id'])) {
                                                            continue;
                                                        }
                                                        $pCode = $line['product_code'] ?? '';
                                                        // Use clean product name (without code prefix); fall back to display_name
                                                        $pName = !empty($line['product_clean_name'])
                                                            ? $line['product_clean_name']
                                                            : $line['product_id'][1];
                                                        // Description = line text if it differs from the resolved name
                                                        $pDesc = $line['name'] !== $pName ? $line['name'] : '';
                                                        $uom = is_array($line['product_uom'])
                                                            ? $line['product_uom'][1]
                                                            : '';
                                                    @endphp
                                                    <tr data-row="{{ $lineIdx }}" data-pricelist="{{ $line['price_unit'] }}">
                                                        <td class="text-center">{{ $lineIdx + 1 }}</td>
                                                        <td>
                                                            <div class="fw-semibold">{{ $pName }}</div>
                                                            @if (!empty($pDesc))
                                                                <div class="text-muted small">{{ $pDesc }}</div>
                                                            @endif
                                                            <input type="hidden"
                                                                name="vendor_prices[{{ $lineIdx }}][product_id]"
                                                                value="{{ $line['product_id'][0] }}">
                                                            <input type="hidden"
                                                                name="vendor_prices[{{ $lineIdx }}][product_name]"
                                                                value="{{ $pName }}">
                                                            <input type="hidden"
                                                                name="vendor_prices[{{ $lineIdx }}][product_code]"
                                                                value="{{ $pCode }}">
                                                            <input type="hidden"
                                                                name="vendor_prices[{{ $lineIdx }}][product_description]"
                                                                value="{{ $pDesc }}">
                                                            <input type="hidden"
                                                                name="vendor_prices[{{ $lineIdx }}][qty]"
                                                                value="{{ $line['product_qty'] }}">
                                                            <input type="hidden"
                                                                name="vendor_prices[{{ $lineIdx }}][uom]"
                                                                value="{{ $uom }}">
                                                            <input type="hidden"
                                                                name="vendor_prices[{{ $lineIdx }}][pricelist_original]"
                                                                id="pricelistOrigHidden_{{ $lineIdx }}"
                                                                value="{{ $line['price_unit'] > 0 ? $line['price_unit'] : '' }}">
                                                        </td>
                                                        <td class="text-center">{{ $line['product_qty'] }}</td>
                                                        <td class="text-center">{{ $uom }}</td>
                                                        <td class="p-1">
                                                            <input type="number" min="0" step="1"
                                                                class="form-control form-control-sm text-end pricelist-ori-input"
                                                                id="pricelistOriInput_{{ $lineIdx }}"
                                                                value="{{ $line['price_unit'] > 0 ? $line['price_unit'] : '' }}"
                                                                placeholder="Opsional"
                                                                oninput="onPricelistChange({{ $lineIdx }}, this.value)">
                                                        </td>
                                                        {{-- price input cells injected by JS --}}
                                                    </tr>
                                                    @php $lineIdx++; @endphp
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {{-- ── Recommended + Notes ── --}}
                                <div class="row g-3 mb-3" id="recommendSection" style="display:none">
                                    <div class="col-md-5">
                                        <label class="form-label fw-semibold">Vendor yang Direkomendasikan <span
                                                class="text-danger">*</span></label>
                                        <select name="selected_vendor" id="selectedVendorDropdown" class="form-select"
                                            required>
                                            <option value="">— Pilih setelah menambah vendor —</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label fw-semibold">Catatan / Justifikasi</label>
                                        <textarea name="notes" class="form-control" rows="3" placeholder="Alasan pemilihan vendor...">{{ old('notes', $prefillSource ? $prefillSource->notes ?? '' : '') }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <div id="vendorRecommendHint" style="display:none"></div>
                                    </div>
                                </div>

                                {{-- Auto-procurement warning banner (hidden by default) --}}
                                <div id="procurementAutoAlert" class="alert mb-3 d-flex align-items-start gap-2" style="display:none!important; background:#f5f3ff; border:1.5px solid #7c3aed; color:#4c1d95">
                                    <i class="bi bi-shield-exclamation fs-5 mt-1" style="color:#7c3aed; flex-shrink:0"></i>
                                    <div>
                                        <div class="fw-semibold">Persetujuan Procurement diperlukan secara otomatis</div>
                                        <div class="small mt-1" id="procurementAutoReason"></div>
                                    </div>
                                </div>

                                {{-- Procurement toggle --}}
                                <div class="mb-3" id="procurementToggleSection">
                                    <div id="procurementToggleCard" class="border rounded p-3 d-flex align-items-center justify-content-between gap-3"
                                        style="cursor:pointer; border-color:#dee2e6; background:#f8f9fa; transition: all .2s">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-shield-check fs-5" id="procurementIcon" style="color:#6c757d"></i>
                                            <div>
                                                <div class="fw-semibold small" id="procurementLabel">Perlu Persetujuan Procurement?</div>
                                                <div class="text-muted small" id="procurementDesc">Klik untuk mengaktifkan jika perbandingan ini membutuhkan review dari tim Procurement sebelum ke Supervisor.</div>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="badge fs-6 px-3 py-2" id="procurementBadge" style="background:#e9ecef; color:#6c757d">Tidak</span>
                                        </div>
                                    </div>
                                    <input type="hidden" name="requires_procurement" id="requiresProcurementInput" value="0">
                                </div>

                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <button type="submit" class="btn btn-primary" id="clvpSubmitBtn" disabled>
                                        <i class="bi bi-send me-2"></i><span id="clvpSubmitLabel">Submit untuk Persetujuan Supervisor</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="openClvpPreview()">
                                        <i class="bi bi-eye me-2"></i>Preview CLVP
                                    </button>
                                </div>

                                {{-- Preview CLVP Modal --}}
                                <div class="modal fade" id="clvpPreviewModal" tabindex="-1" aria-labelledby="clvpPreviewModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="clvpPreviewModalLabel">
                                                    <i class="bi bi-file-earmark-spreadsheet me-2"></i>Preview Dokumen CLVP
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-3" id="clvpPreviewBody">
                                                <p class="text-muted text-center">Memuat preview...</p>
                                            </div>
                                            <div class="modal-footer">
                                                <span class="text-muted small me-auto"><i class="bi bi-info-circle me-1"></i>Preview ini belum tersimpan. Submit form untuk menyimpan.</span>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @php
                                    $productsWithHistory = array_keys(array_filter($history, fn($h) => !empty($h)));
                                @endphp
                                <script>
                                    (function() {
                                        // Product IDs that HAVE confirmed purchase history (Rule 1: empty = never bought)
                                        const productsWithHistory = @json($productsWithHistory);

                                        let manualOn  = false;
                                        let autoOn    = false;

                                        function applyProcurementState(on, locked, reasons) {
                                            const card   = document.getElementById('procurementToggleCard');
                                            const icon   = document.getElementById('procurementIcon');
                                            const label  = document.getElementById('procurementLabel');
                                            const desc   = document.getElementById('procurementDesc');
                                            const badge  = document.getElementById('procurementBadge');
                                            const input  = document.getElementById('requiresProcurementInput');
                                            const btnLbl = document.getElementById('clvpSubmitLabel');
                                            const alert  = document.getElementById('procurementAutoAlert');
                                            const reason = document.getElementById('procurementAutoReason');

                                            if (on) {
                                                card.style.borderColor  = '#7c3aed';
                                                card.style.background   = '#f5f3ff';
                                                icon.style.color        = '#7c3aed';
                                                label.textContent       = 'Perlu Persetujuan Procurement';
                                                desc.textContent        = locked
                                                    ? 'Otomatis diaktifkan berdasarkan aturan sistem. Tidak dapat diubah.'
                                                    : 'Perbandingan ini akan dikirim ke tim Procurement terlebih dahulu sebelum ke Supervisor.';
                                                badge.textContent       = 'Ya';
                                                badge.style.background  = '#7c3aed';
                                                badge.style.color       = '#fff';
                                                input.value             = '1';
                                                btnLbl.textContent      = 'Submit untuk Persetujuan Procurement';
                                            } else {
                                                card.style.borderColor  = '#dee2e6';
                                                card.style.background   = '#f8f9fa';
                                                icon.style.color        = '#6c757d';
                                                label.textContent       = 'Perlu Persetujuan Procurement?';
                                                desc.textContent        = 'Klik untuk mengaktifkan jika perbandingan ini membutuhkan review dari tim Procurement sebelum ke Supervisor.';
                                                badge.textContent       = 'Tidak';
                                                badge.style.background  = '#e9ecef';
                                                badge.style.color       = '#6c757d';
                                                input.value             = '0';
                                                btnLbl.textContent      = 'Submit untuk Persetujuan Supervisor';
                                            }

                                            // Lock / unlock toggle click
                                            card.style.cursor = locked ? 'not-allowed' : 'pointer';
                                            card.style.opacity = locked ? '0.85' : '1';

                                            // Show/hide auto-warning banner
                                            if (locked && on) {
                                                alert.style.display = 'flex';
                                                reason.innerHTML = reasons.map(r => `<span class="d-block">• ${r}</span>`).join('');
                                            } else {
                                                alert.style.display = 'none';
                                            }
                                        }

                                        document.getElementById('procurementToggleCard').addEventListener('click', function() {
                                            if (autoOn) return; // strictly blocked when auto-triggered
                                            manualOn = !manualOn;
                                            applyProcurementState(manualOn, false, []);
                                        });

                                        // Check rules automatically whenever price inputs change
                                        // Get the column index of the currently selected vendor
                                        function getSelectedVendorColIdx() {
                                            const dropdown = document.getElementById('selectedVendorDropdown');
                                            const selectedName = dropdown ? dropdown.value.trim() : '';
                                            if (!selectedName) return null;
                                            let found = null;
                                            document.querySelectorAll('#priceMatrixHeader th[id^="priceColHeader_"]').forEach(function(th) {
                                                const nameEl = th.querySelector('.vendor-col-name');
                                                if (nameEl && nameEl.textContent.trim() === selectedName) {
                                                    found = parseInt(th.id.replace('priceColHeader_', ''));
                                                }
                                            });
                                            return found;
                                        }

                                        window.checkProcurementRules = function() {
                                            const reasons = [];
                                            const selIdx  = getSelectedVendorColIdx(); // null if no vendor selected yet

                                            // Rule 1: product never bought before
                                            document.querySelectorAll('#priceMatrixBody tr[data-row]').forEach(function(row) {
                                                const rowIdx = row.dataset.row;
                                                const pidInp = row.querySelector(`input[name="vendor_prices[${rowIdx}][product_id]"]`);
                                                const pid    = pidInp ? parseInt(pidInp.value) : 0;
                                                if (pid && !productsWithHistory.includes(pid)) {
                                                    const pname = row.querySelector('td:nth-child(2)')?.textContent?.trim() || 'Produk ID ' + pid;
                                                    reasons.push('Produk <strong>' + pname + '</strong> belum pernah dibeli sebelumnya (Rule 1)');
                                                }
                                            });

                                            // Rule 2 & 3: use selected vendor's price; fall back to min if none selected
                                            document.querySelectorAll('#priceMatrixBody tr[data-row]').forEach(function(row) {
                                                const rowIdx = row.dataset.row;
                                                const qtyInp = row.querySelector(`input[name="vendor_prices[${rowIdx}][qty]"]`);
                                                const qty    = qtyInp ? (parseFloat(qtyInp.value) || 0) : 0;
                                                const pname  = row.querySelector('td:nth-child(2)')?.textContent?.trim() || 'Produk';

                                                let price = 0;
                                                if (selIdx !== null) {
                                                    const selInp = row.querySelector(`input[name="vendor_prices[${rowIdx}][prices][${selIdx}]"]`);
                                                    price = selInp ? (parseFloat(selInp.value) || 0) : 0;
                                                } else {
                                                    // No vendor selected yet — skip Rules 2 & 3
                                                    return;
                                                }

                                                // Input already stores discounted price (or manual price), use as-is
                                                const total = price * qty;

                                                if (qty >= 25) {
                                                    reasons.push('Quantity <strong>' + qty + '</strong> ≥ 25 untuk <strong>' + pname + '</strong> (Rule 2)');
                                                }
                                                if (price > 0 && total >= 5000000) {
                                                    reasons.push('Total harga <strong>Rp ' + total.toLocaleString('id-ID') + '</strong> ≥ Rp 5.000.000 untuk <strong>' + pname + '</strong> (Rule 3)');
                                                }
                                            });

                                            autoOn = reasons.length > 0;
                                            applyProcurementState(autoOn || manualOn, autoOn, reasons);
                                        };

                                        // Trigger on vendor dropdown change (primary UX trigger for Rules 2 & 3)
                                        document.addEventListener('change', function(e) {
                                            if (e.target.id === 'selectedVendorDropdown') {
                                                window.checkProcurementRules();
                                            }
                                        });
                                        // Also trigger on price/qty input change
                                        document.addEventListener('input', function(e) {
                                            if (e.target.closest('#priceMatrixBody')) {
                                                window.checkProcurementRules();
                                            }
                                        });
                                        setTimeout(window.checkProcurementRules, 800);
                                        setTimeout(window.checkProcurementRules, 1500);
                                    })();
                                </script>
                            </form>
                        </div>
                    </div>

                    {{-- ── Legend ── --}}
                    <div class="d-flex gap-3 mb-3 flex-wrap align-items-center">
                        <span class="fw-semibold small">Legend:</span>
                        <span class="badge price-best px-2 py-1">&#9733; Best Price</span>
                        <span class="badge price-current px-2 py-1">&#9830; Current RFQ Vendor</span>
                        <span class="badge price-worst px-2 py-1">&#9660; Highest Price</span>
                        <span class="badge bg-primary px-2 py-1">&#128197; Latest Purchase</span>
                        <span class="ms-auto text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            Shows most recent purchase + 3 cheapest historical vendors per product.
                        </span>
                    </div>

                    {{-- ── Odoo vendor master data (embedded for auto-fill) ── --}}
                    <script>
                        const ODOO_VENDORS = @json($vendors);
                        const LOCAL_VENDORS = @json($masterSuppliers ?? []);
                        const ALL_VENDORS = [...ODOO_VENDORS, ...LOCAL_VENDORS];
                        const PRODUCT_ROWS = {{ $lineIdx ?? 0 }};
                        const IS_EDIT_MODE = {{ $isEditMode ? 'true' : 'false' }};
                        const IS_REJECTED_PREFILL = {{ $isRejectedPrefill ? 'true' : 'false' }};
                        const DRAFT_KEY = '{{ $draftKey ?? 'clvp_draft_0' }}';
                        const PREFILL_VENDORS = @json($prefillSource ? $prefillSource->vendors ?? [] : []);
                        const PREFILL_PRICES = @json($prefillSource ? $prefillSource->vendor_prices ?? [] : []);
                        const PREFILL_SELECTED = @json($prefillSource ? $prefillSource->selected_vendor ?? '' : '');
                        let vendorCount = 0;

                        const vendorLookup = {};
                        ALL_VENDORS.forEach(v => {
                            vendorLookup[v.id] = v;
                        });

                        function buildAddress(v) {
                            return [v.street, v.street2, v.city].filter(Boolean).join(', ');
                        }

                        function addVendorCard() {
                            if (vendorCount >= 10) return;
                            const idx = vendorCount;
                            vendorCount++;

                            const container = document.getElementById('vendorCardsContainer');
                            const card = document.createElement('div');
                            card.className = 'card mb-3 vendor-card';
                            card.dataset.idx = idx;
                            const escHtml = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g,
                                '&quot;');
                            const allOptions = ALL_VENDORS.map(v => {
                                const localBadge = v.source === 'local' ? ' <span style="color:#15803d;font-size:.75rem">[Local]</span>' : '';
                                return `<option value="${v.id}">${escHtml(v.name)}${v.city ? ' - ' + escHtml(v.city) : ''}${localBadge}</option>`;
                            }).join('');
                            card.innerHTML = `
                        <div class="card-header py-2 d-flex align-items-center gap-2"
                            style="background:#f8fafc;">
                            <i class="bi bi-shop text-primary"></i>
                            <span class="fw-semibold small" id="cardTitle_${idx}">Vendor ${idx+1}</span>
                            <div class="ms-auto">
                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1"
                                    onclick="removeVendorCard(${idx})" title="Hapus vendor ini">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label small fw-semibold mb-1">Cari Supplier <span class="text-muted fw-normal">(opsional, untuk auto-isi)</span></label>
                                    <select class="form-select form-select-sm odoo-autofill">
                                        <option value="">— Pilih supplier —</option>
                                        ${allOptions}
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold mb-1">Nama Calon Vendor <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm vendor-name-input"
                                        name="vendors[${idx}][name]" required
                                        placeholder="Nama vendor"
                                        oninput="onVendorNameChange(${idx}, this.value)">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold mb-1">Alamat</label>
                                    <input type="text" class="form-control form-control-sm"
                                        name="vendors[${idx}][alamat]" placeholder="Alamat vendor">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Telepon / Fax</label>
                                    <input type="text" class="form-control form-control-sm"
                                        name="vendors[${idx}][phone]" placeholder="No. telepon">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">PIC / Contact Person</label>
                                    <input type="text" class="form-control form-control-sm"
                                        name="vendors[${idx}][pic]" placeholder="Nama kontak">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Term of Payment</label>
                                    <input type="text" class="form-control form-control-sm"
                                        name="vendors[${idx}][term_of_payment]"
                                        placeholder="e.g., 30 hari">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Ketersediaan</label>
                                    <div class="d-flex gap-3 mt-1">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input avail-radio" type="radio"
                                                name="vendors[${idx}][availability]" value="ready" id="avail_ready_${idx}"
                                                onchange="toggleIndentDuration(${idx})">
                                            <label class="form-check-label small" for="avail_ready_${idx}">Ready</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input avail-radio" type="radio"
                                                name="vendors[${idx}][availability]" value="indent" id="avail_indent_${idx}"
                                                onchange="toggleIndentDuration(${idx})">
                                            <label class="form-check-label small" for="avail_indent_${idx}">Indent</label>
                                        </div>
                                    </div>
                                    <div id="indent_dur_wrap_${idx}" style="display:none; margin-top:4px;">
                                        <input type="text" class="form-control form-control-sm"
                                            name="vendors[${idx}][indent_duration]"
                                            placeholder="e.g., 2 minggu, 1 bulan">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Pajak</label>
                                    <select class="form-select form-select-sm" name="vendors[${idx}][tax_info]">
                                        <option value="">— Pilih —</option>
                                        <option value="Exc PPN">Exc PPN</option>
                                        <option value="Inc PPN">Inc PPN</option>
                                        <option value="Tanpa PPN">Tanpa PPN</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Diskon (%)</label>
                                    <input type="number" class="form-control form-control-sm"
                                        name="vendors[${idx}][discount]"
                                        placeholder="e.g., 10"
                                        min="0" max="100" step="0.01"
                                        oninput="recalcDiscountForVendor(${idx})">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold mb-1">Ketentuan Lain-lain dari Calon Supplier</label>
                                    <textarea class="form-control form-control-sm" rows="2"
                                        name="vendors[${idx}][other_terms]"
                                        placeholder="Ketentuan lain dari calon supplier..."></textarea>
                                </div>
                            </div>
                        </div>`;

                            container.appendChild(card);
                            // Init Tom Select on the Odoo supplier dropdown
                            const tsEl = card.querySelector('.odoo-autofill');
                            if (tsEl && typeof TomSelect !== 'undefined') {
                                new TomSelect(tsEl, {
                                    maxOptions: 200,
                                    searchField: ['text'],
                                    placeholder: '— Pilih supplier —',
                                    onChange(val) {
                                        autoFill(idx, val);
                                    },
                                });
                            }
                            addPriceColumn(idx);
                            refreshUI();
                        }

                        function toggleIndentDuration(idx) {
                            const rb = document.querySelector(`[name="vendors[${idx}][availability]"]:checked`);
                            const wrap = document.getElementById(`indent_dur_wrap_${idx}`);
                            if (wrap) wrap.style.display = (rb && rb.value === 'indent') ? '' : 'none';
                        }

                        function removeVendorCard(idx) {
                            const card = document.querySelector(`.vendor-card[data-idx="${idx}"]`);
                            if (card) {
                                const tsEl = card.querySelector('.odoo-autofill');
                                if (tsEl && tsEl.tomselect) tsEl.tomselect.destroy();
                                card.remove();
                            }
                            removePriceColumn(idx);
                            refreshUI();
                        }

                        function autoFill(idx, odooId) {
                            if (!odooId) return;
                            const v = vendorLookup[odooId];
                            if (!v) return;
                            const card = document.querySelector(`.vendor-card[data-idx="${idx}"]`);
                            if (!card) return;

                            // Block if another card already has this vendor
                            const isDup = Array.from(document.querySelectorAll('.vendor-name-input'))
                                .some(inp => {
                                    const otherCard = inp.closest('.vendor-card');
                                    return otherCard && parseInt(otherCard.dataset.idx) !== idx &&
                                        inp.value.trim().toLowerCase() === v.name.trim().toLowerCase();
                                });
                            if (isDup) {
                                alert(`"${v.name}" sudah dipilih di vendor lain. Pilih supplier yang berbeda.`);
                                const tsEl = card.querySelector('.odoo-autofill');
                                if (tsEl && tsEl.tomselect) tsEl.tomselect.clear(true);
                                return;
                            }

                            card.querySelector(`[name="vendors[${idx}][name]"]`).value = v.name;
                            card.querySelector(`[name="vendors[${idx}][alamat]"]`).value = buildAddress(v);
                            card.querySelector(`[name="vendors[${idx}][phone]"]`).value = v.phone || v.mobile || '';
                            const picEl = card.querySelector(`[name="vendors[${idx}][pic]"]`);
                            if (picEl) picEl.value = v.contact_name || '';
                            onVendorNameChange(idx, v.name);
                        }

                        function onVendorNameChange(idx, name) {
                            const titleEl = document.getElementById(`cardTitle_${idx}`);
                            if (titleEl) titleEl.textContent = name || `Vendor ${idx+1}`;

                            // Duplicate check — highlight input if name already used
                            const nameInput = document.querySelector(`[name="vendors[${idx}][name]"]`);
                            if (nameInput && name.trim()) {
                                const dup = Array.from(document.querySelectorAll('.vendor-name-input'))
                                    .some(inp => {
                                        const otherCard = inp.closest('.vendor-card');
                                        return otherCard && parseInt(otherCard.dataset.idx) !== idx &&
                                            inp.value.trim().toLowerCase() === name.trim().toLowerCase();
                                    });
                                nameInput.classList.toggle('is-invalid', dup);
                                let fb = nameInput.nextElementSibling;
                                if (!fb || !fb.classList.contains('ts-dup-msg')) {
                                    if (dup) {
                                        fb = document.createElement('div');
                                        fb.className = 'invalid-feedback ts-dup-msg';
                                        fb.textContent = 'Vendor ini sudah dipilih di card lain.';
                                        nameInput.insertAdjacentElement('afterend', fb);
                                    }
                                } else {
                                    fb.style.display = dup ? '' : 'none';
                                }
                            }

                            // Update price matrix column header
                            const th = document.getElementById(`priceColHeader_${idx}`);
                            if (th) {
                                const nameSpan = th.querySelector('.vendor-col-name');
                                if (nameSpan) nameSpan.textContent = name || `Vendor ${idx+1}`;
                            }

                            // Update recommended dropdown
                            refreshRecommendDropdown();
                        }

                        function isSparepartMode() {
                            const cat = document.querySelector('input[name="category"]:checked');
                            return cat && cat.value === 'sparepart';
                        }

                        function getRowPricelist(rowIdx) {
                            // Use the user-editable pricelist original input value if present; fallback to data-pricelist
                            const inp = document.getElementById(`pricelistOriInput_${rowIdx}`);
                            if (inp) {
                                const v = parseFloat(inp.value);
                                if (v > 0) return v;
                            }
                            // Also check if sparepart mode (use data-pricelist as fallback when input is empty)
                            const row = document.querySelector(`#priceMatrixBody tr[data-row="${rowIdx}"]`);
                            return parseFloat(row?.dataset.pricelist || 0);
                        }

                        function rowHasPricelist(rowIdx) {
                            const inp = document.getElementById(`pricelistOriInput_${rowIdx}`);
                            if (inp) {
                                const v = parseFloat(inp.value);
                                return v > 0;
                            }
                            return false;
                        }

                        function buildPriceCell(rowIdx, idx, sparepart) {
                            const pricelist = getRowPricelist(rowIdx);
                            const hasPl = sparepart ? pricelist > 0 : rowHasPricelist(rowIdx);
                            if (hasPl) {
                                // Sparepart / pricelist mode: input stores DISCOUNTED price per vendor
                                const discInp = document.querySelector(`[name="vendors[${idx}][discount]"]`);
                                const disc    = discInp ? (parseFloat(discInp.value) || 0) : 0;
                                const discounted = Math.round(pricelist * (1 - disc / 100));
                                // Hint shows discount detail
                                const hintText = sparepart
                                    ? `Disk ${disc}% dari Rp ${pricelist.toLocaleString('id-ID')}`
                                    : (disc > 0 ? `Disk ${disc}% dari Rp ${pricelist.toLocaleString('id-ID')}` : '');
                                const hint = `<div class="text-end text-muted" id="discHint_${rowIdx}_${idx}" style="font-size:.7rem;">${hintText}</div>`;
                                return `
                            ${hint}
                            <div class="input-group input-group-sm">
                                <input type="number" min="0" step="1"
                                    class="form-control form-control-sm text-end price-input"
                                    name="vendor_prices[${rowIdx}][prices][${idx}]"
                                    id="priceInput_${rowIdx}_${idx}"
                                    value="${discounted}"
                                    placeholder="0">
                            </div>
                            <div class="form-check mt-1" style="display:none;">
                                <input class="form-check-input tidak-jual-cb" type="checkbox"
                                    id="tj_${rowIdx}_${idx}"
                                    onchange="toggleTidakJual(${rowIdx},${idx},this)">
                                <label class="form-check-label small text-muted" for="tj_${rowIdx}_${idx}">Tidak Menjual Barang</label>
                            </div>`;
                            } else {
                                return `
                            <div class="input-group input-group-sm">
                                <input type="number" min="0" step="1"
                                    class="form-control form-control-sm text-end price-input"
                                    name="vendor_prices[${rowIdx}][prices][${idx}]"
                                    id="priceInput_${rowIdx}_${idx}"
                                    placeholder="0">
                            </div>
                            <div class="form-check mt-1" style="display:none;">
                                <input class="form-check-input tidak-jual-cb" type="checkbox"
                                    id="tj_${rowIdx}_${idx}"
                                    onchange="toggleTidakJual(${rowIdx},${idx},this)">
                                <label class="form-check-label small text-muted" for="tj_${rowIdx}_${idx}">Tidak Menjual Barang</label>
                            </div>`;
                            }
                        }

                        function onPricelistChange(rowIdx, value) {
                            // Sync the hidden input
                            const hidden = document.getElementById(`pricelistOrigHidden_${rowIdx}`);
                            if (hidden) hidden.value = value || '';

                            const sparepart = isSparepartMode();
                            const pricelist = getRowPricelist(rowIdx);
                            // Update existing input values to new discounted prices for hasPl rows
                            document.querySelectorAll('#priceMatrixHeader th[id^="priceColHeader_"]').forEach(function(th) {
                                const idx = parseInt(th.id.replace('priceColHeader_', ''));
                                const td = document.getElementById(`priceCell_${rowIdx}_${idx}`);
                                if (td) {
                                    const hasPl = sparepart ? pricelist > 0 : rowHasPricelist(rowIdx);
                                    if (hasPl) {
                                        const discInp = document.querySelector(`[name="vendors[${idx}][discount]"]`);
                                        const disc = discInp ? (parseFloat(discInp.value) || 0) : 0;
                                        const discounted = Math.round(pricelist * (1 - disc / 100));
                                        const inp = td.querySelector(`#priceInput_${rowIdx}_${idx}`);
                                        if (inp) inp.value = discounted;
                                        const hintEl = document.getElementById(`discHint_${rowIdx}_${idx}`);
                                        if (hintEl) {
                                            hintEl.textContent = sparepart
                                                ? `Disk ${disc}% dari Rp ${pricelist.toLocaleString('id-ID')}`
                                                : (disc > 0 ? `Disk ${disc}% dari Rp ${pricelist.toLocaleString('id-ID')}` : '');
                                        }
                                    } else {
                                        // No pricelist: rebuild to manual input
                                        td.innerHTML = buildPriceCell(rowIdx, idx, sparepart);
                                    }
                                }
                            });
                            if (typeof window.checkProcurementRules === 'function') window.checkProcurementRules();
                            refreshRecommendation();
                        }

                        // Recalc all price cells for a given vendor column from their vendor-card discount
                        function recalcDiscountForVendor(idx) {
                            const sparepart = isSparepartMode();
                            const discInp = document.querySelector(`[name="vendors[${idx}][discount]"]`);
                            const disc    = discInp ? (parseFloat(discInp.value) || 0) : 0;
                            document.querySelectorAll('#priceMatrixBody tr[data-row]').forEach(function(row) {
                                const rowIdx  = row.dataset.row;
                                const hasPl = sparepart ? getRowPricelist(parseInt(rowIdx)) > 0 : rowHasPricelist(parseInt(rowIdx));
                                if (!hasPl) return; // manual-entry rows with no pricelist: don't touch
                                const pricelist = getRowPricelist(parseInt(rowIdx));
                                const price   = Math.round(pricelist * (1 - disc / 100));
                                // Update input value and hint with new discounted price
                                const discounted = Math.round(pricelist * (1 - disc / 100));
                                const inp = document.getElementById(`priceInput_${rowIdx}_${idx}`);
                                if (inp && !inp.disabled) inp.value = discounted;
                                const hintEl = document.getElementById(`discHint_${rowIdx}_${idx}`);
                                if (hintEl) {
                                    hintEl.textContent = sparepart
                                        ? `Disk ${disc}% dari Rp ${pricelist.toLocaleString('id-ID')}`
                                        : (disc > 0 ? `Disk ${disc}% dari Rp ${pricelist.toLocaleString('id-ID')}` : '');
                                }
                            });
                            if (typeof window.checkProcurementRules === 'function') window.checkProcurementRules();
                        }

                        function addPriceColumn(idx) {
                            const rows = document.querySelectorAll('#priceMatrixBody tr[data-row]');
                            if (rows.length === 0) return;
                            const sparepart = isSparepartMode();

                            // Add header
                            const header = document.getElementById('priceMatrixHeader');
                            const th = document.createElement('th');
                            th.id = `priceColHeader_${idx}`;
                            th.className = 'text-center';
                            th.style.minWidth = '130px';
                            th.innerHTML = `<span class="vendor-col-name small">Vendor ${idx+1}</span>
                        <div class="small text-muted fst-italic" style="font-size:.7rem">${sparepart ? 'Diskon → Harga' : 'Harga'}</div>`;
                            header.appendChild(th);

                            // Add input cell per product row
                            rows.forEach(row => {
                                const rowIdx = row.dataset.row;
                                const td = document.createElement('td');
                                td.className = 'p-1';
                                td.id = `priceCell_${rowIdx}_${idx}`;
                                td.innerHTML = buildPriceCell(rowIdx, idx, sparepart);
                                row.appendChild(td);
                            });

                            document.getElementById('priceMatrixSection').style.display = '';
                            document.getElementById('recommendSection').style.display = '';
                            if (typeof window.checkProcurementRules === 'function') window.checkProcurementRules();
                        }

                        function onCategoryChange() {
                            const sparepart = isSparepartMode();
                            // Rebuild every existing price cell
                            document.querySelectorAll('#priceMatrixHeader th[id^="priceColHeader_"]').forEach(function(th) {
                                const idx = parseInt(th.id.replace('priceColHeader_', ''));
                                // Update header subtitle
                                const sub = th.querySelector('.small.text-muted');
                                // Header hint: sparepart always auto-calc; otherwise per-row
                                if (sub) sub.textContent = sparepart ? 'Diskon → Harga' : 'Harga';
                                // Rebuild each row's cell
                                document.querySelectorAll('#priceMatrixBody tr[data-row]').forEach(function(row) {
                                    const rowIdx = row.dataset.row;
                                    const td = document.getElementById(`priceCell_${rowIdx}_${idx}`);
                                    if (td) {
                                        td.innerHTML = buildPriceCell(rowIdx, idx, sparepart);
                                    }
                                });
                            });
                            /* Remark: kept for future use
                            document.querySelectorAll('.pricelist-ori-input').forEach(function(inp) {
                                inp.readOnly = false;
                                inp.style.background = '';
                                inp.style.cursor = '';
                            });
                            */
                            if (typeof window.checkProcurementRules === 'function') window.checkProcurementRules();
                        }

                        function removePriceColumn(idx) {
                            const th = document.getElementById(`priceColHeader_${idx}`);
                            if (th) th.remove();
                            document.querySelectorAll(`[id^="priceCell_"][id$="_${idx}"]`).forEach(td => td.remove());

                            const allHeaders = document.querySelectorAll('#priceMatrixHeader th');
                            if (allHeaders.length <= 5) { // only fixed cols remain
                                document.getElementById('priceMatrixSection').style.display = 'none';
                                document.getElementById('recommendSection').style.display = 'none';
                            }
                        }

                        function toggleTidakJual(rowIdx, vendorIdx, cb) {
                            const input = document.querySelector(`[name="vendor_prices[${rowIdx}][prices][${vendorIdx}]"]`);
                            if (input) {
                                input.disabled = cb.checked;
                                if (cb.checked) input.value = 0;
                            }
                            refreshRecommendation();
                        }

                        function refreshRecommendDropdown() {
                            const dropdown = document.getElementById('selectedVendorDropdown');
                            const prev = dropdown.value;
                            dropdown.innerHTML = '<option value="">— Pilih vendor yang direkomendasikan —</option>';
                            document.querySelectorAll('.vendor-name-input').forEach(inp => {
                                if (inp.value.trim()) {
                                    const opt = document.createElement('option');
                                    opt.value = inp.value.trim();
                                    opt.textContent = inp.value.trim();
                                    if (opt.value === prev) opt.selected = true;
                                    dropdown.appendChild(opt);
                                }
                            });
                            refreshRecommendation();
                        }

                        function refreshRecommendation() {
                            const hint = document.getElementById('vendorRecommendHint');
                            if (!hint) return;

                            const headers = document.querySelectorAll('#priceMatrixHeader th[id^="priceColHeader_"]');
                            if (headers.length < 1) {
                                hint.style.display = 'none';
                                return;
                            }

                            const vendorIndices = Array.from(headers).map(th =>
                                parseInt(th.id.replace('priceColHeader_', '')));
                            const totals = {};
                            vendorIndices.forEach(idx => totals[idx] = 0);

                            const rows = document.querySelectorAll('#priceMatrixBody tr[data-row]');
                            if (rows.length === 0) {
                                hint.style.display = 'none';
                                return;
                            }

                            const getName = idx => {
                                const inp = document.querySelector(`[name="vendors[${idx}][name]"]`);
                                return (inp && inp.value.trim()) ? inp.value.trim() : `Vendor ${idx + 1}`;
                            };
                            const getDisc = idx => {
                                const inp = document.querySelector(`[name="vendors[${idx}][discount]"]`);
                                const v = parseFloat(inp ? inp.value : 0);
                                return v > 0 ? v : 0;
                            };
                            const fmt = n => 'IDR\u00a0' + Math.round(n).toLocaleString('id-ID');

                            // Effective total: use input price as-is (already discounted for pricelist rows)
                            const effective = {};
                            vendorIndices.forEach(idx => { effective[idx] = 0; });

                            rows.forEach(row => {
                                const rowIdx = row.dataset.row;
                                const qtyEl = row.querySelector(`[name="vendor_prices[${rowIdx}][qty]"]`);
                                const qty = qtyEl ? (parseFloat(qtyEl.value) || 1) : 1;

                                vendorIndices.forEach(idx => {
                                    if (effective[idx] === Infinity) return;
                                    const cb = document.getElementById(`tj_${rowIdx}_${idx}`);
                                    if (cb && cb.checked) {
                                        effective[idx] = Infinity;
                                        return;
                                    }
                                    const priceInput = document.querySelector(
                                        `[name="vendor_prices[${rowIdx}][prices][${idx}]"]`);
                                    const val = priceInput ? parseFloat(priceInput.value) : 0;
                                    if (val <= 0) return;
                                    // Input stores final price (discounted or manual), use as-is
                                    effective[idx] += val * qty;
                                });
                            });

                            const valid = vendorIndices.filter(idx => effective[idx] !== Infinity && effective[idx] > 0);
                            if (valid.length === 0) {
                                hint.style.display = 'none';
                                return;
                            }

                            const sorted = [...valid].sort((a, b) => effective[a] - effective[b]);
                            const bestIdx = sorted[0];

                            const fmtVendor = idx => {
                                const d = getDisc(idx);
                                const badge = d > 0 ? ` <span class="badge bg-success ms-1" style="font-size:.72em">${d}% off</span>` :
                                    '';
                                const effPrice = fmt(effective[idx]);
                                return `${getName(idx)}${badge} ${effPrice}`;
                            };

                            let html = `<div class="alert alert-success py-2 px-3 mb-0 d-flex align-items-start gap-2">
                                <i class="bi bi-lightbulb-fill text-warning fs-5 mt-1 flex-shrink-0"></i>
                                <div><strong>Rekomendasi: ${fmtVendor(bestIdx)}</strong>`;

                            if (sorted.length > 1) {
                                const others = sorted.slice(1).map(idx => {
                                    if (effective[idx] === Infinity)
                                        return `<em>${getName(idx)}: Tidak Menjual Barang</em>`;
                                    const diff = effective[idx] - effective[bestIdx];
                                    return `${fmtVendor(idx)} <span class="text-danger fw-semibold">(+${fmt(diff)})</span>`;
                                });
                                html += `<br><small class="text-muted">vs ${others.join(' &nbsp;&middot;&nbsp; ')}</small>`;
                            }

                            // Tidak Jual vendors
                            const cantSupply = vendorIndices.filter(idx => effective[idx] === Infinity);
                            if (cantSupply.length > 0) {
                                html += `<br><small class="text-secondary"><i class="bi bi-x-circle me-1"></i>` +
                                    `Tidak Menjual Barang: ${cantSupply.map(getName).join(', ')}</small>`;
                            }

                            html += `</div></div>`;
                            hint.innerHTML = html;
                            hint.style.display = '';
                        }

                        function renumberVendors() {
                            document.querySelectorAll('.vendor-card').forEach((card, i) => {
                                const idx = parseInt(card.dataset.idx);
                                const nameInput = card.querySelector('.vendor-name-input');
                                const name = nameInput ? nameInput.value.trim() : '';
                                const label = name || `Vendor ${i + 1}`;
                                const titleEl = document.getElementById(`cardTitle_${idx}`);
                                if (titleEl) titleEl.textContent = label;
                                const th = document.getElementById(`priceColHeader_${idx}`);
                                if (th) {
                                    const nameSpan = th.querySelector('.vendor-col-name');
                                    if (nameSpan) nameSpan.textContent = label;
                                }
                            });
                        }

                        function refreshUI() {
                            const cards = document.querySelectorAll('.vendor-card');
                            const n = cards.length;
                            const badge = document.getElementById('vendorCountBadge');
                            badge.textContent = n + ' vendor';
                            badge.className = 'badge ms-2 ' + (n < 3 ? 'bg-secondary' : n <= 10 ? 'bg-success' : 'bg-danger');

                            document.getElementById('addVendorBtn').disabled = n >= 10;
                            document.getElementById('clvpSubmitBtn').disabled = n < 3;
                            renumberVendors();
                            refreshRecommendDropdown(); // also calls refreshRecommendation
                        }

                        // Re-calculate recommendation on every price input or discount change
                        document.getElementById('clvpForm').addEventListener('input', e => {
                            if (e.target.classList.contains('price-input') ||
                                e.target.name && e.target.name.includes('[discount]')) refreshRecommendation();
                        });

                        document.getElementById('clvpForm').addEventListener('submit', function(e) {
                            const cards = document.querySelectorAll('.vendor-card');
                            if (cards.length < 3 || cards.length > 10) {
                                e.preventDefault();
                                alert('Harap tambahkan minimal 3 dan maksimal 10 vendor.');
                                return;
                            }
                            // Block submit if any duplicate vendor names
                            if (document.querySelector('.vendor-name-input.is-invalid')) {
                                e.preventDefault();
                                alert('Terdapat nama vendor yang sama. Harap gunakan vendor yang berbeda.');
                                return;
                            }
                            // Re-enable any disabled price inputs so they POST as 0
                            document.querySelectorAll('.price-input:disabled').forEach(i => {
                                i.disabled = false;
                            });
                            // Clear draft on successful submit
                            try {
                                localStorage.removeItem(DRAFT_KEY);
                            } catch (e) {}
                        });

                        // ── localStorage draft save / restore ──────────────────────
                        function saveDraft() {
                            if (IS_EDIT_MODE) return; // don't draft over an edit
                            try {
                                const data = {
                                    vendors: [],
                                    category: null,
                                    selected_vendor: null,
                                    notes: null
                                };
                                document.querySelectorAll('.vendor-card').forEach(card => {
                                    const v = {};
                                    card.querySelectorAll('[name]').forEach(el => {
                                        const m = el.name.match(/vendors\[\d+\]\[(.+)\]/);
                                        if (!m) return;
                                        if (el.type === 'radio') {
                                            if (el.checked) v[m[1]] = el.value;
                                        } else {
                                            v[m[1]] = el.value;
                                        }
                                    });
                                    data.vendors.push(v);
                                });
                                const cat = document.querySelector('input[name="category"]:checked');
                                data.category = cat ? cat.value : null;
                                const sel = document.getElementById('selectedVendorDropdown');
                                data.selected_vendor = sel ? sel.value : null;
                                const notes = document.querySelector('textarea[name="notes"]');
                                data.notes = notes ? notes.value : null;
                                // prices
                                const prices = [];
                                document.querySelectorAll('#priceMatrixBody tr').forEach(row => {
                                    const rowData = {};
                                    row.querySelectorAll('[name]').forEach(el => {
                                        const m = el.name.match(/vendor_prices\[\d+\]\[(.+)\]/);
                                        if (m) rowData[m[1]] = el.value;
                                    });
                                    if (Object.keys(rowData).length) prices.push(rowData);
                                });
                                data.prices = prices;
                                localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
                            } catch (e) {}
                        }

                        function loadDraft() {
                            if (IS_EDIT_MODE || IS_REJECTED_PREFILL) {
                                // Pre-fill from existing comparison data
                                PREFILL_VENDORS.forEach(v => {
                                    addVendorCard();
                                    const idx = vendorCount - 1;
                                    const card = document.querySelectorAll('.vendor-card')[idx];
                                    const fields = ['name', 'alamat', 'phone', 'pic',
                                        'term_of_payment', 'tax_info', 'discount', 'other_terms', 'indent_duration'
                                    ];
                                    fields.forEach(f => {
                                        const el = card.querySelector(`[name="vendors[${idx}][${f}]"]`);
                                        if (el && v[f] !== undefined) el.value = v[f];
                                    });
                                    if (v['availability']) {
                                        const rb = card.querySelector(
                                            `[name="vendors[${idx}][availability]"][value="${v['availability']}"]`);
                                        if (rb) rb.checked = true;
                                    }
                                    toggleIndentDuration(idx);
                                    // sync recommended dropdown
                                    const nameInp = card.querySelector('.vendor-name-input');
                                    if (nameInp) nameInp.dispatchEvent(new Event('input'));
                                });
                                // Pre-fill pricelist originals per row
                                PREFILL_PRICES.forEach((row, ri) => {
                                    const plVal = row.pricelist_original;
                                    if (plVal !== undefined && plVal !== null && plVal !== '') {
                                        const plInp = document.getElementById(`pricelistOriInput_${ri}`);
                                        const plHid = document.getElementById(`pricelistOrigHidden_${ri}`);
                                        if (plInp) plInp.value = plVal;
                                        if (plHid) plHid.value = plVal;
                                    }
                                });
                                // Pre-fill prices
                                PREFILL_PRICES.forEach((row, ri) => {
                                    PREFILL_VENDORS.forEach((_, vi) => {
                                        const inp = document.querySelector(`[name="vendor_prices[${ri}][prices][${vi}]"]`);
                                        const cb = document.getElementById(`tj_${ri}_${vi}`);
                                        if (inp && row.prices && row.prices[vi] !== undefined) {
                                            const p = row.prices[vi];
                                            if (p == 0 || p === '') {
                                                if (cb) {
                                                    cb.checked = true;
                                                    inp.disabled = true;
                                                    inp.value = 0;
                                                }
                                            } else {
                                                inp.value = p;
                                            }
                                        }
                                    });
                                });
                                // Pre-fill selected vendor
                                if (PREFILL_SELECTED) {
                                    setTimeout(() => {
                                        const sel = document.getElementById('selectedVendorDropdown');
                                        if (sel) sel.value = PREFILL_SELECTED;
                                    }, 100);
                                }
                                refreshRecommendation();
                                return;
                            }
                            // Draft restore
                            try {
                                const raw = localStorage.getItem(DRAFT_KEY);
                                if (!raw) return;
                                const data = JSON.parse(raw);
                                if (!data.vendors || !data.vendors.length) return;
                                if (!confirm('Ditemukan draft tersimpan untuk RFQ ini. Muat kembali?')) {
                                    localStorage.removeItem(DRAFT_KEY);
                                    return;
                                }
                                data.vendors.forEach(v => {
                                    addVendorCard();
                                    const idx = vendorCount - 1;
                                    const card = document.querySelectorAll('.vendor-card')[idx];
                                    const fields = ['name', 'alamat', 'phone', 'pic',
                                        'term_of_payment', 'tax_info', 'discount', 'other_terms', 'indent_duration'
                                    ];
                                    fields.forEach(f => {
                                        const el = card.querySelector(`[name="vendors[${idx}][${f}]"]`);
                                        if (el && v[f] !== undefined) el.value = v[f];
                                    });
                                    if (v['availability']) {
                                        const rb = card.querySelector(
                                            `[name="vendors[${idx}][availability]"][value="${v['availability']}"]`);
                                        if (rb) rb.checked = true;
                                    }
                                    toggleIndentDuration(idx);
                                    const nameInp = card.querySelector('.vendor-name-input');
                                    if (nameInp) nameInp.dispatchEvent(new Event('input'));
                                });
                                if (data.category) {
                                    const cat = document.querySelector(`input[name="category"][value="${data.category}"]`);
                                    if (cat) cat.checked = true;
                                }
                                if (data.selected_vendor) {
                                    setTimeout(() => {
                                        const sel = document.getElementById('selectedVendorDropdown');
                                        if (sel) sel.value = data.selected_vendor;
                                    }, 100);
                                }
                                if (data.notes) {
                                    const notes = document.querySelector('textarea[name="notes"]');
                                    if (notes) notes.value = data.notes;
                                }
                            } catch (e) {
                                localStorage.removeItem(DRAFT_KEY);
                            }
                        }

                        // Debounced auto-save
                        let draftTimer = null;

                        function scheduleSave() {
                            clearTimeout(draftTimer);
                            draftTimer = setTimeout(saveDraft, 1500);
                        }
                        document.getElementById('clvpForm').addEventListener('input', scheduleSave);
                        document.getElementById('clvpForm').addEventListener('change', scheduleSave);

                        function openClvpPreview() {
                            const body = document.getElementById('clvpPreviewBody');
                            body.innerHTML = buildClvpPreviewHtml();
                            const modal = new bootstrap.Modal(document.getElementById('clvpPreviewModal'));
                            modal.show();
                        }

                        function buildClvpPreviewHtml() {
                            // Gather category
                            const catEl = document.querySelector('input[name="category"]:checked');
                            const catRaw = catEl ? catEl.value : '';
                            const catMap = { unit_baru: 'Unit Baru', aksesoris: 'Aksesoris Mobil', sparepart: 'Sparepart', umum: 'Umum' };
                            const catLabel = catMap[catRaw] || catRaw;

                            // Gather vendors from cards
                            const vendors = [];
                            document.querySelectorAll('.vendor-card').forEach(function(card) {
                                const get = f => { const el = card.querySelector(`[name*="[${f}]"]`); return el ? el.value.trim() : ''; };
                                const getRb = f => { const el = card.querySelector(`[name*="[${f}]"]:checked`); return el ? el.value : ''; };
                                const name = get('name');
                                if (!name) return;
                                vendors.push({
                                    name,
                                    pic: get('pic'),
                                    phone: get('phone'),
                                    discount: get('discount'),
                                    availability: getRb('availability'),
                                    indent_duration: get('indent_duration'),
                                    tax_info: get('tax_info'),
                                    term_of_payment: get('term_of_payment'),
                                });
                            });

                            // Gather selected vendor
                            const selVendor = (document.getElementById('selectedVendorDropdown')?.value || '').trim();

                            // Gather rows
                            const rows = [];
                            document.querySelectorAll('#priceMatrixBody tr[data-row]').forEach(function(tr) {
                                const ri = tr.dataset.row;
                                const nameEl = tr.querySelector('td:nth-child(2)');
                                const qtyInp = tr.querySelector(`[name="vendor_prices[${ri}][qty]"]`);
                                const uomInp = tr.querySelector(`[name="vendor_prices[${ri}][uom]"]`);
                                const plHid  = tr.querySelector(`[name="vendor_prices[${ri}][pricelist_original]"]`);
                                const codeInp = tr.querySelector(`[name="vendor_prices[${ri}][product_code]"]`);
                                const descInp = tr.querySelector(`[name="vendor_prices[${ri}][product_description]"]`);
                                const prices = [];
                                document.querySelectorAll(`#priceMatrixHeader th[id^="priceColHeader_"]`).forEach(function(th) {
                                    const idx = parseInt(th.id.replace('priceColHeader_', ''));
                                    const pinp = tr.querySelector(`[name="vendor_prices[${ri}][prices][${idx}]"]`);
                                    prices.push(pinp ? parseFloat(pinp.value) || 0 : 0);
                                });
                                rows.push({
                                    name: nameEl ? nameEl.textContent.trim() : '',
                                    code: codeInp ? codeInp.value : '',
                                    desc: descInp ? descInp.value : '',
                                    qty: qtyInp ? parseFloat(qtyInp.value) || 1 : 1,
                                    uom: uomInp ? uomInp.value : '',
                                    pricelist: plHid ? parseFloat(plHid.value) || 0 : 0,
                                    prices,
                                });
                            });

                            if (vendors.length === 0) {
                                return '<div class="alert alert-warning">Belum ada vendor yang ditambahkan.</div>';
                            }

                            const cur = 'Rp';
                            const fmt = n => cur + Math.round(n).toLocaleString('id-ID');
                            const getDisc = v => { const m = String(v.discount||'').match(/[\d.]+/); return m ? parseFloat(m[0]) / 100 : 0; };

                            // Category checkboxes header
                            const cats = ['unit_baru','aksesoris','sparepart','umum'];
                            const catLabels = { unit_baru: 'Unit Baru', aksesoris: 'Aksesoris Mobil', sparepart: 'Sparepart', umum: 'Umum' };
                            let catHtml = cats.map(c => `<span style="margin-right:14px;"><span style="display:inline-block;width:12px;height:12px;border:1.5px solid #333;vertical-align:middle;margin-right:3px;background:${c===catRaw?'#333':'#fff'};">${c===catRaw?'<span style="color:#fff;font-size:9px;line-height:12px;display:block;text-align:center;">&#10003;</span>':''}</span>${catLabels[c]}</span>`).join('');

                            // Always show pricelist col in preview
                            const showPl = true;

                            // Table header
                            let html = `
                            <div style="font-family:Arial,sans-serif; font-size:11px;">
                            <div style="text-align:center; font-weight:bold; font-size:14px; margin-bottom:6px;">COMPARISON LOCAL VENDOR PRICE ( CLVP )</div>
                            <div style="margin-bottom:8px;">${catHtml}</div>
                            <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse; font-size:11px;">
                            <thead>
                            <tr>
                                <th rowspan="2" style="border:1px solid #000;padding:4px 6px;text-align:center;width:24px;">No</th>
                                <th rowspan="2" style="border:1px solid #000;padding:4px 6px;">Nama Barang</th>
                                <th rowspan="2" style="border:1px solid #000;padding:4px 6px;text-align:center;width:60px;">Kode Barang</th>
                                <th rowspan="2" style="border:1px solid #000;padding:4px 6px;text-align:center;width:36px;">Qty</th>
                                <th rowspan="2" style="border:1px solid #000;padding:4px 6px;text-align:center;width:36px;">UoM</th>
                                <th rowspan="2" style="border:1px solid #000;padding:4px 6px;text-align:center;width:90px;">Pricelist Original</th>
                                <th colspan="${vendors.length}" style="border:1px solid #000;padding:4px 6px;text-align:center;background:#f0f0f0;">MITRA BISNIS</th>
                            </tr>
                            <tr>
                                ${vendors.map(v => {
                                    const isRec = v.name === selVendor;
                                    return `<th style="border:1px solid #000;padding:4px 6px;text-align:center;min-width:100px;${isRec?'background:#d4edda;':''}">
                                        <div style="font-weight:bold;">${v.name}</div>
                                        ${v.pic ? `<div style="font-size:10px;">PIC : ${v.pic}</div>` : ''}
                                        ${v.phone ? `<div style="font-size:10px;">TELP : ${v.phone}</div>` : ''}
                                        ${isRec ? '<div style="font-size:9px;color:#155724;font-weight:bold;">&#10003; Rekomendasi</div>' : ''}
                                    </th>`;
                                }).join('')}
                            </tr>
                            </thead>
                            <tbody>`;

                            // Product rows
                            rows.forEach(function(row, ri) {
                                html += '<tr>';
                                html += `<td style="border:1px solid #000;padding:4px 6px;text-align:center;">${ri+1}</td>`;
                                html += `<td style="border:1px solid #000;padding:4px 6px;">${row.code ? `<span style="background:#6c757d;color:#fff;padding:1px 4px;border-radius:3px;font-size:8px;margin-right:3px;">${row.code}</span>` : ''}${row.name}</td>`;
                                html += `<td style="border:1px solid #000;padding:4px 6px;text-align:center;color:#888;font-size:10px;">${row.code}</td>`;
                                html += `<td style="border:1px solid #000;padding:4px 6px;text-align:center;">${row.qty}</td>`;
                                html += `<td style="border:1px solid #000;padding:4px 6px;text-align:center;">${row.uom}</td>`;
                                html += `<td style="border:1px solid #000;padding:4px 6px;text-align:right;">${row.pricelist > 0 ? fmt(row.pricelist) : ''}</td>`;
                                vendors.forEach(function(v, vi) {
                                    const isRec = v.name === selVendor;
                                    const p = row.prices[vi] || 0;
                                    const display = p > 0 ? fmt(p) : '<span style="color:#888;font-style:italic;">Tidak Menjual Barang</span>';
                                    html += `<td style="border:1px solid #000;padding:4px 6px;text-align:right;${isRec?'background:#f0fff4;':''}">${display}</td>`;
                                });
                                html += '</tr>';
                            });

                            // Spacer rows
                            const spacers = Math.max(0, 6 - rows.length);
                            for (let i = 0; i < spacers; i++) {
                                const fixedCols = showPl ? 6 : 5;
                                html += '<tr>' + Array(fixedCols + vendors.length).fill('<td style="border:1px solid #000;padding:4px 6px;">&nbsp;</td>').join('') + '</tr>';
                            }

                            // Disc row
                            const hasDisc = vendors.some(v => v.discount > 0);
                            if (hasDisc) {
                                const fixedCols = showPl ? 6 : 5;
                                html += '<tr>' + Array(fixedCols).fill('<td style="border:1px solid #000;"></td>').join('');
                                vendors.forEach(function(v) {
                                    const isRec = v.name === selVendor;
                                    const d = parseFloat(v.discount) || 0;
                                    html += `<td style="border:1px solid #000;padding:4px 6px;text-align:center;font-size:10px;font-weight:bold;color:#c0392b;${isRec?'background:#f0fff4;':''}">${d > 0 ? 'Disc ' + d + '%' : ''}</td>`;
                                });
                                html += '</tr>';
                            }

                            // Total row
                            let origTotal = 0;
                            rows.forEach(r => { origTotal += r.pricelist * r.qty; });
                            const fixedCols = showPl ? 5 : 5;
                            html += `<tr style="font-weight:bold;background:#f9f9f9;">`;
                            html += `<td colspan="${fixedCols}" style="border:1px solid #000;padding:4px 6px;text-align:right;font-weight:bold;">TOTAL</td>`;
                            if (showPl) html += `<td style="border:1px solid #000;padding:4px 6px;text-align:right;">${fmt(origTotal)}</td>`;
                            vendors.forEach(function(v, vi) {
                                const isRec = v.name === selVendor;
                                let vTotal = 0;
                                rows.forEach(r => { vTotal += (r.prices[vi] || 0) * r.qty; });
                                html += `<td style="border:1px solid #000;padding:4px 6px;text-align:right;font-weight:bold;${isRec?'background:#f0fff4;':''}">${fmt(vTotal)}</td>`;
                            });
                            html += '</tr>';

                            html += `</tbody></table></div></div>`;
                            return html;
                        }

                        // Load draft / prefill after DOM ready
                        document.addEventListener('DOMContentLoaded', function() {
                            loadDraft();
                            /* Remark: kept for future use
                            document.querySelectorAll('.pricelist-ori-input').forEach(function(inp) {
                                inp.readOnly = false;
                                inp.style.background = '';
                                inp.style.cursor = '';
                            });
                            */
                        });
                        @if (session('clear_draft_key'))
                            try {
                                localStorage.removeItem('{{ session('clear_draft_key') }}');
                            } catch (e) {}
                        @endif
                    </script>
                @endif
            @endif {{-- end $isActiveRfq --}}
        @endauth

        {{-- ── Per-product comparison blocks ── --}}
        @forelse($rfq['lines'] as $line)
            @php
                // Skip lines where Odoo returned false for product_id (unlinked lines)
                if (!is_array($line['product_id'])) {
                    continue;
                }
                $productId = $line['product_id'][0];
                $productName = $line['product_id'][1];
                $uom = is_array($line['product_uom']) ? $line['product_uom'][1] : '';
                $rfqVendorId = is_array($rfq['partner_id']) ? $rfq['partner_id'][0] : null;
                $rfqVendorNm = is_array($rfq['partner_id']) ? $rfq['partner_id'][1] : '—';

                // Vendor rows: start with the current RFQ vendor (if it exists in history)
                $vendorRows = $history[$productId] ?? [];

                // Collect all prices (confirmed history + current RFQ) to determine best/worst
                $allPrices = array_column(array_values($vendorRows), 'price_unit');
                $allPrices[] = $line['price_unit']; // include current RFQ price
                $allPrices = array_filter($allPrices, fn($p) => $p > 0);
                $bestPrice = !empty($allPrices) ? min($allPrices) : null;
                $worstPrice = !empty($allPrices) ? max($allPrices) : null;
            @endphp

            <div class="product-block">
                <div class="card">
                    <div class="card-header py-2 d-flex align-items-center gap-3">
                        <span class="product-name d-flex align-items-center gap-2">
                            <i class="bi bi-box-seam text-muted"></i>
                            <span class="badge bg-secondary">{{ $productName }}</span>
                            {{ $line['name'] }}
                        </span>
                        <span class="badge bg-light text-dark border">
                            {{ $line['product_qty'] }} {{ $uom }}
                        </span>
                        <span class="ms-auto text-muted small">
                            RFQ Unit Price:&nbsp;
                            <strong @class([
                                'text-success' => $bestPrice !== null && $line['price_unit'] == $bestPrice,
                                'text-danger' =>
                                    $worstPrice !== null &&
                                    $line['price_unit'] == $worstPrice &&
                                    $bestPrice !== $worstPrice,
                            ])>
                                {{ $currency }} {{ number_format($line['price_unit'], 2, ',', '.') }}
                            </strong>
                        </span>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
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
                                    {{-- Current RFQ vendor row (always shown first) --}}
                                    @php
                                        $isCurrentBest = $bestPrice !== null && $line['price_unit'] == $bestPrice;
                                        $isCurrentWorst =
                                            $worstPrice !== null &&
                                            $line['price_unit'] == $worstPrice &&
                                            $bestPrice !== $worstPrice;
                                        $currentClass = 'price-current';
                                        if ($isCurrentBest && count($allPrices) > 1) {
                                            $currentClass = 'price-best';
                                        } elseif ($isCurrentWorst && count($allPrices) > 1) {
                                            $currentClass = 'price-worst';
                                        }
                                    @endphp
                                    <tr class="{{ $currentClass }}">
                                        <td class="ps-3 fw-semibold">
                                            <i class="bi bi-star-fill text-warning me-1"></i>{{ $rfqVendorNm }}
                                        </td>
                                        <td class="text-center fw-bold">
                                            {{ $currency }} {{ number_format($line['price_unit'], 2, ',', '.') }}
                                        </td>
                                        <td class="text-center">{{ $line['product_qty'] }}</td>
                                        <td class="text-center">{{ $uom }}</td>
                                        <td class="text-muted">{{ $rfq['name'] }}</td>
                                        <td class="text-muted">
                                            {{ \Illuminate\Support\Carbon::parse($rfq['date_order'])->format('d M Y') }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark">Current RFQ</span>
                                        </td>
                                    </tr>

                                    {{-- Historical vendors (ALL vendors including same vendor as RFQ) --}}
                                    @php
                                        $otherRows = array_values(array_values($vendorRows));

                                        // Most recent purchase (by date desc)
                                        $byDate = $otherRows;
                                        usort($byDate, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
                                        $mostRecentRow = $byDate[0] ?? null;

                                        // 3 cheapest (by price asc), excluding the most-recent row to avoid duplicate
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

                                    {{-- Most Recent Purchase row --}}
                                    @if ($mostRecentRow)
                                        @php
                                            $isBest = $bestPrice !== null && $mostRecentRow['price_unit'] == $bestPrice;
                                        @endphp
                                        <tr class="table-info" style="border-left: 3px solid #0d6efd;">
                                            <td class="ps-3 fw-semibold">
                                                {{ $mostRecentRow['vendor_name'] }}
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
                                            <td>
                                                <a href="{{ route('rfq.show', $mostRecentRow['order_id']) }}?from={{ $rfq['id'] }}&from_name={{ urlencode($rfq['name']) }}"
                                                    class="text-decoration-none small">
                                                    {{ $mostRecentRow['po_name'] }}
                                                </a>
                                            </td>
                                            <td class="fw-semibold">
                                                {{ \Illuminate\Support\Carbon::parse($mostRecentRow['date'])->format('d M Y H:i') }}
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary">Latest Purchase</span>
                                                @if ($isBest)
                                                    <span class="badge bg-success">Best Price</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif

                                    {{-- 3 cheapest (excluding the most-recent row already shown above) --}}
                                    @foreach ($cheapRows as $row)
                                        @php
                                            $isBest = $bestPrice !== null && $row['price_unit'] == $bestPrice;
                                            $isWorst =
                                                $worstPrice !== null &&
                                                $row['price_unit'] == $worstPrice &&
                                                $bestPrice !== $worstPrice;
                                            $rowClass = match (true) {
                                                $isBest => 'price-best',
                                                $isWorst => 'price-worst',
                                                default => '',
                                            };
                                        @endphp
                                        <tr class="{{ $rowClass }}">
                                            <td class="ps-3">{{ $row['vendor_name'] }}</td>
                                            <td class="text-center">
                                                {{ $currency }} {{ number_format($row['price_unit'], 2, ',', '.') }}
                                                @if ($isBest)
                                                    <i class="bi bi-check-circle-fill text-success ms-1"
                                                        title="Best Price"></i>
                                                @elseif($isWorst)
                                                    <i class="bi bi-arrow-up-circle-fill text-danger ms-1"
                                                        title="Highest Price"></i>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $row['product_qty'] }}</td>
                                            <td class="text-center">{{ $row['uom'] }}</td>
                                            <td>
                                                <a href="{{ route('rfq.show', $row['order_id']) }}?from={{ $rfq['id'] }}&from_name={{ urlencode($rfq['name']) }}"
                                                    class="text-decoration-none small">
                                                    {{ $row['po_name'] }}
                                                </a>
                                            </td>
                                            <td class="text-muted">
                                                {{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y H:i') }}
                                            </td>
                                            <td class="text-center">
                                                @if ($isBest)
                                                    <span class="badge bg-success">Best Price</span>
                                                @elseif($isWorst)
                                                    <span class="badge bg-danger">Highest</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach

                                    @if (!$mostRecentRow && empty($cheapRows))
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-3 small">
                                                <i class="bi bi-clock-history me-1"></i>
                                                No purchase history from other vendors for this product.
                                            </td>
                                        </tr>
                                    @elseif ($totalHistoryCount > 4)
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-2 small fst-italic">
                                                Showing latest purchase + 3 cheapest of {{ $totalHistoryCount }} vendors in
                                                history.
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-circle me-2"></i>This RFQ has no order lines.
            </div>
        @endforelse

        <div class="mt-3">
            <a href="{{ route('rfq.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Comparison List
            </a>
        </div>

    @endif

@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
@endpush
