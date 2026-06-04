@extends('layouts.app')

@section('title', 'Edit profile — ' . config('easelogs.display_name'))

@section('content')
    <div class="profile-page">
        <h2 class="page-heading">Edit profile</h2>
        <p class="page-intro">Update your name and email address.</p>

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
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="field">
                        <label for="name">Name</label>
                        <input type="text" name="name" id="name" required autocomplete="name"
                               value="{{ old('name', $user->name) }}">
                    </div>

                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" required autocomplete="email"
                               value="{{ old('email', $user->email) }}">
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <a href="{{ route('profile.show') }}" class="btn">Cancel</a>
                    </div>
                </form>
            @endif
        </section>
    </div>
@endsection
