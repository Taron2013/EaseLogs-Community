{{--
    OAuth / social login extension point (not enabled in core yet).
    Future providers: Google, Microsoft, Facebook (Meta), GitHub.
    Implement App\Contracts\Auth\SocialLoginProvider and register in config('easelogs.auth.social_providers').
    See docs/AUTH_EXTENSIONS.md.
--}}
@php($socialProviders = config('easelogs.auth.social_providers', []))
@if (! empty($socialProviders))
    <div class="form-section" style="margin-bottom:1rem;" aria-label="Social sign-in">
        {{-- Social provider buttons will render here when extensions are installed. --}}
    </div>
@endif
