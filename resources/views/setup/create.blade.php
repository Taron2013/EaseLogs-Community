@extends('layouts.guest')

@section('title', 'First-time setup — ' . config('easelogs.display_name'))

@section('content')
    <h2 class="page-heading">Create your account</h2>
    <p class="page-intro">
        EaseLogs has no users yet. Create the owner account for this install. You will be signed in automatically.
    </p>

    @include('auth._social_login_extension')

    <div class="form-section">
        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('setup.store') }}">
            @csrf

            <div class="field">
                <label for="name">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="username">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required autocomplete="new-password">
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn">Create account and continue</button>
        </form>
    </div>
@endsection
