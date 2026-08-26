# ebioro-payment-woocommerce

WooCommerce payment gateway for Ebioro hosted checkout (public repository). At checkout the shopper is redirected to the Ebioro-hosted payment page; settlement is confirmed asynchronously by a signed webhook that flips the WooCommerce order to paid. It is a thin client of the Ebioro merchant public API — the same HMAC signing, hosted checkout, and webhook contract used by the PrestaShop (`ebioro-payment-prestashop`) and Odoo (`ebioro-odoo`) plugins. A gotcha found here usually applies to those two as well.

## Architecture
- PHP plugin, GPL-3.0+. Entry `ebioro-payment-woocommerce.php`; gateway class `class-wc-gateway-ebioro.php`; API client `includes/class-ebioro-api-handler.php`; block-checkout integration in `includes/blocks` + `resources/js`.
- Auth: every API call signed with HMAC-SHA256 over `path + timestamp + method + body`, sent as `X-Digest-Key` / `X-Digest-Signature` / `X-Digest-Timestamp`. Webhooks verified with the same secret over the exact raw body.
- Test mode toggles the API base URL and the key pair (`test_api_key`/`test_api_secret` vs live). Live and test secrets are separate settings.

## Dev commands
- `npm install && npm run build` — builds the block-checkout JS with `@wordpress/scripts` (this repo is npm-based; there is no yarn lockfile).
- No PHP build step. Package as a zip of the plugin folder for releases.
- Enable **Debug log** in the gateway settings when testing — it is off by default and failures leave no trail without it.

## Gotchas
- **Redirect with `shortUrl`, never `hostedUrl`.** `hostedUrl` carries an auth token in the query string that would then sit in the shopper's browser history and referrer headers. The gateway prefers `shortUrl` and only falls back to `hostedUrl`; do not "simplify" that back. Same rule in the PrestaShop and Odoo plugins.
- **`order_key` must not be sent in payment metadata or logged** — it is a capability secret for the order-pay/received pages. Only `order_id` is needed to resolve the order on the webhook.
- **A stale API secret surfaces as a blank failure.** The API returns 401, `process_payment()` returns `result: fail` with no message, and the shopper sees a generic "Something went wrong". Check the stored gateway settings (test vs live pair) before debugging code.
- **Amount comparison asymmetry is deliberate.** `get_total()` is in major units and is multiplied by 100; `payment.amount.value` on a GET is already in minor units and must not be. See the comment above the comparison.
- **Never log `$args` of an API request** — it contains the digest headers. Log only the body, and only when `WP_DEBUG` is on.
- Detect WooCommerce by `class_exists('WC_Payment_Gateway')`, not by plugin path — the path check missed network-activated multisite installs.
- The webhook is the only thing that changes order state. The return URL is unauthenticated and must never mark an order paid.

<!-- BEGIN ebioro-non-negotiables v2 — master: Ebioro-UAB/documentation -->
## Ebioro non-negotiables

- **GitHub text hygiene — the KU corridor country is never named.** In any
  GitHub-visible text (commit messages, PR titles/bodies, reviews, issues,
  branch names, release notes, code/spec comments) write `KU` — never the
  country's name, demonym, or capital. The ISO code `CU` as functional data
  (string literals, catalog entries, `=== 'CU'` checks) and full-country
  datasets (countries.json, i18n locales) are fine. End-user UI strings may
  carry the real name; keep those literals minimal. Scrub old text on touch.
- **Never merge or push to `main`, never tag a production release, never deploy.**
  Open the PR and stop. Merges and deploys are human-only — no exception for
  "the review passed" or "it's just a patch bump". Never delete `main`,
  `master`, or `development`.
- **Never commit `.env` or any file containing a secret.** `.gitignore` covers
  `.env*` with `.env.example` as the only tracked variant. A secret that lands
  in git is leaked even after the file is removed — rotate it.
- **No AI attribution in git or GitHub text.** No `Co-Authored-By:` lines, no
  "Generated with …" footers in commits, PR bodies, or issues.
- **Error handling: `neverthrow` Result types. Never `try/catch`.**
- **TypeORM migrations: snake_case column identifiers only.** Quoting
  `"customerId"` preserves camelCase; TypeORM then queries `customer_id` and
  crashes at runtime. `build` does not catch it. This has cost two fix
  migrations already.
- **Follow the existing flow in code, not design docs or mockups.** Find the
  nearest equivalent already implemented and match it. Design notes are
  proposals.
- **Money paths**: integer stroops/cents, never floats. Idempotency keys on
  anything that moves money. Stellar sequence numbers fetched fresh. Never log
  or expose a signing key or an API secret.
- **Ebioro never holds keys for customer funds.** Before creating any key or
  account, ask whose funds it will hold. If a customer's, it cannot be a key
  Ebioro can use alone.
- **User-facing copy hides blockchain jargon.** "Payment reference", not
  "transaction hash". "Settled", not "confirmed on ledger N". "Network fee",
  not "XLM base fee". No signing-vendor names. It should read like a bank app,
  not a block explorer.
- **No regulatory claims** in user-facing text — not "licensed", "registered",
  "authorised", "MiCAR-compliant", or any variant. Route legal-sounding copy
  through Ebioro before merging.
- **Soft-delete only** (`deletedAt`). Never hard-delete.
- **Never skip pre-commit hooks** (`--no-verify`). Flag new dependencies in the
  PR description.
- **Never paste production data, customer PII, credentials, or KYC/AML content
  into an AI tool.** Anonymised or synthetic only.
<!-- END ebioro-non-negotiables v2 -->
