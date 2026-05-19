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

        .header-row {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 10px;
        }

        .logo-box {
            border: 2px solid #000;
            padding: 5px 12px;
            font-size: 17px;
            font-weight: 900;
            color: #c00;
            letter-spacing: 2px;
            min-width: 80px;
            text-align: center;
        }

        .logo-sub {
            font-size: 7px;
            font-weight: normal;
            color: #555;
            letter-spacing: 0;
        }

        .cat-row {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .cat-box {
            display: inline-flex;
            align-items: center;
            gap: 4px;
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
    @endphp

    <div class="header-row">
        <div class="logo-box">
            <img src="{{ public_path('logo.png') }}" style="height:55px; max-width:140px; object-fit:contain;">
        </div>
        <div class="cat-row">
            @foreach ($catMap as $val => $lbl)
                <div class="cat-box">
                    <span class="checkbox">{{ $comparison->category === $val ? 'V' : '' }}</span>
                    {{ $lbl }}
                </div>
            @endforeach
            &nbsp;&nbsp;
            <span>Status:
                <span
                    class="status-badge {{ $comparison->isApproved() ? 'badge-approved' : ($comparison->isRejected() ? 'badge-rejected' : 'badge-pending') }}">
                    {{ $comparison->statusLabel() }}
                </span>
            </span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:24px">No</th>
                <th rowspan="2">Nama Barang</th>
                <th rowspan="2" style="width:55px">Kode Barang</th>
                <th rowspan="2" style="width:30px">Qty</th>
                <th rowspan="2" style="width:30px">UoM</th>
                <th rowspan="2" style="width:80px">Pricelist Original</th>
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
            {{-- Product rows --}}
            @foreach ($vpRows as $ri => $row)
                <tr>
                    <td class="text-center">{{ $ri + 1 }}</td>
                    <td>
                        {{ $row['product_name'] ?? '' }}
                        @if (!empty($row['product_description']))
                            <div style="font-size:9px;color:#555;margin-top:2px;">{{ $row['product_description'] }}</div>
                        @endif
                    </td>
                    <td class="text-center text-muted" style="font-size:9px;"></td>
                    <td class="text-center">{{ $row['qty'] ?? '' }}</td>
                    <td class="text-center">{{ $row['uom'] ?? '' }}</td>
                    <td class="text-right">{{ number_format($row['pricelist_original'] ?? 0, 0, ',', '.') }}</td>
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
                                <span class="text-muted">Tidak jual</span>
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
                    <td></td>
                    @foreach ($vendors as $v)
                        @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                        <td class="text-center {{ $isRec ? 'rec-cell' : '' }}"
                            style="font-size:9px; color:#c0392b; font-weight:bold;">
                            {{ !empty($v['discount']) ? 'Disc ' . $v['discount'] : '' }}
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
                    <td></td>
                    @foreach ($vendors as $v)
                        <td></td>
                    @endforeach
                </tr>
            @endfor

            {{-- TOTAL --}}
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL</td>
                <td class="text-right">
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
                    <td class="text-right {{ $isRec ? 'rec-cell' : '' }}">
                        {{ $currency }}{{ number_format($vTotalDisc, 0, ',', '.') }}
                    </td>
                @endforeach
            </tr>

            {{-- Availability --}}
            <tr>
                <td colspan="6"></td>
                @foreach ($vendors as $v)
                    @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                    <td class="{{ $isRec ? 'rec-cell' : '' }}" style="font-size:9px;">
                        <span class="checkbox">{{ ($v['availability'] ?? '') === 'ready' ? 'V' : '' }}</span> Ready<br>
                        <span class="checkbox">{{ ($v['availability'] ?? '') === 'indent' ? 'V' : '' }}</span>
                        Indent/Kosong
                        @if (!empty($v['indent_duration']))
                            <br><span style="font-size:8px; color:#555;">{{ $v['indent_duration'] }}</span>
                        @endif
                    </td>
                @endforeach
            </tr>

            {{-- Tax --}}
            <tr>
                <td colspan="6" style="font-size:9px;">Tax / PPN</td>
                @foreach ($vendors as $v)
                    @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                    <td class="{{ $isRec ? 'rec-cell' : '' }}" style="font-size:9px;">{{ $v['tax_info'] ?? '' }}</td>
                @endforeach
            </tr>

            {{-- Payment terms --}}
            <tr>
                <td colspan="6" style="font-size:9px;">Term of Payment</td>
                @foreach ($vendors as $v)
                    @php $isRec = ($v['name'] ?? '') === $comparison->selected_vendor; @endphp
                    <td class="{{ $isRec ? 'rec-cell' : '' }}" style="font-size:9px;">
                        {{ $v['term_of_payment'] ?? '' }}</td>
                @endforeach
            </tr>

        </tbody>
    </table>

    <div class="footer">
        <div>
            <strong>NOTES : {{ $comparison->po_name }}</strong>
            @if ($comparison->notes)
                <div style="color:#555;margin-top:2px;">{{ $comparison->notes }}</div>
            @endif
        </div>
        <div style="text-align:right;">
            Tgl {{ $comparison->created_at->format('d/m/y') }}<br>
            Dibuat oleh,<br><br><br><br><br>
            ({{ $comparison->creator->name ?? '—' }})
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
