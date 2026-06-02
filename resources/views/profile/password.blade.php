@extends('layouts.app')

@section('title', 'Change password — ' . config('easelogs.display_name'))

@section('content')
    <h2 class="page-heading">Change password</h2>
    <p class="page-intro">Enter your current password, then choose a new one.</p>

    <section class="form-section profile-card">
        @if ($errors->any())
            <div class="errors" role="alert" aria-live="polite">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('profile.password.update') }}">
            @csrf
            @method('PATCH')

            <div class="field">
                <label for="current_password">Current password</label>
                <input type="password" name="current_password" id="current_password" required autocomplete="current-password">
            </div>

            <div class="field">
                <label for="password">New password</label>
                <input type="password" name="password" id="password" required autocomplete="new-password">
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Update password</button>
                <a href="{{ route('profile.show') }}" class="btn">Cancel</a>
            </div>
        </form>
    </section>
@endsection
