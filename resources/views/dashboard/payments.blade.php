@extends('dashboard.layout')

@section('title', __('dashboard.menu_payments'))

@section('content')
    @php($title = __('dashboard.menu_payments'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.payments_page_intro') }}</p>

        <section class="card" style="margin-bottom: 24px;">
            <h3 style="margin: 0 0 12px;">{{ __('dashboard.payments_section_wallet') }}</h3>
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.table_col_phone') }}</th>
                        <th>{{ __('dashboard.table_col_type') }}</th>
                        <th>{{ __('dashboard.table_col_amount') }}</th>
                        <th>{{ __('dashboard.table_col_balance_after') }}</th>
                        <th>{{ __('dashboard.table_col_meta') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($transactions as $tx)
                        @php($u = $tx->wallet?->user)
                        <tr>
                            <td>{{ $tx->id }}</td>
                            <td>{{ $u?->name ?? '—' }}</td>
                            <td class="mono">{{ $u?->phone ?? '—' }}</td>
                            <td>{{ $tx->type }}</td>
                            <td>{{ $tx->amount }}</td>
                            <td>{{ $tx->balance_after }}</td>
                            <td style="max-width: 220px; overflow: hidden; text-overflow: ellipsis;">
                                @if(is_array($tx->meta))
                                    <code style="font-size: 12px;">{{ \Illuminate\Support\Str::limit(json_encode($tx->meta), 80) }}</code>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $tx->created_at?->toDateTimeString() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($transactions->hasPages())
                <div style="margin-top: 12px;">{{ $transactions->withQueryString()->links() }}</div>
            @endif
        </section>

        @if ($qicardPayments !== null)
            <section class="card">
                <h3 style="margin: 0 0 12px;">{{ __('dashboard.payments_section_qicard') }}</h3>
                <div style="overflow:auto;">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>{{ __('dashboard.table_col_user') }}</th>
                            <th>{{ __('dashboard.table_col_phone') }}</th>
                            <th>{{ __('dashboard.table_col_amount') }}</th>
                            <th>{{ __('dashboard.table_col_currency') }}</th>
                            <th>{{ __('dashboard.table_col_status') }}</th>
                            <th>{{ __('dashboard.table_col_payment_id') }}</th>
                            <th>{{ __('dashboard.table_col_credited') }}</th>
                            <th>{{ __('dashboard.table_col_created') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($qicardPayments as $p)
                            @php($u = $p->user)
                            <tr>
                                <td>{{ $p->id }}</td>
                                <td>{{ $u?->name ?? '—' }}</td>
                                <td class="mono">{{ $u?->phone ?? '—' }}</td>
                                <td>{{ $p->amount }}</td>
                                <td>{{ $p->currency }}</td>
                                <td>{{ $p->status }}</td>
                                <td class="mono" style="max-width:140px;overflow:hidden;text-overflow:ellipsis;">{{ $p->payment_id ?? '—' }}</td>
                                <td>{{ $p->credited_at?->toDateTimeString() ?? '—' }}</td>
                                <td>{{ $p->created_at?->toDateTimeString() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">{{ __('dashboard.table_empty') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($qicardPayments->hasPages())
                    <div style="margin-top: 12px;">{{ $qicardPayments->withQueryString()->links() }}</div>
                @endif
            </section>
        @endif
    @endcomponent
@endsection
