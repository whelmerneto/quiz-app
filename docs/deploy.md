# Deploying Real ou 3D?

The application runs on Laravel Cloud. Its database and its image storage do
not: Postgres comes from Neon and the images from a Cloudflare R2 bucket, both
on free plans and both configured by hand. Laravel Cloud sells an attached
database and attached object storage that would remove most of this document,
and if the budget ever allows them, attaching either one changes environment
variables and nothing else.

This runbook assumes the repository is already connected to a Laravel Cloud
environment and deploying on push.

---

## 1. Why the pieces are split

**Postgres on Neon, not Supabase.** A Supabase free project pauses after seven
days with no database activity and has to be restored from the dashboard by
hand. A quiz shown at occasional events sits idle for weeks, so the first
visitor after a quiet stretch would meet an error nobody is watching for. Neon
suspends too, after five minutes, but resumes on the next connection with no
intervention. Both give 0.5 GB, far past what four small tables need.

**Images on R2 held directly, not through Laravel Cloud.** Laravel Cloud's
object storage is R2 resold at $0.02/GB-month plus $0.10/GB of transfer. Held
directly, R2's free plan covers 10 GB, 1M writes and 10M reads a month, and
charges nothing for egress at any volume. Egress is what decides it: every round
serves ten images.

**The application container's filesystem is ephemeral.** Anything written to it
disappears on the next deploy, which is why uploads must reach a bucket.

---

## 2. Provision Neon

1. Create a project at [neon.tech](https://neon.tech). Pick the region closest
   to the Laravel Cloud environment — every query pays the round trip.
2. Copy the **pooled** connection string. Neon offers a direct endpoint and a
   pooled one; use pooled. Laravel Cloud runs persistent PHP-FPM workers, and
   the pooler is what keeps a burst of them from exhausting the connection
   limit.
3. Note the host, database, user and password from that string.

Nothing else on Neon needs configuring. The schema arrives with `migrate`.

## 3. Provision R2

1. In the Cloudflare dashboard, **R2 → Create bucket**. Any name; the runbook
   assumes `quiz-app`.
2. **Settings → Public access.** Enable it, either through the `r2.dev`
   development subdomain or a custom domain. The quiz images are public content
   and signed URLs would defeat CDN caching. Copy the resulting public hostname:
   this becomes `QUIZ_STORAGE_URL`.
3. **R2 → API → Manage API tokens → Create token**, scoped to *Object Read &
   Write* on this bucket only. Copy the Access Key ID, the Secret Access Key and
   the S3 API endpoint.

The S3 API endpoint and the public hostname are different hosts. The first is
where the application writes, the second is where browsers read.

### Image previews in the admin form need a CORS policy

The public site loads images through `<img src>`, which no CORS rule governs. The
admin upload field is different: FilePond builds its own preview by calling
`fetch()` on the stored file, and a `pub-*.r2.dev` host answers without an
`Access-Control-Allow-Origin` header. The blocked fetch leaves the field in a
failed state and the form stops submitting — the symptom is a
`TypeError: Failed to fetch` from `file-upload.js` in the console.

Both upload fields therefore set `previewable(false)`, which removes the fetch.
Operators still browse by the table thumbnail, which is an `<img>`.

To get the in-field preview back, give the bucket a CORS policy under
**R2 → bucket → Settings → CORS policy**, listing every origin the panel is
served from:

```json
[
  {
    "AllowedOrigins": ["https://<production host>", "http://localhost:8089"],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedHeaders": ["*"],
    "MaxAgeSeconds": 3600
  }
]
```

Then swap `previewable(false)` back to `imagePreviewHeight('180')` in
`QuizImageForm` and `PrizeForm`.

---

## 4. Environment variables

Set these on the Laravel Cloud environment. Values in `<>` come from the two
steps above.

```dotenv
APP_NAME="Real ou 3D?"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<your-environment>.laravel.cloud
APP_KEY=<php artisan key:generate --show>

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=America/Sao_Paulo

DB_CONNECTION=pgsql
DB_HOST=<neon pooled host>
DB_PORT=5432
DB_DATABASE=<neon database>
DB_USERNAME=<neon user>
DB_PASSWORD=<neon password>
DB_SSLMODE=require

# Required on the pooled endpoint. Neon's `-pooler` host runs transaction-mode
# pooling, which hands each transaction whichever backend is free, so a
# server-side prepared statement created on one is missing on the next. PHP 8.4
# with libpq 17+ negotiates around it; older runtimes do not, and the failure is
# intermittent because it needs concurrent workers to appear at all. It surfaces
# as `SQLSTATE[25P02] current transaction is aborted` on the statement AFTER the
# one that actually failed, which is why the message never names the cause.
DB_DISABLE_PREPARES=true

# Neither belongs on the database here. Both default to `database`, which puts a
# query on every request including visitors who never start a round — on a plan
# measured in compute-hours that spends the budget keeping an idle database
# awake. The quiz keeps one uuid in the session, which fits a signed cookie, and
# the application has no shared cache worth protecting.
SESSION_DRIVER=cookie
CACHE_STORE=file
QUEUE_CONNECTION=sync

QUIZ_DISK=quiz_storage
QUIZ_QUESTIONS_PER_ROUND=10

QUIZ_STORAGE_KEY=<r2 access key id>
QUIZ_STORAGE_SECRET=<r2 secret access key>
QUIZ_STORAGE_REGION=auto
QUIZ_STORAGE_BUCKET=quiz-app
QUIZ_STORAGE_ENDPOINT=<r2 s3 api endpoint>
QUIZ_STORAGE_URL=<r2 public hostname>
QUIZ_STORAGE_PATH_STYLE=false
```

These are deliberately not called `AWS_*`. Laravel's stock bucket names its
variables after the SDK, which reads as "you need an AWS account" when none is
involved: `driver => 's3'` names a **protocol**, not Amazon. R2, MinIO,
Backblaze B2 and Spaces all speak it, and `QUIZ_STORAGE_ENDPOINT` is what points
the client at Cloudflare rather than at Amazon.

`QUIZ_STORAGE_REGION` is `auto` on R2, not a region name. `QUIZ_STORAGE_URL` is
not optional: without it `Storage::url()` returns a path the browser cannot
resolve and every image in the quiz renders broken.

### PHP version

Laravel Cloud offers 8.2 through 8.5 and defaults new environments to 8.5.
`composer.json` requires `^8.4` and `rector.php` targets `PHP_84`. Either select
8.4 in the environment's General Settings, or validate the suite on 8.5 before
leaving the default in place.

---

## 5. Deploy commands

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
```

**The order is not free.** `filament:upgrade` runs from `post-autoload-dump` and
clears the config, route and view caches. So `composer install` comes first and
the cache commands after it, and no `composer dump-autoload` may run once the
caches are warm — it would silently empty them.

## 6. Create the first operator

Once, after the first successful deploy, from the environment's command runner:

```bash
php artisan quiz:create-admin
```

Run it **without** `--password`. A password passed on the command line is
visible to `ps` and is written into the Laravel Cloud deploy log. The command
prompts for it, upserts on the email, and never deletes, so it is safe to re-run
to reset a password.

---

## 7. Verify the deployment

Run all four. The first is the one that catches a missing database, and it is
the failure the landing page hides — `SESSION_DRIVER=cookie` means the site
still renders without Postgres, and only a route that queries gives it away.

```bash
# 404, not 500. A 500 here means the database is unreachable.
curl -s -o /dev/null -w '%{http_code}\n' \
  https://<host>/quiz/00000000-0000-4000-8000-000000000000

# 200, the health route.
curl -s -o /dev/null -w '%{http_code}\n' https://<host>/up

# 200, the operator sign-in page.
curl -s -o /dev/null -w '%{http_code}\n' https://<host>/admin/login
```

### Check storage before uploading anything

```bash
php artisan quiz:check-storage
```

Run this **before** an operator registers a single image. It prints the disk in
use and the R2 settings, then writes a probe object, fetches it back over the
public URL exactly as a browser would, and deletes it. Exit code 0 means an
upload will survive a deploy and be readable.

This check exists because the failure it catches is silent. Writing to the
`public` disk on an ephemeral container **succeeds** — the file lands, the row is
created, the panel reports success — and the image only disappears on the next
deploy. The symptom is a 404 in a visitor's browser, hours later and far from
the action that caused it. Configuration alone would not catch it either: a
bucket can be named correctly and still be private or unreachable.

If it reports `Disk in use: public`, the storage variables did not reach the
running application. Confirm what it actually sees:

```bash
php artisan tinker --execute="echo json_encode(['disk'=>config('quiz.disk'),'bucket'=>config('filesystems.disks.quiz_storage.bucket'),'endpoint'=>config('filesystems.disks.quiz_storage.endpoint'),'url'=>config('filesystems.disks.quiz_storage.url')]);"
```

`null` values mean the variable is absent rather than wrong. **Adding a variable
is not enough on its own** — `config:cache` freezes every value at build time,
so anything added after the last deploy is invisible until the next one.

Then, in a browser: sign in at `/admin`, upload one PNG, and open the image's
URL directly. It must serve from the R2 public hostname.

---

## 8. Before launch — blocking

Neither is built. Both block going public, not deploying.

- [ ] **Marketing consent.** The start screen collects a name and an email with
      no consent field. Until one exists, those addresses may be used to deliver
      a prize and nothing else. Open question 10 in the spec.
- [ ] **Bucket visibility confirmed.** Upload an image through `/admin` and
      fetch its URL from a browser with no session. It must return the file.

## 9. Before launch — worth doing

- [ ] **Ten active images minimum.** A round draws
      `QUIZ_QUESTIONS_PER_ROUND` images and refuses to start below that. With
      exactly ten, every round is the whole library in a different order; more
      images buy variety. Lowering the setting instead makes the 10-correct
      prize tier unreachable, so the two move together.
- [ ] **At least one prize tier**, or every round ends with no prize.

---

## 10. Cost

| Piece | Plan | Limit | Cost |
|---|---|---|---|
| Laravel Cloud | Starter | $5/month in usage credits | $5/month |
| Neon Postgres | Free | 0.5 GB, 100 compute-hours/month | $0 |
| Cloudflare R2 | Free | 10 GB, 1M writes, 10M reads/month, no egress charge | $0 |

The free limits sit far outside this application's shape: four small tables and
a library of a few dozen PNGs. Revisit only if the image library reaches several
hundred files, or if traffic becomes steady enough to keep Neon's compute awake
for most of the month.
