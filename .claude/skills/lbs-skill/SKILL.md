---
name: lbs-skill
description: Build or edit a LUBESTATION (DST) site page in dst-fullsite/ — visual identity, card/KPI layout, auth-gate pattern, Supabase backend conventions, and the local-test-then-deploy workflow. Use whenever asked to create a new page for the site, restyle an existing one, add a login-gated section, or touch anything under dst-fullsite/.
---

# LUBESTATION page-building skill

Distilled from building `dst-fullsite/corporate-governance.html`. Follow these conventions for any new or edited page in `dst-fullsite/` so the site stays visually and structurally consistent.

## 1. Visual identity

- **Theme**: dark background, amber/gold (`#FDB913` / `var(--amber)`) as the single accent color, `Vazirmatn` font, RTL (`dir="rtl" lang="fa"`), Persian digits (use a `fa()` formatter for numbers).
- **Header block** (top of every page, centered):
  1. DST logo image (pyramid mark + "DST" wordmark + "DIESEL SUPER TECHNOLOGY" tagline — reuse the existing base64 `<img class="logo">`, don't regenerate it)
  2. Page brand line: `LUBESTATION` in white, bold, letter-spaced
  3. Role/section subtitle directly under it, in amber (e.g. `مدیریت ارشد`)
  4. A single `HOME` link back to `index.html` (icon + text) — never more than one link in the header; don't stack a second button next to it, it overlaps.
- **Cards**: every content card (`.kpi`, `.p4`, `.panel`) gets a full `1px solid var(--amber)` border and rounded corners (`border-radius:14–16px`), `background:var(--surface)`. No plain grey (`var(--line)`) borders on content cards — amber is the standard now.
- **KPI cards** (`.sum .kpi`): centered text (`text-align:center`, flex column, `align-items:center;justify-content:center`), a muted label (`span`), a large bold number (`b`, ~30px), optional muted unit (`small`). Don't let them run tall/empty — size via `min-height` + padding, not a fixed height, so content still fits.
- **Background ambiance**: a `position:fixed;inset:0;z-index:0;pointer-events:none` canvas behind `.wrap` (which must have `z-index:1`). Current standard is a lightweight standalone Three.js particle-sphere scene (see `corporate-governance.html`'s `<script type="module">` block) — two co-axial gold ellipsoid point-shells, slow rotation, `prefers-reduced-motion` respected. Don't add a heavy 2D canvas animation instead; the particle sphere is the agreed look. Keep particle size/opacity tuned so it reads as a visible sphere, not invisible or an overwhelming blob — verify with a screenshot, don't guess.
- Remove decorative elements the user doesn't want promptly and fully (e.g. the old oil-droplet 2D canvas, the "سطح ۱" badge, the demo-disclaimer banner were all removed on request) — delete the CSS rule, the DOM element, and the script block together, don't leave orphaned dead code.

## 2. Layout equalization gotcha

Some pages use a JS `equalizeFour()`-style helper that force-stretches sibling panels (station card / map / chart / rank) to match the tallest one. If a specific card should size to its own content instead of being stretched, give it a `no-equalize` class and exclude that class from the equalizer's `querySelectorAll` — don't fight it with CSS `!important`.

## 3. Auth gate pattern (`auth-gate.js`)

- One shared `dst-fullsite/auth-gate.js` file, included via `<script src="auth-gate.js?vN" data-roles="..." data-live-panel="false"></script>`.
- `data-roles`: `"admin"` restricts to admin; `"any"` allows any active account; comma-separate for multiple roles.
- `data-live-panel="false"` suppresses the small "live data from Supabase" panel the gate can inject — only show it where it actually makes sense (don't put it on a page that's just a picker/listing, e.g. Partner Ecosystem).
- **Only gate pages that actually need it.** A picker/listing page (e.g. choosing a dealership) should stay open; the login belongs on the step that actually shows protected data, or on the dedicated real-app login link.
- **Bump the `?v=N` query on `auth-gate.js` whenever you change it.** Browsers cache it; without a version bump, testers will see stale behavior and report false bugs.

## 4. Backend conventions (independent Supabase project)

Do **not** depend on the Lovable-managed Supabase project for new work — debugging it requires relaying every SQL change through a second AI with no direct feedback loop, which is slow and unreliable. Use a **separate, dedicated Supabase project** this session can test against directly with `curl`/`fetch`, end to end, before telling the user it works.

- **Login identity**: phone number + password. Map phone → a synthetic email as `phone + "@<a domain the project owner actually owns>"` (e.g. `@barandigital.ir`). **Never use a fake TLD like `.local`/`.test`/`.invalid`** — Supabase's signup endpoint rejects those as "placeholder/mistyped domain". A real owned domain passes.
- **Email confirmation**: a fresh Supabase project requires confirmation by default. After self-signup via the public API, confirm with a plain SQL update (safe, no password hashing involved):
  ```sql
  update auth.users set email_confirmed_at = now() where email = '...';
  ```
- **Schema**: a `public.profiles` table (`id uuid references auth.users`, `phone text unique`, `full_name`, `role text check (role in ('admin','viewer'))`) plus a trigger on `auth.users` insert that creates the profile row, reading `phone`/`full_name` out of `raw_user_meta_data`.
- **Granting the first admin role**: a plain `update public.profiles set role='admin' where phone='...'` — no RLS bootstrap trap here because `role` lives directly on `profiles` (not a separate permissions table gated by "must already be admin to grant admin").
- **Never hand-craft a bcrypt password hash via SQL** (`crypt()`/`gen_salt()`) to set a user's login password — it repeatedly produced accounts that existed but could not log in, for reasons not fully pinned down. Always create/set the password through Supabase's own API: `POST /auth/v1/signup` (self-service, anon key) or `supabase.auth.admin.createUser` / `updateUserById` (service-role, if available). If a password genuinely needs resetting without API access, that's a sign to re-signup/recreate the user via the API rather than patch the hash directly.
- **Never write real passwords into committed files** (docs, skills, scripts). Use a placeholder like `<test-password>` when documenting a flow; keep actual credentials only in chat with the user or in untracked local scratch files.

## 5. Local-test-before-deploy workflow

GitHub Pages deploys take ~20–40s and browser caching can hide whether a fix actually landed, so don't iterate by push-and-eyeball on the live URL:

1. Serve the working directory locally: `python3 -m http.server <port> --bind 127.0.0.1` (run with `nohup ... & disown`, not a plain trailing `&` in a command that also runs other things — mixing `pkill` and server-start in one `&&` chain has caused the shell tool to report a spurious non-zero exit here; start/stop them as separate commands).
2. Drive it with Playwright (`chromium.launch()` — the sandbox's pre-installed Chromium needs no special options for `http://127.0.0.1`; only `https://` external fetches from this sandbox need `proxy: { server: process.env.HTTPS_PROXY }`).
3. For visual changes, actually read the resulting screenshot (`page.screenshot()`) before claiming success — don't trust `page.$eval`/pixel-reading tricks alone. `canvas.getContext(...).readPixels()` can read back all-zero even when something is genuinely rendering, because WebGL's drawing buffer can already be cleared/swapped by the time a separate `evaluate()` call reads it (no `preserveDrawingBuffer`). A real screenshot is the reliable check.
4. For a numeric/geometric request ("10% smaller", "exactly centered"), **measure** the actual rendered `getBoundingClientRect()` before and after — don't eyeball a percentage. Iterate the CSS value, remeasure, confirm within ~1% of the target before moving on.
5. Only after local verification: commit, push to `main`, then poll the live URL / raw file (`curl ... | grep <a fingerprint string unique to the new code>`) in a loop until the GitHub Pages Actions deploy has actually picked it up, before telling the user it's live.
6. If something that worked moments ago suddenly reads as broken (e.g. a nav link "stopped working"), check working theories in order: (a) is this actually tested against the live deployed file, not a local change not yet pushed; (b) server-side `curl` check that the fix is actually present in the served HTML; (c) browser-side caching (`cache-control`/`etag` on the response) before concluding there's a real regression.

## 6. GitHub Pages / custom-domain pitfall

If a `CNAME` file is committed (custom domain), GitHub Pages **redirects the default `*.github.io` URL to that domain immediately**, even before the domain's DNS is configured — this can make the whole site unreachable from any URL. Don't commit a `CNAME` until DNS is confirmed ready; if it's committed too early, remove it, and also clear **Settings → Pages → Custom domain** in the GitHub UI (removing just the file doesn't retract GitHub's stored custom-domain setting).

## 7. Shell gotcha

A literal `$$` inside a **double-quoted** bash string is not literal — it expands to the shell's PID. This silently corrupted a password value in several debugging rounds when it appeared in one. Always single-quote literal values that might contain `$`, or build request bodies with a heredoc and verify the exact string with `echo`/`cat` before using it in a `curl` call.
