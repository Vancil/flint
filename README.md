# Flint

A lightweight, fast PHP framework with Laravel-style ergonomics and a fraction of the overhead. Expressive routing, Active Record ORM, queue jobs, schema builder, and a CLI — all with zero magic.

**PHP >= 8.1 required.**

---

## Philosophy

Flint is built around three ideas:

**Slim.** Core ships with only what every application needs — routing, ORM, validation, queues, and a CLI. Nothing more. Auth, caching, mail, and third-party integrations are available as installable packages so you only carry what you actually use.

**Fast.** The entire framework boots in a single `Application::boot()` call. No deferred service providers, no lazy container chains, no runtime class generation. What you see is what runs.

**Auditable.** No facades, no static proxies, no magic. Every dependency is injected directly and every call can be followed in a debugger. The full framework source is small enough to read in an afternoon.

### Packages

Features that don't belong in core are available as official Vancil packages:

| Package | Description |
|---------|-------------|
| `vancil/flint-auth` | JWT and API key authentication middleware |
| `vancil/flint-cache` | Cache layer with file, Redis, and APCu drivers |
| `vancil/flint-mail` | Mailable classes with SMTP and log drivers |
| `vancil/flint-rate-limit` | Rate limiting middleware with cache-backed storage |

Install any package with Composer and register it in your application — no configuration files to publish, no service providers to remember.

---

## Getting Started

```bash
composer install
cp .env.example .env
# edit .env with your database credentials
php flint migrate
php -S localhost:8000 public/index.php
```

Visit `http://localhost:8000` — you should see `{"framework":"Flint","status":"ok"}`.

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
├── routes/
│   └── web.php
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

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flint
DB_USERNAME=root
DB_PASSWORD=

QUEUE_DRIVER=database
```

Config files live in `config/`. Access values anywhere using dot notation:

```php
config('app.name');        // "Flint"
config('database.driver'); // "mysql"
config('app.debug');       // true
```

---

## Routing

Define routes in `routes/web.php`. The `$router` variable is available automatically.

```php
$router->get('/users',         [UserController::class, 'index']);
$router->post('/users',        [UserController::class, 'store']);
$router->put('/users/{id}',    [UserController::class, 'update']);
$router->delete('/users/{id}', [UserController::class, 'destroy']);

// Closure routes
$router->get('/', fn() => Response::json(['status' => 'ok']));
```

### Route Groups

Groups apply a URI prefix and/or middleware to every route inside them:

```php
$router->group(['prefix' => '/api', 'middleware' => ['auth', 'json']], function ($router) {
    $router->get('/profile', [ProfileController::class, 'show']);
    $router->put('/profile', [ProfileController::class, 'update']);
});
```

### Route Parameters

Named segments are injected into controller methods by matching parameter name:

```php
// Route: /users/{id}
public function show(int $id): Response { ... }

// Route: /posts/{slug}
public function show(string $slug): Response { ... }
```

---

## Controllers

Generate a controller with the CLI:

```bash
php flint make:controller User
```

Controllers are plain classes in `app/Controllers/`. Dependencies declared in the constructor are resolved automatically from the container.

```php
namespace App\Controllers;

use Flint\Request;
use Flint\Response;
use App\Models\User;

class UserController
{
    public function index(): Response
    {
        return Response::json(User::all());
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        return Response::json(User::create($data), 201);
    }

    public function show(int $id): Response
    {
        return Response::json(User::findOrFail($id)->toArray());
    }

    public function destroy(int $id): Response
    {
        User::findOrFail($id)->delete();
        return Response::noContent();
    }
}
```

---

## Request

The `Flint\Request` object is injected into any controller method that declares it as a parameter.

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
    'name'                  => 'required|string|max:255',
    'email'                 => 'required|email|unique:users,email',
    'password'              => 'required|min:8|confirmed',
    'role'                  => 'required|in:admin,editor,user',
    'score'                 => 'nullable|numeric|min:0',
]);
```

On failure a `422` JSON response is returned automatically:

```json
{
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

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
Response::json($data, 200);          // application/json
Response::html('<h1>Hello</h1>');    // text/html
Response::text('plain text');        // text/plain
Response::redirect('/login', 302);   // redirect
Response::noContent();               // 204

// Chaining
return Response::json(['id' => 1])
    ->withHeader('X-Custom', 'value')
    ->withStatus(201);
```

---

## Models & ORM

Generate a model:

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
    protected array $hidden = [];
    protected array $casts = ['published' => 'bool', 'score' => 'float'];
}
```

### Querying

```php
Post::all();                        // array of all records
Post::find(1);                      // ?Post
Post::findOrFail(1);                // Post or 404 exception
Post::first();                      // ?Post

Post::where('published', true)->get();
Post::where('score', '>', 4.5)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();
```

### Creating & Updating

```php
$post = Post::create(['title' => 'Hello', 'body' => '...']);

$post->update(['title' => 'Updated']);

$post->title = 'Also works';
$post->save();

$post->delete();
```

### Relationships

Define relationships as methods on your model. Import each related model class at the top of the file so PHP can resolve `ClassName::class`:

```php
use App\Models\Post;
use App\Models\Profile;

class User extends Model
{
    public function posts(): array
    {
        return $this->hasMany(Post::class);
    }

    public function profile(): ?Profile
    {
        return $this->hasOne(Profile::class);
    }
}

class Post extends Model
{
    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }
}
```

Usage:

```php
$user = User::findOrFail(1);

$user->posts();           // array of post arrays
$user->profile();         // Profile instance or null
$user->profile()->bio;    // access properties directly

$post = Post::findOrFail(1);
$post->user();            // User instance or null
$post->user()->name;
```

Foreign keys are inferred automatically from the class name (`User` → `user_id`, `BlogPost` → `blog_post_id`). Override them explicitly if needed:

```php
$this->hasMany(Post::class, 'author_id');
$this->belongsTo(User::class, 'author_id');
```

### Serialisation

```php
$post->toArray();   // respects $hidden and $casts
$post->toJson();
```

Timestamps (`created_at`, `updated_at`) are managed automatically. Opt out with:

```php
protected bool $timestamps = false;
```

---

## Migrations

Generate a migration:

```bash
php flint make:migration create_posts_table
```

This creates a timestamped file in `database/migrations/`. Edit it using the Schema builder:

```php
use Flint\Schema;
use Flint\Blueprint;

return new class {
    public function up(PDO $pdo): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body')->nullable();
            $table->boolean('published')->default(false);
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(PDO $pdo): void
    {
        Schema::dropIfExists('posts');
    }
};
```

Run and roll back:

```bash
php flint migrate
php flint migrate:rollback
```

### Schema Builder Reference

**Column types:**

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
| `timestamp('col')` | `TIMESTAMP` |
| `json('col')` | `JSON` |
| `timestamps()` | Adds `created_at` + `updated_at` |
| `softDeletes()` | Adds `deleted_at` |

**Modifiers** (chainable on any column):

```php
->nullable()          // allow NULL
->default($value)     // set a DEFAULT value
->unique()            // add a UNIQUE index
->unsigned()          // UNSIGNED (MySQL only)
```

**Modifying an existing table:**

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('avatar_url')->nullable();
});
```

---

## Middleware

Reference middleware by alias in route definitions:

```php
$router->group(['middleware' => ['cors']], function ($router) {
    // ...
});
```

**Built-in middleware:**

| Alias | Behaviour |
|-------|-----------|
| `cors` | Adds CORS headers; handles OPTIONS preflight with 204 |

Auth, throttling, and other concerns are handled by installable packages. To register a custom middleware alias, call `$router->setMiddlewareAliases()` in `Application::registerMiddlewareAliases()` or point directly to a class name in your route definition.

---

## Queue

Generate a job:

```bash
php flint make:job SendWelcomeEmail
```

```php
namespace App\Jobs;

use Flint\Queue\Job;

class SendWelcomeEmail extends Job
{
    public int $tries = 3;
    public int $retryAfter = 60;

    public function __construct(
        private readonly int $userId,
        private readonly string $email,
    ) {}

    public function handle(): void
    {
        // send the email...
    }

    public function failed(\Throwable $e): void
    {
        // called after all retries are exhausted
    }
}
```

### Dispatching

```php
use Flint\Queue\Queue;

Queue::dispatch(new SendWelcomeEmail($user['id'], $user['email']));

// Delayed dispatch
Queue::later(300, new SendWelcomeEmail($user['id'], $user['email']));
```

### Running the Worker

```bash
php flint queue:work
php flint queue:work --queue=emails
php flint queue:work --queue=default --sleep=3
```

The worker handles retries, failure logging, and graceful shutdown on `SIGTERM`/`SIGINT`.

**Queue drivers** are set in `.env`:

```env
QUEUE_DRIVER=database   # default — stores jobs in MySQL/SQLite
QUEUE_DRIVER=redis      # requires the redis PHP extension
```

---

## CLI Reference

```bash
php flint key:generate                 # generate APP_SECRET and write to .env
php flint make:controller <Name>       # app/Controllers/NameController.php
php flint make:model <Name>            # app/Models/Name.php
php flint make:job <Name>              # app/Jobs/NameJob.php
php flint make:migration <name>        # database/migrations/<timestamp>_name.php
php flint migrate                      # run pending migrations
php flint migrate:rollback             # roll back last batch
php flint queue:work                   # start queue worker
php flint queue:work --queue=<name>    # worker on a named queue
```

---

## Error Handling

| Exception | HTTP Response |
|-----------|---------------|
| `ValidationException` | `422` with `{ "errors": { ... } }` |
| `ModelNotFoundException` | `404` with error message |
| Any other `Throwable` | `500` — full trace if `APP_DEBUG=true`, generic message if false |

---