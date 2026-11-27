# Bearer Token (JWT) Demo App

A Laravel API that issues JSON Web Tokens (bearer tokens) for a front-end app, with
role-protected routes. It also keeps the laravel/ui session login pages.

Stack: Laravel 13 (PHP 8.4.1+, tested on 8.5), `php-open-source-saver/jwt-auth` (maintained
fork of `tymon/jwt-auth`), laravel/ui 4, Vite + Bootstrap 5 + Vue 3.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
npm install && npm run build
```

## API (`/api`, throttled 60 requests/minute)

| Method | URI | Auth | Notes |
|---|---|---|---|
| POST | `auth/register` | – | name, username, email, role (`user`/`admin`), password |
| POST | `auth/login` | – | username, password → `access_token`, `token_type`, `expires_in`; the token carries a `role` claim |
| POST | `auth/me` | bearer | the signed-in user |
| POST | `auth/refresh` | bearer | new token; the old one is blacklisted |
| POST | `auth/logout` | bearer | invalidates the token |
| POST | `auth/requestreset` | – | email → emails `<APP_FE_URL>/reset/<token>` (valid 60 min) |
| POST | `auth/reset` | – | token, password |
| GET | `user` | bearer | the signed-in user |
| POST | `iamadmin` / `iamuser` | bearer | role `admin` / `user` only (403 otherwise) |

Send the token as `Authorization: Bearer <token>`.

## Tests

```bash
php vendor/bin/phpunit
```
