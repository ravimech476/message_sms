@extends('_layouts/default')

@section('content')
        <form method="post" action="{{ route('login') }}" class="w-full md:w-1/2 lg:w-1/3 mx-auto px-4 md:px-0 my-6">
            @csrf
            <div class="mb-3">
                <label for="email" class="font-semibold">Username</label>
                <input type="text" name="email" value="{{ old('email') }}" class="form-input w-full{{ $errors->has('email') ? ' is-invalid' : '' }}" required autofocus>
                @if ($errors->has('email'))
                    <div class="text-red-500 text-sm mt-1">{{ $errors->first('email') }}</div>
                @endif
            </div>
            <div class="mb-3">
                <label for="password" class="font-semibold">Password</label>
                <input type="password" class="form-input w-full" name="password" required>
            </div>
            <div class="mb-3">
                <label>
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} class="font-semibold"> {{ __('Remember Me') }}
                </label>
            </div>
            <div>
                <button type="submit" class="inline-flex items-center bg-green-500 text-white rounded px-3 py-2 hover:bg-gray-700 hover:text-gray-100 cursor-pointer">Login</button>
            </div>
        </form>
@endsection
