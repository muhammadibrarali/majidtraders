# CLAUDE.md — Permanent project rules for majidtraders.org

These rules apply to EVERY task in this repository. Read them before changing anything.

## Project
- Company: **Majid Traders**, Mardan, Khyber Pakhtunkhwa, Pakistan. Import & export, government & private contracting, general order supply.
- Brand: orange and charcoal, "MT" logo, tagline **"Live the Trader's Life"**.
- Main website: `https://majidtraders.org` (always without www).
- Staff portal `portal.majidtraders.org` is a SEPARATE site: **never touch it**. We only link to it ("Staff Login").

## The owner
- The owner does not code. Do all the work and explain each step in simple English.

## Hosting & deploy
- Hostinger shared hosting (LiteSpeed/Apache, PHP 8, MySQL).
- This repo auto-deploys to `public_html` on every push to `main`. **There is NO build step**: files are served exactly as committed.
  - No npm build, no React/Next/Vue, no Composer on the server.
  - If a PHP library is needed (e.g. PHPMailer), commit its files into `/lib`.
- Repo root = website root (`public_html`).
- When a task is done: commit with a clear message and push to `main`. If only another branch can be pushed, explain step by step how to merge it into `main` on GitHub.
- The owner has no local server and tests on the live site.

## Tech stack
- Front end: semantic HTML5, modern CSS (custom properties, grid, clamp), vanilla JavaScript.
- Animations: GSAP + ScrollTrigger and Lenis smooth scroll, loaded from jsDelivr with **pinned versions**. Every section animates on scroll. Respect `prefers-reduced-motion`. Content must stay visible and indexable if JavaScript fails (never hide content with CSS that only JS can undo).
- Back end: plain PHP 8 with PDO (MySQL). Clean URLs via `.htaccess` + `router.php`.
- Routing rule (LiteSpeed-safe): `.htaccess` uses the WordPress-style rule "not a file/folder -> /router.php"; router.php maps `/x` -> `x.php` and `/admin/x` -> `admin/x.php` (and later `/projects/<slug>` etc.). Do NOT put `.htaccess` files in folders that contain pages (LiteSpeed and Apache treat parent rewrite rules differently there), and do NOT use `%{DOCUMENT_ROOT}/$1` file tests. Test routing on real Apache with `.htaccess`, not the PHP built-in server.
- Admin panel at `/admin` to manage all content without code.

## Private folder (outside public_html, never in GitHub)
- Location relative to website root: `../private/`
- `../private/config.php` = database credentials, SMTP, secret keys. The repo only contains `config.example.php` with placeholder values.
- `../private/uploads/` = all images and PDFs uploaded from admin, served through `/media.php`. **Never store uploads inside public_html** — every deploy replaces its files.

## Security (always)
- Never commit passwords, keys or real config. `.gitignore` covers config.php, .env, uploads, backups, *.sql.
- PDO prepared statements only. Escape all output. CSRF token on every form. `password_hash` / `password_verify`.
- Secure session cookies (HttpOnly, Secure, SameSite=Lax); regenerate session ID on login. Login rate limiting and lockout.
- Upload validation: real MIME check, size limit, re-encode images, random file names, never executable.
- Security headers: HSTS, Content-Security-Policy, X-Content-Type-Options, frame-ancestors, Referrer-Policy, Permissions-Policy. Force HTTPS and non-www.
  - The CSP is sent by PHP (`SITE_CSP` in `includes/helpers.php`, so media.php can send a PDF-friendly one); the other headers are in `.htaccess`. Inline `<script>`/`<style>` and `style=""` attributes are NOT allowed — put CSS/JS in `/assets`. If a new external service is added (map, analytics, fonts), update `SITE_CSP`.
- Block web access to hidden files, .git, .md, .sql, `/includes` and `/lib`. Admin pages `noindex`. Never show PHP errors to visitors; show friendly error pages (`error.php`).

## SEO (always)
- One H1 per page, unique title and meta description, canonical URL, Open Graph tags, JSON-LD schema, descriptive alt text.
- Clean URLs (e.g. `/projects/school-renovation-mardan`), dynamic `sitemap.xml`, `robots.txt`.
- Fast: WebP images, lazy loading, minimal JS, aim for Lighthouse 90+ on mobile.

## Design
- Modern 2026 corporate style: large bold typography, generous white space, charcoal sections with orange accents, bento grids, client logo marquee, animated counters, smooth section reveals, subtle parallax, horizontal-scroll project showcase.
- Mobile-first, fully responsive.
- Brand colours (CSS custom properties): orange `#F26B1D`, charcoal `#1E2124`.

## Database
- Design lives in `includes/schema.php`. Tables: admin_users, login_attempts, media, settings (key/value), counters, services, service_images, clients, project_categories, projects, project_images, project_services, team_members, blog_categories, blog_posts, certifications, testimonials, contact_messages.
- Content tables have `status` ('draft'/'published'), `sort_order`, `created_at`, `updated_at`; slugs are UNIQUE.
- Setup is complete on the live site and admin/setup.php was deleted.
- Upgrades: `includes/migrations.php` runs numbered steps automatically when an admin opens any admin page (`settings.schema_version`, currently 4). To change the database: update schema.php (fresh installs) AND add the next numbered, idempotent step to migrations.php (bump SCHEMA_VERSION).
- v2 added: `media.title`, `service_images` (service gallery), all Site Settings keys, company details, 3 starter services (drafts).
- v3 added: `projects.project_status` (completed/ongoing), `project_services` (project ↔ service), `clients.show_in_marquee`, `team_members.department` (and DROPPED `team_members.phone` — never store staff phone numbers or home addresses), `blog_posts.tags`, `.reading_minutes`, `.author_team_id` (author = admin OR team member), `certifications.grade`, `.show_number`.
- v4 added settings: `site_live` ('0' = preview), `home_meta_title`, `home_*` section texts (eyebrow/title per section, about heading/image, `home_process` JSON steps, CTA heading/text).
- Public pages must respect: status = 'published'; blog posts also `published_at <= NOW()` (future = scheduled); team uses status as "Shown on website"; certifications hide `registration_number` when `show_number = 0`; logo marquee = clients with `show_in_marquee = 1`; home showcase = projects with `is_featured = 1`.
- Settings are key/value (`includes/settings.php`: `setting()`, `save_settings()`); media references in settings store the media ID (keys listed in `media_setting_keys()`).

## Admin pattern (reuse for every content section)
- `includes/admin_crud.php` → `crud_run([...])`: list (thumbnail, status badge, search, status filter, pagination, up/down order), add/edit form (help text, validation, auto slug + uniqueness, Draft/Published, position, SEO title + meta description with counters and Google preview, unsaved-changes warning, success flash), delete confirmation page. All changes POST + CSRF. See `admin/services.php` for a complete example.
- Field types: text, int, url, date, datetime, slug, textarea, richtext, icon, select (optional with 'empty'), checkboxes (many-to-many link table), switch, media (accept image|document|any), gallery (ordered images + optional captions in a link table), custom (render/collect callbacks). Fields with 'side' => true go in the right-hand Publish box. Renderers live in `includes/admin_form.php`.
- Resource options (documented at the top of admin_crud.php): filters, list_select/list_meta/list_badges, status_labels, sortable/order_by, is_live, defaults, validate, before_save, delete_check (block delete, e.g. category in use), delete_warning, page_actions, menu_key.
- Sections built with it: services, projects (+ project-categories), clients, team, blog (+ blog-categories), certifications, testimonials. "View on site" URLs: /services/{slug}, /projects/{slug}, /projects/category/{slug}, /blog/{slug}, /blog/category/{slug}, /clients#{slug}, /team#{slug}, /certifications#{slug}.
- Messages (`admin/messages.php`, custom page): inbox for contact_messages (status 'new' = unread), open = mark read, mark read/unread, bulk actions, delete with confirmation, CSV export (POST + CSRF, formula-injection safe). Unread count shows on the sidebar and dashboard.
- Dashboard warns about certificates expiring within 60 days or already expired.
- Rich text: local editor in `assets/js/admin.js`; every saved HTML goes through `clean_html()` (`includes/sanitize.php`, HTML Purifier 4.19.1 in `lib/htmlpurifier`, allow-list: p, br, strong/b, em/i, u, s, h2–h4, ul/ol/li, blockquote, hr, a[href], img[src|alt] from this site only).
- When adding a content table that references media, also add it to `media_usage()` in `includes/media_lib.php` (delete warnings).

## Media Library
- Upload rules: JPG/PNG/WebP/PDF, max 8 MB, real type checked with finfo, images decoded + re-encoded to WebP (EXIF/GPS removed, rotation applied), sizes lg 1920 / md 1024 / sm 400, random names in `../private/uploads/<2 chars>/`. Alt text required for images.
- Public URLs: `/media/<id>/<slug>.webp`, `/media/<id>/md/…`, `/media/<id>/sm/…`, `/media/<id>/<slug>.pdf` → router.php → media.php (no session, 1-year immutable cache, ETag/304, PDFs inline). Use `media_url($row, 'md')` in templates.

## Public website (Step 5)
- Every public page: `define('MT_PUBLIC_PAGE', true); require __DIR__ . '/includes/site.php'; site_gate(); site_head([...]); …; site_footer();` (see index.php).
  - MT_PUBLIC_PAGE = no session/cookie for normal visitors (a session only opens if the visitor already has the admin cookie).
  - `site_gate($isHome)`: Website live → everyone; not live + logged-in admin → page + orange "Preview mode" bar + noindex; not live + visitor → home shows `includes/coming_soon.php`, other pages redirect home. Switch: Site Settings › Website status.
  - `site_head()` meta: title, description, path, active, hero (transparent header), preload (hero media row; also og:image fallback), og_image, jsonld.
  - Helpers in includes/site.php: `s()`, `site_media()`, `media_img()` (srcset sm/md/lg + width/height + lazy), `media_dims()`, `whatsapp_link()`, `tel_link()`, `social_links()`, `site_services()`, `section_head()`, `json_ld()`.
- Design system: ONE file `assets/css/site.css` (tokens on :root: colours, fluid type --step-*, spacing, radius, shadows; buttons .btn--primary/secondary/ghost/ghost-light; .eyebrow; .section-title; .card; .badge; .container; .grid-12). Fonts self-hosted in assets/fonts: Bricolage Grotesque (headings) + Inter (body), variable WOFF2, OFL licences included.
  - Contrast rules: orange buttons use charcoal text (never white on orange); orange TEXT on light backgrounds uses --orange-ink #B5490C.
- Animations: `assets/js/site.js` + GSAP 3.15.0 (gsap, ScrollTrigger, SplitText) and Lenis 1.3.26 from jsDelivr, pinned, with SRI hashes in `SITE_SCRIPTS` (includes/site.php). To upgrade: pick a version ≥2 weeks old, recompute sha384 from the npm package files, update URL + hash.
  - Markup hooks: data-reveal, data-reveal-group/data-reveal-item, data-split (words), data-split-lines, data-mask-reveal, data-count, data-parallax, data-hscroll/data-hscroll-track (pinned horizontal, desktop only), data-timeline(-progress), data-slider.
  - Content is visible without JS; hero text uses CSS-only animation; prefers-reduced-motion → no Lenis, no parallax, no pinning, fades only.
- Local CSS/JS links ALWAYS use `asset('/assets/...')` (includes/helpers.php): it adds `?v=<file modified time>` so every deploy gets fresh URLs and no browser/LiteSpeed/Hostinger-CDN cache can serve an old or broken copy. Never hard-code `/assets/css/x.css` without asset(). (Fonts referenced from CSS keep fixed names.)
- `.htaccess` sets explicit MIME types (css, js, svg, webp, woff2…) because we send `X-Content-Type-Options: nosniff`. router.php answers missing static files (css/js/fonts/images) with a plain-text, non-cached 404 — never the HTML error page.
- `/admin/health` (link on the dashboard): requests every CSS/JS/font/image from the server itself and shows status, MIME type and caching headers with plain-English fixes. Use it first when anything looks unstyled.

## Pages (built step by step)
Home, About, Services (+ detail pages), Projects (+ detail pages), Clients, Team, Blog (+ posts), Certifications & Registrations, Contact (form + WhatsApp + map), Privacy Policy, plus a Staff Login link to portal.majidtraders.org.

## Folder structure
```
/                     website root (= public_html)
├── .htaccess         redirects, clean URLs, security headers, blocking rules
├── .user.ini         PHP settings (errors hidden from visitors)
├── index.php         home page (all sections from the database)
├── error.php         friendly error page (403/404/500/503)
├── router.php        clean-URL router: .htaccess sends every non-file address here
├── media.php         serves Media Library files (/media/<id>/...)
├── robots.txt
├── config.example.php  template for ../private/config.php (placeholders only)
├── assets/
│   ├── css/          stylesheets
│   ├── js/           scripts
│   ├── img/          logo, favicon, static design images (not uploads)
│   └── fonts/        self-hosted WOFF2 fonts + OFL licences
├── includes/         shared PHP — blocked from web
│   ├── helpers.php   e(), is_https(), show_error_page() — no DB/config needed
│   ├── bootstrap.php loads ../private/config.php, secure session, db() PDO, CSRF helpers
│   ├── auth.php      admin login, 5-tries/15-min lockout, 30-min idle logout, require_admin()
│   ├── admin_layout.php  admin sidebar layout, "coming soon" placeholder
│   ├── admin_crud.php    reusable list/edit/delete/reorder manager (crud_run)
│   ├── admin_form.php    reusable form fields (text, slug, richtext, media picker, gallery…)
│   ├── icons.php     inline SVG icon set (admin + public site), service icon choices
│   ├── media_lib.php uploads, WebP sizes, media_url(), media_usage(), media_delete()
│   ├── migrations.php automatic numbered database upgrades
│   ├── sanitize.php  clean_html() via HTML Purifier
│   ├── settings.php  setting(), save_settings()
│   ├── site.php      public website framework (gate/preview, head, header, footer, image helpers)
│   ├── coming_soon.php  coming-soon page for visitors while the site is not live
│   └── schema.php    full database design (CREATE TABLE IF NOT EXISTS ...)
├── lib/              third-party PHP libraries — blocked from web
│   └── htmlpurifier/ HTML Purifier 4.19.1 (LGPL-2.1, library folder only)
└── admin/            admin panel (noindex + no-cache sent by PHP admin_headers(); local CSS/JS only, no CDN)
    ├── index.php     dashboard          ├── login.php / logout.php (logout = POST + CSRF)
    ├── password.php  change password (logs out other sessions)
    ├── messages.php  contact-form inbox (custom page)
    ├── health.php    website health check (asset delivery, MIME types, caching)
    ├── project-categories.php / blog-categories.php  category managers (block delete while in use)
    └── <section>.php one file per sidebar section; every page starts with require_admin()

../private/           OUTSIDE the website, never in git
├── config.php
├── sessions/         PHP session files (created automatically)
├── cache/            HTML Purifier cache (created automatically)
└── uploads/
```

## Workflow
- Work in small steps.
- At the end of each task tell the owner in simple words: (1) what you did, (2) anything they must do in Hostinger, (3) how to check it on the live site.
