# Flint

<p align="center">
  <a href="https://github.com/Vancil/flint/actions/workflows/ci.yml"><img src="https://img.shields.io/github/actions/workflow/status/Vancil/flint/ci.yml?label=tests" alt="Tests"></a>
  <a href="https://packagist.org/packages/vancil/flint"><img src="https://img.shields.io/packagist/dt/vancil/flint" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/vancil/flint"><img src="https://img.shields.io/packagist/v/vancil/flint" alt="Latest Version on Packagist"></a>
  <a href="https://github.com/Vancil/flint/blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue" alt="License"></a>
</p>

A lightweight, fast PHP framework built for the web. Laravel-style ergonomics, session-based auth, a Blade-like template engine, and a fraction of the overhead. Expressive routing, Active Record ORM, queue jobs, schema builder, and a CLI — all with zero magic.

**PHP >= 8.1 required.**

---

## Philosophy

Flint is built around three ideas:

**Slim.** Core ships with only what every application needs — routing, ORM, validation, queues, sessions, templates, and a CLI. Nothing more. Auth scaffolding, mail, and third-party integrations are available as installable packages so you only carry what you actually use.

**Fast.** The entire framework boots in a single `Application::boot()` call. No deferred service providers, no lazy container chains, no runtime class generation. What you see is what runs.

**Auditable.** No facades, no static proxies, no magic. Every dependency is injected directly and every call can be followed in a debugger. The full framework source is small enough to read in an afternoon.

### Packages

Features that don't belong in core are available as official Vancil packages:

| Package | Description |
|---------|-------------|
| [`vancil/flint-auth`](https://github.com/Vancil/flint-auth) | Auth scaffold — Bootstrap, Vue, and React presets with login, register, password reset, and email verification |
| [`vancil/flint-mail`](https://github.com/Vancil/flint-mail) | Mail package — Mailable classes, async queueing, and six drivers (SMTP, Mailgun, Postmark, SES, SendGrid, Log) |

Install any package with Composer and register it in your application — no configuration files to publish, no service providers to remember.

---

## Getting Started

```bash
composer install
cp .env.example .env
# edit .env with your database credentials
php flint key:generate
php flint migrate
php -S localhost:8000 -t public
```

To scaffold auth pages and views immediately:

```bash
composer require vancil/flint-auth
php flint ui bootstrap --auth
php flint migrate
```

---

## Directory Structure

```
├── app/
│   ├── Controllers/
│   ├── Models/
│   └── Jobs/
├── config/
├── core/              # Framework internals (do not edit)
├── database/
│   └── migrations/
├── public/
│   └── index.php      # Single entry point
├── resources/
│   ├── views/         # Spark template files (.spark.php)
│   └── js/            # Frontend JS (Vue/React)
├── routes/
│   └── web.php
├── storage/
│   └── views/         # Compiled view cache (git-ignored)
└── flint              # CLI
```

---

## Configuration

Copy `.env.example` to `.env` and fill in your values:

```env
APP_NAME=Flint
APP_ENV=local
APP_DEBUG=true
APP_SECRET=change-me-in-production
APP_URL=http://localhost:8000

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flint
DB_USERNAME=root
DB_PASSWORD=

SESSION_COOKIE=flint_session
SESSION_LIFETIME=7200

MAIL_DRIVER=log
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="Flint"

QUEUE_DRIVER=database
```

Config files live in `config/`. Access values anywhere using dot notation:

```php
config('app.name');        // "Flint"
config('database.driver'); // "mysql"
config('app.debug');       // true
```

---

## Spark Template Engine

Flint ships with **Spark**, a Blade-like template engine. View files use the `.spark.php` extension and live in `resources/views/`.

### Rendering a View

```php
return Response::view('home', ['user' => $user]);
```

### Syntax

**Escaped output** (XSS-safe):
```
{{ $name }}
```

**Raw output:**
```
{!! $html !!}
```

**Control structures:**
```
@if ($user->isAdmin())
    <p>Admin panel</p>
@elseif ($user->isEditor())
    <p>Editor panel</p>
@else
    <p>Welcome</p>
@endif

@foreach ($posts as $post)
    <h2>{{ $post->title }}</h2>
@endforeach
```

**Auth directives:**
```
@auth
    <a href="/dashboard">Dashboard</a>
@endauth

@guest
    <a href="/login">Login</a>
@endguest
```

**Forms:**
```html
<form method="POST" action="/login">
    @csrf
    <input type="email" name="email" value="@old('email')">
    @error('email')
        <p class="error">{{ $message }}</p>
    @enderror
    <button type="submit">Login</button>
</form>
```

**Layouts:**

`resources/views/layouts/app.spark.php`:
```html
<!DOCTYPE html>
<html>
<head><title>{{ $title ?? 'Flint' }}</title></head>
<body>
    @yield('content')
</body>
</html>
```

`resources/views/home.spark.php`:
```
@extends('layouts.app')

@section('content')
    <h1>Hello, {{ $name }}!</h1>
@endsection
```

**Partials:**
```
@include('partials.nav')
@include('partials.alert', ['type' => 'success', 'message' => 'Saved'])
```

### CLI

```bash
php flint make:view auth.login     # resources/views/auth/login.spark.php
php flint make:layout app          # resources/views/layouts/app.spark.php
php flint view:clear               # delete compiled cache files
```

---

## Sessions

Sessions start automatically on every request (via global `SessionMiddleware`). Use the `session()` helper or inject `Flint\Session` directly:

```php
session()->set('key', 'value');
session()->get('key', 'default');
session()->has('key');
session()->forget('key');
session()->flash('status', 'Saved successfully!');
```

**Old input** is available in views after a failed form submission:

```php
old('email');        // PHP
@old('email')        // Spark directive
```

---

## CSRF Protection

All `POST`, `PUT`, `PATCH`, and `DELETE` requests are CSRF-verified automatically. Include the token in every form:

```html
<form method="POST" action="/profile">
    @csrf
    ...
</form>
```

For AJAX requests, send the token as a header:

```js
fetch('/api/data', {
    method: 'POST',
    headers: { 'X-CSRF-Token': await fetch('/api/csrf-token').then(r => r.json()).then(d => d.token) },
})
```

API routes under `/api/*` are excluded from CSRF verification by default. Customise via `config/csrf.php`:

```php
return [
    'except' => ['/api/*', '/webhooks/*'],
];
```

---

## Auth

The `Flint\Auth\Auth` class provides session-based authentication. It is available via constructor injection:

```php
use Flint\Auth\Auth;

class DashboardController
{
    public function __construct(private readonly Auth $auth) {}

    public function index(): Response
    {
        return Response::view('dashboard', ['user' => $this->auth->user()]);
    }
}
```

| Method | Description |
|--------|-------------|
| `$auth->user()` | Authenticated user object, or `null` |
| `$auth->check()` | `true` if logged in |
| `$auth->id()` | Authenticated user's ID |
| `$auth->guest()` | `true` if not logged in |
| `$auth->login($user, $remember)` | Log in; optionally set a 30-day remember-me cookie |
| `$auth->logout()` | End session and clear remember-me cookie |

Protect routes with the `auth` middleware alias:

```php
$router->group(['middleware' => ['auth']], function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});
```

To scaffold full auth pages (login, register, forgot password, email verification), install `vancil/flint-auth`.

---

## Routing

Define routes in `routes/web.php`. The `$router` variable is available automatically.

```php
$router->get('/users',         [UserController::class, 'index']);
$router->post('/users',        [UserController::class, 'store']);
$router->put('/users/{id}',    [UserController::class, 'update']);
$router->delete('/users/{id}', [UserController::class, 'destroy']);

// Closure routes
$router->get('/', fn() => Response::view('home'));
```

### Route Groups

```php
$router->group(['prefix' => '/api', 'middleware' => ['auth']], function ($router) {
    $router->get('/profile', [ProfileController::class, 'show']);
    $router->put('/profile', [ProfileController::class, 'update']);
});
```

### Route Parameters

```php
// Route: /users/{id}
public function show(int $id): Response { ... }

// Route: /posts/{slug}
public function show(string $slug): Response { ... }
```

---

## Controllers

```bash
php flint make:controller User
```

Controllers are plain classes in `app/Controllers/`. Dependencies are resolved via constructor injection.

```php
namespace App\Controllers;

use Flint\Request;
use Flint\Response;
use App\Models\User;

class UserController
{
    public function index(): Response
    {
        return Response::view('users.index', ['users' => User::all()]);
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        User::create($data);
        return Response::redirect('/users');
    }
}
```

---

## Request

```php
$request->method();                  // "GET", "POST", etc.
$request->uri();                     // "/users/42"
$request->input('name');             // single value from GET/POST/JSON
$request->input('role', 'user');     // with default
$request->all();                     // all input merged
$request->has('email');              // bool
$request->header('Authorization');   // raw header value
$request->bearerToken();             // strips "Bearer " prefix
$request->isJson();                  // checks Content-Type
$request->file('avatar');            // $_FILES entry
$request->ip();                      // client IP
```

### Validation

```php
$data = $request->validate([
    'name'     => 'required|string|max:255',
    'email'    => 'required|email',
    'password' => 'required|min:8',
    'role'     => 'required|in:admin,editor,user',
    'score'    => 'nullable|numeric|min:0',
]);
```

On failure in a web (non-JSON) request, the user is redirected back with errors and old input automatically. In a JSON request, a `422` response is returned.

**Available rules:**

| Rule | Description |
|------|-------------|
| `required` | Must be present and non-empty |
| `string` | Must be a string |
| `int` / `integer` | Must be an integer |
| `numeric` | Must be numeric |
| `email` | Must be a valid email |
| `min:N` | Length or value >= N |
| `max:N` | Length or value <= N |
| `in:a,b,c` | Must be one of the listed values |
| `nullable` | Allow null/missing (skips remaining rules) |
| `confirmed` | Must match `{field}_confirmation` |
| `unique:table,column` | Must not already exist in the database |

---

## Response

```php
Response::view('home', ['name' => 'Dan']); // render Spark view
Response::json($data, 200);                // application/json
Response::html('<h1>Hello</h1>');          // text/html
Response::text('plain text');              // text/plain
Response::redirect('/login', 302);         // redirect
Response::back();                          // redirect to previous URL
Response::noContent();                     // 204

// Web redirect helpers (chainable)
return Response::back()
    ->withErrors(['email' => ['Invalid credentials.']])
    ->withInput(['email' => $data['email']]);

// Header chaining
return Response::json(['id' => 1])
    ->withHeader('X-Custom', 'value')
    ->withStatus(201);
```

---

## Mail

Send email via the `Flint\Mail\Mailer` (injected via constructor):

```php
use Flint\Mail\Mailer;

class WelcomeController
{
    public function __construct(private readonly Mailer $mailer) {}

    public function store(Request $request): Response
    {
        // ... create user ...

        $this->mailer
            ->to($user->email, $user->name)
            ->subject('Welcome to ' . config('app.name'))
            ->view('emails.welcome', ['user' => $user])
            ->send();

        return Response::redirect('/dashboard');
    }
}
```

Set the driver in `.env`:

```env
MAIL_DRIVER=log    # writes to storage/logs/mail.log (default)
MAIL_DRIVER=smtp   # sends real email
```

---

## Models & ORM

```bash
php flint make:model Post
```

```php
namespace App\Models;

use Flint\Model;

class Post extends Model
{
    protected string $table = 'posts';
    protected array $fillable = ['title', 'body', 'user_id'];
    protected array $casts = ['published' => 'bool'];
}
```

### Querying

```php
Post::all();
Post::find(1);
Post::findOrFail(1);
Post::where('published', true)->get();
Post::where('score', '>', 4.5)->orderBy('created_at', 'DESC')->limit(10)->get();
Post::where('slug', $slug)->firstModel();
```

### Creating & Updating

```php
$post = Post::create(['title' => 'Hello', 'body' => '...']);
$post->update(['title' => 'Updated']);
$post->title = 'Also works';
$post->save();
$post->delete();
```

---

## Migrations

```bash
php flint make:migration create_posts_table
```

```php
use Flint\Schema;
use Flint\Blueprint;

return new class {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body')->nullable();
            $table->boolean('published')->default(false);
            $table->datetime('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

```bash
php flint migrate
php flint migrate:rollback
```

### Schema Builder Reference

| Method | MySQL type |
|--------|------------|
| `id()` | `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` |
| `string('col', 255)` | `VARCHAR(255)` |
| `text('col')` | `TEXT` |
| `longText('col')` | `LONGTEXT` |
| `integer('col')` | `INT` |
| `bigInteger('col')` | `BIGINT` |
| `boolean('col')` | `TINYINT(1)` |
| `float('col')` | `FLOAT` |
| `decimal('col', 8, 2)` | `DECIMAL(8,2)` |
| `datetime('col')` | `DATETIME` |
| `timestamp('col')` | `TIMESTAMP` |
| `json('col')` | `JSON` |
| `timestamps()` | Adds `created_at` + `updated_at` |
| `softDeletes()` | Adds `deleted_at` |

Modifiers: `->nullable()`, `->default($value)`, `->unique()`, `->unsigned()`

---

## Middleware

Built-in aliases registered automatically:

| Alias | Behaviour |
|-------|-----------|
| `cors` | CORS headers + OPTIONS preflight (204) |
| `session` | Start session (applied globally) |
| `csrf` | Verify CSRF token on state-changing requests (applied globally) |
| `auth` | Redirect to `/login` if unauthenticated |

Apply per-route:

```php
$router->group(['middleware' => ['auth']], function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});
```

---

## Cache

Configure the driver in `config/cache.php` or via `.env`:

```env
CACHE_DRIVER=file   # file (default), redis, array
CACHE_TTL=3600      # default TTL in seconds
CACHE_PREFIX=flint_
```

Use the `cache()` helper or inject `Flint\Cache\Cache` directly:

```php
cache()->put('key', $value);          // store with default TTL
cache()->put('key', $value, 60);      // store for 60 seconds
cache()->forever('key', $value);      // store indefinitely
cache()->get('key');                  // retrieve, null if missing
cache()->get('key', 'default');       // retrieve with fallback
cache()->has('key');                  // true/false
cache()->forget('key');               // remove one entry
cache()->flush();                     // remove all entries
cache()->pull('key');                 // get and immediately remove
```

`remember` retrieves a cached value or computes and stores it on a miss:

```php
$user = cache()->remember('user.' . $id, 300, function () use ($id) {
    return User::find($id);
});

$settings = cache()->rememberForever('settings', fn() => Settings::all());
```

### Drivers

| Driver | Description |
|--------|-------------|
| `file` | Serialized files in `storage/cache/` — no extra dependencies |
| `redis` | Uses the `php-redis` extension; shares Redis config with the queue |
| `array` | In-memory only, no persistence — useful for testing |

```bash
php flint cache:clear   # flush all cached values
```

---

## Queue

```bash
php flint make:job SendWelcomeEmail
```

```php
namespace App\Jobs;

use Flint\Queue\Job;

class SendWelcomeEmail extends Job
{
    public int $tries = 3;

    public function __construct(
        private readonly int    $userId,
        private readonly string $email,
    ) {}

    public function handle(): void { /* ... */ }
    public function failed(\Throwable $e): void { /* ... */ }
}
```

```php
use Flint\Queue\Queue;

Queue::dispatch(new SendWelcomeEmail($user->id, $user->email));
Queue::later(300, new SendWelcomeEmail($user->id, $user->email));
```

```bash
php flint queue:work
php flint queue:work --queue=emails
```

---

## CLI Reference

```bash
php flint key:generate                 # generate APP_SECRET and write to .env
php flint make:controller <Name>       # app/Controllers/NameController.php
php flint make:model <Name>            # app/Models/Name.php
php flint make:job <Name>              # app/Jobs/NameJob.php
php flint make:migration <name>        # database/migrations/<timestamp>_name.php
php flint make:view <name>             # resources/views/<name>.spark.php
php flint make:layout <name>           # resources/views/layouts/<name>.spark.php
php flint migrate                      # run pending migrations
php flint migrate:rollback             # roll back last batch
php flint queue:work                   # start queue worker
php flint cache:clear                  # flush all cached values
php flint view:clear                   # clear compiled Spark view cache
```

---

## Error Handling

| Situation | Response |
|-----------|----------|
| `ValidationException` in JSON request | `422` with `{ "errors": { ... } }` |
| `ValidationException` in web request | Redirect back with errors + old input |
| `ModelNotFoundException` | `404` with error message |
| CSRF mismatch | `419` plain text |
| Any other `Throwable` | `500` — full trace if `APP_DEBUG=true`, generic message if false |

---
