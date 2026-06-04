@if ($easelogsDemo['enabled'] && $easelogsDemo['demo_login']['allows_one_click_login'])
    <form method="POST" action="{{ route('login.demo') }}" class="demo-one-click-login">
        @csrf
        <button type="submit" class="btn btn-secondary">Login as Demo User</button>
    </form>
@endif
