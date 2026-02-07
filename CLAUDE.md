# PHP Todo App — Project Context

## Overview
A secure REST API backend (JSON-only) for a todo listing app with user management, JWT authentication, and reminder system. Built in vanilla PHP (no framework) with MySQL.

## Tech Stack
- **Language:** PHP 8.1+
- **Database:** MySQL 8.0+ (InnoDB, utf8mb4)
- **Auth:** JWT (firebase/php-jwt) — dual token strategy
- **Dependencies:** Composer (firebase/php-jwt, vlucas/phpdotenv, ramsey/uuid)

## Architecture
Vanilla PHP with custom router. MVC-like structure:
- `public/index.php` — single entry point, all requests routed here
- `src/Router.php` — regex-based router with middleware support
- `src/Controllers/` — handle HTTP requests, call services
- `src/Models/` — database access via PDO prepared statements
- `src/Services/` — business logic (JWT, auth)
- `src/Middleware/` — auth, CORS, rate limiting
- `src/Helpers/` — Response, Validator, Security utilities
- `src/Config/` — Database connection
- `migrations/` — SQL DDL scripts
- `cron/` — scheduled tasks

## Database Tables
- `users` — id, uuid, username, email, password_hash (Argon2id), failed_login_attempts, locked_until
- `remember_tokens` — id, user_id (FK), token_jti, token_hash (SHA-256), family_id, expires_at, revoked
- `todos` — id, user_id (FK), title, description, todo_date, is_completed, reminder_time, reminder_triggered
- `rate_limits` — id, identifier, endpoint, hits, window_start
- `password_resets` — id, user_id (FK), token_hash (SHA-256), expires_at, used

## JWT Strategy
- **Access Token:** 15 min expiry, HS256, stateless, sent via `Authorization: Bearer` header
- **Remember-Me Token:** 30 day expiry, HS256 (separate secret), stored hashed in DB, supports revocation
- **Token Rotation:** On refresh, old remember-me token is revoked, new pair issued (same family_id)
- **Theft Detection:** Reuse of revoked token → entire token family invalidated
- **JTI:** Every token gets a unique UUID as `jti` claim

## Security Requirements
- Argon2id password hashing (`PASSWORD_ARGON2ID`)
- Account lockout after 5 failed login attempts (15 min)
- Password complexity: min 8 chars, uppercase, lowercase, number, special char
- PDO prepared statements everywhere (no string concatenation in SQL)
- Input validation on all endpoints
- Rate limiting: login 5/min, register 3/min, password reset 3/min, API 60/min per user
- Password reset: secure random token (SHA-256 hashed in DB), 1-hour expiry, single-use, revokes all sessions
- Security headers: X-Content-Type-Options, X-Frame-Options, Cache-Control, HSTS
- CORS with configurable allowed origins
- Separate JWT secrets for access and remember-me tokens

## API Endpoints
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | /api/auth/register | No | Register user |
| POST | /api/auth/login | No | Login, get tokens |
| POST | /api/auth/refresh | Remember-me | Refresh access token |
| POST | /api/auth/logout | Yes | Revoke tokens |
| POST | /api/auth/forgot-password | No | Request password reset token |
| POST | /api/auth/reset-password | No | Reset password with token |
| GET | /api/user/profile | Yes | Get profile |
| PUT | /api/user/profile | Yes | Update profile |
| PUT | /api/user/password | Yes | Change password |
| GET | /api/todos?date=YYYY-MM-DD | Yes | List todos |
| POST | /api/todos | Yes | Create todo |
| GET | /api/todos/{id} | Yes | Get todo |
| PUT | /api/todos/{id} | Yes | Update todo |
| DELETE | /api/todos/{id} | Yes | Delete todo |
| PATCH | /api/todos/{id}/complete | Yes | Toggle complete |
| GET | /api/todos/reminders/triggered | Yes | Get triggered reminders |
| PATCH | /api/todos/{id}/reminder-ack | Yes | Acknowledge reminder |

## Reminder System
- Users set `reminder_time` (datetime) on a todo
- Cron job runs every minute: flags todos where `reminder_time <= NOW()` and `reminder_triggered = 0`
- Frontend polls `/api/todos/reminders/triggered` to display notifications
- User acknowledges via `/api/todos/{id}/reminder-ack`

## Coding Conventions
- PSR-4 autoloading (namespace `App\`)
- All SQL via PDO prepared statements — never concatenate user input into queries
- JSON responses via `Response::json()` helper (includes security headers)
- Input validation via `Validator` class before any processing
- Models return arrays, not objects
- Controllers are thin — delegate to services for business logic
- Environment variables via `.env` (never commit `.env`, use `.env.example`)

## Git Workflow
- Granular commits (one feature per commit)
- Each commit should be a working increment
- Commit messages describe what was added/changed
- Never commit `.env`, `vendor/`, or IDE files
