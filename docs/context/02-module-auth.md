# Construmax2 ERP — 02: Auth Module

> **Business purpose:** User authentication, registration, password management, 2FA, email verification, and session management.  
> **Context file covers:** Auth, Fortify, Jetstream, Sanctum, Profile.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Config | `config/auth.php` | Session-based web guard, Eloquent user provider |
| Config | `config/sanctum.php` | SPA mode, no token expiration |
| Config | `config/jetstream.php` | Inertia stack, Sanctum guard, only profile photos enabled |
| Config | `config/fortify.php` | Standard Fortify config |
| Actions | `app/Actions/Fortify/CreateNewUser.php` | Registration |
| Actions | `app/Actions/Fortify/ResetUserPassword.php` | Forgot-password reset |
| Actions | `app/Actions/Fortify/UpdateUserPassword.php` | Logged-in password change |
| Actions | `app/Actions/Fortify/UpdateUserProfileInformation.php` | Name/email/photo update |
| Actions | `app/Actions/Jetstream/DeleteUser.php` | Account deletion |
| Model | `app/Models/User.php` | `HasApiTokens`, `HasRoles`, `HasProfilePhoto`, `Notifiable`, `TwoFactorAuthenticatable` |
| Vue pages | `resources/js/Pages/Auth/` | Login, Register, ForgotPassword, ResetPassword, ConfirmPassword, TwoFactorChallenge, VerifyEmail |
| Vue pages | `resources/js/Pages/Profile/` | Profile management (info, password, 2FA, sessions, delete) |
| Vue pages | `resources/js/Pages/API/` | API token management (Sanctum) |
| Routes | `routes/web.php` | `/login` redirect, `/dashboard` |

---

## Authentication flow

1. User visits `/` → redirected to `/login`
2. Login form (`Auth/Login.vue`) — custom-branded with "Acceso Corporativo" title, 480px card
3. On success → redirect to `/dashboard`
4. Session maintained via Sanctum SPA cookies (`web` guard)
5. 2FA available per-user (TOTP via Jetstream)
6. Email verification available (registration → verify email flow)
7. Password confirmation required for sensitive actions (3-hour timeout)

---

## Jetstream features enabled/disabled

| Feature | Status |
|---------|--------|
| Profile photos | ✅ Enabled (public disk) |
| API tokens | ✅ Framework available, UI exists (`Pages/API/`) |
| Teams | ❌ Disabled |
| Account deletion | ❌ Disabled (page exists but may not be surfaced) |
| Terms of service | ❌ Disabled |

---

## Key behaviors

- **Profile photo:** Updated via `UpdateUserProfileInformationAction`. Stored in `profile_photo_path` on `users` table.
- **Email changes:** If user implements `MustVerifyEmail`, changing email nullifies `email_verified_at` and sends a new verification email.
- **Password resets:** Token expires in 60 minutes. Throttled at 1 request per 60 seconds.
- **Session management:** Users can log out other browser sessions (requires password confirmation).
- **2FA:** Users can enable, view QR code, view recovery codes, and disable 2FA from Profile → Two-Factor Authentication.
- **API tokens:** Full CRUD UI at `/user/api-tokens`. Not actively used by app features — framework scaffolding only.

---

## Dependencies on other modules

- **Users module** (`03`): `User` model is shared; profile page is auth-scoped but uses the same model
- **Roles & Permissions** (`03`): `User` model uses Spatie `HasRoles` trait; all authorization gates depend on it

---

## Known limitations / cautions

- **No registration UI:** The `Register.vue` page exists (Jetstream default) but may not be linked — check the welcome page `canRegister` prop
- **Teams disabled:** Spatie teams feature is off; all roles/permissions are global
- **API tokens not validated:** The API token UI exists but `/api/user` is the only API route. No production API usage pattern established
- **2FA enrollment:** Managed entirely by Jetstream — no custom enrollment flow
- **Account deletion:** `Jetstream/DeleteUser.php` action exists but the feature may be disabled in Jetstream config
