@extends('layouts.app')

@section('title', $rfq ? 'Comparison – ' . $rfq['name'] : 'Comparison')

@section('content')

    @if ($error)
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ $error }}
        </div>
        <a href="{{ route('rfq.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to RFQ List
        </a>
    @elseif($rfq)
        {{-- ── Breadcrumb ── --}}
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('rfq.index') }}">RFQ List</a></li>
                <li class="breadcrumb-item active">{{ $rfq['name'] }}</li>
            </ol>
        </nav>

        {{-- ── RFQ Header Card ── --}}
        <div class="card mb-4">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <h5><i class="bi bi-file-earmark-text me-2"></i>{{ $rfq['name'] }}</h5>
                <span class="badge {{ $rfq['state'] === 'sent' ? 'badge-sent' : 'badge-rfq' }} fs-6">
                    {{ $rfq['state'] === 'sent' ? 'RFQ Sent' : 'RFQ' }}
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted small">Vendor</div>
                        <div class="fw-semibold">
                            {{ is_array($rfq['partner_id']) ? $rfq['partner_id'][1] : '—' }}
                        </div>
                    </div>
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

        {{-- ── Legend ── --}}
        <div class="d-flex gap-3 mb-3 flex-wrap align-items-center">
            <span class="fw-semibold small">Legend:</span>
            <span class="badge price-best px-2 py-1">&#9733; Best Price</span>
            <span class="badge price-current px-2 py-1">&#9830; Current RFQ Vendor</span>
            <span class="badge price-worst px-2 py-1">&#9660; Highest Price</span>
            <span class="ms-auto text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                History shows the latest confirmed purchase order per vendor for each product.
            </span>
        </div>

        {{-- ── Approval submission panel (creators only) ── --}}
        @auth
            @php $existing = \App\Models\VendorComparison::where('po_id', $rfq['id'])->first(); @endphp

            @if ($existing)
                <div
                    class="alert d-flex align-items-center gap-3 mb-4
                {{ $existing->isApproved() ? 'alert-success' : ($existing->isRejected() ? 'alert-danger' : 'alert-info') }}">
                    <i
                        class="bi {{ $existing->isApproved() ? 'bi-patch-check-fill' : ($existing->isRejected() ? 'bi-x-circle-fill' : 'bi-hourglass-split') }} fs-4"></i>
                    <div>
                        <strong>Comparison already submitted.</strong>
                        Status: <span class="badge {{ $existing->statusBadgeClass() }}">{{ $existing->statusLabel() }}</span>
                        &nbsp;
                        <a href="{{ route('comparisons.show', $existing) }}" class="btn btn-sm btn-outline-secondary ms-2">
                            <i class="bi bi-eye me-1"></i>View Approval Details
                        </a>
                    </div>
                </div>
            @elseif(Auth::user()->isCreator())
                {{-- ════════════════════════════════════════════════════════
                     DATA CALON VENDOR — CLVP Input Form
                ════════════════════════════════════════════════════════ --}}
                <div class="card mb-4 border-primary">
                    <div class="card-header py-2 d-flex align-items-center justify-content-between"
                        style="background:#eff6ff; border-color:#bfdbfe;">
                        <h6 class="mb-0 text-primary">
                            <i class="bi bi-table me-2"></i>Data Calon Vendor — Comparison Local Vendor Price (CLVP)
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

                        <form method="POST" action="{{ route('comparisons.store') }}" id="clvpForm">
                            @csrf
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
                                                {{ old('category', 'umum') === $val ? 'checked' : '' }}>
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
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle" id="priceMatrix">
                                        <thead class="table-light">
                                            <tr id="priceMatrixHeader">
                                                <th class="text-center" style="width:40px">No</th>
                                                <th>Nama Barang</th>
                                                <th class="text-center" style="width:60px">Qty</th>
                                                <th class="text-center" style="width:60px">UoM</th>
                                                <th class="text-center" style="width:110px">Pricelist Ori (Rp)</th>
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
                                                    $pName = $line['product_id'][1];
                                                    $pCode = $line['product_id'][0]; // use ID as code placeholder
                                                    $uom = is_array($line['product_uom'])
                                                        ? $line['product_uom'][1]
                                                        : '';
                                                @endphp
                                                <tr data-row="{{ $lineIdx }}">
                                                    <td class="text-center">{{ $lineIdx + 1 }}</td>
                                                    <td>
                                                        {{ $pName }}
                                                        <input type="hidden"
                                                            name="vendor_prices[{{ $lineIdx }}][product_id]"
                                                            value="{{ $line['product_id'][0] }}">
                                                        <input type="hidden"
                                                            name="vendor_prices[{{ $lineIdx }}][product_name]"
                                                            value="{{ $pName }}">
                                                        <input type="hidden" name="vendor_prices[{{ $lineIdx }}][qty]"
                                                            value="{{ $line['product_qty'] }}">
                                                        <input type="hidden" name="vendor_prices[{{ $lineIdx }}][uom]"
                                                            value="{{ $uom }}">
                                                        <input type="hidden"
                                                            name="vendor_prices[{{ $lineIdx }}][pricelist_original]"
                                                            value="{{ $line['price_unit'] }}">
                                                    </td>
                                                    <td class="text-center">{{ $line['product_qty'] }}</td>
                                                    <td class="text-center">{{ $uom }}</td>
                                                    <td class="text-end pe-2">
                                                        {{ number_format($line['price_unit'], 0, ',', '.') }}</td>
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
                                    <select name="selected_vendor" id="selectedVendorDropdown" class="form-select" required>
                                        <option value="">— Pilih setelah menambah vendor —</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label fw-semibold">Catatan / Justifikasi</label>
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Alasan pemilihan vendor...">{{ old('notes') }}</textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" id="clvpSubmitBtn" disabled>
                                <i class="bi bi-send me-2"></i>Submit untuk Persetujuan Supervisor
                            </button>
                        </form>
                    </div>
                </div>

                {{-- ── Odoo vendor master data (embedded for auto-fill) ── --}}
                <script>
                    const ODOO_VENDORS = @json($vendors);
                    const PRODUCT_ROWS = {{ $lineIdx ?? 0 }};
                    let vendorCount = 0;

                    const vendorLookup = {};
                    ODOO_VENDORS.forEach(v => {
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
                            <div class="mb-2">
                                <label class="form-label small text-muted mb-1">
                                    <i class="bi bi-tools me-1"></i>Auto-isi dari Supplier Master
                                    <em>(opsional — ketik manual di bawah)</em>
                                </label>
                                <select class="form-select form-select-sm odoo-autofill"
                                    onchange="autoFill(${idx}, this.value)">
                                    <option value="">— Pilih supplier dari Odoo —</option>
                                    ${ODOO_VENDORS.map(v =>
                                        `<option value="${v.id}">${v.name}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div class="row g-2">
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
                                    <label class="form-label small fw-semibold mb-1">Email</label>
                                    <input type="email" class="form-control form-control-sm"
                                        name="vendors[${idx}][email]" placeholder="email@vendor.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">PIC / Contact Person</label>
                                    <input type="text" class="form-control form-control-sm"
                                        name="vendors[${idx}][pic]" placeholder="Nama kontak">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Metode Pembayaran</label>
                                    <select class="form-select form-select-sm" name="vendors[${idx}][payment_method]">
                                        <option value="">-- Pilih --</option>
                                        <option>Transfer Bank</option>
                                        <option>COD</option>
                                        <option>Giro</option>
                                        <option>Tunai</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Rekening Bank</label>
                                    <input type="text" class="form-control form-control-sm"
                                        name="vendors[${idx}][bank_account]"
                                        placeholder="e.g., BCA - 1234567890 a.n. PT XYZ">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Term of Payment</label>
                                    <input type="text" class="form-control form-control-sm"
                                        name="vendors[${idx}][term_of_payment]"
                                        placeholder="e.g., 30 hari">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Delivery Time</label>
                                    <input type="text" class="form-control form-control-sm"
                                        name="vendors[${idx}][delivery_time]"
                                        placeholder="e.g., 5 Hari Kerja">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Ketersediaan</label>
                                    <div class="d-flex gap-3 mt-1">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox"
                                                name="vendors[${idx}][ready]" value="1" id="ready_${idx}">
                                            <label class="form-check-label small" for="ready_${idx}">Ready</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox"
                                                name="vendors[${idx}][indent]" value="1" id="indent_${idx}">
                                            <label class="form-check-label small" for="indent_${idx}">Indent</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Pajak</label>
                                    <input type="text" class="form-control form-control-sm"
                                        name="vendors[${idx}][tax_info]"
                                        placeholder="e.g., Exc PPN">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Diskon</label>
                                    <input type="text" class="form-control form-control-sm"
                                        name="vendors[${idx}][discount]"
                                        placeholder="e.g., 10%">
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
                        addPriceColumn(idx);
                        refreshUI();
                    }

                    function removeVendorCard(idx) {
                        const card = document.querySelector(`.vendor-card[data-idx="${idx}"]`);
                        if (card) card.remove();
                        removePriceColumn(idx);
                        refreshUI();
                    }

                    function autoFill(idx, odooId) {
                        if (!odooId) return;
                        const v = vendorLookup[odooId];
                        if (!v) return;
                        const card = document.querySelector(`.vendor-card[data-idx="${idx}"]`);
                        if (!card) return;
                        card.querySelector(`[name="vendors[${idx}][name]"]`).value = v.name;
                        card.querySelector(`[name="vendors[${idx}][alamat]"]`).value = buildAddress(v);
                        card.querySelector(`[name="vendors[${idx}][phone]"]`).value = v.phone || v.mobile || '';
                        card.querySelector(`[name="vendors[${idx}][email]"]`).value = v.email || '';
                        onVendorNameChange(idx, v.name);
                    }

                    function onVendorNameChange(idx, name) {
                        const titleEl = document.getElementById(`cardTitle_${idx}`);
                        if (titleEl) titleEl.textContent = name || `Vendor ${idx+1}`;

                        // Update price matrix column header
                        const th = document.getElementById(`priceColHeader_${idx}`);
                        if (th) {
                            const nameSpan = th.querySelector('.vendor-col-name');
                            if (nameSpan) nameSpan.textContent = name || `Vendor ${idx+1}`;
                        }

                        // Update recommended dropdown
                        refreshRecommendDropdown();
                    }

                    function addPriceColumn(idx) {
                        const rows = document.querySelectorAll('#priceMatrixBody tr[data-row]');
                        if (rows.length === 0) return;

                        // Add header
                        const header = document.getElementById('priceMatrixHeader');
                        const th = document.createElement('th');
                        th.id = `priceColHeader_${idx}`;
                        th.className = 'text-center';
                        th.style.minWidth = '130px';
                        th.innerHTML = `<span class="vendor-col-name small">Vendor ${idx+1}</span>
                        <div class="small text-muted fst-italic" style="font-size:.7rem">PIC/Telp</div>`;
                        header.appendChild(th);

                        // Add input cell per product row
                        rows.forEach(row => {
                            const rowIdx = row.dataset.row;
                            const td = document.createElement('td');
                            td.className = 'p-1';
                            td.id = `priceCell_${rowIdx}_${idx}`;
                            td.innerHTML = `
                            <div class="input-group input-group-sm">
                                <input type="number" min="0" step="1"
                                    class="form-control form-control-sm text-end price-input"
                                    name="vendor_prices[${rowIdx}][prices][${idx}]"
                                    placeholder="0">
                            </div>
                            <div class="form-check mt-1">
                                <input class="form-check-input tidak-jual-cb" type="checkbox"
                                    id="tj_${rowIdx}_${idx}"
                                    onchange="toggleTidakJual(${rowIdx},${idx},this)">
                                <label class="form-check-label small text-muted" for="tj_${rowIdx}_${idx}">Tidak Jual</label>
                            </div>`;
                            row.appendChild(td);
                        });

                        document.getElementById('priceMatrixSection').style.display = '';
                        document.getElementById('recommendSection').style.display = '';
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
                            if (cb.checked) {
                                input.value = 0;
                            }
                        }
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
                    }

                    function refreshUI() {
                        const cards = document.querySelectorAll('.vendor-card');
                        const n = cards.length;
                        const badge = document.getElementById('vendorCountBadge');
                        badge.textContent = n + ' vendor';
                        badge.className = 'badge ms-2 ' + (n < 3 ? 'bg-secondary' : n <= 10 ? 'bg-success' : 'bg-danger');

                        document.getElementById('addVendorBtn').disabled = n >= 10;
                        document.getElementById('clvpSubmitBtn').disabled = n < 3;
                        refreshRecommendDropdown();
                    }

                    document.getElementById('clvpForm').addEventListener('submit', function(e) {
                        const cards = document.querySelectorAll('.vendor-card');
                        if (cards.length < 3 || cards.length > 10) {
                            e.preventDefault();
                            alert('Harap tambahkan minimal 3 dan maksimal 10 vendor.');
                            return;
                        }
                        // Re-enable any disabled price inputs so they POST as 0
                        document.querySelectorAll('.price-input:disabled').forEach(i => {
                            i.disabled = false;
                        });
                    });
                </script>
            @endif
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
                        <span class="product-name">
                            <i class="bi bi-box-seam me-1 text-muted"></i>{{ $productName }}
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

                                    {{-- Historical vendors (excluding current RFQ vendor to avoid duplicate) --}}
                                    @php
                                        // Sort by price ascending
                                        $sortedRows = array_values($vendorRows);
                                        usort($sortedRows, fn($a, $b) => $a['price_unit'] <=> $b['price_unit']);
                                    @endphp

                                    @foreach ($sortedRows as $row)
                                        @if ($row['vendor_id'] === $rfqVendorId)
                                            @continue
                                        @endif
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
                                                <a href="{{ route('rfq.show', $row['order_id']) }}"
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

                                    @if (empty($vendorRows) ||
                                            (count($vendorRows) === 1 && isset($vendorRows[$rfqVendorId])) ||
                                            count(array_filter($sortedRows, fn($r) => $r['vendor_id'] !== $rfqVendorId)) === 0)
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-3 small">
                                                <i class="bi bi-clock-history me-1"></i>
                                                No purchase history from other vendors for this product.
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
                <i class="bi bi-arrow-left me-1"></i>Back to RFQ List
            </a>
        </div>

    @endif

@endsection
