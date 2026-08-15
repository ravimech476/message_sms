@extends('_layouts.default')

@php
    // Badge colour per status code (Tailwind), mirroring production's
    // .status-delivered / -failed / -pending / -progress palette.
    $badge = [
        'delivered' => 'bg-green-100 text-green-800',
        'failed'    => 'bg-red-100 text-red-800',
        'pending'   => 'bg-yellow-100 text-yellow-800',
        'progress'  => 'bg-cyan-100 text-cyan-800',
    ];
@endphp

@section('content')
    <div class="container mx-auto">
        <div class="mb-4">
            <h1 class="text-3xl font-semibold text-gray-700">SMS Log</h1>
            <p class="text-sm text-gray-500">Delivery status from the SMPP pipeline (smsg_log)</p>
        </div>

        {{-- Filter bar: status tabs + date range --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="inline-flex rounded overflow-hidden border border-gray-300">
                @foreach ($filters as $key => $label)
                    <a href="{{ route('sms-log', array_merge(request()->query(), ['status' => $key, 'page' => null])) }}"
                       class="px-4 py-2 text-sm font-medium {{ $status === $key ? 'bg-blue-900 text-white' : 'bg-white text-gray-600 hover:bg-gray-100' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('sms-log') }}" class="flex items-end gap-2">
                <input type="hidden" name="status" value="{{ $status }}">
                <div>
                    <label class="block text-xs text-gray-500">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 rounded px-2 py-1 text-sm">
                </div>
                <button type="submit" class="bg-blue-900 text-white text-sm rounded px-3 py-1.5">Filter</button>
                @if ($from || $to)
                    <a href="{{ route('sms-log', ['status' => $status]) }}" class="text-sm text-gray-500 hover:underline px-2 py-1.5">Reset</a>
                @endif
            </form>
        </div>

        {{-- Summary cards --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded shadow p-4">
                <div class="text-xs uppercase tracking-wider text-gray-500">Total</div>
                <div class="text-2xl font-bold text-gray-700">{{ number_format($stats['total']) }}</div>
            </div>
            <div class="bg-white rounded shadow p-4">
                <div class="text-xs uppercase tracking-wider text-gray-500">Delivered</div>
                <div class="text-2xl font-bold text-green-600">{{ number_format($stats['delivered']) }}</div>
            </div>
            <div class="bg-white rounded shadow p-4">
                <div class="text-xs uppercase tracking-wider text-gray-500">Failed</div>
                <div class="text-2xl font-bold text-red-600">{{ number_format($stats['failed']) }}</div>
            </div>
            <div class="bg-white rounded shadow p-4">
                <div class="text-xs uppercase tracking-wider text-gray-500">Pending</div>
                <div class="text-2xl font-bold text-yellow-600">{{ number_format($stats['pending']) }}</div>
            </div>
            <div class="bg-white rounded shadow p-4">
                <div class="text-xs uppercase tracking-wider text-gray-500">Delivery Rate</div>
                <div class="text-2xl font-bold text-blue-700">{{ $stats['delivery_rate'] }}%</div>
            </div>
        </div>

        <table class="table-fixed w-full text-sm bg-white">
            <tr>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase w-40">Sent</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase w-32">To</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase w-28">From</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Message</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase w-32">Status</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase w-40">Delivered</th>
            </tr>
            @forelse ($rows as $row)
                <tr>
                    <td class="border px-4 py-3 whitespace-nowrap">{{ $row->sent_at }}</td>
                    <td class="border px-4 py-3">{{ $row->mobnum }}</td>
                    <td class="border px-4 py-3">{{ $row->originator }}</td>
                    <td class="border px-4 py-3 truncate" title="{{ $row->message }}">{{ $row->message }}</td>
                    <td class="border px-4 py-3">
                        <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold {{ $badge[$row->status_code] ?? 'bg-gray-100 text-gray-800' }}">
                            {{ $row->status_label }}
                        </span>
                        @if ($row->status_code === 'failed' && $row->sentstatustext)
                            <div class="text-xs text-red-500 mt-1">{{ $row->sentstatustext }}</div>
                        @endif
                    </td>
                    <td class="border px-4 py-3 whitespace-nowrap">{{ $row->delivered_at }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="border px-4 py-6 text-center text-gray-400">No messages yet.</td>
                </tr>
            @endforelse
        </table>

        <div class="flex mt-4">
            {{ $rows->links('vendor.pagination.simple-default') }}
        </div>
    </div>
@endsection
