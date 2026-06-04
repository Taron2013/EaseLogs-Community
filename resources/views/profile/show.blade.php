@extends('layouts.app')

@section('title', 'Profile — ' . config('easelogs.display_name'))

@section('content')
    <div class="profile-page">
        <h2 class="page-heading">Profile</h2>
        <p class="page-intro">Your account details for this EaseLogs installation.</p>

        <section class="form-section profile-card">
            <h2>Account</h2>
            <dl class="profile-account-details">
                <dt>Name</dt>
                <dd>{{ $user->name }}</dd>

                <dt>Email</dt>
                <dd>{{ $user->email }}</dd>
            </dl>

        <div class="actions">
            @unless ($easelogsDemo['blocks_account_changes'] ?? false)
                <a href="{{ route('profile.edit') }}" class="btn btn-primary">Edit profile</a>
                <a href="{{ route('profile.password.edit') }}" class="btn">Change password</a>
            @else
                <p class="field-hint demo-restriction-notice">{{ $easelogsDemo['message_account_changes'] }}</p>
            @endunless
        </div>
        </section>
    </div>
@endsection
