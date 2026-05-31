@extends('layouts.guest')

@section('title', 'Sign in — ' . config('easelogs.display_name'))

@section('content')
    <h2 class="page-heading">Sign in</h2>
    <p class="page-intro">Sign in to manage your artwork inventory.</p>

    @include('auth._social_login_extension')

    <div class="form-section">
        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required autocomplete="current-password">
            </div>

            <div class="field field-inline" style="display:flex;align-items:center;gap:0.5rem;">
                <input type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                <label for="remember" style="margin:0;font-weight:400;">Remember me</label>
            </div>

            <button type="submit" class="btn">Sign in</button>
        </form>
    </div>
@endsection
