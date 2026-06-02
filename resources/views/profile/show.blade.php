@extends('layouts.app')

@section('title', 'Profile — ' . config('easelogs.display_name'))

@section('content')
    <h2 class="page-heading">Profile</h2>
    <p class="page-intro">Your account details for this EaseLogs installation.</p>

    <section class="form-section profile-card">
        <h2>Account</h2>
        <dl class="detail-grid">
            <dt>Name</dt>
            <dd>{{ $user->name }}</dd>

            <dt>Email</dt>
            <dd>{{ $user->email }}</dd>
        </dl>

        <div class="actions">
            <a href="{{ route('profile.edit') }}" class="btn btn-primary">Edit profile</a>
            <a href="{{ route('profile.password.edit') }}" class="btn">Change password</a>
        </div>
    </section>
@endsection
