=== Sayed WP Conductor for Claude ===
Contributors: tahersayed
Tags: mcp, claude, ai, oauth, rest-api
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your WordPress site into a secure Model Context Protocol (MCP) server for Claude and any MCP-compatible AI client.

== Description ==

**Sayed WP Conductor** gives Claude (and any MCP-compatible AI client) full, secure, audited control of your WordPress site over the official Model Context Protocol — JSON-RPC 2.0 over Streamable HTTP, MCP spec **2025-06-18**.

Most "AI for WordPress" plugins either hand over an admin session to a third party or skip authentication entirely. Sayed WP Conductor is built around hard interlocks at every layer.

= Built on hard interlocks =

* **No anonymous access.** Every request needs a bearer token or an OAuth 2.1 access token.
* **Tokens hashed at rest** (SHA-256). The plaintext is shown to the admin once and is never recoverable.
* **Triple gate per call.** MCP scope (`read` / `write` / `admin`) + WordPress capability (`current_user_can`) + an optional "destructive ops" interlock for permanent deletes and sensitive option writes.
* **Audit log** for every successful and failed call: token id, IP, JSON-RPC method, tool, status code, note.
* **Transport hardening.** HTTPS enforced, Origin header allowlisted (DNS rebinding mitigation), request size capped, per-token rate limit, constant-time token comparison.
* **OAuth 2.1 with PKCE (S256 required)**, refresh token rotation, RFC 7009 revocation, RFC 8414 / RFC 9728 discovery, RFC 7591 Dynamic Client Registration **off by default** (admin opt-in).
* **Brute-force protection** with progressive lockout (5 / 10 / 20 fails → 5 min / 1 h / 24 h) and optional email alerts.
* **SSRF guard** on `media_upload` — rejects private, loopback and reserved IPs.
* **Self-protection.** The plugin refuses to deactivate itself via the `plugins_toggle` tool.

= 25 built-in tools, scoped read / write / admin =

**Read scope:** `site_info`, `site_health`, `posts_list`, `posts_get`, `posts_search`, `media_list`, `comments_list`, `terms_list`, `settings_get`

**Write scope:** `posts_create`, `posts_update`, `posts_delete`, `media_upload`, `media_delete`, `comments_moderate`, `terms_create`

**Admin scope:** `users_list`, `users_create`, `users_update`, `plugins_list`, `plugins_toggle`, `themes_list`, `themes_activate`, `settings_update`

You can register your own tools through the `cmcp_register_tools` filter — see the FAQ.

= Two authentication modes =

* **Personal access tokens (PATs)** — issue from the admin UI, bind to a least-privileged WP user, pin to an IP allowlist, set an expiry. Ideal for server-to-server use or your own scripts.
* **OAuth 2.1** — the full authorize / token / refresh / revoke flow with PKCE. Ideal for desktop / browser clients that need a one-click connect. Public clients are supported; confidential clients get a hashed secret.

= Endpoints =

* MCP RPC: `https://your-site/wp-json/claude-mcp/v1/rpc`
* Discovery: `https://your-site/wp-json/claude-mcp/v1/discovery`
* OAuth metadata: `https://your-site/.well-known/oauth-authorization-server`

= Privacy =

Sayed WP Conductor does not "phone home". No analytics, no remote logging, no third-party requests beyond what you explicitly ask a tool to do (e.g. `media_upload` fetches the URL you pass it). All data — tokens, audit log, OAuth clients/codes/tokens — lives in your own database.

== Installation ==

1. Upload the `sayed-wp-conductor-for-claude/` folder to `/wp-content/plugins/` (or install the zip via **Plugins → Add New → Upload**).
2. Activate **Sayed WP Conductor for Claude** in **Plugins**.
3. The setup wizard appears on first load — walk through it, or skip and use **Sayed WP Conductor** in the admin menu.
4. Go to **Sayed WP Conductor → Tokens** and issue a token. Bind it to a least-privileged WP user. Save the plaintext somewhere safe — it is shown once.
5. Tune **Sayed WP Conductor → Settings** — allowed origins, rate limit, destructive ops, enabled tools, OAuth options.
6. (Optional) **Sayed WP Conductor → OAuth Clients** — enable Dynamic Client Registration if you want clients like Claude Desktop to self-register.

Requirements: PHP 8.0+ and WordPress 6.2+.

== Frequently Asked Questions ==

= Is this safe to run on a production site? =

Yes — that is the entire point of the design. Every request is authenticated, scope-checked, capability-checked, rate-limited and audit-logged. Destructive operations (permanent deletes, edits to `siteurl` / `home` / `admin_email` / `default_role` / `permalink_structure`) require an additional admin opt-in. That said: keep WordPress and PHP up to date, use HTTPS, bind tokens to the lowest-privileged WP user that can do the job, and review the audit log.

= Does it work with Claude Desktop? =

Yes. Enable **Settings → OAuth → Dynamic Client Registration**, then add the server URL in Claude Desktop. PKCE with S256 is required.

= Does it work with curl / Python / Node clients? =

Yes. Issue a personal access token under **Tokens**, then send `Authorization: Bearer <token>` to `/wp-json/claude-mcp/v1/rpc`. See the plugin README on GitHub for a curl quickstart.

= Can I add my own tools? =

Yes. Hook the `cmcp_register_tools` filter and return objects extending `\CMCP\Tools\AbstractTool`. Define `name()`, `description()`, `input_schema()`, `required_scope()`, `required_capability()` and `execute()`. Enable the new tool's name under **Settings → Enabled tools**.

= My site is behind Cloudflare / a load balancer. Brute-force lockout locks out everyone. =

Enable **Settings → Trust reverse proxy**. With this on the plugin reads `CF-Connecting-IP`, `X-Forwarded-For` or `X-Real-IP` to identify the real client. Leave it OFF if WordPress is reachable directly, otherwise an attacker can spoof the header.

= How are tokens stored? =

Tokens are hashed with SHA-256 before insertion. The plaintext is shown to the admin exactly once when the token is created. The same applies to OAuth access and refresh tokens, and to OAuth client secrets.

= What happens on uninstall? =

Uninstall drops every plugin table (tokens, audit log, OAuth clients / codes / tokens), removes the `cmcp_settings` option, all `cmcp_*` transients, the bot user the wizard may have created, and the daily-cleanup cron.

= Is there a CLI? =

Yes, WP-CLI:

`wp cmcp token issue --label="ci" --scopes=read,write --user-id=2 --expires-days=90`

`wp cmcp token list`

`wp cmcp token revoke 5`

= How do I report a security issue? =

Please email security@hbs-it-gmbh.de rather than opening a public issue.

== Screenshots ==

1. Dashboard — KPIs, 7-day call volume, top tools and live security status at a glance.
2. Tokens — issue personal access tokens, bind to a user, scope, IP allowlist, expiry.
3. Settings — transport, audit, alerts, destructive ops interlock, per-tool enable toggles.
4. OAuth Clients — registered apps with active-token counts and one-click revoke.
5. Audit Log — every call, with token id, IP, JSON-RPC method, tool, status, note.

== Changelog ==

= 1.9.0 =
* **Rename:** Plugin renamed to **Sayed WP Conductor for Claude**, slug `sayed-wp-conductor-for-claude`. The previous name ("Sayed WP Conductor — Secure MCP Control" / slug `mcp-for-claude`) was flagged by the WordPress.org review team — "Sayed WP Conductor" is too generic, and starting the slug with `mcp-for-claude` implies false affiliation with Anthropic. New name follows the directory's "distinctive prefix … for Trademark" pattern. **Text domain changed** to `sayed-wp-conductor-for-claude` to match. The plugin was not yet on WordPress.org, so existing installs do not need to migrate translations.
* **Branding:** Removed all references to "HBS IT GmbH" from the plugin (admin pages, readme, author headers, descriptions). The plugin is now a personal project by Taher Sayed. The `hbsitgmbh` contributor was removed from `readme.txt`.
* **Internal:** The `wp-commander-bot` system username and the `X-Commander-Signature` webhook header keep their names — they're protocol/contract identifiers (existing receivers and stored audit rows depend on them), not user-facing branding.

= 1.8.2 =
* **Security (CRITICAL):** `/oauth/revoke` endpoint now authenticates the calling client per RFC 7009. The previous implementation accepted any token value, looked it up by hash, and revoked it — with `permission_callback => __return_true` and NO client authentication. An attacker who observed a token in logs/transit could revoke it remotely, denying the legitimate user service. The endpoint now requires HTTP Basic or POST client credentials (the same path the token endpoint uses), and verifies that the token row's `client_id` matches the authenticated client before revoking. RFC 7009 §2.2 200-response semantics preserved for unknown / mismatched tokens to avoid leaking existence. Per-IP rate limit added (20 attempts/min) to prevent the endpoint being used as a revoke-spam oracle.
* **Compliance:** Removed user-facing attribution from the OAuth consent + error pages — the WordPress.org review team flagged the "powered by Taher Sayed" footer as forbidden under Guideline 10 (no user-facing credits without explicit opt-in). The consent title was also changed from a product-branded string to a neutral "Authorize access". Admin-area attribution (Dashboard, Settings) is unchanged — that's permitted.
* **Compliance:** Inline `<script>` and `<style>` blocks moved out of admin views and the OAuth consent HTML. Token + Settings page scripts now ship as `assets/js/tokens-page.js` and `assets/js/settings-page.js`, registered via `wp_enqueue_script()` and configured via `wp_add_inline_script()` (PHP→JS data + i18n strings). OAuth consent + error pages now link `assets/css/oauth-consent.css` instead of inlining the CSS.
* **Compliance:** `uninstall.php` no longer directly `require_once`s `wp-admin/includes/user.php`. The wp-commander-bot account is only auto-deleted when `wp_delete_user` is already loaded in the uninstall context; otherwise it's left in place for the admin to remove manually (it's an ordinary WP user, visible in Users → All Users).

= 1.8.1 =
* **Fix:** DCR client deduplication. When a remote app (Claude.ai) re-registered after losing its credentials, the plugin used to leave an orphan row in `cmcp_oauth_clients` on every reconnect — admins were ending up with N "Claude" entries, most with zero active tokens. `rest_register()` now sweeps any existing DCR clients that share the incoming metadata (name + redirect_uris + auth method) and have no active tokens *before* inserting the new row, so the table only grows by one row when a connection is actually live. The daily cleanup cron also reaps DCR clients older than 7 days with no active tokens (orphans from abandoned flows).
* **Security:** Tighter SSRF guard on `media_upload`. The previous IPv4-only `gethostbynamel()` lookup let an attacker host with an AAAA record bypass the filter and target IPv6 loopback / ULA / link-local. The guard now resolves both A and AAAA via `dns_get_record()`, checks literal-IP hosts directly (no DNS step), explicitly rejects IPv6 ULA (fc00::/7), link-local (fe80::/10), IPv4-mapped IPv6 (::ffff:a.b.c.d), and well-known cloud metadata endpoints (169.254.169.254, fd00:ec2::254) belt-and-braces on top of the existing `FILTER_FLAG_NO_PRIV_RANGE` / `NO_RES_RANGE` filters.
* **Security:** `media_delete` now respects the "Allow destructive operations" setting (it was an unconditional hard delete before — wp_delete_attachment with force=true). Matches the gate that `posts_delete` and `settings_update` already enforce.
* **UX:** OAuth Clients page action buttons relabelled. "Revoke tokens" → "Sign out" (revoke active tokens, keep registration so the client can reconnect with the same client_id). "Delete client" → "Disconnect" (revoke + remove registration entirely). Tooltips and confirmation prompts spell out the difference so admins don't pick the wrong one.
* **Internal:** Encoding-safe tool-file rewrites — the 1.8.0 release accidentally mojibake'd em-dashes in 24 tool source files because PowerShell 5.1's `Get-Content` defaulted to Windows-1252 on UTF-8-without-BOM. Restored from v1.7.2 and re-applied the rename via raw UTF-8 IO. No runtime impact (PHP doesn't care about source comment encoding), but `git blame` is now legible again.

= 1.8.0 =
* **Breaking-compatible:** Tool names changed from dotted (`site.info`, `posts.list`) to underscored (`site_info`, `posts_list`) so Claude.ai's remote-MCP UI accepts them. Claude.ai enforces `^[a-zA-Z0-9_-]{1,64}$` on tool names and rejected the dotted form (`tools.36.FrontendRemoteMcpToolDefinition.name: String should match pattern ...`). Claude Desktop / Claude Code clients also accept the new names. Existing installs are auto-migrated on first load — any dotted slugs saved in the `enabled_tools` option are rewritten to underscored equivalents, so admins keep their tool selection without manual action.
* **Compat:** External callers that hard-coded `tools/call` with `name: "site.info"` need to switch to `site_info`. The legacy dotted names are not aliased on the wire — they are simply gone.

= 1.7.2 =
* **Fix:** OAuth `service_documentation` (RFC 8414) and `resource_documentation` (RFC 9728) URLs were hardcoded to the author's site. They now default to the project's canonical GitHub URL and can be overridden per-site via the new `cmcp_oauth_service_documentation` and `cmcp_oauth_resource_documentation` filters. All other endpoints already auto-detect the install site via `home_url()` / `rest_url()`.

= 1.7.1 =
* **Add:** Bot user banner on the Tokens page — surfaces the `wp-commander-bot` service account (when present) with role + Edit-user link. The banner was advertised in the 1.7.0 changelog but the view edit was lost from that commit; this release restores it.

= 1.7.0 =
* **OAuth sessions are now visible.** A new "Active OAuth sessions" table on the OAuth Clients page lists every individual access token issued through the OAuth flow — client, bound WP user, scopes, issued / last-used / access-expires / refresh-expires timestamps — with a per-row Revoke action that kills one session without deleting the parent client.
* **Audit log filters + pagination.** Filter form for status, method, tool, IP, date range, free-text search across method/tool/note/ip. Results paginated at 50 per page. Default view is the most recent page.
* **Audit log CSV export.** New "Export CSV" button streams up to the last 50 000 audit rows as a downloadable `commander-audit-YYYYMMDD-HHMMSS.csv`.
* **Bot user banner** on the Tokens page — surfaces the `wp-commander-bot` service account (if the setup wizard created it) with role + Edit-user link, and explains when to bind tokens to it.
* **Audit log noise dedup.** The post-OAuth-refresh `(pre-auth) cmcp_invalid_token` cascade (caused by the SDK retrying with a now-revoked stale token) is now deduplicated to one row per IP per minute when there's been a successful OAuth token issuance from the same IP in the same window.

= 1.6.3 =
* **Fix:** OAuth consent screen and HTML error pages were being JSON-encoded by WordPress's REST API serializer (which always `wp_json_encode`s the response body even when `Content-Type: text/html` is set). Browsers received a quoted JSON string instead of HTML and rendered an empty page. `html_response()` and `raw_redirect()` now `echo`+`exit` to bypass the REST serializer entirely.

= 1.6.2 =
* **Fix:** Self-healing upgrade routine. `Plugin::maybe_upgrade()` now runs on every load and compares the stored `cmcp_version` option against `CMCP_VERSION`; if the version differs (or our primary `cmcp_tokens` table is missing for any reason), it re-runs the activation routine (idempotent `dbDelta`). Fixes the silent "token was created but doesn't appear in the list" bug seen when WordPress's "Replace current with uploaded" upgrade path doesn't trigger `register_activation_hook`.

= 1.6.1 =
* **WordPress.org compliance.** Removed the GitHub auto-updater (the `Update URI:` header, `includes/class-github-updater.php`, and the `GithubUpdater::init()` call). WordPress.org plugins must not ship their own update mechanism — updates come from the .org directory.
* **Fix:** Converted the Python and Node snippets in `includes/admin/views/partials/token-snippets.php` from heredoc syntax (`<<<PY`, `<<<JS`) to plain string concatenation. The Plugin Check rule `PluginCheck.CodeAnalysis.Heredoc.NotAllowed` flags heredoc as a guideline violation.

= 1.6.0 =
* **Slug rename to `sayed-wp-conductor-for-claude`** — WordPress.org review assigned the slug `sayed-wp-conductor-for-claude` to this plugin. Folder, main file, text domain and POT have been renamed accordingly. The display name "Sayed WP Conductor for Claude" is unchanged. Existing installs from earlier zips need a one-time fresh install (settings stay in the DB but the old folder must be removed).

= 1.5.4 =
* **Fix:** OAuth consent screen bounced logged-in admins back to wp-login on sites where the REST API is hit by direct browser navigation. WordPress's REST routes only honor cookie auth when the request carries an X-WP-Nonce header — which a top-level navigation does not. `OAuth::rest_authorize_get/post` now manually calls `wp_validate_auth_cookie` to set the current WP user from the browser cookie. Fully verified (HMAC-signed cookie); not an auth bypass.

= 1.5.3 =
* **Token issuance UX fix.** The "Bind to WP user" field defaulted to `0` (anonymous) — admins kept issuing tokens that passed the scope check but failed every WordPress capability check, giving the impression admin tools were broken. Now:
  - Field is a dropdown of suggested users (Administrators / Editors / Authors), with the **current admin pre-selected**.
  - "Other — enter ID manually" option reveals the number input for edge cases.
  - "0 — anonymous" is still available as the last option (in red), with a live warning banner when picked.
  - Label clarified: "Run as WP user" with an explanation that admin-scope tools also require the bound user to have the corresponding WP capability.

= 1.5.2 =
* **Fix:** Critical fatal error on activation in 1.5.1. The new GitHub auto-updater class was named `GitHubUpdater`, but the autoloader's CamelCase→kebab-case regex splits at every lower-to-upper boundary (`tH`, `bU`), so it tried to load `class-git-hub-updater.php` while the actual file is `class-github-updater.php`. Renamed the class to `GithubUpdater` so the filename matches. **Do not run 1.5.1 — install 1.5.2 directly.**

= 1.5.1 =
* **GitHub auto-updater** — once installed, the plugin polls api.github.com/repos/TaherSayed/commander-secure-mcp-control/releases/latest at most every 12 hours and surfaces new versions through WordPress's standard "Update available" banner. One-click update preserves all data. Yields to WordPress.org's update channel automatically once published there.
* "View details" modal shows the GitHub release notes for the new version.
* Plugins-page row links: GitHub repo + Report issue.
* **Fix:** JSON-RPC error messages no longer contain HTML entities (e.g. apostrophes were rendered as `&#039;`). Exception messages are now entity-decoded before being returned in the JSON body.

= 1.5.0 =
* **Quick-connect snippets** per token row — collapsible block with tabs for `curl`, Claude Desktop config JSON, Python (requests), and Node (fetch). For freshly-issued / rotated tokens the plaintext is substituted automatically; for existing rows a `<YOUR_TOKEN>` placeholder is used (plaintext is never stored server-side).
* **7-day sparkline** per token row — inline SVG chart of call activity, alongside the total count.
* **Outbound webhooks** — POSTs a small JSON `{ event, timestamp, site, data }` to a configurable URL on brute-force lockouts and new OAuth client registrations. Optional HMAC-SHA256 shared secret produces an `X-Commander-Signature` header for receiver verification. "Send test ping" button in settings.
* Webhook delivery is async via WP-Cron (falls back to short-timeout sync if cron is disabled).

= 1.4.0 =
* **Token UX overhaul:** new Status column (Active / Idle / Stale / Expired / Revoked) with colored pulsing dot, scope badges, last-IP column, 7-day call counter, and per-row actions.
* **Copy-to-clipboard** button on freshly-issued tokens.
* **Test connection** button — fires a real `initialize` against `/rpc` with the just-shown token and reports OK + MCP protocol version + round-trip latency, or the actual failure reason.
* **Rotate** action — one-click reissue with the same label / scopes / user / IP allowlist / remaining expiry, old token revoked atomically.
* **Permanent Delete** action — wipes the token row entirely (Revoke remains the compliance-friendly default that keeps the audit trail).
* Inline notices for revoke / delete / rotate outcomes.

= 1.3.0 =
* **Security:** Dynamic Client Registration (RFC 7591) is now off by default and requires explicit admin opt-in. When on, registration is rate-limited per IP.
* **Security:** `is_https()` only honors `X-Forwarded-Proto` when the new "Trust reverse proxy" setting is enabled — prevents header-spoof bypass of the HTTPS gate.
* **Security:** `client_ip()` reads `CF-Connecting-IP` / `X-Forwarded-For` / `X-Real-IP` only when "Trust reverse proxy" is on — prevents single-IP-locks-out-everyone DoS behind Cloudflare.
* **Security:** Per-IP rate limit added to `oauth/register`, `oauth/authorize` (POST) and `/.well-known/oauth-*`.
* **Security:** Consent screen form action now uses `home_url()` instead of `Host:` header — fixes a host-header redirection vector.
* **Security:** `serve_well_known` early-returns on non-matching paths instead of parsing every front-end request URL.
* **Fix:** Dashboard view's status-row helper is no longer redeclared on second page load (fatal on revisit).
* **Fix:** Dashboard "destructive ops OFF" color is now correct (operator-precedence bug inverted it before).
* **Fix:** Admin POST handlers now `wp_unslash()` before sanitizing.
* **Uninstall:** Drops all `cmcp_*` transients, deletes the `wp-commander-bot` user if present, flushes rewrite rules.
* **i18n:** Brute-force, server, OAuth and dashboard strings translated. Adds `languages/claude-mcp-secure.pot`.

= 1.2.0 =
* Setup wizard, dashboard with KPIs and 7-day chart, dashboard widget, OAuth 2.1 with PKCE and DCR, brute-force protection with progressive lockout, scanner-UA block list, token rotation warnings, email alerts.

= 1.1.0 =
* OAuth 2.1 endpoints (authorize / token / register / revoke / metadata).

= 1.0.0 =
* Initial release. Personal access tokens, scope + capability gating, audit log, rate limiting, SSRF guard on media.upload, 25 built-in tools, WP-CLI command, custom-tool filter.

== Upgrade Notice ==

= 1.7.0 =
Major UX additions: OAuth sessions view + per-session revoke, audit log filters / search / pagination / CSV export, bot user banner, dedup of post-refresh 401 noise. No DB schema change.

= 1.6.3 =
Fixes OAuth consent screen rendering as blank/garbled. Required for any client (Claude Desktop, Claude Code) connecting via OAuth.

= 1.6.2 =
Self-heals missing DB tables on load. Recommended for anyone who upgraded via WP's "Replace current with uploaded" flow and saw silent token-creation failures.

= 1.6.1 =
Drops the GitHub auto-updater (required for WordPress.org listing) and a heredoc syntax issue flagged by Plugin Check. No functional changes.

= 1.6.0 =
**Slug rename to `sayed-wp-conductor-for-claude`** — this version's folder is `sayed-wp-conductor-for-claude/`. If you have an older `commander-secure-mcp-control/` folder, deactivate it via WP admin (don't click Delete — it would drop your tokens table), then delete that folder via FTP/SSH, then install this 1.6.0 zip fresh. Database is untouched.

= 1.5.4 =
Fix: OAuth consent screen now recognises your existing WordPress login session (was bouncing logged-in admins back to wp-login). Required if you use security plugins that move the login URL.

= 1.5.3 =
Fixes a footgun in the token form — "Bind to WP user" now defaults to the current admin instead of 0. Existing tokens are not affected.

= 1.5.2 =
**Critical fix.** v1.5.1 had a fatal-error bug on activation due to a class-name / autoloader mismatch — do not run 1.5.1; install 1.5.2 directly.

= 1.5.1 =
Adds GitHub auto-updater — every future release will appear as a standard "Update available" notification on your Plugins page. **Install this version once manually (Replace via upload) and every release after 1.5.1 updates automatically.**

= 1.5.0 =
Quick-connect snippets (curl / Claude Desktop / Python / Node) per token, 7-day sparkline, and outbound webhooks on lockouts + new OAuth clients. Pure additive — no settings or schema change.

= 1.4.0 =
Token management overhaul — Copy, Test, Rotate, Delete, live status badges, last-IP and 7-day call counts. Pure additive — no settings change required.

= 1.3.0 =
Important security update. Dynamic Client Registration is now off by default — if you relied on it for Claude Desktop one-click connect, re-enable under Sayed WP Conductor → Settings → OAuth. Behind a reverse proxy, also enable "Trust reverse proxy".
