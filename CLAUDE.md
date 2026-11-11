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
  - The CSP lives in `.htaccess`. Inline `<script>`/`<style>` and `style=""` attributes are NOT allowed by it — put CSS/JS in `/assets`. If a new external service is added (map, analytics, fonts), update the CSP.
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
- Design lives in `includes/schema.php`. Tables: admin_users, login_attempts, media, settings (key/value), counters, services, clients, project_categories, projects, project_images, team_members, blog_categories, blog_posts, certifications, testimonials, contact_messages.
- Content tables have `status` ('draft'/'published'), `sort_order`, `created_at`, `updated_at`; slugs are UNIQUE.
- Setup is complete on the live site and admin/setup.php was deleted. To add/change tables later: update schema.php AND add a small, admin-only upgrade step.

## Pages (built step by step)
Home, About, Services (+ detail pages), Projects (+ detail pages), Clients, Team, Blog (+ posts), Certifications & Registrations, Contact (form + WhatsApp + map), Privacy Policy, plus a Staff Login link to portal.majidtraders.org.

## Folder structure
```
/                     website root (= public_html)
├── .htaccess         redirects, clean URLs, security headers, blocking rules
├── .user.ini         PHP settings (errors hidden from visitors)
├── index.php         home page
├── error.php         friendly error page (403/404/500/503)
├── router.php        clean-URL router: .htaccess sends every non-file address here
├── robots.txt
├── config.example.php  template for ../private/config.php (placeholders only)
├── assets/
│   ├── css/          stylesheets
│   ├── js/           scripts
│   └── img/          logo, favicon, static design images (not uploads)
├── includes/         shared PHP — blocked from web
│   ├── helpers.php   e(), is_https(), show_error_page() — no DB/config needed
│   ├── bootstrap.php loads ../private/config.php, secure session, db() PDO, CSRF helpers
│   ├── auth.php      admin login, 5-tries/15-min lockout, 30-min idle logout, require_admin()
│   ├── admin_layout.php  admin sidebar layout, icons, "coming soon" placeholder
│   └── schema.php    full database design (CREATE TABLE IF NOT EXISTS ...)
├── lib/              third-party PHP libraries (e.g. PHPMailer) — blocked from web
└── admin/            admin panel (noindex + no-cache sent by PHP admin_headers(); local CSS/JS only, no CDN)
    ├── index.php     dashboard          ├── login.php / logout.php (logout = POST + CSRF)
    ├── password.php  change password (logs out other sessions)
    └── <section>.php one file per sidebar section; every page starts with require_admin()

../private/           OUTSIDE the website, never in git
├── config.php
├── sessions/         PHP session files (created automatically)
└── uploads/
```

## Workflow
- Work in small steps.
- At the end of each task tell the owner in simple words: (1) what you did, (2) anything they must do in Hostinger, (3) how to check it on the live site.
