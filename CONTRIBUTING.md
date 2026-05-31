# Contributing to Flint

Thanks for your interest in contributing. Flint is intentionally small — please read this before opening a PR so we stay on the same page.

---

## Philosophy

Before contributing, understand what Flint is and isn't:

- **No magic.** No facades, static proxies, or hidden object graphs. If you can't follow the call stack in a debugger, it doesn't belong in core.
- **No bloat.** Every addition to core has to justify its existence. Auth, throttling, caching, mail — these belong in packages, not here.
- **Fail loudly.** Misconfigurations throw descriptive exceptions. No silent fallbacks, no swallowed errors.
- **PHP 8.1+ only.** Use typed properties, constructor promotion, match expressions, enums, and readonly where they add clarity. Don't write PHP 7 style code.

---

## What Belongs in Core

Core (`core/`) should only contain things every Flint application needs regardless of what it does:

- Routing and middleware pipeline
- Request / Response
- Dependency injection container
- Active Record ORM and schema builder
- Migrations
- Validation
- Queue system
- CLI kernel

If your contribution adds an optional integration (auth, caching, email, third-party APIs) — it should be a separate Composer package, not a PR to this repo.

---

## Reporting Bugs

Open a GitHub issue and include:

1. PHP version and OS
2. The minimal code to reproduce the problem
3. What you expected to happen
4. What actually happened (include the full exception and stack trace)

If you can reproduce it in a single file without a database, do that — it makes fixing it much faster.

---

## Suggesting Features

Open a GitHub issue with the `feature` label before writing any code. Describe:

- What problem it solves
- Why it belongs in core rather than a package
- Rough idea of the API

This avoids you spending time on something that won't be merged.

---

## Submitting a Pull Request

1. Fork the repo and create a branch from `master`.
2. Keep PRs small and focused — one thing per PR.
3. Follow the code style of the surrounding files (strict types, no comments explaining *what* the code does, one-line docblocks on public methods only).
4. Do not add dependencies to `composer.json`. The only allowed runtime dependency is `vlucas/phpdotenv`.
5. Run `php -l` on every file you touched before submitting:
   ```bash
   find core app -name "*.php" | xargs php -l
   ```
6. Write a clear PR description explaining *why* the change is needed, not just what it does.

PRs that add features without a prior issue discussion, introduce dependencies, or break the no-magic rule will be closed.

---

## Code Style

- `declare(strict_types=1)` at the top of every file
- Classes have a single responsibility — if it's doing two things, split it
- No inline comments explaining what the code does — name things well instead
- One-line docblocks on public methods: `/** Return the validated data array. */`
- No `else` after a `return` or `throw`
- Prefer `match` over `switch`
- Prefer constructor property promotion over manual assignment

---

## License

By contributing you agree that your code will be released under the [MIT License](LICENSE).
