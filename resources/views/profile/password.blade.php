@extends('layouts.app')

@section('title', 'Change password — ' . config('easelogs.display_name'))

@section('content')
    <div class="profile-page">
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

            @if ($easelogsDemo['blocks_account_changes'] ?? false)
                <p class="field-hint demo-restriction-notice">{{ $easelogsDemo['message_account_changes'] }}</p>
                <div class="actions">
                    <a href="{{ route('profile.show') }}" class="btn">Back to profile</a>
                </div>
            @else
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
            @endif
        </section>
    </div>
@endsection
