# Safaricom M-PESA (Daraja) Integration — UNILIS

This document is the operating manual for the short-course M-PESA payment
integration. It covers architecture, configuration, the sandbox workflow, local
testing with a public callback URL, database changes, reconciliation, security,
testing, and the production checklist.

The implementation **extends the existing UNILIS codebase**. It does not add a
framework, replace the LMS, or reinvent any module that already exists. All
Daraja-specific code lives in one service file; the Short Courses module talks
to a small Payments layer, never to Safaricom directly.

---

## 1. Architecture

| Concern | File | Notes |
| --- | --- | --- |
| Daraja service (config, OAuth, STK Push, B2B, token cache) | `learn/includes/mpesa.php` | All `learn_mpesa_*` helpers. Only file that talks to Safaricom. |
| OAuth token acquisition + caching | `learn_mpesa_token()` | Cached per environment + consumer key until shortly before expiry. |
| Payment initiation for Short Courses | `learn/payment.php` | Price from DB, phone validation, CSRF, duplicate-prevention, status panel. |
| Student payment-status endpoint | `api/learn_payment_status.php` | JSON, learner-session auth; check status without new payment. |
| STK callback endpoint | `api/mpesa_callback.php` | Public HTTPS, no login cookies, idempotent, amount-verified, enrols on success. |
| B2B (department settlement) callback | `api/mpesa_b2b_callback.php` | Marks payouts paid/failed. |
| Admin config + reconciliation | `admin/mpesa_integration.php`, `admin/mpesa_stk_test.php`, `api/mpesa_reconcile.php` | Super admin only; sandbox STK test; reconcile stale transactions. |
| Schema | `migrations/short_course_mpesa.php`, `migrations/2026_10_04_mpesa_reconciliation.php` | Creates/extends `short_course_payments`. |
| Config wiring | `.env.docker.example`, `docker-compose.yml` | Secrets only ever come from env vars. |
| CI/CD | `.github/workflows/main.yml`, `.github/workflows/deploy.yml` | GitHub Secrets injected; values never echoed. |

### Payment flow

1. Learner opens a paid Short Course and is not enrolled -> Course page shows
   *Pay with M-Pesa* -> `learn/payment.php`.
2. UNILIS displays the **authoritative** price from `public_courses.price`
   (plus platform fee) and asks for the Safaricom number.
3. On submit (CSRF-checked, double-click guarded) UNILIS:
   - verifies no payment is already in flight for this learner + course,
   - creates a **PENDING** row in `short_course_payments`,
   - requests an OAuth token (cached),
   - issues the Daraja **STK Push**.
4. Daraja returns `CheckoutRequestID`, which is stored on the row.
5. The learner authorises on their phone. Safaricom POSTs the result to the
   configured callback URL.
6. `api/mpesa_callback.php` looks up the row by `checkout_request_id`, verifies
   the charged amount matches the stored amount, and:
   - **0 / success** -> marks `paid`, records the M-PESA receipt, enrols the
     learner (`external_enrollments`, idempotent), then optionally triggers the
     department B2B payout;
   - **1032** -> `cancelled`; **1037** -> `timeout`; other non-zero -> `failed`;
   - **amount mismatch on success** -> `reconciliation_required`, **no
     enrolment** (flagged for a human).
7. The learner can check status on the payment page / `api/learn_payment_status.php`
   without creating a new payment.
8. If a callback is late or missing, an administrator runs the reconciliation on
   the M-Pesa Integration page; nothing is auto-approved as paid.

---

## 2. Transaction states

`short_course_payments.status` (extended by the reconciliation migration):

| Status | Meaning |
| --- | --- |
| `pending` | STK request sent, awaiting the callback. |
| `paid` | Confirmed success; learner is enrolled. |
| `failed` | M-Pesa reported an unsuccessful transaction. |
| `cancelled` | The payer cancelled the prompt (code 1032). |
| `timeout` | The prompt timed out (code 1037) or checkout was never created. |
| `reconciliation_required` | Callback was inconsistent/missing; an operator must decide. |
| `payout_pending` / `payout_paid` / `payout_failed` | Department B2B settlement state for paid orders. |

Security rules that hold throughout:
- The browser never supplies the amount, course price, or a "paid" decision.
- The price is always re-read from the database.
- A successful callback must match the stored amount and must transition a
  `pending` row; callbacks are idempotent.
- An accepted HTTP request (**ResponseCode 0 / CheckoutRequestID**) does **not**
  equal a paid transaction - only a verified successful callback does.
---

## 3. Environment variables

Set these in the **server's untracked `.env`** (`.env` is gitignored) or via
GitHub Secrets for CI. Placeholders only in `.env.docker.example`.

| Variable | Purpose |
| --- | --- |
| `MPESA_ENVIRONMENT` | `sandbox` (default) or `production`. |
| `MPESA_CONSUMER_KEY` / `MPESA_CONSUMER_SECRET` | Daraja app credentials (Basic auth for OAuth). |
| `MPESA_SHORTCODE` | Business shortcode used as `PartyB`. |
| `MPESA_PASSKEY` | Lipa Na M-Pesa passkey used to build the STK password. |
| `MPESA_STK_RESULT_URL` (alias `MPESA_CALLBACK_URL` / `MPESA_RESULT_URL`) | Public HTTPS callback for STK results. |
| `MPESA_STK_TIMEOUT_URL` (optional) | Timeout URL; not sent for STK push. |
| `MPESA_B2B_*` | Separate B2B credentials for department settlements. |

> Credentials **must never** be hard-coded, logged, echoed in CI, stored in the
> database, or sent to the browser. The service layer reads them only via
> `getenv()` and uses them only to build HTTP headers.

---

## 4. Database migrations

Run while signed in as the super administrator (`admin@unilis.com`).

1. `migrations/short_course_mpesa.php` - creates `short_course_payments` and the
   department payout fields.
2. `migrations/2026_10_04_mpesa_reconciliation.php` - extends the `status` enum
   with `cancelled`, `timeout`, `reconciliation_required` and adds the
   `(status, created_at)` index.

Both are idempotent and safe to re-run. The M-Pesa Integration page has
buttons to run them.

Key columns in `short_course_payments`:
`id`, `learner_id`, `course_id`, `phone`, `amount`, `course_amount`,
`platform_fee`, `department_amount`, `merchant_request_id`,
`checkout_request_id` (unique), `mpesa_receipt`, `status`, `result_code`,
`result_description`, `raw_callback` (JSON), `payout_reference`,
`created_at`, `paid_at`, `updated_at`.

---

## 5. Local development

Run the app (root Compose stack, port `8080`) with your sandbox values set in
`.env`.

**Public callback URL with ngrok**
Daraja must reach your callback while you develop locally:

```bash
ngrok http 8080
# -> https://<random>.ngrok.io
```

Then set:

```
MPESA_ENVIRONMENT=sandbox
MPESA_STK_RESULT_URL=https://<random>.ngrok.io/api/mpesa_callback.php
MPESA_CONSUMER_KEY=<sandbox app consumer key>
MPESA_CONSUMER_SECRET=<sandbox app consumer secret>
MPESA_SHORTCODE=<sandbox shortcode>
MPESA_PASSKEY=<sandbox passkey>
```

`localhost` should **not** be used as the callback URL - Safaricom cannot reach
your machine.

**Sandbox application**
1. Sign in to the Safaricom **Daraja** developer portal and create a Sandbox
   app (client credentials grant).
2. Copy the app's consumer key and secret.
3. Register the public callback URL against your STK push.
4. Use a sandbox test number (e.g. `254712345678`) - the admin *Sandbox STK Push*
   tool sends a KSh 1 prompt to verify the wiring end-to-end.

---

## 6. Security review

- **No secrets in source** - consumer key/secret, shortcode, and passkey are
  read from environment variables only.
- **No secrets to the browser** - payment-status and admin responses expose only
  status, amount, receipt, and timestamps, never credentials or the OAuth token.
- **No secrets in logs** - log lines reference checkouts and error messages;
  the token cache and secrets are never logged.
- **Server-side only** - all Daraja calls and OAuth happen in PHP.
- **CSRF** - payment, STK-test, and reconcile POSTs check the session token.
- **Authentication** - the callback is public by design (Safaricom calls it) and
  is validated by a unique `checkout_request_id`; reconciliation is restricted to
  the super administrator.
- **Idempotent callbacks** - status updates use `WHERE status='pending'`, so a
  duplicate callback cannot double-enrol or double-pay.
- **Reconciliation never auto-approves** - stale/unknown outcomes are flagged,
  never marked paid.

---

## 7. Testing

`test/mpesa_daraja_test.php` runs the pure logic (phone normalisation, token
cache, callback code->status mapping, amount-match decision) without needing
credentials or the network:

```bash
php test/mpesa_daraja_test.php
```

CI (`deploy.yml`) runs `php -l` over the whole tree, so syntax is continuously
checked. Live sandbox tests (OAuth, STK push, a real callback) require the
sandbox app credentials and are performed from the admin M-Pesa Integration page
or by running the application against the sandbox.

---

## 8. Production checklist

1. `MPESA_ENVIRONMENT=production` is **not** set automatically - switch it only
   deliberately.
2. Production credentials come from secure server `.env` / GitHub Secrets, never
   from the repository.
3. Callbacks use **HTTPS** (`https://your-domain.example/api/mpesa_callback.php`).
4. Run both migrations once, as the super administrator.
5. Verify the sandbox flow first, then a small production payment.
6. Configure monitoring/alerts on `reconciliation_required` and
   `payout_failed` statuses.
7. Do **not** claim the integration is production-ready until all Safaricom
   production configuration (shortcode, passkey, result URLs, B2B credentials)
   is available and confirmed.

---

## 9. Troubleshooting

| Symptom | Likely cause / fix |
| --- | --- |
| "M-Pesa API credentials are not configured" | Consumer key/secret not in the server `.env` (or not passed to PHP/Compose). |
| STK config "Incomplete" on the admin page | `MPESA_SHORTCODE`, `MPESA_PASSKEY`, or result URL missing. Check the admin page; values are never printed. |
| Callback not arriving | Wrong/private result URL; use an HTTPS public URL (ngrok for local). |
| "amount mismatch … reconciliation required" | The wallet charged a different amount than stored; handle manually via the Reconciliation panel. |
| Payment stuck `pending` | No callback received; run Reconciliation to flag it, then verify against M-Pesa and resolve. |
| Token cache serving stale token | TTL has a 30 s early-refresh margin; clear via a fresh PHP process if you rotate credentials. |