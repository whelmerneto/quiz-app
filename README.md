# Real ou 3D?

A quiz that asks one question ten times: was this image captured by a camera, or
built in 3D? A visitor leaves a name and an email, plays a round drawn at random
from the image library, and reaches a result screen with their score and
whichever prize tier it unlocked. An operator manages the images, their
classification and the prize ladder from an admin panel.

Laravel 13 on PHP 8.4, Postgres, Filament 5 for the admin, and a Blade + Alpine
+ Tailwind v4 public surface built on Apple's Liquid Glass material.

---

## Running it locally

Docker provides PHP, nginx and Postgres. Node runs on the host.

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
npm install && npm run build
```

The site is at **http://localhost:8089**, the admin at **/admin**. Postgres is
on host port **5434** so it does not collide with a sibling project on 5433.

Create an operator, then sign in:

```bash
docker compose exec app php artisan quiz:create-admin
```

A round needs at least `QUIZ_QUESTIONS_PER_ROUND` active images (ten by
default), so upload that many through the panel before playing.

### The host PHP is not used

The host runs PHP 8.2 and this project requires 8.4. **Every `composer` and
`php artisan` command runs inside the container** — `docker compose exec app …`
— or it fails the platform check. Node and npm are the exception: Vite runs on
the host.

---

## Commands

```bash
docker compose exec app composer lint     # pint --test, phpstan, rector --dry-run
docker compose exec app php artisan test  # 156 tests, ~10s
docker compose exec app composer test:browser
npm run dev                               # Vite, on the host
```

`php artisan test` deliberately excludes the browser suite. Those tests drive a
real Chromium, which only exists in the `test` build stage:

```bash
APP_BUILD_TARGET=test docker compose build app
APP_BUILD_TARGET=test docker compose up -d app
docker compose exec app composer test:browser
```

Going back is the same commands without the variable. A bare `docker compose
build` always produces `base`.

Folding the browser into the default image took it from 209 MB to 1.4 GB, paid
by everyone to support one thirty-second test, so it is opt-in.

### Contrast harness

```bash
node docs/contrast-probe.mjs http://localhost:8089/
```

Glass surfaces are translucent, so a colour's ratio against a token says nothing
about the shipped page. The probe photographs each page twice, once with the
text turned transparent, and compares every glyph against the ground actually
composited underneath it. It exits non-zero on any run below its WCAG threshold.

---

## How a round works

The round is materialised on the server when it starts: one `quiz_attempts` row
and N `quiz_attempt_answers` rows holding the drawn images and their positions.
The browser receives positions and file URLs, never a label.

Answers are accepted one at a time, only at the first unanswered position. A
foreign session is refused, a finished round is refused, a replayed position
returns its stored verdict without changing the score, and a position past the
current one is refused so a client cannot sample labels by posting ahead.

At completion the score resolves to the highest active prize at or below it, and
that decision is frozen on the attempt. Editing a prize afterwards does not
rewrite a round that already won it.

The result URL is shareable, but only the browser that played the round sees the
per-question review. That review is the answer key to every image in the round,
which with a small library is most of the library.

---

## Deploying

See [docs/deploy.md](docs/deploy.md). Laravel Cloud runs the application; Neon
provides Postgres and Cloudflare R2 holds the images, both on free plans wired
by hand.

## Specification

`src/docs/specs/real-ou-3d-spec.md` is the contract this was built against. It
is deliberately untracked.
