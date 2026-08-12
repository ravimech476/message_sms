@extends('_layouts.default')

@section('content')
    <div class="container mx-auto">
        <div class="mb-4">
            <h1 class="text-3xl font-semibold text-gray-700">Sent Messages</h1>
        </div>
        <table class="table table-fixed w-full text-sm bg-white">
            <tr>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">When</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Practice</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Provider</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Recipient</th>
                <th class="bg-gray-200 px-4 py-2 font-medium tracking-wider text-left text-xs uppercase">Status</th>
            </tr>
        @foreach ($messages as $row)
            <tr {!! $row->status !== 'sent' ? 'class="text-red-500"' : '' !!}>
                <td class="border px-4 py-3">{{ $row->created_at }}</td>
                <td class="border px-4 py-3">{{ $row->message->provider->domain->domain }}</td>
                <td class="border px-4 py-3">{{ $row->message->provider->provider['name'] }}</td>
                <td class="border px-4 py-3">{{ strpos($row->message->provider->domain->domain, 'footfall') !== false ? $row->message->message_data->to : App\Support\Str::mask($row->message->message_data->to, '*', -10, 8) }}</td>
                <td class="border px-4 py-3">{{ $row->status_note }}</td>
            </tr>
        @endforeach
        </table>
        <div class="flex">
            {{ $messages->links('vendor.pagination.simple-default') }}
        </div>
    </div>
@endsection
