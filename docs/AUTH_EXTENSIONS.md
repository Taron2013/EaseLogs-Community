# EaseLogs authentication extensions

EaseLogs core ships:

- **First-run setup** (`/setup`) when no users exist — creates the single owner account
- **Email/password login** (`/login`) after setup is complete
- **Session authentication** for artwork and CSV routes

No default password is created by seeders or installers.

## Planned OAuth / social providers

Future shared-core extensions may add:

| Provider | Key | Typical use |
|----------|-----|-------------|
| Google | `google` | General sign-in |
| Microsoft | `microsoft` | Work/school accounts |
| Facebook (Meta) | `facebook` | Social sign-in |
| GitHub | `github` | Developer / self-hosted installs |

## Extension point

1. Implement `App\Contracts\Auth\SocialLoginProvider` (redirect URL + provider key).
2. Register provider classes in `config/easelogs.php` under `auth.social_providers`.
3. Render buttons from `resources/views/auth/_social_login_extension.blade.php` on setup and login pages.
4. Add OAuth callback routes outside the `setup.available` middleware group.

First-run setup should remain available only when `users` is empty. Social login must not create additional local users through `/setup`; link OAuth identities to the existing owner account or a dedicated auth layer in a future edition.
