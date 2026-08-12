@extends('_layouts.default')

@section('content')
    <div class="container mx-auto">
        <div class="w-1/2 mx-auto flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold text-gray-700">Edit: <span class="font-light">{{ $practice->practice_name }}</span></h1>
        </div>
        <practice-edit :practice="{{ $practice }}" provider="{{ $provider }}" provider-uri="{{ $providerUri }}" back-uri="{{ route('practices') }}"></practice-edit>
    </div>
@endsection
