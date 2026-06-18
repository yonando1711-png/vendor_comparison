<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            padding: 14px;
        }

        h1 {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .header-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }

        .header-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .logo-cell {
            width: 160px;
            border: 2px solid #000;
            padding: 5px 12px;
            text-align: center;
        }

        .cat-cell {
            padding-left: 20px;
            vertical-align: middle;
        }

        .cat-box {
            display: inline-block;
            margin-right: 14px;
        }

        .checkbox {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1.5px solid #000;
            text-align: center;
            line-height: 12px;
            font-size: 10px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 10px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 3px 5px;
        }

        th {
            background: #f0f0f0;
            text-align: center;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-muted {
            color: #888;
            font-style: italic;
        }

        .rec-col {
            background: #d4edda !important;
        }

        .rec-cell {
            background: #f0fff4 !important;
        }

        .total-row {
            font-weight: bold;
            background: #f9f9f9;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 10px;
        }

        .sig-row {
            display: flex;
            gap: 30px;
            margin-top: 8px;
            font-size: 10px;
        }

        .sig-box {
            text-align: center;
        }

        .status-badge {
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }

        .badge-pending {
            background: #ffc107;
            color: #000;
        }

        .badge-approved {
            background: #198754;
            color: #fff;
        }

        .badge-rejected {
            background: #dc3545;
            color: #fff;
        }
    </style>
</head>

<body>

    <h1>COMPARISON LOCAL VENDOR PRICE ( CLVP )</h1>

    @php
        $vendors = $comparison->vendors ?? [];
        $vpRows = $comparison->vendor_prices ?? [];
        $currency = 'Rp';
        $catMap = [
            'unit_baru' => 'Unit Baru',
            'aksesoris' => 'Aksesoris Mobil',
            'sparepart' => 'Sparepart',
            'umum' => 'Umum',
        ];
        $logoPath = public_path('logo.png');
        $logoSrc = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;
        // Only show Pricelist Original column if at least one row has a non-zero value
        $showPricelistCol = collect($vpRows)->contains(fn($r) => !empty($r['pricelist_original']) && (float)$r['pricelist_original'] > 0);
    @endphp

    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if ($logoSrc)
                    <img src="{{ $logoSrc }}" style="height:55px; max-width:140px; object-fit:contain;">
                @endif
            </td>
            <td class="cat-cell">
                @foreach ($catMap as $val => $lbl)
                    <span class="cat-box">
                        <span class="checkbox">{{ $comparison->category === $val ? 'V' : '' }}</span>
                        {{ $lbl }}
                    </span>
                @endforeach
                &nbsp;&nbsp;
                <span>Status:
                    <span
                        class="status-badge {{ $comparison->isApproved() ? 'badge-approved' : ($comparison->isRejected() ? 'badge-rejected' : 'badge-pending') }}">
                        {{ $comparison->statusLabel() }}
                    </span>
                </span>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:24px">No</th>
                <th rowspan="2">Nama Barang</th>
                <th rowspan="2" style="width:55px">Kode Barang</th>
                <th rowspan="2" style="width:30px">Qty</th>
                <th rowspan="2" style="width:30px">UoM</th>
                @if ($showPricelistCol)
                <th rowspan="2" style="width:80px">Pricelist Original</th>
                @endif
                @if (!empty($vendors))
                    <th colspan="{{ count($vendors) }}" style="background:#e0e0e0;">MITRA BISNIS</th>
                @endif
            </tr>
            <tr>
                @foreach ($vendors as $v)
                    @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                    <th style="min-width:90px; {{ $isRec ? 'background:#d4edda;' : '' }}">
                        <div>{{ $v['name'] ?? '—' }}</div>
                        @if (!empty($v['pic']))
                            <div style="font-weight:normal;font-size:9px;">PIC: {{ $v['pic'] }}</div>
                        @endif
                        @if (!empty($v['phone']))
                            <div style="font-weight:normal;font-size:9px;">TELP: {{ $v['phone'] }}</div>
                        @endif
                        @if ($isRec)
                            <div style="font-size:8px;color:#155724;font-weight:bold;">✓ Rekomendasi</div>
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{-- Build an ordered index of RFQ lines for product name lookup --}}
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
                    <td class="text-center">{{ $ri + 1 }}</td>
                    <td>
                        @if (!empty($pCode))
                            <span
                                style="background:#6c757d;color:#fff;padding:1px 5px;border-radius:3px;font-size:8px;margin-right:3px;">{{ $pCode }}</span>
                        @endif
                        {{ $pName }}
                    </td>
                    <td class="text-center text-muted" style="font-size:9px;">{{ $pCode }}</td>
                    <td class="text-center">{{ $row['qty'] ?? '' }}</td>
                    <td class="text-center">{{ $row['uom'] ?? '' }}</td>
                    @if ($showPricelistCol)
                    <td class="text-right">{{ number_format($row['pricelist_original'] ?? 0, 0, ',', '.') }}</td>
                    @endif
                    @foreach ($vendors as $vi => $v)
                        @php
                            $price = $row['prices'][$vi] ?? null;
                            $isRec = ($v['name'] ?? '') === $comparison->selected_vendor;
                            preg_match('/[\d.]+/', $v['discount'] ?? '', $dm);
                            $dRate = isset($dm[0]) ? (float) $dm[0] / 100 : 0;
                            $discountedPrice = $price && $dRate > 0 ? (float) $price * (1 - $dRate) : null;
                        @endphp
                        <td class="text-right {{ $isRec ? 'rec-cell' : '' }}">
                            @if ($price === null || $price === '' || $price == 0)
                                <span class="text-muted">Tidak Menjual Barang</span>
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
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    @if ($showPricelistCol)<td></td>@endif
                    @foreach ($vendors as $v)
                        @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                        <td class="text-center {{ $isRec ? 'rec-cell' : '' }}"
                            style="font-size:9px; color:#c0392b; font-weight:bold;">
                            {{ !empty($v['discount']) ? 'Disc ' . rtrim($v['discount'], '%') . '%' : '' }}
                        </td>
                    @endforeach
                </tr>
            @endif

            {{-- Spacer rows --}}
            @for ($i = 0; $i < max(0, 6 - count($vpRows)); $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    @if ($showPricelistCol)<td></td>@endif
                    @foreach ($vendors as $v)
                        <td></td>
                    @endforeach
                </tr>
            @endfor

            {{-- TOTAL --}}
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL</td>
                @if ($showPricelistCol)
                <td class="text-right">
                    @php 
                        $origTotal = 0;
                        foreach ($vpRows as $row) {
                            $origTotal += (float)($row['pricelist_original'] ?? 0) * (float)($row['qty'] ?? 1);
                        }
                    @endphp
                    {{ $currency }}{{ number_format($origTotal, 0, ',', '.') }}
                </td>
                @endif
                @foreach ($vendors as $vi => $v)
                    @php
                        $vTotal = 0;
                        $vTotalDisc = 0;
                        preg_match('/[\d.]+/', $v['discount'] ?? '', $dm);
                        $dRate = isset($dm[0]) ? (float) $dm[0] / 100 : 0;
                        foreach ($vpRows as $row) {
                            $p = (float) ($row['prices'][$vi] ?? 0);
                            $qty = (float) ($row['qty'] ?? 1);
                            $vTotal += $p * $qty;
                            $vTotalDisc += $p * $qty * (1 - $dRate);
                        }
                        $isRec = ($v['name'] ?? '') === $comparison->selected_vendor;
                    @endphp
                    <td class="text-right {{ $isRec ? 'rec-cell' : '' }}">
                        {{ $currency }}{{ number_format($vTotalDisc, 0, ',', '.') }}
                    </td>
                @endforeach
            </tr>

            {{-- Availability --}}
            <tr>
                <td colspan="{{ $showPricelistCol ? 6 : 5 }}"></td>
                @foreach ($vendors as $v)
                    @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                    <td class="{{ $isRec ? 'rec-cell' : '' }}" style="font-size:9px;">
                        @php
                            $isReady = ($v['availability'] ?? '') === 'ready' || !empty($v['ready']);
                            $isIndent = ($v['availability'] ?? '') === 'indent' || !empty($v['indent']);
                        @endphp
                        <span class="checkbox">{{ $isReady ? 'V' : '' }}</span> Ready<br>
                        <span class="checkbox">{{ $isIndent ? 'V' : '' }}</span>
                        Indent/Kosong
                    </td>
                @endforeach
            </tr>

            {{-- Indent duration row --}}
            @php $hasIndentDuration = collect($vendors)->contains(fn($v) => !empty($v['indent_duration'])); @endphp
            @if ($hasIndentDuration)
                <tr>
                    <td colspan="{{ $showPricelistCol ? 6 : 5 }}" style="font-size:9px; font-style:italic; color:#c05c00;">Durasi Indent</td>
                    @foreach ($vendors as $v)
                        @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                        <td class="{{ $isRec ? 'rec-cell' : '' }}"
                            style="font-size:9px; text-align:center; color:#c05c00; font-weight:bold;">
                            {{ $v['indent_duration'] ?? '' }}</td>
                    @endforeach
                </tr>
            @endif

            {{-- Tax --}}
            <tr>
                <td colspan="{{ $showPricelistCol ? 6 : 5 }}" style="font-size:9px;">Tax / PPN</td>
                @foreach ($vendors as $v)
                    @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                    <td class="{{ $isRec ? 'rec-cell' : '' }}" style="font-size:9px;">{{ $v['tax_info'] ?? '' }}</td>
                @endforeach
            </tr>

            {{-- Payment terms --}}
            <tr>
                <td colspan="{{ $showPricelistCol ? 6 : 5 }}" style="font-size:9px;">Term of Payment</td>
                @foreach ($vendors as $v)
                    @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                    <td class="{{ $isRec ? 'rec-cell' : '' }}" style="font-size:9px;">
                        @php
                            $top = $v['term_of_payment'] ?? '';
                            if (is_numeric(trim($top))) {
                                $top .= ' Hari';
                            }
                        @endphp
                        {{ $top }}</td>
                @endforeach
            </tr>

        </tbody>
    </table>

    <div class="footer">
        <div>
            <strong>No. CLVP : {{ $comparison->comparison_code ?? $comparison->po_name }}</strong>
            <div style="color:#888; font-size:9px; margin-top:1px;">Ref PO: {{ $comparison->po_name }}</div>
            @if ($comparison->notes)
                <div style="color:#555;margin-top:2px;">{{ $comparison->notes }}</div>
            @endif
        </div>
        <div style="text-align:right;">
            Tgl {{ $comparison->created_at->format('d/m/y') }}<br>
            Dibuat oleh,<br><br><br><br><br>
            ({{ $comparison->creator->name ?? '—' }})<br>
            <span style="font-size:8px; color:#555;">SDP/FR/PCH/12, Rev.02</span>
        </div>
    </div>

    {{-- @if ($comparison->isApproved())
        <div class="sig-row">
            <div class="sig-box">
                Disetujui Supervisor,<br><br><br>
                ({{ $comparison->supervisor->name ?? '—' }})<br>
                <small>{{ $comparison->supervisor_approved_at?->format('d/m/Y') }}</small>
            </div>
            <div class="sig-box">
                Disetujui Manager,<br><br><br>
                ({{ $comparison->manager->name ?? '—' }})<br>
                <small>{{ $comparison->manager_approved_at?->format('d/m/Y') }}</small>
            </div>
        </div>
    @endif --}}

</body>

</html>
