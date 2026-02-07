# PHP Todo API

A secure REST API backend for a todo listing application with user management, JWT authentication, and a reminder system. Built from scratch in vanilla PHP (no framework) with MySQL.

## Features

- **User Management** — Registration, login, profile updates, password changes, password reset
- **Dual JWT Authentication** — Short-lived access tokens (15 min) + long-lived remember-me tokens (30 days) with automatic rotation
- **Token Theft Detection** — Remember-me tokens use a family-based rotation strategy; reuse of a revoked token invalidates the entire token family
- **Todo Management** — Full CRUD with per-date organization and ownership enforcement
- **Reminder System** — Set a reminder time on any todo; a cron job flags triggered reminders, and the frontend polls to display notifications
- **High Security** — Argon2id password hashing, account lockout, rate limiting, input validation, prepared statements, security headers, CORS

## Tech Stack

| Component | Technology |
|-----------|------------|
| Language | PHP 8.1+ |
| Database | MySQL 8.0+ (InnoDB, utf8mb4) |
| Auth | JWT via [firebase/php-jwt](https://github.com/firebase/php-jwt) |
| Env Config | [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) |
| UUIDs | [ramsey/uuid](https://github.com/ramsey/uuid) |

## Project Structure

```
├── public/index.php              # Single entry point, route definitions
├── src/
│   ├── Router.php                # Custom regex-based router
│   ├── Config/Database.php       # PDO singleton connection
│   ├── Controllers/              # AuthController, UserController, TodoController, ReminderController
│   ├── Models/                   # User, Todo, RememberToken, PasswordReset (PDO prepared statements)
│   ├── Services/                 # JWTService, AuthService (business logic)
│   ├── Middleware/               # AuthMiddleware, CorsMiddleware, RateLimitMiddleware
│   └── Helpers/                  # Response, Validator, Security utilities
├── migrations/                   # SQL DDL scripts (5 tables)
├── cron/check_reminders.php      # Cron job to flag triggered reminders
├── .env.example                  # Environment variable template
└── composer.json                 # Dependencies and PSR-4 autoloading
```

## Getting Started

### Prerequisites

- PHP 8.1 or higher (with `pdo_mysql` and `argon2id` support)
- MySQL 8.0 or higher
- Composer

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/php-todo-api.git
   cd php-todo-api
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   ```
   Edit `.env` with your database credentials and generate two separate random strings for `JWT_ACCESS_SECRET` and `JWT_REMEMBER_SECRET`.

4. **Create the database and run migrations**
   ```bash
   mysql -u root -p -e "CREATE DATABASE todo_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p todo_app < migrations/001_create_users_table.sql
   mysql -u root -p todo_app < migrations/002_create_remember_tokens_table.sql
   mysql -u root -p todo_app < migrations/003_create_todos_table.sql
   mysql -u root -p todo_app < migrations/004_create_rate_limits_table.sql
   mysql -u root -p todo_app < migrations/005_create_password_resets_table.sql
   ```

5. **Start the development server**
   ```bash
   php -S localhost:8000 -t public
   ```

6. **Set up the reminder cron job** (optional)
   ```bash
   * * * * * php /path/to/project/cron/check_reminders.php
   ```

## API Endpoints

### Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/auth/register` | No | Register a new user |
| `POST` | `/api/auth/login` | No | Login and receive tokens |
| `POST` | `/api/auth/refresh` | Remember-me token | Refresh access token (rotates remember-me token) |
| `POST` | `/api/auth/logout` | Bearer token | Revoke remember-me token(s) |
| `POST` | `/api/auth/forgot-password` | No | Request a password reset token |
| `POST` | `/api/auth/reset-password` | No | Reset password using token |

### User Profile

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/user/profile` | Bearer token | Get current user profile |
| `PUT` | `/api/user/profile` | Bearer token | Update username and email |
| `PUT` | `/api/user/password` | Bearer token | Change password |

### Todos

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/todos?date=YYYY-MM-DD` | Bearer token | List todos (optional date filter) |
| `POST` | `/api/todos` | Bearer token | Create a todo |
| `GET` | `/api/todos/{id}` | Bearer token | Get a single todo |
| `PUT` | `/api/todos/{id}` | Bearer token | Update a todo |
| `DELETE` | `/api/todos/{id}` | Bearer token | Delete a todo |
| `PATCH` | `/api/todos/{id}/complete` | Bearer token | Toggle todo completion |

### Reminders

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/todos/reminders/triggered` | Bearer token | Get triggered reminders |
| `PATCH` | `/api/todos/{id}/reminder-ack` | Bearer token | Acknowledge a reminder |

## Usage Examples

### Register
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username": "john", "email": "john@example.com", "password": "Secret@123"}'
```

### Login with remember-me
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "john@example.com", "password": "Secret@123", "remember_me": true}'
```

### Create a todo with reminder
```bash
curl -X POST http://localhost:8000/api/todos \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -d '{"title": "Buy groceries", "description": "Milk, eggs, bread", "todo_date": "2025-01-15", "reminder_time": "2025-01-15 09:00:00"}'
```

### Forgot password
```bash
curl -X POST http://localhost:8000/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email": "john@example.com"}'
```

### Reset password
```bash
curl -X POST http://localhost:8000/api/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{"token": "THE_RESET_TOKEN_FROM_ABOVE", "new_password": "NewSecret@456"}'
```

### Refresh token
```bash
curl -X POST http://localhost:8000/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"remember_me_token": "YOUR_REMEMBER_ME_TOKEN"}'
```

## Security

- **Passwords** — Hashed with Argon2id
- **Account Lockout** — 5 failed login attempts triggers a 15-minute lockout
- **Password Policy** — Minimum 8 characters, requires uppercase, lowercase, number, and special character
- **SQL Injection** — All queries use PDO prepared statements
- **Password Reset** — Cryptographically secure tokens (SHA-256 hashed in DB), 1-hour expiry, single-use, revokes all sessions on reset
- **Rate Limiting** — Login: 5/min, Register: 3/min, Password reset: 3/min, API: 60/min per user
- **Security Headers** — `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Cache-Control`, `HSTS`
- **JWT Secrets** — Separate secrets for access and remember-me tokens
- **Token Rotation** — Remember-me tokens are rotated on each refresh; reuse of a revoked token invalidates the entire token family

## Database Schema

Five tables: `users`, `remember_tokens`, `todos`, `rate_limits`, `password_resets`. See `migrations/` for full DDL.

## License

MIT
