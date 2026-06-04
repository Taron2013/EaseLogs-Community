@if ($easelogsDemo['enabled'] && $easelogsDemo['demo_login']['show_login_hint'])
    <div class="demo-login-hint" role="note">
        <p><strong>Demo account</strong></p>
        <p class="demo-login-hint-credentials">
            Email: <code>{{ $easelogsDemo['demo_login']['email'] }}</code><br>
            Password: <code>{{ $easelogsDemo['demo_login']['password'] }}</code>
        </p>
    </div>
@endif
