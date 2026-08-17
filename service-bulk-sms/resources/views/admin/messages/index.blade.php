@extends('_layouts.default')

@php
    // Delivery badge derived from status + delivered_at (no enum change needed).
    $badgeFor = function ($row) {
        if ($row->delivered_at) {
            return ['Delivered', 'bg-green-100 text-green-800'];
        }
        if ($row->status === 'failed') {
            return ['Failed', 'bg-red-100 text-red-800'];
        }
        if ($row->status === 'sent') {
            return ['Sent', 'bg-cyan-100 text-cyan-800'];
        }
        return ['Pending', 'bg-yellow-100 text-yellow-800'];
    };
@endphp

@section('content')
    <div class="container mx-auto">
        <div class="mb-4">
            <h1 class="text-3xl font-semibold text-gray-700">Sent Messages</h1>
        </div>
        <table class="table-fixed w-full text-sm bg-white">
            <tr>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Sent</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Practice</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Provider</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Recipient</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase w-32">Status</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Delivered</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Message ID</th>
            </tr>
        @foreach ($messages as $row)
            @php [$label, $classes] = $badgeFor($row); @endphp
            <tr>
                <td class="border px-4 py-3 whitespace-nowrap">{{ $row->created_at }}</td>
                <td class="border px-4 py-3">{{ $row->message->provider->domain->domain }}</td>
                <td class="border px-4 py-3">{{ $row->message->provider->provider['name'] }}</td>
                <td class="border px-4 py-3">{{ strpos($row->message->provider->domain->domain, 'footfall') !== false ? $row->message->message_data->to : App\Support\Str::mask($row->message->message_data->to, '*', -10, 8) }}</td>
                <td class="border px-4 py-3">
                    <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold {{ $classes }}">{{ $label }}</span>
                    @if ($row->status === 'failed' && $row->status_note)
                        <div class="text-xs text-red-500 mt-1">{{ $row->status_note }}</div>
                    @endif
                </td>
                <td class="border px-4 py-3 whitespace-nowrap">{{ $row->delivered_at ? $row->delivered_at->format('Y-m-d H:i:s') : '—' }}</td>
                <td class="border px-4 py-3 font-mono text-xs break-all text-gray-600">{{ $row->supplier_message_id ?: '—' }}</td>
            </tr>
        @endforeach
        </table>
        <div class="flex">
            {{ $messages->links('vendor.pagination.simple-default') }}
        </div>
    </div>
@endsection
