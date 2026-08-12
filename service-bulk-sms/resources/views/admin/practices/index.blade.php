@extends('_layouts.default')

@section('content')
    <div class="container mx-auto">
        <practices-table
            practices-uri="{{ route('api.practices') }}"
            providers-uri="{{ route('api.providers') }}"
            bulk-update-uri="{{ route('practices.update') }}"
            :ccg-list="{{ $ccgList }}"
            :stp-list="{{ $stpList }}"
            :provider-list="{{ $providerList  }}">
        </practices-table>
    </div>
@endsection
