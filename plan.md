# NG-Admin-Kit — Custom Admin Panel Project Plan

### Base: AdminLTE (fully re-skinned) → Laravel Blade Views (standalone project first)

> **Goal:** একটি reusable, brand-agnostic admin panel যা AdminLTE-এর কাঠামো ব্যবহার করে কিন্তু ভিজ্যুয়ালি সম্পূর্ণ কাস্টম (কেউ বুঝবে না এটা AdminLTE)। প্রথমে একটা সাধারণ standalone Laravel প্রজেক্টে সব ফিচার বানিয়ে, বাস্তব ডেটা দিয়ে টেস্ট করে, ফাইনাল করা হবে। সব স্থিতিশীল হওয়ার পর (Phase 20) সেটাকে composer প্যাকেজে রূপান্তর করে প্রতিটা নতুন প্রজেক্টে reuse করা যাবে।

---

## 0. Tech Stack & Build Strategy

> **সিদ্ধান্ত:** প্যাকেজ আগে না বানিয়ে, প্রথমে একটা সাধারণ standalone Laravel প্রজেক্টে (যেমন `admin-kit-dev`) সব ফিচার বানিয়ে বাস্তব ডেটা দিয়ে টেস্ট করা হবে। সব ফিচার স্থিতিশীল হওয়ার পর **Phase 20**-এ পুরো `admin` মডিউলটা composer প্যাকেজে extract করা হবে। এই কারণে শুরু থেকেই কোড এমনভাবে লেখা হবে যাতে পরে কেটে সরানো সহজ হয় (নিচে "Package-Ready Conventions" দেখুন)।

| Layer         | Choice                                                                                                                  |
| ------------- | ----------------------------------------------------------------------------------------------------------------------- |
| Framework     | Laravel 13                                                                                                              |
| Views         | Blade (AdminLTE 4 structure, fully re-themed CSS)                                                                       |
| CSS approach  | Copy AdminLTE compiled CSS → strip branding → rebuild with custom CSS variable tokens so theme is swappable per project |
| JS            | Alpine.js (lightweight) + vanilla JS helpers                                                                            |
| Permissions   | `spatie/laravel-permission`                                                                                             |
| Activity Log  | `spatie/laravel-activitylog`                                                                                            |
| Media         | `spatie/laravel-medialibrary`                                                                                           |
| Backup        | `spatie/laravel-backup`                                                                                                 |
| SEO           | `artesaos/seotools` or custom meta table                                                                                |
| Notifications | Laravel native Notification system (database + mail channel)                                                            |

**Dev-phase structure (standalone Laravel app):**

```
app/
├── Http/
│   ├── Controllers/Admin/...
│   └── Middleware/...
├── Models/...
├── Traits/HasDataTable.php
├── Notifications/...
└── Support/SettingsManager.php
resources/
├── views/admin/... (auth, layouts, components, pages)
└── assets/ (css, js, images — de-branded AdminLTE)
database/migrations/
config/admin-kit.php
routes/admin.php
```

### Package-Ready Conventions (follow from Day 1)

এগুলো মেনে চললে Phase 20-এ কাটাকুটি ৯০% কমে যাবে:

- সব admin কোড এক জায়গায় গুচ্ছ রাখুন — Controllers, Models, Middleware সব `*/Admin/` সাবফোল্ডারে, বাকি প্রজেক্ট কোডের সাথে মিশাবেন না।
- View namespace শুরু থেকেই `admin::` ব্যবহার করুন (Blade-এ `View::addNamespace('admin', resource_path('views/admin'))` — `AppServiceProvider`-এ এখনই যোগ করে ফেলুন, খরচ প্রায় শূন্য, কিন্তু পরে ৫০+ view রিনেম করা লাগবে না)।
- Config values হার্ডকোড না করে `config('admin-kit.xxx')` থেকে পড়ুন, `config/admin-kit.php` আজই বানিয়ে ফেলুন।
- Route গুলো `routes/admin.php`-তে আলাদা রাখুন, `RouteServiceProvider`/`bootstrap/app.php` থেকে include করুন।
- Migration ফাইলের নামে `admin_kit_` prefix না দিলেও চলবে, কিন্তু টেবিল নাম প্রজেক্ট-স্পেসিফিক কিছুর সাথে না মিলিয়ে জেনেরিক রাখুন (`settings`, `menus`, `contact_messages` ইত্যাদি)।

**Extraction step (Phase 20):** এই কনভেনশন মানলে তখন কাজ শুধু হবে — ফোল্ডার কপি → namespace `App\...` থেকে `NgCoder\AdminKit\...`-এ পাল্টানো (IDE-তে bulk find-replace) → `composer.json` + `ServiceProvider` লেখা → প্যাকেজ নিজের git repo-তে push।

---

## Phase 1 — Foundation & Base Layout ✅

**গোল:** প্রজেক্ট সেটআপ + config + base layout তৈরি (এখনো কোনো প্যাকেজ নয়, সাধারণ Laravel প্রজেক্ট)।

- [x] নতুন Laravel 13 প্রজেক্ট তৈরি (`admin-kit-dev` বা যেকোনো রিয়েল প্রজেক্ট নাম) — এই রিপোই ব্যবহার হচ্ছে (Laravel 13.26, Vue/Inertia starter kit; admin মডিউল আলাদা Blade মডিউল হিসেবে পাশাপাশি চলছে)
- [x] `config/admin-kit.php` — brand, version, route prefix/guard, theme tokens (light + dark), menu driver, widgets placeholder
- [x] `AppServiceProvider::boot()`-এ `View::addNamespace('admin', resource_path('views/admin'))` + `Theme`/`MenuManager` singleton bind
- [x] Base master layout `admin::layouts.app` (sidebar, topbar, footer) — সব ক্লাস `nk-` prefixed, কোনো AdminLTE ক্লাস/লোগো/কমেন্ট নেই (টেস্ট দিয়ে যাচাই করা)
- [x] CSS variable theme file — `resources/assets/css/admin.css`, প্রতিটা রঙ/সাইজ `--nk-*` টোকেন থেকে, টোকেনগুলো config থেকে রেন্ডার হয়
- [x] Sidebar menu system — `MenuManager` (config driver এখন, `extend('database', …)` দিয়ে Phase 7-এ DB driver), permission-based visibility + recursive tree + active state
- [x] `.env` driven app name/logo fallback before Settings module (Phase 13) exists — `ADMIN_BRAND_*` কী `.env`/`.env.example`-এ যোগ করা

**Claude Code prompt focus:** "Set up the Laravel project foundation, admin config file, and de-branded AdminLTE base layout with CSS variables for theming, following the admin:: view namespace convention."

---

## Phase 2 — Auth: Login & Forgot Password (Custom Design) ✅

**গোল:** Laravel Breeze/Fortify-এর বদলে নিজস্ব controller + fully custom-designed auth pages।

- [x] `AdminAuthController` (login, logout) — Fortify থেকে সম্পূর্ণ আলাদা, নিজস্ব guard/redirect/throttle
- [x] `ForgotPasswordController`, `ResetPasswordController` — token-based; `AdminResetPassword` notification + নিজস্ব branded HTML mail template (`admin::mail.reset-password`), তাই পাবলিক অ্যাপের মেইল আলাদাই থাকে
- [x] Views: `admin::auth.login`, `admin::auth.forgot-password`, `admin::auth.reset-password` + শেয়ার্ড `admin::layouts.auth`
- [x] Split-screen বা centered-card — `config('admin-kit.theme.auth_layout')` দিয়ে সুইচ, দুটোই টেস্ট করা
- [x] Rate limiting — per-account (email+IP) throttle `LoginRequest`-এ + per-IP `throttle:admin-login` limiter; `remember me` config দিয়ে অন/অফ
- [x] 2FA hook — `AdminAuthController::twoFactorChallenge()` stub + `admin-kit.auth.two_factor` ফ্ল্যাগ
- [x] Login attempt logging — `App\Support\Admin\AuthLogger` (login / failed / lockout / logout / password reset), Phase 18-এ activitylog-এ সোয়াপ করার জন্য পাতলা seam

**অতিরিক্ত:** `bootstrap/app.php`-এ admin-aware guest/user redirect — `/admin/*`-এ গেস্ট গেলে `admin.login`-এ যাবে, পাবলিক অ্যাপ আগের মতোই `login`-এ।

---

## Phase 3 — Error Pages (404, 403, 500, 503) ✅

**গোল:** সব HTTP error page এর জন্য branded, on-theme design।

- [x] `resources/views/admin/errors/{404,403,500,503,419,429}.blade.php` + `fallback.blade.php` (যে স্ট্যাটাসের নিজস্ব পেজ নেই তার জন্য)
- [x] Shared `admin::errors.layout` — icon + status code + title + message + context-aware বাটন (লগইন থাকলে "Back to dashboard", না থাকলে "Go to sign in")
- [x] `bootstrap/app.php`-এ `$exceptions->render(new AdminExceptionRenderer)` — শুধু admin রিকোয়েস্টে কাজ করে, বাকি সব ক্ষেত্রে `null` রিটার্ন করে তাই পাবলিক অ্যাপের error handling অক্ষত
- [x] 503 পেজে maintenance message — এখন `config('admin-kit.maintenance.message')` থেকে, Phase 15-এ `setting('maintenance_message')`-এ সোয়াপ হবে

**বিস্তারিত:** render callback Laravel-এর exception mapping-এর আগে চলে, তাই `AuthorizationException`/`ModelNotFoundException`/`TokenMismatchException` নিজেরাই ম্যাপ করা হয়েছে; `AuthenticationException`/`ValidationException` ইচ্ছাকৃতভাবে ছাড় দেওয়া (ওগুলো redirect, error page নয়)। `APP_DEBUG=true` হলে 500-এ Laravel-এর ডিবাগ স্ক্রিনই দেখায়। HttpException-এর হেডার (`Retry-After`) পাস-থ্রু হয় — আসল `php artisan down`-এ যাচাই করা।

---

## Phase 4 — Custom Toaster & Confirm Dialog System ✅

**গোল:** SweetAlert2-based but re-styled toaster + confirm modal, single reusable JS API.

- [x] Zero-dependency (SweetAlert2 বাদ দেওয়া হয়েছে — এর `swal2-*` ক্লাস/স্টাইলশিট রি-স্কিন করা মানেই AdminLTE-এর মতো আরেকটা fingerprint সমস্যা তৈরি করা; Alpine store + টোকেন সিস্টেম দিয়েই যথেষ্ট)
- [x] Global JS helper: `NK.toast(type, message, { duration, title })` — Alpine store (`nkToast`), auto-dismiss (default 5s, `duration: 0` = sticky), hover/focus পজ করে
- [x] Global JS helper: `NK.confirm({ title, text, confirmLabel, cancelLabel, variant, onConfirm, onCancel })` — Promise<boolean> রিটার্ন করে + callback দুটোই সাপোর্ট করে; একটাই reusable dialog, ফোকাস ট্র্যাপ + পূর্বের ফোকাসে ফিরে যাওয়া
- [x] `<x-admin::toast-container />` — `admin::layouts.app`-এ একবার বসানো; session flash (`success`/`error`/`warning`/`info`/`status`) + মাল্টি-টোস্ট queue (`nk_toasts`) + ভ্যালিডেশন এরর সামারি (এক টোস্টে "X fields") সব JSON হিসেবে পাস হয়, ইনলাইন স্ক্রিপ্টে ইন্টারপোলেট না করে — তাই মেসেজের ভেতরের কোনো মার্কআপ script ব্লক ভাঙতে পারে না (টেস্ট করা)
- [x] `AdminController::flashSuccess/flashError/flashWarning/flashInfo()` (Phase 1 থেকেই ছিল) + নতুন `flashToast($type, $message)` — এক রেসপন্সে একাধিক টোস্ট পাঠানোর জন্য
- [x] Declarative confirm — `<form data-nk-confirm data-nk-confirm-title="...">` বা `<a data-nk-confirm>` — সাবমিট/ক্লিক আটকে কনফার্ম করে, ok হলে `requestSubmit()`

**নোট:** টেস্টিং-এ একটা framework quirk পাওয়া গেছে — একই টেস্টে `assertSessionHasErrors()` কল করার পর `$this->get()` করলে Laravel-এর in-process session `Store` দ্বিতীয়বার `start()` হয় (আসল রিকোয়েস্টে যা কখনো হয় না), আর `marshalErrorBag()` idempotent না হওয়ায় errors bag খালি হয়ে যায়। টেস্টে `assertSessionHasErrors()` চেইন না করে সরাসরি ফলাফল (toast payload) যাচাই করে এড়ানো হয়েছে।

---

## Phase 5 — DataTable System: Pagination, Search, Sort, Multi-Delete ✅

**গোল:** একটা reusable Blade component + trait যা যেকোনো Eloquent model এর জন্য listing page বানিয়ে দেয়।

- [x] `HasDataTable` trait (`app/Http/Controllers/Admin/Concerns/`) — server-side pagination, multi-column `LIKE` search, allow-listed sortable columns via query string; `$name` প্যারামিটার দিয়ে একই পেজে একাধিক টেবিল থাকলে query-key নেমস্পেসিং (`{name}_q`, `{name}_sort` ইত্যাদি)
- [x] Blade component `<x-admin::datatable :headers :rows :bulk-actions />` — checkbox কলাম, sortable header links, header-এর `format` closure দিয়ে কাস্টম সেল রেন্ডার (raw HTML, escaping caller-এর দায়িত্ব — badge/link-এর জন্য দরকার)
- [x] Bulk action bar — ≥1 row চেক করলে দেখা যায়, প্রতিটা action নিজের `formaction`/`formmethod` বহন করে (একটাই শেয়ার্ড ফর্ম, ভিন্ন ভিন্ন URL-এ পোস্ট করে), extensible
- [x] AJAX-free — pure Blade + query string, কোনো fetch/XHR নেই; sort/search/page সব সাধারণ GET লিংক
- [x] `HasBulkActions` trait — `bulkDestroy(Request, Builder, redirectRoute)`, Phase 4-এর `data-nk-confirm` দিয়ে ক্লায়েন্ট-সাইড কনফার্ম করানো হয় (route handler-এর নাম `bulkDestroy` রাখা যায়নি কারণ ট্রেইট মেথডের সাথে নাম কলিশন হয়ে recursion তৈরি করত — কন্ট্রোলারের অ্যাকশন `bulkDelete`, যেটা ভেতরে ট্রেইটের `bulkDestroy()` কল করে)
- [x] Export hook (CSV) — stub only, প্ল্যান অনুযায়ী এই ফেজে বাস্তবায়ন করা হয়নি
- [x] **ডেমো/প্রুফ**: `/admin/users` — read-only Users listing (search: name+email, sort: name/email/created_at, bulk-delete) real DB data দিয়ে। Full CRUD/roles আসবে Phase 8-এ একই রাউটে। মেনুতে "Users" যোগ করা হয়েছে।

**নোট:**

- Pagination-এর জন্য কাস্টম view `admin::components.pagination` — Laravel-এর built-in Tailwind/Bootstrap pagination view-এর বদলে, `$paginator->links('admin::components.pagination')` দিয়ে।
- একটা সাবটল বাগ ধরা পড়েছিল ও ঠিক করা হয়েছে: search term-এ literal `%`/`_` থাকলে LIKE wildcard হিসেবে ম্যাচ হয় (SQL LIKE ESCAPE clause ছাড়া escaping কোনো কাজ করে না) — Laravel-এর কোনো fluent builder API নেই ESCAPE clause যোগ করার জন্য, আর raw SQL দিয়ে করলে PHPStan-এর `literal-string` টাইপ চেক ফেল করে (column নাম রানটাইম ভ্যারিয়েবল থেকে আসায়)। সমাধান: escaping বাদ দেওয়া হয়েছে — এটা বেশিরভাগ admin search box-এর মতোই আচরণ (কোনো security issue না, শুধু edge-case UX), documented + test করা।

---

## Phase 6 — SMTP, Analytics, Search Console, FB Messenger, WhatsApp Setup ✅

**গোল:** Settings-driven integration panel (values saved in DB, not just `.env`)।

- [x] `App\Models\Admin\IntegrationSetting` + `integration_settings` migration — **grouped JSON per integration** (`group` unique + `data`), `data` কলাম পুরোটাই `encrypted:array` cast দিয়ে এনক্রিপ্টেড (SMTP পাসওয়ার্ড আলাদা করে এনক্রিপ্ট মনে রাখা লাগে না)। Read/write `App\Support\Admin\IntegrationSettings` সার্ভিসের মাধ্যমে — cache-ব্যাকড (`rememberForever` + `set()`-এ `forget()`), DB টেবিল না থাকলেও (fresh install) ভাঙে না (try/catch → `[]`)
- [x] UI tabs (`/admin/settings/integrations`, Alpine tab-switching, AJAX-free, প্রতিটা ট্যাব নিজের POST route-এ সাবমিট করে `?tab=` প্যারাম দিয়ে ফিরে আসে):
    - **SMTP** — host/port/user/pass/encryption/from — "Send test email" বাটন সেভ + টেস্ট মেইল একসাথে করে, **সবসময় সাইন-ইন করা অ্যাডমিনের নিজের ইমেইলে যায়** (arbitrary recipient না); পাসওয়ার্ড ফর্মে কখনো ফিরে দেখানো হয় না, blank রাখলে আগের পাসওয়ার্ড থেকে যায়
    - **Google Analytics** — measurement ID + enable টগল
    - **Google Search Console** — verification meta tag content (HTML ফাইল আপলোড এই ফেজে বাদ — Media Library লাগবে, Phase 12-এর পরে সহজ হবে)
    - **Facebook Messenger** — Page ID/App ID + chat plugin টগল
    - **WhatsApp** — নাম্বার + কাস্টম মেসেজ + floating widget টগল
- [x] Runtime mail config override — `IntegrationSettings::applyRuntimeMailConfig()`, `AppServiceProvider::boot()`-এ প্রতি রিকোয়েস্টে কল হয়; SMTP host সেভ না থাকলে no-op (`.env`/`config/mail.php` অক্ষত)
- [x] `<x-admin::tracking-scripts />` — GA4 লোডার, GSC ভেরিফিকেশন meta, Messenger chat plugin, নিজের বানানো floating WhatsApp বাটন (official widget না) — সবকিছু নিজের `IntegrationSettings` resolve করে (কোনো view composer লাগে না), তাই `admin::` নেমস্পেসের বাইরেও কাজ করে। `resources/views/app.blade.php`-এর `</body>`-এর ঠিক আগে বসানো হয়েছে (এই প্রজেক্টের একমাত্র পাবলিক Blade shell) — কিছু কনফিগার না থাকলে কিছুই রেন্ডার হয় না

**বিস্তারিত/সিদ্ধান্ত:**

- WhatsApp বাটনের CSS ইনলাইন `<style>` ব্লকে — admin.css পাবলিক সাইটে লোড হয় না বলে `nk-*` ক্লাসে নির্ভর করলে স্টাইলবিহীন থাকত।
- `php artisan config:cache`-এর সাথে বেমানান — সেটা রানটাইম DB override-কে একটা স্ট্যাটিক ফাইলে ফ্রিজ করে ফেলবে। লোকাল ডেভে সমস্যা না; Phase 20-এ প্যাকেজ README-তে উল্লেখ করা দরকার।
- Debugging নোট: PHPStan-এর `nullsafe.neverNull` রুল একটা জেনুইন কারেক্ট পয়েন্ট ধরেছিল — PHP-র `??` অপারেটর নিজেই `?->`-এর মতো null-safe (isset-সদৃশ) আচরণ করে চেইনের বাম পাশে, তাই `$x?->prop ?? $default` -এ `?` টা রিডানড্যান্ট। ইনভেস্টিগেট করে নিশ্চিত হয়ে ঠিক করা হয়েছে (PHPStan সাজেশন ভুল ছিল না)।

---

## Phase 7 — Menu, Role & Permission Management (Spatie) ✅

**গোল:** UI দিয়ে menu + role + permission ম্যানেজ করা।

- [x] `spatie/laravel-permission` — মাইগ্রেশন পাবলিশ + রান, `User` মডেলে `HasRoles` ট্রেইট, guard `admin-kit.guard` (web) এর সাথে মেলানো
- [x] `menus` টেবিল — label, icon, route/url (ঠিক একটা লাগবে, দুটোই না), parent_id (self-ref, cascade delete), order, permission_name, is_active। `App\Models\Admin\Menu` + `App\Support\Admin\DatabaseMenuSource` — Phase 1-এর `MenuManager::extend('database', …)` হুক-এ প্লাগ হয় (config driver-ই এখনো ডিফল্ট, `ADMIN_MENU_DRIVER=database` দিয়ে অপ্ট-ইন)
- [x] CRUD UI:
    - **Menus** — ট্রি ভিউ, drag-drop reorder (Alpine + SortableJS) — একমাত্র জায়গা যেখানে AJAX ব্যবহার হয়েছে (`fetch()`), বাকি সবকিছু AJAX-free ফুল-পেজ ফর্ম; parent picker নিজের এবং নিজের descendant-দের বাদ দেয় (সার্কুলার রেফারেন্স ঠেকাতে); route লিংক শুধু param-ছাড়া GET `admin.*` রাউট থেকে বাছাই করা যায় (`MenuRouteOptions`)
    - **Roles** — DataTable + permission checkbox matrix (module প্রিফিক্স দিয়ে গ্রুপড, যেমন `users.*`, `menus.*`)। Protected role (`Super Admin`, config-driven) রিনেম/ডিলিট/পারমিশন-স্ট্রিপ করা যায় না — কন্ট্রোলারে এক্সপ্লিসিট গার্ড
    - **Permissions** — create/delete শুধু, rename নেই ইচ্ছাকৃতভাবে (রাউট মিডলওয়্যার/সিডার/মেনু আইটেম সব নামের উপর নির্ভর করে, রিনেম করলে সব ভেঙে পড়বে নীরবে)
- [x] `App\Http\Middleware\Admin\CheckPermission` (alias `checkpermission:xxx`) — Menus/Roles/Permissions-এর নিজস্ব নতুন রাউটগুলো গার্ড করে। Users/Settings-Integrations (Phase 5/6) ইচ্ছাকৃতভাবে এখনো গার্ড করা হয়নি — Phase 8 ("User Management, Permission-Wise") এই retrofit-এর কথা এক্সপ্লিসিটলি বলে, তাই সেখানেই হবে। মেনু-হাইড করার কাজ Phase 1-এর `MenuManager::isVisible()` আগে থেকেই করে
- [x] `Gate::before()` হুক — protected role সবসময় সব পারমিশন চেক পাস করে (কোনো নির্দিষ্ট Permission row থাকা লাগে না) — তাই সব Permission row ডিলিট হয়ে গেলেও অন্তত একটা রোল লক-আউট-প্রুফ থাকে
- [x] `Database\Seeders\Admin\RolePermissionSeeder` — ৭টা বেস পারমিশন, তিনটা রোল (Super Admin সব পারমিশন সহ, Admin/Editor সংকীর্ণ সেট), + `menus` টেবিলে config-driven মেনুর সমতুল্য সিড (idempotent — দ্বিতীয়বার রান করলে ডুপ্লিকেট হয় না)

**দুটো আসল বাগ ধরা পড়েছে এই ফেজে, দুটোই ঠিক করা হয়েছে:**

1. কন্ট্রোলার অ্যাকশন আর ট্রেইট মেথডের নাম একই রাখলে (`bulkDestroy`) রিকার্শন হয়ে যেত — Phase 5-এর মতোই এবার `RoleController`/`PermissionController`-এ `bulkDelete()` নাম আলাদা রাখা হয়েছে।
2. **Blade কম্পাইলার বাগ** — কোনো ফরম্যাট ক্লোজার যদি ডাবল-কোটেড HTML অ্যাট্রিবিউট (`class="..."`) রিটার্ন করে আর সেটা সরাসরি `<x-admin::datatable :headers="[...]">`-এর ভেতরে ইনলাইন লেখা হয়, Blade-এর component-tag পার্সার নেস্টেড কোটের হিসাব রাখতে না পেরে পুরো ট্যাগটাকেই আনকম্পাইলড টেক্সট রেখে দেয় — পুরো ডেটাটেবিলটাই "খালি" দেখায় কোনো এরর ছাড়াই। ধরতে অনেকক্ষণ লেগেছে (ডেটা ঠিক আছে, ভিউ রেন্ডার করছে না — এই দুটো আলাদা করে টেস্ট করে বোঝা গেছে)। সমাধান: `roles/index.blade.php`-এ headers/bulk-actions অ্যারে `@php` ব্লকে বিল্ড করে `:headers="$roleHeaders"` (প্লেইন ভ্যারিয়েবল রেফারেন্স) — এই প্যাটার্নটা এখন থেকে যেকোনো জটিল ফরম্যাট ক্লোজার থাকা datatable-এ মানা উচিত।

---

## Phase 8 — User Management (Permission-Wise) ✅

**গোল:** ইউজার তৈরি + রোল/পারমিশন এসাইন।

- [x] `UserController` — ফুল CRUD (Phase 5-এর read-only লিস্টিং একই রাউটে এক্সটেন্ড হয়েছে) + status (active/suspended, `App\Enums\Admin\UserStatus` ব্যাকড enum)। Avatar upload **ইচ্ছাকৃতভাবে বাদ** — Phase 12 (Media Library) এর অপেক্ষায়, যেমন Phase 6-এ Search Console-এর HTML ফাইল আপলোডও বাদ দেওয়া হয়েছিল একই কারণে
- [x] Role(s) + direct permission override — চেকবক্স UI, `syncRoles()`/`syncPermissions()`। ফর্মে দেখানো "direct permissions" শুধু user-এর নিজস্ব সরাসরি পারমিশন (`$user->permissions`, `getAllPermissions()` না — পরেরটায় রোল থেকে ইনহেরিটেড পারমিশনও চলে আসত, যেটা ভুলভাবে টিক-মার্ক দেখাত)
- [x] Invite-by-email — Phase 2-এর password-reset টোকেন মেকানিজম পুনর্ব্যবহার করে (`Password::broker()->createToken()`), আলাদা `AdminUserInvited` নোটিফিকেশন (welcome-copy, রিসেট মেইলের থেকে আলাদা)। ইনভাইট করলে ইউজার একটা random ৪০-ক্যারেক্টার পাসওয়ার্ড দিয়ে তৈরি হয় (কখনো ব্যবহারযোগ্য না) আর ফর্মে টাইপ করা পাসওয়ার্ড (যদি থাকে) উপেক্ষা করা হয়
- [x] User list — Phase 5-এর DataTable + role filter dropdown (`?role=`)
- [x] **Suspension enforcement** — `LoginRequest::authenticate()` এখন `attempt()`-এর বদলে `validate()`+ম্যানুয়াল `login()` ব্যবহার করে, যাতে সাসপেন্ড হওয়া অ্যাকাউন্ট ভুল পাসওয়ার্ড থেকে আলাদা করে চেনানো যায় ("This account has been suspended" — জেনেরিক মেসেজ না, কারণ এটা তখনই দেখা যায় যখন পাসওয়ার্ড ইতিমধ্যে সঠিক, তাই অ্যাকাউন্ট এক্সিস্ট করে এটা লুকানোর মানে নেই)
- [x] **Permission-wise retrofit (Phase 7-এ যেটা ডেফার করা হয়েছিল)** — `routes/admin.php`-এ Users-এর প্রতিটা অ্যাকশন এখন নিজের পারমিশন চেক করে (`users.view`/`create`/`update`/`delete` — ব্লাংকেট একটা চেক না, যাতে Editor রোল লিস্ট দেখতে পারে কিন্তু তৈরি/ডিলিট করতে না পারে), এবং `settings/integrations/*` পুরোটা `settings.integrations.manage` দিয়ে গার্ডেড। `config('admin-kit.menu.items')`-এও সংশ্লিষ্ট `permission` কী ফিরিয়ে আনা হয়েছে (Phase 7-এ যেটা সরিয়ে রাখা হয়েছিল ঠিক এই মুহূর্তটার জন্য)

**সিডার আপডেট:** নতুন পারমিশন `users.create`, `users.update` — Admin রোলে যোগ, Editor রোল আগের মতোই view-only (`dashboard.view`, `users.view`)।

**বিদ্যমান টেস্ট রিফ্যাক্টর:** Phase 5/6-এর `AdminUsersTest`/`AdminIntegrationSettingsTest`-এ এখন প্রতিটা টেস্টের acting user-কে দরকারি পারমিশন দেওয়া হয় (আগে গার্ড না থাকায় দরকার ছিল না) — এটাই আসলে "Permission-Wise" রিফ্যাক্টরের আসল প্রমাণ যে গার্ডগুলো সত্যিই কাজ করছে।

---

## Phase 9 — User Profile & Password Reset (Self-Service) ✅

**গোল:** লগইন করা ইউজার নিজের প্রোফাইল/পাসওয়ার্ড ম্যানেজ করবে।

- [x] `ProfileController` — update name/email (avatar upload **ইচ্ছাকৃতভাবে বাদ**, Phase 8-এর মতোই একই কারণে Phase 12/Media Library-এর অপেক্ষায়), email পরিবর্তনের আগে `current_password` ভেরিফিকেশন (Laravel-এর built-in `current_password:GUARD` rule)। ইমেইল পরিবর্তন হলে `email_verified_at` null করে দেওয়া হয়
- [x] `PasswordController` — change password (current + new + confirm), সফল হলে স্বয়ংক্রিয়ভাবে "log out other devices"-ও ট্রিগার হয় (হাইজ্যাক হওয়া সেশন থাকলে পাসওয়ার্ড পরিবর্তনেই সেটা মরে যায়, আলাদা বাটন চাপার অপেক্ষায় থাকে না)
- [x] Session management: "Log out other devices" — Laravel-এর `Auth::guard()->logoutOtherDevices($password)`। **গুরুত্বপূর্ণ আবিষ্কার:** এটা কাজ করার জন্য রাউটে `AuthenticateSession` মিডলওয়্যার (`auth.session`) সক্রিয় থাকা লাগে (নাহলে শুধু পাসওয়ার্ড রি-হ্যাশ হয়, অন্য সেশন আসলে টার্মিনেট হয় না) — `config('admin-kit.route.auth_middleware')`-এ যোগ করা হয়েছে, শুধু admin গ্রুপে (পাবলিক সাইটের Fortify/Inertia auth ফ্লো অক্ষত রাখতে গ্লোবাল `web` গ্রুপে না)। দুটো cookie jar দিয়ে সত্যিকারের দুই-ডিভাইস সিমুলেশন করে লাইভ smoke test-এ ভেরিফাই করা হয়েছে — ডিভাইস ১ লগইন থাকে, ডিভাইস ২ পরের রিকোয়েস্টেই লগইন পেজে ফেরত যায়
- [x] Activity log entries — `AuthLogger`-এ তিনটা নতুন মেথড (`profileUpdated`, `passwordChanged`, `otherDevicesLoggedOut`), Phase 2/8-এর মতোই একই প্যাটার্নে (Phase 18-এ Spatie activitylog-এ সরানোর অপেক্ষায়)

**নামযুক্ত এরর ব্যাগ (named error bags):** এক পেজে তিনটা আলাদা ফর্মের প্রত্যেকটাতেই `current_password` ফিল্ড আছে — ডিফল্ট শেয়ার্ড `$errors` ব্যাগ ব্যবহার করলে একটা ফর্মের এরর আরেকটার একই-নামের ফিল্ডে "লিক" করত। `PasswordUpdateRequest::$errorBag = 'updatePassword'` আর `$request->validateWithBag('logoutOtherDevices', ...)` দিয়ে সমাধান করা হয়েছে (Jetstream-এর established প্যাটার্ন)।

**টেস্ট:** `AdminProfileTest`-এ ১৭টা নতুন টেস্ট — page render, name-only update (পাসওয়ার্ড ছাড়া), email change validation (password required, uniqueness, verified_at reset), password change (rehash confirm, wrong-password reject, confirmation mismatch), logout-other-devices (validation, নিজের সেশন বেঁচে থাকে), আর activity log assertions। "অন্য ডিভাইস আসলেই লগআউট হয়" এর মেকানিজম দুই ভাগে টেস্ট করা হয়েছে যাতে ভঙ্গুর মাল্টি-কুকি-জার সিমুলেশনের দরকার না পড়ে: (১) logout-other-devices পাসওয়ার্ড হ্যাশ বদলে দেয় তা assert করা, (২) সেশনে পুরনো/অমিল হ্যাশ থাকলে `AuthenticateSession` তা ধরে গেস্ট বানিয়ে দেয় তা আলাদাভাবে assert করা — দুটো মিলিয়েই পুরো চেইন প্রমাণিত হয়, আর লাইভ smoke test দুই আসল cookie jar দিয়ে end-to-end নিশ্চিত করেছে।

---

## Phase 10 — Dashboard (Widget System) ✅

**গোল:** Modular, drag-arrangeable widget dashboard।

- [x] Widget contract/interface: `App\Support\Admin\AdminWidget` (`key()`, `title()`, `size()`, `render(): View`) — `key()` প্ল্যানের অরিজিনাল স্পেকে ছিল না, ড্র্যাগ-অর্ডার localStorage-এ স্টেবলভাবে সেভ করতে যোগ করা হয়েছে (ক্লাস নাম দিয়ে নয়, কারণ রিফ্যাক্টরে ক্লাস নাম বদলাতে পারে)
- [x] `App\Support\Admin\WidgetManager` — `config('admin-kit.widgets')`-এর FQCN লিস্ট কন্টেইনার দিয়ে resolve করে (MenuManager-এর মতোই), যাতে widget নিজের dependency inject করতে পারে
- [x] Built-in widgets — বাস্তব ডেটা দিয়ে:
    - `StatCardsWidget` — Users/Roles/Permissions/Menu items real count (আগে `DashboardController`-এ ছিল, এখান থেকে সরানো হয়েছে)
    - `UsersGrowthChartWidget` — গত ৬ মাসের নতুন ইউজার (Chart.js line chart)। DB-ড্রাইভার-নির্ভর `DATE_FORMAT`/`strftime` এড়িয়ে PHP-তে (Carbon দিয়ে) মাস-ভিত্তিক group করা হয়েছে, যাতে MySQL (dev) আর SQLite (টেস্ট) দুটোতেই একই আচরণ হয় — এটা রিইউজেবল প্যাকেজের জন্য জরুরি
    - **ইচ্ছাকৃতভাবে বাদ** — "recent activity feed" (AuthLogger এখনো শুধু লগ ফাইলে লেখে, DB-তে queryable টেবিল Phase 18-এ Spatie activitylog আসার পর হবে) আর "latest contact messages" (ContactMessage মডেল Phase 16-এর আগে এক্সিস্টই করে না) — একই প্যাটার্ন যেমন Phase 8-এ avatar upload বাদ দেওয়া হয়েছিল
- [x] `config('admin-kit.widgets')` array — প্রতিটা প্রজেক্ট নিজের widget class যোগ করে এখানে
- [x] **Chart.js ইন্টিগ্রেশন** — পুরো `chart.js/auto` না এনে শুধু line chart-এর জন্য দরকারি piece-গুলো (`LineController`, `LineElement` ইত্যাদি) রেজিস্টার করা হয়েছে বান্ডেল সাইজ কমাতে; `admin.js`-এর `initCharts()` generic — যেকোনো ভবিষ্যৎ widget `<canvas data-chart='{...}'>` রেন্ডার করলেই চার্ট পেয়ে যাবে, কালার `--nk-primary` থিম টোকেন থেকে রানটাইমে পড়া হয় (রিব্র্যান্ডে কোনো JS বদলাতে হবে না)
- [x] **ড্র্যাগ-অ্যারেঞ্জেবল** — `initWidgetGrid()`-এ SortableJS (মেনু-ট্রি-র Phase 7 প্যাটার্নের মতোই handle-based drag), অর্ডার localStorage-এ সেভ (`nk.widgetOrder`)
- [ ] ~~Optional: per-user widget layout saved in DB~~ — প্ল্যানেই "v2 nice-to-have stretch goal" হিসেবে চিহ্নিত ছিল, তাই স্কিপ করা হয়েছে; localStorage-ভিত্তিক (per-browser) অর্ডার তার বদলে বাস্তব, কাজ-করা drag-and-drop দেয় সার্ভার/অথ ইনফ্রা ছাড়াই

**একটা রিয়েল বাগ ধরা পড়েছে ও ঠিক করা হয়েছে (লাইভ smoke test-এ):** চার্ট widget-এর `data-chart` অ্যাট্রিবিউটে প্রথমে `@js()` ডিরেক্টিভ ব্যবহার করা হয়েছিল, যা raw JSON না দিয়ে `JSON.parse('...')` — একটা JS *expression* — আউটপুট করে (এটা `onclick="..."` এর মতো JS-কনটেক্সটের জন্য ঠিক, কিন্তু plain HTML attribute-এর জন্য না)। ব্রাউজারে `canvas.dataset.chart` তখন সেই wrapper টেক্সট-সহ স্ট্রিং হিসেবে ফেরত দিত, আর admin.js-এর `JSON.parse()` কল সাইলেন্টলি ফেইল করত। প্লেইন `{{ json_encode(...) }}` (Blade auto-escape) দিয়ে ঠিক করা হয়েছে, আর একটা রিগ্রেশন টেস্ট যোগ করা হয়েছে যা রেন্ডার-করা attribute-কে ব্রাউজারের মতোই HTML-decode করে `json_decode` করে দেখে।

**টেস্ট:** `WidgetManagerTest` (config থেকে resolve, order, dependency injection), `StatCardsWidgetTest`, `UsersGrowthChartWidgetTest` (মাস-ভিত্তিক bucketing সঠিক কিনা), `AdminDashboardWidgetsTest` (default widgets রেন্ডার হয়, প্রজেক্ট-নিজস্ব widget config দিয়ে override হয়, খালি widget list ভেঙে পড়ে না, chart attribute ভ্যালিড JSON-এ ডিকোড হয়) — মোট ১১টা নতুন টেস্ট।

---

## Phase 11 — CMS Module ✅

**গোল:** Pages/Posts/Sections editable content, generic enough for any project।

**নোট — ফেজ অর্ডার:** প্ল্যানের নিজের "Suggested Build Order" সেকশন CMS-এর আগে Phase 12 (Media Library) আর Phase 13/14 (Settings/SEO) করার পরামর্শ দিয়েছিল, কিন্তু এই সেশনে এতদিন ধরে numeric অর্ডারেই (1→2→...→10→11) এগোনো হচ্ছিল, তাই সেই ধারাবাহিকতা বজায় রাখা হয়েছে। যেখানে Phase 11 আগাম-নির্ভরতা রাখে (Media Library, SEO fields), সেখানে Phase 8/9/10-এর মতোই explicit deferral প্যাটার্ন অনুসরণ করা হয়েছে — নিচে দেখুন।

- [x] `App\Models\Admin\Page` (title, slug, content, status, template, published_at) — **SEO fields ইচ্ছাকৃতভাবে বাদ**, migration-এ কমেন্ট করে বলা আছে Phase 14-এর নিজস্ব reusable SEO trait/কলাম আসার পর যোগ হবে (avatar upload-কে Phase 12-এর জন্য বাদ দেওয়ার মতোই একই প্যাটার্ন)
- [x] WYSIWYG এডিটর — **Quill** (self-hosted npm প্যাকেজ, কোনো cloud API key/CDN লাগে না — TinyMCE-এর বদলে বেছে নেওয়া হয়েছে কারণ self-hosted হলেও TinyMCE-এর নতুন মেজর ভার্সনগুলো API key-এর জন্য nag করে)। `initEditors()` (admin.js) generic — `[data-editor]` wrapper একটা plain `<textarea data-editor-target>`-কে ঘিরে থাকে, তাই JS বন্ধ থাকলেও ফর্ম সাবমিট কাজ করে (progressive enhancement)। টুলবারের ইমেজ বাটন এখন base64 inline embed করে — **ইচ্ছাকৃতভাবে অস্থায়ী**, Phase 12-এর Media Library আসার পর প্রকৃত আপলোডে বদলাবে
- [ ] ~~Optional: Section/block-based builder~~ — প্ল্যানেই "v2/stretch" চিহ্নিত ছিল, স্কিপ করা হয়েছে
- [x] স্লাগ — টাইটেল থেকে অটো-জেনারেট (Alpine, `slugTouched` ফ্ল্যাগ দিয়ে ম্যানুয়াল এডিটের পর আর ওভাররাইট করে না) + সার্ভার-সাইড ফলব্যাক (`Page::generateUniqueSlug()`, JS বন্ধ থাকলেও কাজ করে) + uniqueness validation। **ডিজাইন সিদ্ধান্ত:** ফাঁকা স্লাগ থেকে অটো-জেনারেট করা কলিশন চুপচাপ `-2`/`-3` সাফিক্স দিয়ে ডিজঅ্যাম্বিগুয়েট হয়, কিন্তু অ্যাডমিন নিজে টাইপ করা স্লাগ কলিশন করলে ভ্যালিডেশন এরর দেয় — নিজের টাইপ করা ভ্যালু চুপচাপ বদলে দেওয়া surprising হতো
- [x] Draft/Published/Scheduled status (`App\Enums\Admin\PageStatus`, `UserStatus`-এর মতোই) — Scheduled-এর জন্য future `published_at` লাগে (ভ্যালিডেশনে `after:now`), Published খালি রাখলে এখনকার সময় বসে, Draft-এ `published_at` সবসময় null

**পারমিশন/মেনু:** `pages.view/create/update/delete` — Users-এর (Phase 8) মতোই per-action গার্ড। Editor রোল এবার `pages.view/create/update` পায় (কিন্তু delete না) — যেহেতু "Editor" নামটাই কনটেন্ট-এডিটিং-এর জন্য, Users-এর read-only প্যাটার্নের থেকে আলাদা এখানে ইচ্ছাকৃতভাবে। সাইডবারে "Pages" নতুন টপ-লেভেল আইটেম যোগ হয়েছে (Users-এর ঠিক পরে)।

**পাবলিক রেন্ডারিং নেই:** Phase 11-এর বুলেট লিস্টে কোথাও পাবলিক রুট/টেমপ্লেট রেন্ডারিং চাওয়া হয়নি — এটা বিশুদ্ধ অ্যাডমিন CRUD, ঠিক Users/Roles/Menus-এর মতোই। `template` ফিল্ড এখন শুধু মেটাডেটা (`config('admin-kit.cms.templates')` থেকে ড্রপডাউন) — কোন প্রজেক্ট কীভাবে পাবলিকলি রেন্ডার করবে সেটা তাদের সিদ্ধান্ত।

**নিরাপত্তা নোট:** `quill@2.0.3` (latest) একটা low-severity XSS অ্যাডভাইজরি বহন করে ("XSS via HTML export"), আর `npm audit fix`-এর সাজেশন উল্টো — পুরনো ভার্সনে downgrade (কোনো প্যাচড ভার্সন এখনো নেই)। যেহেতু Page content শুধু trusted, permission-gated অ্যাডমিন ইউজাররাই লেখে (পাবলিক arbitrary ইনপুট না, আর এখনো কোনো পাবলিক রেন্ডারার-ই নেই), আর যেকোনো CMS-এর WYSIWYG কনটেন্টই আসলে trusted raw HTML হিসেবে স্টোর হয় — তাই downgrade না করে latest ভার্সনই রাখা হয়েছে, রিস্কটা এখানে ডকুমেন্ট করে।

**টেস্ট:** `AdminPagesTest`-এ ১৭টা টেস্ট — access control (per-permission গার্ড), listing, create (অটো-স্লাগ, কলিশন ডিজঅ্যাম্বিগুয়েশন, এক্সপ্লিসিট কলিশন রিজেকশন, স্লাগ ফরম্যাট, টেমপ্লেট ভ্যালিডেশন), status/published_at লজিক (published→now default, scheduled→future-date required), update (নিজের স্লাগ কলিশন না, অন্য পেজের স্লাগ কলিশন হ্যাঁ), delete + bulk delete। লাইভ smoke test-এ curl দিয়ে লগইন → পেজ তৈরি → এডিট পেজে কনটেন্ট প্রি-ফিল যাচাই → এক্সপ্লিসিট স্লাগ কলিশন রিজেকশন — সব বাস্তব HTTP রিকোয়েস্টে ভেরিফাই করা হয়েছে।

---

## Phase 12 — Media Library ✅

**গোল:** কেন্দ্রীয় ফাইল/ইমেজ ম্যানেজার।

- [x] `spatie/laravel-medialibrary` (v11.23.5) ইনস্টল। **ডিজাইন সিদ্ধান্ত:** medialibrary-এ কোনো "no owner" ধারণা নেই (media সবসময় কোনো Eloquent মডেলের সাথে যুক্ত থাকে), তাই একটা কেন্দ্রীয় লাইব্রেরি বানাতে `App\Models\Admin\MediaFolder` তৈরি করা হয়েছে — প্রতিটা আপলোড কোনো না কোনো folder-এর media, folder না বেছে নিলে অটো-প্রোভিশন করা "Uncategorized" ডিফল্ট folder-এ যায় (protected role-এর মতোই ডিলিট করা যায় না)। ফাইল-খালি folder ছাড়া অন্য কোনো folder ডিলিট করা যায় না (move/re-parent ফিচার ছাড়াই ডেটা-লস এড়াতে) — flat structure, nested না, যেহেতু প্ল্যানে শুধু "folder/collection grouping" চাওয়া হয়েছিল, ট্রি না
- [x] Media Manager UI (`admin::pages.media.index`) — grid view, drag-drop আপলোড (Alpine দিয়ে, প্লেইন ফাইল-ইনপুট+সাবমিট বাটন ফলব্যাক-সহ যাতে JS বন্ধ থাকলেও কাজ করে), folder সাইডবার, filename সার্চ (`HasDataTable` পুনর্ব্যবহার করে কুয়েরি লজিকের জন্য, রেন্ডারিং টেবিলের বদলে গ্রিড)
- [x] Image conversions — `thumbnail` (200×200) ও `medium` (800px wide), আপলোডের সাথে সাথে সিঙ্ক্রোনাসলি জেনারেট হয় (`queue_conversions_by_default` ডিফল্ট `false`-এ ওভাররাইড করা হয়েছে, যাতে queue worker ছাড়াই আপলোডের রেসপন্সেই থাম্বনেইল কাজ করে)
- [x] **Reusable Media Picker** — `<x-admin::media-picker />` (layout-এ একবার রেন্ডার করা global modal, `$store.nkMediaPicker` দিয়ে চালিত, `nkConfirm`-এর ঠিক একই আর্কিটেকচার) + `NK.pickMedia()` (Promise-based, `NK.confirm()`-এর মতোই) + `<x-admin::form.media-picker>` (হিডেন-ইনপুট+প্রিভিউ ফিল্ড wrapper)। **দুটো আসল consumer-এ ওয়্যার করা হয়েছে:**
    - **User avatar** — Phase 8-এ ইচ্ছাকৃতভাবে বাদ দেওয়া অ্যাভাটার আপলোড এখন এই Media Picker দিয়ে সম্পন্ন (`users.avatar_media_id` FK, `User::avatar()`/`avatarUrl()`), টপবার + ইউজার লিস্টে দেখা যায়
    - **CMS (Pages) ইমেজ ইনসার্শন** — Phase 11-এ Quill-এর ডিফল্ট base64 ইমেজ হ্যান্ডলার এখন Media Picker দিয়ে প্রতিস্থাপিত (`initEditors()`-এ কাস্টম টুলবার হ্যান্ডলার)
    - Settings-এ logo/favicon (Phase 13, এখনো তৈরি হয়নি) — deferred, একই প্যাটার্নে documented
- [x] Storage driver agnostic — `config('media-library.disk_name')` (env `MEDIA_DISK`, ডিফল্ট 'public') ইতিমধ্যেই `config/filesystems.php`-এর local/s3 disk দুটোর মধ্যে যেকোনোটা বেছে নিতে পারে, প্যাকেজ নিজেই এটা দেয় বলে আলাদা কোনো admin-kit কনফিগ লাগেনি

**পারমিশন:** একটা ব্লাংকেট `media.manage` (Users/Pages-এর per-action split না, বরং Menus/Roles/Permissions/Settings-এর মতো — এটাই এই কোডবেসে বেশি প্রচলিত প্যাটার্ন, ইউটিলিটি-টুল রিসোর্সের জন্য)। কিন্তু **picker JSON এন্ডপয়েন্ট (`GET admin/media/picker`) ইচ্ছাকৃতভাবে `media.manage`-এর আড়ালে না** — শুধু auth লাগে, কারণ এটা Pages/Profile-এর মতো ইতিমধ্যে-গার্ডেড কনটেক্সটের ভেতর থেকে ব্যবহৃত হয়; পুরো লাইব্রেরি ম্যানেজমেন্ট পেজের দরজা এটা না।

**একটা রিয়েল বাগ ধরা পড়েছে ও ঠিক করা হয়েছে:** `HasBulkActions::bulkDestroy()` (Users/Pages-এ ব্যবহৃত shared trait) একটাই bulk SQL DELETE ইস্যু করে, যেটা প্রতিটা row-এর জন্য আলাদা Eloquent model event fire করে না — কিন্তু medialibrary-এর ফাইল-ডিলিট (ডিস্ক থেকে আসল ফাইল সরানো) ঠিক সেই `deleted` ইভেন্টের উপর নির্ভর করে (`MediaObserver`)। তাই Media bulk-delete-এ ওই trait ব্যবহার না করে `Media::destroy($ids)` (যেটা প্রতিটা row আলাদাভাবে লোড+ডিলিট করে) ব্যবহার করা হয়েছে। টেস্টে এটা ভেরিফাই করা হয়েছে ডিস্ক-এক্সিস্টেন্স অ্যাসার্শন দিয়ে (শুধু DB row মুছে যাওয়া দেখলে এই বাগ ধরা পড়ত না)।

**টেস্ট:** `AdminMediaTest`-এ ১৯টা টেস্ট (আপলোড+conversions, folder filter/CRUD, delete/bulk-delete ডিস্ক-ক্লিনআপ-সহ, picker endpoint auth+search), `AdminUsersTest`/`AdminFoundationTest`-এ অ্যাভাটার-সংক্রান্ত নতুন টেস্ট — মোট ২৩টা নতুন টেস্ট। লাইভ smoke test-এ curl দিয়ে ভেরিফাই: login → folder create → picker JSON (থাম্বনেইল URL আসলেই serve হয় তা curl দিয়ে যাচাই) → avatar picker ফিল্ড দিয়ে সেট করা → টপবারে আসল ছবি দেখা যাওয়া, সব বাস্তব HTTP রিকোয়েস্টে।

---

## Phase 13 — Centralized Settings System ✅

**গোল:** এক জায়গা থেকে পুরো সাইটের branding/contact/social নিয়ন্ত্রণ।

- [x] `settings` table — প্ল্যানে বলা ঠিক সেই শেপে (key, value, group, type), IntegrationSettings-এর (Phase 6) group+JSON-blob শেপের থেকে ইচ্ছাকৃতভাবে আলাদা — one-row-per-KEY, IntegrationSettings-এর one-row-per-GROUP-এর বদলে, যেহেতু প্ল্যান স্কিমাটা এক্সপ্লিসিটলি বলে দিয়েছিল। `App\Support\Admin\SettingsManager` + global `setting('site_name')` হেল্পার (`app/Support/helpers.php`, composer.json-এর `autoload.files`-এ রেজিস্টার করা)
- [x] **ক্যাশ — "tagged cache" চাওয়া হয়েছিল, কিন্তু প্লেইন cache key ব্যবহার করা হয়েছে**: `Cache::tags()` শুধু Redis/Memcached-জাতীয় স্টোরে কাজ করে, এই প্রজেক্টের (আর বেশিরভাগ কনজিউমিং প্রজেক্টের) আসল ডিফল্ট database/file ড্রাইভারে থ্রো করে — ঠিক এই কারণেই Phase 6-এর IntegrationSettings-ও tags এড়িয়ে গিয়েছিল। save-এ পুরো ক্যাশ forget হয়ে যাওয়াই একই ফলাফল দেয় কোনো ড্রাইভার-অনুমান ছাড়া
- [x] Tabs: **General** (site name, tagline, logo/favicon media-picker, timezone — blank মানে "app default ব্যবহার করো"), **Contact** (email, phone, address, map embed — raw HTML না, শুধু একটা URL যেটা `<iframe src="...">`-এ বসে), **Social** (dynamic {platform, url} রিপিটার, migration ছাড়াই যেকোনো platform যোগ করা যায় — একটাই `social_links` (type='json') সেটিং হিসেবে স্টোর হয়)
- [x] Logo/favicon — `<x-admin::form.media-picker>` দিয়ে (Phase 12)
- [x] `<x-admin::social-icons />` — **ডিজাইন সিদ্ধান্ত:** platform-ভিত্তিক ব্র্যান্ড লোগো আঁকা হয়নি (Facebook/Instagram/... আইকন) — যেহেতু platform নাম সম্পূর্ণ ফ্রিফর্ম (admin যা টাইপ করে), একটা ভুল/inaccurate ব্র্যান্ড-মার্ক আঁকা কোনো আইকন না থাকার চেয়ে খারাপ। generic external-link glyph + platform-এর নিজের নাম টেক্সট হিসেবে দেখানো হয়

**রানটাইম ব্র্যান্ড ওভাররাইড:** Phase 1-এর `<x-admin::brand />` কম্পোনেন্টের নিজের কমেন্টই বলেছিল "Settings (Phase 13) later overrides those config values" — `SettingsManager::applyRuntimeBrandConfig()` এখন ঠিক সেটাই করে (`IntegrationSettings::applyRuntimeMailConfig()`-এর একই প্যাটার্নে, `AppServiceProvider::bootAdminKit()`-এ প্রতি রিকোয়েস্টে কল হয়) — সাইডবার ব্র্যান্ড, favicon `<link>` কোনো পরিবর্তন ছাড়াই এই নতুন মান পিক আপ করে।

**দুটো রিয়েল বাগ ডেভেলপমেন্টের মাঝেই ধরা পড়েছে ও ঠিক হয়েছে:**
1. **বুট-ক্র্যাশ রিস্ক:** যেহেতু `applyRuntimeBrandConfig()` প্রতিটা রিকোয়েস্টে (এমনকি প্রতিটা artisan কমান্ডেও) চলে, একটা corrupt/broken cache entry (এই সেশনেই সত্যিকারের ঘটেছিল — dev DB-তে stale cache row-এর কারণে `php artisan cache:clear`-ই বুট হতে পারছিল না, নিজেকে ঠিক করার জন্য যে কমান্ড দরকার ছিল সেটাই ক্র্যাশ করছিল!) পুরো বুট সাইকেল নামিয়ে দিতে পারত। ফিক্স: cache read-কে try/catch দিয়ে wrap করে লাইভ DB read-এ fallback করা — একই ঝুঁকি থাকা `IntegrationSettings::get()`-এও (Phase 6) একই ফিক্স প্রয়োগ করা হয়েছে, কারণ এটা সেই কোডেও সমানভাবে সত্যি ছিল, শুধু কখনো ট্রিগার হয়নি
2. **Collection generic covariance:** `Illuminate\Support\Collection`-এর template covariant না হওয়ায় PHPStan বারবার "should return X but returns X" এরর দিচ্ছিল, এমনকি টাইপ হুবহু মিলিয়েও। সমাধান: ইন্টারনাল প্লাম্বিং-এ Collection-এর বদলে প্লেইন PHP array ব্যবহার করা (`rows()`/`get()`/`group()`) — এটা PHPStan-ও খুশি করেছে, আর cache-এ একটা Collection অবজেক্টের বদলে প্লেইন array সিরিয়ালাইজ হওয়ায় (১) নম্বর বাগটার প্রকৃত root cause-ও কমিয়ে দিয়েছে (অবজেক্ট আনসিরিয়ালাইজেশন প্লেইন array-এর চেয়ে বেশি ভঙ্গুর)

**টেস্ট:** `AdminSettingsTest`-এ ২২টা টেস্ট — সব তিনটা ট্যাব, রানটাইম ব্র্যান্ড ওভাররাইড, blank social-row ফিল্টারিং, `setting()` হেল্পার, cache-invalidation-on-save, আর **broken cache entry থেকে গ্রেসফুল ডিগ্রেডেশনের একটা ডেডিকেটেড রিগ্রেশন টেস্ট** (উপরের বাগ ১ নম্বর যাতে চুপচাপ ফিরে না আসে)। লাইভ smoke test-এ curl দিয়ে real HTTP রিকোয়েস্টে ভেরিফাই করা হয়েছে — General/Contact/Social তিনটাই সেভ, আর সবচেয়ে গুরুত্বপূর্ণভাবে সেভের পর একটা সম্পূর্ণ নতুন রিকোয়েস্টে (dev server, টেস্ট কার্নেলের boot-once সীমাবদ্ধতা ছাড়াই) সাইডবার ব্র্যান্ড নাম আসলেই বদলে যাওয়া দেখা গেছে।

---

## Phase 14 — SEO Setup ✅

**গোল:** প্রতিটা পেজে meta control + global SEO defaults।

- [x] **Polymorphic `seo_meta` টেবিল** (`seoable_type`/`seoable_id` + meta_title, meta_description, meta_keywords, og_image_media_id, canonical_url, robots) — শুধু `Page`-এর জন্য না, `App\Models\Admin\Concerns\HasSeoMeta` ট্রেইট যেকোনো ভবিষ্যৎ কনটেন্ট-টাইপ একই ফিল্ড বিনামূল্যে পায় (নিজের মাইগ্রেশন ছাড়াই — শুধু `use HasSeoMeta;` + ফর্মে `@include('admin::partials.seo-fields', ...)`)। খালি সাবমিশন সম্পূর্ণ row-ই মুছে দেয় (null-ভর্তি row রাখার বদলে) — "override নেই" মানে ঠিক সেটাই বোঝায়
- [x] Global defaults — Settings-এ (Phase 13) নতুন ৪র্থ ট্যাব "SEO": `meta_title_suffix`, `default_og_image_media_id` (Media Picker), `robots_txt`
- [x] `<x-admin::seo-meta :model="$page" />` — পূর্ণ `<title>`, meta description/keywords, canonical, robots, OpenGraph, Twitter Card। explicit override না থাকলে fallback title-এ suffix যোগ হয়, explicit override থাকলে হুবহু সেটাই ব্যবহার হয় (suffix ছাড়া) — অ্যাডমিনকে পুরো নিয়ন্ত্রণ দেওয়ার জন্য ইচ্ছাকৃত সিদ্ধান্ত
- [x] `php artisan sitemap:generate` (`app/Console/Commands/Admin/GenerateSitemap.php`) — Published পেজ + যেসব Scheduled পেজের সময় ইতিমধ্যে পার হয়ে গেছে (Phase 11-এ কোনো cron নেই যেটা Scheduled→Published অটো-ফ্লিপ করে, তাই এই কমান্ডের প্রতিটা scheduled রান-ই আসলে সেই মুহূর্তটা তৈরি করে) — `routes/console.php`-এ `Schedule::command('sitemap:generate')->daily()` দিয়ে শিডিউলড
- [x] `/robots.txt` — **স্ট্যাটিক জেনারেট করা ফাইল না, লাইভ রুট** (`routes/web.php`), Settings থেকে সরাসরি পড়ে — সেভ করলেই সাথে সাথে প্রতিফলিত হয়, sitemap.xml-এর মতো রিজেনারেশন স্টেপ লাগে না

**Page/PageRequest/PageController রেট্রোফিট:** `PageRequest`-এ `App\Http\Requests\Admin\Concerns\ValidatesSeoMeta` ট্রেইট (`seo.*` রুলস, যেকোনো future SEO-enabled রিসোর্স পুনর্ব্যবহার করতে পারবে) মার্জ করা হয়েছে; `PageController::store()/update()` সেভের পর `$page->syncSeoMeta($request->validated('seo', []))` কল করে।

**একটা সূক্ষ্ম বাগ ধরা পড়েছে:** nested `seo[meta_title]` ফিল্ড-নেম থেকে ভ্যালিডেশন এরর Laravel *dot* নোটেশনে (`seo.meta_title`) কী হয়, bracket নোটেশনে না — তাই ফর্ম কম্পোনেন্টগুলোর নিজস্ব `@error($name)`/`$errors->first($name)` অটো-লুকআপ কখনো ম্যাচ করত না। ঠিক করা হয়েছে প্রতিটা SEO ফিল্ডে এক্সপ্লিসিট `:error="$errors->first('seo.meta_title')"` পাস করে, আর `<x-admin::form.media-picker>`-এও (আগে শুধু `@error($name)` নির্ভর ছিল, এখন অন্য সব form কম্পোনেন্টের মতোই `:error` prop সাপোর্ট করে) — কনসিস্টেন্সির জন্য।

**লাইভ smoke test-এ একটা রিয়েল, গুরুত্বপূর্ণ বাগ ধরা পড়েছে (অটোমেটেড টেস্টে অদৃশ্য ছিল):** `/robots.txt` রুট curl-এ বারবার একটা truncated ডিফল্ট ("Disallow:" এর পরে কিছু নেই) সার্ভ করছিল, যদিও `setting()` হেল্পার সরাসরি tinker-এ পুরো স্ট্রিং ঠিকই ফেরত দিচ্ছিল। কারণ: Laravel-এর নিজস্ব project skeleton-এ একটা স্ট্যাটিক `public/robots.txt` প্লেসহোল্ডার ফাইল **ডিফল্টভাবেই থাকে**, আর যেকোনো ওয়েবসার্ভার (আর PHP-এর built-in dev server) রাউটিং-এ পৌঁছানোর আগেই `public/`-এ এক্সিস্ট করা স্ট্যাটিক ফাইল সার্ভ করে দেয় — তাই আমার নতুন ডাইনামিক রুট **কখনো এক্সিকিউটই হচ্ছিল না**, যেকোনো এনভায়রনমেন্টে (শুধু dev-এ না, প্রোডাকশনেও)। অটোমেটেড টেস্ট এটা ধরতে পারেনি কারণ Pest/PHPUnit-এর HTTP টেস্টিং ক্লায়েন্ট সরাসরি framework router-এর মধ্য দিয়ে যায়, রিয়েল স্ট্যাটিক-ফাইল-প্রায়োরিটি সিমুলেট করে না। ফিক্স: `public/robots.txt` ডিলিট করা — এটা এই সেশনে বারবার প্রমাণ করেছে কেন প্রতিটা ফেজেই লাইভ HTTP smoke test চালানো জরুরি, শুধু টেস্ট স্যুট পাস করাই যথেষ্ট না।

**⚠️ নোট — dev ডেটাবেসে রিয়েল ইউজার ডেটা পাওয়া গেছে:** এই smoke test চালানোর সময় dev ডেটাবেসের `settings` টেবিলে দেখা গেছে ব্যবহারকারীর নিজস্ব বাস্তব সাইটের ডেটা (site_name, real email, phone, address, Google Maps embed) — সম্ভবত ব্যবহারকারী নিজেই এই সেশনের মাঝে লাইভ অ্যাডমিন প্যানেলে ঢুকে Settings টেস্ট করেছেন। এটা লক্ষ্য করার সাথে সাথে blanket cleanup (আগের ফেজগুলোতে যেমন `Setting::query()->delete()` করা হতো) বন্ধ করে শুধু নিজের তৈরি করা নির্দিষ্ট row (`robots_txt` key) সার্জিক্যালি মুছে ফেলা হয়েছে — বাকি সব ডেটা অক্ষত যাচাই করা হয়েছে।

**টেস্ট:** `SeoTest`-এ ১৩টা টেস্ট (seo-meta কম্পোনেন্টের fallback/override লজিক, sitemap ফিল্টারিং সহ due-scheduled কেস, /robots.txt), `AdminPagesTest`-এ ৫টা নতুন (per-page SEO সেভ, blank-submission-ক্লিয়ার, ভ্যালিডেশন), `AdminSettingsTest`-এ ৪টা নতুন (SEO ট্যাব) — মোট ২২টা নতুন টেস্ট।

---

## Phase 15 — System Settings (Maintenance, Timezone, Date Format, Currency) ✅

**গোল:** DB-driven runtime system configuration (no `.env` edit needed).

- [x] `settings` টেবিলে group=`system` — `maintenance_mode` (নতুন `boolean` type, SettingsManager-এ যোগ করা হয়েছে), `maintenance_message`, `maintenance_allowed_ips` (json list), `date_format`, `time_format`, `currency_code`, `currency_symbol`, `currency_position`। **ডিজাইন সিদ্ধান্ত: `timezone` ডুপ্লিকেট করা হয়নি** — Phase 13-এর General ট্যাবেই ইতিমধ্যে একটা `timezone` সেটিং ছিল (group=`general`), প্ল্যান আবার সেটা group=`system`-এ চাইলেও দুটো আলাদা "Timezone" ফিল্ড দুই ট্যাবে রাখলে confusion/drift হতো — তাই বিদ্যমান সেটিংটাই reuse করা হয়েছে, শুধু এখন সেটা আসলে কিছু করে (নিচে দেখুন)
- [x] `App\Http\Middleware\CheckMaintenanceMode` — `bootstrap/app.php`-এ `web` মিডলওয়্যার গ্রুপে **prepend** করা (session/auth/Inertia হ্যান্ডলিং শুরুর আগেই), admin রুট (prefix-ভিত্তিক চেক, লগইন পেজ-সহ — নাহলে admin নিজেই লক আউট হয়ে যেত) আর allow-listed IP বাদে সব রিকোয়েস্টে maintenance_mode চেক করে। Phase 3-এর `admin::errors.503` পেজ সরাসরি রেন্ডার করে (exception ছুঁড়ে `AdminExceptionRenderer`-এর মধ্য দিয়ে না, কারণ সেটা শুধু *admin* রিকোয়েস্ট ব্র্যান্ড করে — এখানে ঠিক উল্টো, পাবলিক ভিজিটরদের দেখাতে হবে)
- [x] Global helpers `format_date($date, $withTime = false)`, `format_currency($amount)` (`app/Support/helpers.php`) — `setting('date_format')`/`setting('time_format')`/`setting('currency_symbol')`/`setting('currency_position')` পড়ে, কিছু সেভ না থাকলে সেন্সিবল ডিফল্টে fallback করে
- [x] `SettingsManager::applyRuntimeTimezone()` — `AppServiceProvider::bootAdminKit()`-এ প্রতি রিকোয়েস্টে কল হয় (`applyRuntimeBrandConfig()`-এর ঠিক একই প্যাটার্নে), `date_default_timezone_set()` কল করে যাতে `now()`/`Carbon::now()` তখনই সেই টাইমজোন রিফ্লেক্ট করে — অবৈধ/অচেনা timezone ভ্যালু চুপচাপ ignore করা হয় (`DateTimeZone::listIdentifiers()`-এর বিপরীতে ভ্যালিডেট করে)

**AdminExceptionRenderer/config এর forward-reference ক্লোজ করা হয়েছে:** Phase 3-এ `AdminExceptionRenderer::dataFor()`-এ একটা কমেন্ট ছিল "Phase 15 replaces this with setting('maintenance_message')" — এখন ঠিক তাই করা হয়েছে, আর `config('admin-kit.maintenance.message')` + `ADMIN_MAINTENANCE_MESSAGE` env var সম্পূর্ণ সরিয়ে ফেলা হয়েছে (config comment নিজেই বলেছিল "Phase 15 moves this into the settings table")।

**একটা রিয়েল বাগ ধরা পড়েছে (টেস্টেই, লাইভ smoke test দিয়ে আবার কনফার্ম করা হয়েছে):** `maintenance_mode` checkbox আনচেক করলে ব্রাউজার সেই ফিল্ডটা POST-এই পাঠায় না — `SystemSettingsRequest`-এর `'sometimes'` রুলের কারণে `$request->validated()`-এ কী-টাই থাকত না, ফলে `saveGroup()` আগের ভ্যালু (true) touch-ই করত না — মানে maintenance mode বন্ধ করার চেষ্টা চুপচাপ ব্যর্থ হতো! ফিক্স: `SettingsController::updateSystem()`-এ `$request->boolean('maintenance_mode')` এক্সপ্লিসিটলি ব্যবহার করে, যেটা ফিল্ড অনুপস্থিত থাকলেও সবসময় true/false রিজলভ করে।

**⚠️ dev ডেটাবেসে রিয়েল ইউজার সেটিংস আবারও সম্মান করা হয়েছে:** স্মোক টেস্টের সময় শুধু নিজের তৈরি করা `system` গ্রুপের row-গুলো সার্জিক্যালি ডিলিট করা হয়েছে (`Setting::where('group', 'system')->delete()`), general/contact গ্রুপের বাস্তব সাইট ডেটা (site_name, email, phone, address, map_embed) স্পর্শ করা হয়নি — ডিলিটের পর explicitly যাচাই করা হয়েছে যে সব ঠিক আছে।

**টেস্ট:** `AdminSettingsTest`-এ ৭টা নতুন (System ট্যাব সেভ, boolean round-trip রিগ্রেশন টেস্ট, ভ্যালিডেশন, `applyRuntimeTimezone()`), `MaintenanceModeTest`-এ ৯টা (মিডলওয়্যার এনফোর্সমেন্ট: block/allow, admin exemption, IP allowlist, robots.txt-ও ব্লকড হওয়া), `FormattingHelpersTest`-এ ৮টা — মোট ২৪টা নতুন টেস্ট। লাইভ smoke test-এ curl দিয়ে সম্পূর্ণ চক্র ভেরিফাই করা হয়েছে: maintenance mode অন করা → পাবলিক হোমপেজ 503 → admin ঠিকই কাজ করে → checkbox আনচেক করে সেভ → পাবলিক হোমপেজ আবার 200।

---

## Phase 16 — Contact Message Listing (with Status) ✅

**গোল:** পাবলিক সাইটের contact form সাবমিশন ম্যানেজ করা।

- [x] `App\Models\Admin\ContactMessage` (name, email, phone, subject, message, status: new/read/replied/archived — `App\Enums\Admin\ContactMessageStatus`, `UserStatus`/`PageStatus`-এর মতোই, ip)। রিপ্লাই টেক্সট + `replied_at` সরাসরি এই একই row-এ (`reply_message` কলাম) — প্ল্যান আলাদা কোনো reply-history টেবিল চায়নি, এক মেসেজে এক রিপ্লাই
- [x] Admin listing (DataTable) — status filter (`?status=`), filename/body সার্চ, bulk actions তিনটা: "Mark as read", "Archive", "Delete selected"। **bulk "mark as replied" ইচ্ছাকৃতভাবে নেই** — Replied status মানে আসলেই একটা রিপ্লাই পাঠানো হয়েছে, শুধু বাটনে ক্লিক করা না
- [x] Detail/reply view — মেসেজ খোলামাত্র (এখনো New থাকলে) status অটো "Read"-এ চলে যায় (যেকোনো ইনবক্সের মতোই)। রিপ্লাই সেভ করা SMTP সেটিংস (Phase 6) দিয়েই যায় — কোনো এক্সট্রা ওয়্যারিং লাগেনি, কারণ `IntegrationSettings::applyRuntimeMailConfig()` ইতিমধ্যেই প্রতি রিকোয়েস্টে চলে
- [x] **New-message notification ইচ্ছাকৃতভাবে বাদ (Phase 17-এর জন্য)** — কোনো কাস্টম ইভেন্ট ক্লাস না বানিয়েই: Eloquent নিজে থেকেই প্রতিটা `ContactMessage::create()`-এ `eloquent.created: ContactMessage` ইভেন্ট ফায়ার করে, যেটা Phase 17-এর observer/listener-এর জন্য যথেষ্ট — এই ফেজের কোনো কোড ছোঁয়ারই দরকার হবে না যখন Phase 17 আসবে
- [x] `App\Http\Requests\ContactFormRequest` + `<x-admin::contact-form />` — **honeypot spam-protection** যোগ করা হয়েছে (প্ল্যানে চাওয়া হয়নি, কিন্তু unauthenticated public ফর্মের জন্য standard hardening) — `website` ফিল্ড CSS দিয়ে লুকানো (`hidden` attribute দিয়ে না, কারণ কিছু বট নির্দিষ্টভাবে `hidden="hidden"` ফিল্ড এড়িয়ে যায়), ভর্তি থাকলে `prohibited` রুলে রিজেক্ট। পাশাপাশি per-IP rate limit (৫/মিনিট, admin-login limiter-এর একই প্যাটার্নে)। **স্কোপ সিদ্ধান্ত:** কোনো পাবলিক "/contact" পেজ বানানো হয়নি (Phase 11/14-এর precedent অনুসরণ করে) — শুধু কম্পোনেন্ট + রুট + কন্ট্রোলার, কনজিউমিং প্রজেক্ট যেকোনো পাবলিক পেজে বসাবে

**পারমিশন:** `contact-messages.view/manage/delete` (তিন-ভাগ, Pages-এর চার-ভাগের বদলে — মেসেজ তো অ্যাডমিন "তৈরি" করে না, তাই আলাদা create পারমিশনের দরকার নেই)। Editor রোল view+manage পায় (রিপ্লাই দিতে পারে, কনটেন্ট-অ্যাডজাসেন্ট কাজ) কিন্তু delete না।

**রাউট-প্যারামিটার নেমিং নিয়ে সাবধানতা:** `{contactMessage}` (camelCase) ব্যবহার করা হয়েছে `{contact_message}`-এর বদলে, যাতে কন্ট্রোলার মেথডের `ContactMessage $contactMessage` প্যারামিটারের সাথে হুবহু মিলে যায় (Laravel-এর implicit route-model-binding নাম মিলিয়ে রিজলভ করে) — snake_case ব্যবহার করলে PHP ভ্যারিয়েবল নেমিং কনভেনশন ভাঙত, তাই route parameter-টাই camelCase-এ রাখা হয়েছে। লাইভ curl টেস্টে এক্সপ্লিসিটলি ভেরিফাই করা হয়েছে যে বাইন্ডিং আসলেই কাজ করে।

**টেস্ট:** `AdminContactMessagesTest`-এ ১৬টা (access control, listing/filter/search, auto-read-on-open, reply+email অ্যাসার্শন সহ, delete, bulk actions তিনটাই), `ContactFormTest`-এ ৮টা (ভ্যালিডেশন, honeypot রিজেকশন, rate limiting, কম্পোনেন্ট রেন্ডার) — মোট ২৪টা নতুন টেস্ট। লাইভ smoke test-এ curl দিয়ে সম্পূর্ণ চক্র ভেরিফাই করা হয়েছে: পাবলিক ফর্ম সাবমিশন (XSRF কুকি ডিকোড করে, যেহেতু পাবলিক সাইট Inertia-ভিত্তিক) → honeypot রিজেকশন → route-model-binding → bulk archive, সব বাস্তব HTTP রিকোয়েস্টে।

---

## Phase 17 — Notification System ✅

**গোল:** Laravel native notification, database + mail channel, admin-facing UI।

- [x] `DatabaseNotification` (Laravel built-in `notifications` table, `php artisan make:notifications-table` + migrate)
- [x] Notification classes: `App\Notifications\Admin\AdminAlert` (concrete extensible বেস ক্লাস — title/body/actionUrl/actionLabel/icon কনস্ট্রাক্টর প্যারাম, `via()` রিটার্ন করে `['database', 'mail']`), `SystemAlert`, `NewContactMessage`, `NewUserRegistered` — প্রতিটাই `AdminAlert`-এর সাবক্লাস, শুধু নিজের ডোমেইন ডেটা থেকে title/body/action সাজায়
- [x] Recipient resolution: `User::admins()` local scope (`whereHas('roles')`) — যেকোনো role-ওয়ালা user-ই "admin", পাবলিক Fortify সিগনআপে কোনো role থাকে না তাই সেগুলো বাদ পড়ে যায় নিজে থেকেই
- [x] তিনটা রিয়েল ট্রিগার ওয়্যার করা হয়েছে (কোনোটাই ডেড/আনইউজড কোড না):
  - `NewContactMessage` → `App\Observers\Admin\ContactMessageObserver::created()`, `AppServiceProvider::bootAdminKit()`-এ রেজিস্টার্ড — Phase 16-এ `ContactMessage`-এর docblock-এ যে হুক প্রমিস করা হয়েছিল, এটাই সেটা বন্ধ করল
  - `NewUserRegistered` → `UserController::store()`-এ এক্সপ্লিসিট ডিসপ্যাচ (গ্লোবাল `User::created` observer না, কারণ `User` মডেল পাবলিক Fortify রেজিস্ট্রেশনের সাথেও শেয়ার্ড — গ্লোবাল observer পাবলিক সিগনআপেও ফায়ার করে ফেলত)। অ্যাক্টিং অ্যাডমিন নিজে ও নতুন ইউজার — দুজনকেই বাদ দিয়ে বাকি সব admin-কে নোটিফাই করে
  - `SystemAlert` → `SettingsController::updateSystem()`-এ, শুধু `maintenance_mode` false→true ট্রানজিশনে (প্রতিটা System ট্যাব সেভে না) — অ্যাক্টিং অ্যাডমিন বাদে বাকি সবাইকে জানায় যে সাইট পাবলিকদের জন্য বন্ধ হয়ে গেছে
- [x] Topbar bell আইকন — unread ডট ব্যাজ (আগে থেকেই CSS-এ `.nk-iconbtn__dot` রেডি ছিল), ড্রপডাউনে latest ৫টা প্রিভিউ + "Mark all as read" + "View all notifications" লিংক — ইউজারমেনুর ঠিক পাশেই একই `.nk-dropdown` markup প্যাটার্নে
- [x] Full history page (`admin::pages.notifications.index`) — mark as read/unread (প্রতি রো-তে), mark all read। ফিল্টার-বাই-টাইপ প্ল্যানে ছিল কিন্তু বাদ দেওয়া হয়েছে: `DatabaseNotification`-এর `data` কলাম JSON, `HasDataTable`-এর searchable/sortable শেপে ফিট করে না, আর এই মুহূর্তে মাত্র ৩ ধরনের নোটিফিকেশন — একটা টাইপ ড্রপডাউন যোগ করার ROI খুবই কম
- [x] `NotificationController` self-service (Profile-এর মতোই permission-গেটেড না) — প্রতিটা lookup `$request->user()->notifications()`-এর মধ্য দিয়ে যায় (route-model-bound bare `DatabaseNotification` না) — এটাই একজন admin-কে অন্য admin-এর UUID আন্দাজ করে তার নোটিফিকেশন পড়া/মুছা থেকে আটকায়
- [ ] **স্কিপড (প্ল্যানেই stretch goal হিসেবে চিহ্নিত ছিল):** Broadcast-ready structure (Pusher/Reverb রিয়েল-টাইম bell আপডেট)

**ডিজাইন সিদ্ধান্ত:** `AdminAlert` বেস ক্লাস অ্যাবস্ট্র্যাক্ট না, concrete — কোনো ডেডিকেটেড সাবক্লাস দরকার না হলে সরাসরি `new AdminAlert(...)` কলও চলে; `SystemAlert`/`NewContactMessage`/`NewUserRegistered` আলাদা ক্লাস শুধু এই কারণেই যে প্রতিটার একটা ফিক্সড, পুনর্ব্যবহারযোগ্য শেপ আছে।

**টেস্ট:** `AdminNotificationsTest`-এ ১৫টা নতুন — recipient scope, তিনটা ট্রিগারই আলাদা করে (assertSentTo/assertNotSentTo/assertNothingSent দিয়ে, actor নিজে বাদ যাচ্ছে এটাও), NotificationController-এর প্রতিটা অ্যাকশন, cross-user authorization (অন্য admin-এর নোটিফিকেশন পড়তে ৪০৪), আর টপবার badge রেন্ডারিং। পুরো সুইট (৪৩০টা টেস্ট, আগের ১৬টা ফেজ + এই ফেজ) পাস, Pint/PHPStan/Prettier/ESLint/`npm run build` সব ক্লিন। লাইভ smoke test-এ curl দিয়ে সম্পূর্ণ চক্র ভেরিফাই করা হয়েছে: রিয়েল login সেশন → contact message তৈরি হলে observer ফায়ার করে notification সেভ হওয়া → টপবারে unread dot + প্রিভিউ দেখা যাওয়া → mark-all-read → dot চলে যাওয়া — সব বাস্তব HTTP রিকোয়েস্টে, শেষে temp user/role/message সার্জিক্যালি ক্লিনআপ করে ভেরিফাই করা হয়েছে যে রিয়েল settings (৯ রো) আর users (৫টা) কাউন্ট অপরিবর্তিত আছে।

---

## Phase 18 — Activity Log ✅

**গোল:** কে, কখন, কী পরিবর্তন করলো তার ট্র্যাকিং।

- [x] `spatie/laravel-activitylog` ইনস্টল করা হয়েছে, `activity_log` টেবিল মাইগ্রেট করা হয়েছে
- [x] `LogsActivity` trait — `User` (শুধু `logOnly(['name','email','status'])`, পাসওয়ার্ড কখনোই ডিফের মধ্যে যাবে না), `Page`, `ContactMessage`, `Setting` (সবগুলোতেই `logOnlyDirty()` + `dontLogEmptyChanges()` + কাস্টম `setDescriptionForEvent()` যাতে লিস্টিং-এ শুধু "updated" না দেখিয়ে "Page \"X\" updated" এর মতো মানুষ-পড়ার-উপযোগী বর্ণনা দেখায়)
- [x] **Role/Permission-এর জন্য ভিন্ন পথ:** এগুলো spatie/laravel-permission-এর নিজস্ব vendor মডেল, তাই `LogsActivity` trait সরাসরি লাগানো যায়নি — এর বদলে `App\Http\Controllers\Admin\Concerns\LogsRoleAndPermissionActivity` trait দিয়ে `RoleController`/`PermissionController`-এর create/update/delete/bulk-delete-এ ম্যানুয়ালি `activity()` কল করা হয়েছে, একই `activity_log` টেবিলে একই শেপে সেভ হয় বলে লিস্টিং/ডিটেইল UI-তে দুটোর মধ্যে কোনো পার্থক্য বোঝা যায় না
- [x] **`AuthLogger` (Phase 2/9) পূরণ করা হলো:** এই ক্লাসটা শুরু থেকেই একটা "thin seam" হিসেবে বানানো হয়েছিল ঠিক এই ফেজের জন্য — `write()`-এর ভেতরটা প্লেইন PSR লগারের বদলে এখন `activity('auth')` কল করে (log_name='auth'), কিন্তু পাবলিক API (loggedIn/loggedOut/failed/lockedOut/suspended/passwordReset*/profileUpdated/passwordChanged/otherDevicesLoggedOut) অবিকল একই থাকায় কোনো কলার (AdminAuthController, ProfileController, PasswordController, ইত্যাদি) ছুঁতে হয়নি
- [x] Admin UI: `ActivityLogController` — লিস্টিং (causer/user, subject type, date-range ফিল্টার + description সার্চ), ডিটেইল ভিউ — before/after ডিফ টেবিল (attribute_changes থেকে) + properties JSON pretty-print (auth লগের ip/user-agent/context-এর জন্য) — দুটোই একসাথে সাপোর্ট করে যেহেতু trait-লগড এন্ট্রি আর ম্যানুয়াল auth/role/permission এন্ট্রি ভিন্ন শেপ ব্যবহার করে
- [x] Retention policy — নতুন Settings > System ফিল্ড `activity_log_retention_days` (ফাঁকা/0 মানে চিরকাল রাখা), `admin:prune-activity-log` কমান্ড (`App\Console\Commands\Admin\PruneActivityLog`) প্রতিদিন শিডিউলড (`routes/console.php`, sitemap:generate-এর মতোই)
- [x] নতুন পারমিশন `activity-log.view` (শুধু Super Admin + Admin, Editor না — Settings/Roles/Permissions-এর মতোই), Settings মেনুর নিচে "Activity Log" আইটেম

**একটা রিয়েল বাগ ধরা পড়েছে (activitylog দিয়েই, এর আগে কখনো প্রকাশ পায়নি):** `UserFactory`-তে `status` ফিল্ড কখনো এক্সপ্লিসিটলি সেট করা হতো না (শুধু DB কলামের ডিফল্ট `'active'`-এর উপর নির্ভর করত) — `factory()->create()`-এর পরপরই `$user->status` in-memory `null` থাকত (DB ডিফল্ট কখনো ফেচ-ব্যাক হতো না)। activitylog-এর `updated` ইভেন্ট হ্যান্ডলিং internally `fresh()` কল করে DB থেকে আসল ভ্যালু আনে, ফলে সেই একই টেস্ট-প্রসেসে আগে থেকে থাকা "old" স্ন্যাপশটে `status` কখনো ছিলই না (null) — এতে প্রতিটা User আপডেটে ভুয়া "status: null → active" ডিফ দেখাত, যদিও status আসলে বদলায়নি। ফিক্স: `UserFactory::definition()`-এ এক্সপ্লিসিটলি `'status' => UserStatus::Active` যোগ করা হয়েছে, DB কলামের ডিফল্টের সাথে মিলিয়ে।

**টেস্ট:** `AdminActivityLogTest`-এ ১৩টা নতুন (auto-logging সব মডেলে, ম্যানুয়াল Role/Permission logging, ফিল্টার, ডিটেইল ডিফ, retention command দুই কেসে), `AdminLoginTest`/`AdminProfileTest`-এর পুরনো ৫টা `Log::spy()`-নির্ভর টেস্ট `Activity` মডেল-ভিত্তিক অ্যাসার্শনে রিরাইট করা হয়েছে (পুরনো আচরণ অপরিবর্তিত, শুধু ভেরিফিকেশনের মাধ্যম বদলেছে) — মোট ৪৪৩টা টেস্ট পাস। Pint/PHPStan/Prettier/ESLint/`npm run build` সব ক্লিন। লাইভ smoke test-এ curl দিয়ে সম্পূর্ণ চক্র ভেরিফাই করা হয়েছে: Page create/update, Role create/delete, Permission create/delete — সবই Activity Log লিস্টিং আর ডিটেইল পেজে ঠিকঠাক দেখা গেছে। **এই ফেজে dev ডেটাবেসে নিজের তৈরি করা কিছু ডিবাগ-আর্টিফ্যাক্ট (৪টা টেস্ট ইউজার + activity_log-এর সব রো, যেগুলো এই সেশনেই তৈরি — টেবিলটাই নতুন) সার্জিক্যালি পরিষ্কার করে ভেরিফাই করা হয়েছে যে আসল settings (৯ রো) আর users (৫টা) কাউন্ট বাইরে থেকে অপরিবর্তিত আছে।**

---

## Phase 19 — Backup ✅

**গোল:** DB + file backup, manageable থেকে UI।

- [x] `spatie/laravel-backup` ইনস্টল, config পাবলিশ করা হয়েছে। **exclude লিস্টে যোগ করা হয়েছে** `storage/app/private` (লোকাল ব্যাকআপ যেখানে জমা হয়) আর `storage/app/backup-temp` — নাহলে প্রতিটা নতুন ব্যাকআপ তার আগের সব ব্যাকআপকেই আবার জিপ করে ফেলত, সীমাহীন বেড়ে যেত। Media Library-এর আপলোড থাকে `public` ডিস্কে, তাই এই exclude-এ প্রভাবিত হয় না।
- [x] Admin UI (`BackupController`) — লিস্টিং (তারিখ, ডিস্ক, সাইজ — `Number::fileSize()`), "Run backup now" বাটন (সিঙ্ক্রোনাসভাবে চলে, এই প্যানেলের বাকি সব কমান্ডের মতোই — GenerateSitemap, PruneActivityLog), download, delete। ফাইল সিস্টেম থেকে সরাসরি পড়া হয় (`Spatie\Backup\BackupDestination\BackupDestination`), কোনো DB টেবিল নেই বলে `HasDataTable` ব্যবহার হয়নি। **সিকিউরিটি:** `$disk`/`$file` দুটোই route-এর user-controlled ইনপুট — `$disk` শুধু `config('backup.backup.destination.disks')`-এ থাকা ডিস্কেই সীমাবদ্ধ (অন্য কোনো configured disk যেমন 'public' পৌঁছানো যায় না), আর `basename($file)` path traversal ঠেকায়
- [x] Schedule — নতুন Settings > System ফিল্ড `backup_schedule` (disabled/daily/weekly), `routes/console.php`-এ `backup:run` প্রতিদিনই ট্রিগার হয় কিন্তু `->when()` ক্লোজার সেটিং চেক করে ঠিক করে আজকে আসলে চলবে কিনা — সার্ভারের cron না ছুঁয়েই সেটিং বদলালে সাথে সাথে কাজ করে। `backup:clean` আলাদাভাবে প্রতিদিন চলে (নিজের retention স্ট্র্যাটেজি, schedule সেটিং-এর সাথে সম্পর্কহীন)
- [x] Notification — `App\Notifications\Admin\BackupAlert` (`AdminAlert`-এর সাবক্লাস, Phase 17) + `App\Listeners\Admin\BackupEventSubscriber`, যেটা spatie-এর নিজস্ব `BackupWasSuccessful`/`BackupHasFailed` ইভেন্ট শোনে। প্যাকেজের নিজস্ব mail-only নোটিফিকেশন সিস্টেম (`config/backup.php`) ইচ্ছাকৃতভাবে খালি করে দেওয়া হয়েছে যাতে ডুপ্লিকেট না হয়, সব admin-facing নোটিফিকেশন একই AdminAlert পাইপলাইনে যায়
- [x] S3 — অপশনাল, `.env`-এর AWS_* ভেরিয়েবল দিয়ে ক্রেডেনশিয়াল (`config/filesystems.php`-এর আগে থেকে থাকা 's3' ডিস্ক), আর Settings > System-এ একটা টগল (`backup_s3_enabled`) — অন করলে আর bucket সেট থাকলেই `SettingsManager::applyRuntimeBackupConfig()` রানটাইমে `backup.backup.destination.disks`-এ 's3' যোগ করে দেয় (Phase 13-এর `applyRuntimeBrandConfig()`-এর একই প্যাটার্নে)। AWS bucket না থাকলে UI-তে হিন্ট দেখায় কী env var লাগবে
- [x] নতুন পারমিশন `backups.manage` (শুধু Super Admin + Admin, Editor না), top-level মেনু আইটেম "Backups" (Contact Messages আর Settings-এর মাঝে)

**⚠️ একটা রিয়েল বাগ ধরা পড়েছে, শুধু লাইভ smoke test-এই (অটোমেটেড টেস্ট প্রথমে মিস করেছিল):** প্রতিটা ব্যাকআপ সাকসেস/ফেইলিওরে প্রতিটা admin-কে **দুইবার** নোটিফিকেশন যাচ্ছিল। কারণ: Laravel-এর ডিফল্ট event auto-discovery `app/Listeners/` ফোল্ডার স্ক্যান করে `BackupEventSubscriber::handleSuccess()`/`handleFailure()`-কে (প্যারামিটারের টাইপ-হিন্ট দেখে) নিজে থেকেই লিসেনার হিসেবে রেজিস্টার করে ফেলেছিল — আমার এক্সপ্লিসিট `Event::subscribe()` কলের উপরে, ফলে একই ইভেন্টে দুটো রেজিস্ট্রেশন। **ফিক্স:** `bootstrap/app.php`-এ `->withEvents(discover: false)` — এই কোডবেসের সর্বত্র থাকা "ম্যাজিক না, এক্সপ্লিসিট রেজিস্ট্রেশন" কনভেনশনের সাথেই মানানসই। **টেস্ট গ্যাপ ধরাও ফিক্স করা হয়েছে:** `Notification::assertSentTo()` শুধু "কমপক্ষে একবার" চেক করে, ডুপ্লিকেট ধরতে পারে না — টেস্ট আপডেট করে `assertSentToTimes(..., 1)` যোগ করা হয়েছে যাতে ভবিষ্যতে এই ধরনের বাগ আবার আসলে টেস্টেই ধরা পড়ে।

**টেস্ট:** `AdminBackupsTest`-এ ১২টা (access control, লিস্টিং/এম্পটি-স্টেট, download/delete, disk allowlist + path traversal প্রোটেকশন, `backup:run` (ছোট করে scope করা source দিয়ে যাতে দ্রুত চলে — sqlite `:memory:`-এর জন্য ডাটাবেজ ডাম্প বাদ), notification bridge exact-count সহ), `AdminSettingsTest`-এ ৫টা নতুন (schedule/S3 টগল সেভ, boolean round-trip, `applyRuntimeBackupConfig()` দুই কেসে) — মোট ১৭টা নতুন টেস্ট, পুরো সুইট ৪৫৯টা টেস্ট পাস। Pint/PHPStan/Prettier/ESLint/`npm run build` সব ক্লিন। লাইভ smoke test-এ curl দিয়ে সম্পূর্ণ চক্র ভেরিফাই করা হয়েছে: fake backup file দিয়ে লিস্টিং/download/delete, System ট্যাবে নতুন Backups কার্ড রেন্ডার, আসল `BackupWasSuccessful`/`BackupHasFailed` ইভেন্ট ফায়ার করে নোটিফিকেশন পাইপলাইন (ডুপ্লিকেট বাগ এখানেই ধরা পড়েছিল আর ফিক্স হয়েছে) — শেষে সব টেম্প আর্টিফ্যাক্ট (ইউজার, রোল, নোটিফিকেশন, activity log এন্ট্রি) সার্জিক্যালি পরিষ্কার করে ভেরিফাই করা হয়েছে আসল settings (৯), users (৫), roles (৩) অপরিবর্তিত।

---

## Phase 20 — Extract to Composer Package

**গোল:** স্থিতিশীল, টেস্ট করা admin module-টা standalone প্যাকেজে রূপান্তর করা, যাতে নতুন প্রজেক্টে এক কমান্ডে বসানো যায়।

- [ ] নতুন repo/ফোল্ডার `packages/ng-coder/admin-kit/` তৈরি করে dev প্রজেক্ট থেকে `app/*/Admin/`, `resources/views/admin/`, `routes/admin.php`, `database/migrations/` কপি করুন
- [ ] Namespace bulk-replace: `App\Http\Controllers\Admin` → `NgCoder\AdminKit\Http\Controllers`, ইত্যাদি
- [ ] `composer.json` (PSR-4 autoload) + `AdminKitServiceProvider` লিখুন — routes/views/migrations register + publish tags (`admin-kit-assets`, `admin-kit-config`, `admin-kit-views`, `admin-kit-migrations`)
- [ ] `php artisan admin-kit:install` command — publishes সব কিছু, migration রান করে, default role/admin user seed করে
- [ ] Theming guide লিখুন: কোন CSS variable ওভাররাইড করলে নতুন প্রজেক্টে ভিন্ন ব্র্যান্ডিং আসবে
- [ ] README.md — install steps, screenshot, ফিচার চেকলিস্ট
- [ ] প্যাকেজের নিজস্ব git repo-তে `v1.0.0` ট্যাগ করুন
- [ ] Distribution: প্রথমে VCS repository হিসেবে ব্যবহার করুন (`"repositories": [{"type": "vcs", "url": "..."}]` in consuming project's composer.json) — পরে চাইলে private Packagist-এ সরান
- [ ] dev প্রজেক্টটাকেই প্যাকেজের প্রথম "consumer" হিসেবে রেখে দিন — এতে রিয়েল ইউজেজে বাগ ধরা পড়বে

---

## Suggested Build Order (fastest path to a usable v1)

1. Phase 1 (Foundation)
2. Phase 2 (Auth)
3. Phase 3 (Error pages)
4. Phase 4 (Toaster/Confirm)
5. Phase 5 (DataTable)
6. Phase 7 (Menu/Role/Permission)
7. Phase 8 (User mgmt)
8. Phase 9 (Profile)
9. Phase 13 (Settings — needed by many later phases)
10. Phase 14 (SEO)
11. Phase 15 (System settings)
12. Phase 6 (Integrations)
13. Phase 12 (Media library — needed by CMS/Settings logo)
14. Phase 11 (CMS)
15. Phase 16 (Contact messages)
16. Phase 17 (Notifications)
17. Phase 18 (Activity log)
18. Phase 19 (Backup)
19. Phase 10 (Dashboard widgets — pulls data from everything above)
20. Active 20 menu check
21. Phase 21 (Polish/package/docs)

> এই অর্ডারে করলে প্রতিটা ফেজ আগের ফেজের উপর নির্ভর করে গড়ে ওঠে (Settings আগে বানালে Integrations/SEO/System সহজ হয়ে যায়), এবং প্রতিটা ফেজ শেষে git commit করে পরের ফেজে যাওয়া যাবে — আপনার existing workflow অনুযায়ী।

---

## Notes for Claude Code Sessions

- প্রতিটা ফেজ শুরুতে এই ফাইলের সংশ্লিষ্ট সেকশনটা paste করে দিন প্রম্পট হিসেবে।
- প্রতি ফেজ শেষে: `git add -A && git commit -m "feat(admin): phase X - <name>"`.
- View namespace শুরু থেকেই consistent রাখুন: `admin::`।
- ডি-ব্র্যান্ডিং checklist প্রতি ফেজে মনে রাখবেন: কোনো `adminlte-*` class name, কোনো AdminLTE লোগো/কমেন্ট থেকে না যায়।
- Phase 20-এর আগ পর্যন্ত কোনো `composer.json`/`ServiceProvider`/প্যাকেজ namespace নিয়ে ভাবতে হবে না — সাধারণ Laravel প্রজেক্ট কনভেনশনেই কাজ করুন।
