@extends('layouts.app')

@section('title', 'RFQ List')

@section('content')

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2 text-purple-600"></i>Request For Quotation (RFQ)</h4>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted">{{ count($rfqs) }} record(s) found</span>
            <form method="POST" action="{{ route('rfq.refresh') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh from Odoo
                </button>
            </form>
        </div>
    </div>

    @if ($error)
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ $error }}
        </div>
    @endif

    @if (empty($rfqs) && !$error)
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>No RFQs found in the system.
        </div>
    @endif

    @if (!empty($rfqs))
        <div class="card">
            <div class="card-header py-3">
                <h5><i class="bi bi-table me-2"></i>Open Requests For Quotation</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
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
                                <th class="text-center" style="width:100px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rfqs as $rfq)
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $rfq['name'] }}</td>
                                    <td>
                                        {{ is_array($rfq['partner_id']) ? $rfq['partner_id'][1] : '—' }}
                                    </td>
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
                                        @php
                                            $currency = is_array($rfq['currency_id']) ? $rfq['currency_id'][1] : 'IDR';
                                        @endphp
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

@endsection
