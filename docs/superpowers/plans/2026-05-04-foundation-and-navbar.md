# UI Redesign — Foundation, Top Navbar, Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hover-collapsed `options_container` in the left nav-pane with a permanent ~38px top navbar in the application shell, and restyle the login screen as a Bootstrap card — without altering any JS handler or breaking the 3-pane resizable layout.

**Architecture:** Lift the navbar markup into `main.template` so it lives in the application shell above `#wrapper`. Reduce `#wrapper` height by the navbar height so the three resizable panes still fit. Strip the now-duplicate `options_container` from `navframe.main.template` and the obsolete `.hovered` / `#options_container` / `#portalinfo_container` rules from `TholosBuilder.css`. The login screen replaces its bespoke `#login` div with a centered Bootstrap card.

**Tech Stack:** Vanilla Bootstrap 5.3.8 (already installed at `vendor/bower-asset/bootstrap`), FontAwesome 6 (already loaded via `assets/js/fontawesome/`), Eisodos template engine (`.template` files in `assets/templates/tholosbuilder/`), `assets/css/TholosBuilder.css` for project styling. No PHP test framework — verification is manual in a browser against a live builder instance.

**Spec:** `docs/superpowers/specs/2026-05-04-foundation-and-navbar-design.md`

**No test framework note:** This codebase has no PHPUnit / no `tests/` directory and no JS test harness. The plan therefore omits automated test steps; each task ends with a manual visual/functional check in the browser, then a commit.

---

## File Structure

| File | Change | Responsibility |
|---|---|---|
| `assets/templates/tholosbuilder/main.template` | Modify (insert) | Add the new top-level `<nav class="navbar tb-navbar">` block above `<div id="wrapper">`. |
| `assets/templates/tholosbuilder/navframe.main.template` | Modify (delete + keep) | Remove the entire `<div id="options_container">…</div>` block (lines 1–53). Keep `<div id="app_tree_container">…</div>`. |
| `assets/templates/tholosbuilder/login.main.template` | Modify (replace body) | Swap the `<div id="login">…</div>` block (lines 17–28) with the new Bootstrap-card markup. |
| `assets/css/TholosBuilder.css` | Modify (add + change + delete) | Add `.tb-navbar` rules, change `#wrapper` height, add `.login-card` rules, delete obsolete `#login` / `#options_container*` / `#portalinfo_container*` rules. |

Tasks are ordered so each commit leaves the system in a working state:

- Task 1 adds the new navbar (CSS + template + height adjustment) — page now has both the new navbar and the legacy `options_container` simultaneously, but everything works.
- Task 2 deletes the legacy `options_container` HTML — page now has only the top navbar.
- Task 3 deletes obsolete CSS rules — page is unchanged visually but the stylesheet is cleaner.
- Task 4 restyles the login screen as a Bootstrap card.

Background: confirmed via `grep` that `#options_container` is referenced only in `navframe.main.template` line 1 and `TholosBuilder.css` lines 156–182, and `#portalinfo_container` is referenced only in `TholosBuilder.css` lines 152–154 (no template uses it — safe to delete).

---

### Task 1: Introduce the new top navbar

**Files:**
- Modify: `assets/css/TholosBuilder.css` (add `.tb-navbar` block, change `#wrapper` height on line 65)
- Modify: `assets/templates/tholosbuilder/main.template` (insert navbar block after `<body>` line 16)

- [ ] **Step 1: Add `.tb-navbar` CSS rules to `assets/css/TholosBuilder.css`**

Append the following block at the end of the file (after the existing `.tx-editor-wrapper` rule on line 557):

```css

/* === Top app-shell navbar (.tb-navbar) =================== */
.navbar.tb-navbar {
  background-color: #3a3f44;
  --bs-navbar-padding-y: .25rem;
  --bs-navbar-brand-font-size: 13px;
  --bs-navbar-brand-padding-y: .15rem;
  --bs-navbar-nav-link-padding-x: .55rem;
  font-size: 12px;
  min-height: 38px;
}
.navbar.tb-navbar .nav-link {
  font-size: 12px;
  padding-top: .25rem;
  padding-bottom: .25rem;
}
.navbar.tb-navbar .navbar-brand {
  font-size: 13px;
  font-weight: 600;
}
.navbar.tb-navbar .brand-icon-wrap {
  background: #fff;
  border-radius: 4px;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.navbar.tb-navbar .brand-icon {
  width: 18px;
  height: 18px;
}
.navbar.tb-navbar .form-control,
.navbar.tb-navbar .btn {
  font-size: 12px;
  padding: .15rem .5rem;
  line-height: 1.4;
  height: 26px;
}
.navbar.tb-navbar .navbar-search {
  width: 280px;
}
.navbar.tb-navbar .nav-icon-btn {
  color: rgba(255,255,255,.85);
  background: transparent;
  border: 1px solid rgba(255,255,255,.18);
}
.navbar.tb-navbar .nav-icon-btn:hover {
  background: rgba(255,255,255,.10);
  color: #fff;
}
.navbar.tb-navbar .dropdown-menu {
  font-size: 12px;
}
.navbar.tb-navbar .dropdown-item {
  padding: .3rem .7rem;
}
```

- [ ] **Step 2: Change `#wrapper` height in `assets/css/TholosBuilder.css`**

Locate the `#wrapper` rule (lines 64–70) and change `height: 100%;` to `height: calc(100% - 38px);`. Final block:

```css
#wrapper {
  height: calc(100% - 38px);
  margin: 0px;
  padding: 0px;
  position: absolute;
  width: 100%;
}
```

- [ ] **Step 3: Insert the navbar block in `assets/templates/tholosbuilder/main.template`**

Insert the following block immediately after the opening `<body>` tag (after line 16) and immediately before `<div id="wrapper" class="container-fluid">`:

```html
<nav class="navbar navbar-expand-lg navbar-dark tb-navbar">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="#">
      <span class="brand-icon-wrap"><img src="$TholosBuilderAssetsDir/images/favicon.png" alt="Tholos" class="brand-icon"></span>
      Tholos Builder
    </a>

    <form class="d-flex me-2" role="search" onsubmit="searchApp($('#searchText').val()); return false;">
      <div class="input-group input-group-sm">
        <input id="searchText" class="form-control navbar-search" placeholder="Search components, queries, properties…">
        <button id="searchButton" class="btn btn-outline-light" type="submit"><i class="fa-regular fa-search"></i></button>
      </div>
    </form>

    <div class="btn-group me-2" role="group" aria-label="History">
      <button class="btn nav-icon-btn" title="Back" onclick="historyStepBack();"><i class="fa-regular fa-chevron-left"></i></button>
      <button class="btn nav-icon-btn" title="Forward" onclick="historyStepForward();"><i class="fa-regular fa-chevron-right"></i></button>
    </div>

    <button class="btn nav-icon-btn me-2" title="Refresh tree" onclick="loadAppTree('#app_tree');"><i class="fa-regular fa-refresh" id="globalLoading"></i></button>

    <ul class="navbar-nav ms-auto align-items-center">
      <li class="nav-item">
        <a class="nav-link" href="javascript:showTranslate();"><i class="fa-regular fa-language me-1"></i>Language</a>
      </li>

      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="fa-regular fa-book me-1"></i>Docs</a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="javascript:showComponentTypeDocumentation();"><i class="fa-regular fa-university me-2"></i>Component type documentation</a></li>
          <li><a class="dropdown-item" href="javascript:downloadComponentTypeDocumentation();"><i class="fa-regular fa-download me-2"></i>Download component type documentation</a></li>
          <li><a class="dropdown-item" href="javascript:generateUserHelp();"><i class="fa-regular fa-question-circle me-2"></i>Generate User Guide</a></li>
        </ul>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="javascript:showRoutes();"><i class="fa-regular fa-filter me-1"></i>Routes</a>
      </li>

      <li class="nav-item ms-2">
        <button class="btn btn-primary" onclick="compile('T');"><i class="fa-regular fa-landmark me-1"></i>Build</button>
      </li>

      <li class="nav-item ms-2">
        <button class="btn btn-success" onclick="compile('F');"><i class="fa-regular fa-circle-check me-1"></i>Compile</button>
      </li>

      <li class="nav-item dropdown ms-3">
        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="fa-regular fa-user me-1"></i>$user_name</a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="javascript:showUserProfile();"><i class="fa-regular fa-id-card me-2"></i>Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="$cgi?action=logout"><i class="fa-regular fa-sign-out me-2"></i>Logout</a></li>
        </ul>
      </li>
    </ul>
  </div>
</nav>
```

After insertion the top of `main.template` should read:

```html
<body>
<nav class="navbar navbar-expand-lg navbar-dark tb-navbar">
  ...
</nav>
<div id="wrapper" class="container-fluid">
```

- [ ] **Step 4: Verify visually in the browser**

Reload the running builder app. Expected:

1. New 38px dark-gray navbar appears at the top, with: favicon-on-white-pill brand, search field with magnifier button, ‹ ›, ⟳, Language, Docs ▾, Routes, blue Build button, green Compile button, user dropdown showing the logged-in user's name with a chevron.
2. The legacy `options_container` is **also still visible** below the navbar inside the left pane (this is expected — it gets removed in Task 2).
3. The 3-pane layout below the navbar still fills the screen with no body-level scrollbar.
4. Functional checks (every one must work):
   - Type something in the navbar search and press Enter → tree filters (calls `searchApp(...)`).
   - Click ‹ / › → history navigation works.
   - Click ⟳ → tree reloads (icon `id="globalLoading"` should still pulse during load).
   - Click Language → translation flow opens (`showTranslate()`).
   - Click Docs → dropdown opens; each item invokes its handler.
   - Click Routes / Build / Compile → respective flows open.
   - Click user-name dropdown → Profile and Logout entries appear and work.

If the navbar overflows on narrow viewports, that's acceptable for this task — the navbar uses `navbar-expand-lg`, so a hamburger toggle would be the follow-up. (Builder is desktop-only in practice.)

- [ ] **Step 5: Commit**

```bash
git add assets/css/TholosBuilder.css assets/templates/tholosbuilder/main.template
git commit -m "Add top app-shell navbar (.tb-navbar) above the 3-pane layout"
```

---

### Task 2: Remove the legacy options_container

**Files:**
- Modify: `assets/templates/tholosbuilder/navframe.main.template` (delete lines 1–53)

- [ ] **Step 1: Delete the `options_container` block**

Open `assets/templates/tholosbuilder/navframe.main.template` and remove lines 1–53 — i.e. the entire `<div id="options_container" class="well well-sm">…</div>` block AND the inline `<script>` that follows it (the `$("#searchText").keyup(...)` handler — no longer needed because the new navbar uses a `<form onsubmit=…>` to handle Enter).

Final file contents (only `app_tree_container` remains, starting at the new line 1):

```html
<div id="app_tree_container" class="well well-sm">
  <div id="apps" class="col-sm-12">

    <div class="tab-content">
      <div id="tab_app_tree" class="tab-pane active">
         <div id="app_tree" class="col-sm-12">
         </div>
      </div>
    </div>
  </div>
</div>
```

- [ ] **Step 2: Verify visually in the browser**

Reload. Expected:

1. The legacy options_container block in the left pane is gone.
2. Only the top navbar provides app actions; the left pane shows just the component tree.
3. All functional checks from Task 1 Step 4 still pass — search, history, refresh, Language, Docs, Routes, Build, Compile, user dropdown.
4. Right-clicking on a tree node still produces the context menu containing Add component, Query wizard, Stored procedure wizard, Grid wizard, Edit form wizard (these are the items intentionally removed from the navbar — confirm the context menu still serves them).

- [ ] **Step 3: Commit**

```bash
git add assets/templates/tholosbuilder/navframe.main.template
git commit -m "Drop options_container — superseded by top navbar"
```

---

### Task 3: Clean up obsolete CSS rules

**Files:**
- Modify: `assets/css/TholosBuilder.css` (delete lines 152–154 and 156–182)

- [ ] **Step 1: Delete `#portalinfo_container` rules**

Delete lines 152–154 from `assets/css/TholosBuilder.css`:

```css
#portalinfo_container { position:absolute; width: 100%; }
#portalinfo_container .logo { background-color: #3d6c91; color: #fff; font-size: 14pt; padding-left: 5px; }
#portalinfo_container .portal-info { background-color: #8cb0cb; color: #fff; font-size: 10px; padding-left: 5px; }
```

These have no template references (verified via grep) and are dead code.

- [ ] **Step 2: Delete `#options_container*` rules**

Delete lines 156–182 from `assets/css/TholosBuilder.css` (the block beginning `#options_container {` through the commented-out `/* #options_container .dropdown-menu { position: relative; } */`):

```css
#options_container {
  margin-bottom: 0px;
  position: absolute;
  top: 0px;
  width: 100%;
  height: 23px;
  z-index: 500;
  padding-top: 3px;
  padding-bottom: 3px;
}

#options_container:hover {
  height: auto;
}

#options_container .info {
  font-size: 8pt;
}

#options_container .hovered {
  display: none;
}

#options_container:hover .hovered {
  display: flex;
}
/* #options_container .dropdown-menu { position: relative; } */
```

After both deletions, the section between the previous block (`#userprofile p input` ending around line 150) and the next block (`/* template tree */` comment around line 184) should contain only the comment header `/* left frame */` and a blank line.

- [ ] **Step 3: Verify visually in the browser**

Reload. Expected: page renders identically to after Task 2 (no visual difference). The deleted rules had no remaining selectors in the DOM, so this is a pure cleanup.

- [ ] **Step 4: Commit**

```bash
git add assets/css/TholosBuilder.css
git commit -m "Remove dead CSS — #options_container and #portalinfo_container rules"
```

---

### Task 4: Restyle the login screen as a Bootstrap card

**Files:**
- Modify: `assets/templates/tholosbuilder/login.main.template` (replace lines 17–28)
- Modify: `assets/css/TholosBuilder.css` (replace `#login*` rules on lines 12–62 with new `.login-page` / `.login-card` rules)

- [ ] **Step 1: Replace the login body in `login.main.template`**

In `assets/templates/tholosbuilder/login.main.template`, replace lines 17–28 (the entire `<div id="login">…</div>` block) with:

```html
<div class="login-page">
  <div class="login-card">
    <div class="login-head">
      <span class="icon-pill"><img src="$TholosBuilderAssetsDir/images/favicon.png" alt="Tholos"></span>
      <h1>Tholos Builder</h1>
      <div class="subtitle">Sign in to continue</div>
    </div>

    [%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=errormsg;value=;true=;false=<div class="alert alert-danger alert-sm" role="alert"><i class="fa-regular fa-circle-exclamation me-1"></i>$errormsg</div>%]

    <form action="$CGI" method="POST">
      <input type="hidden" name="action" value="login">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" class="form-control" name="p_user" autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" class="form-control" name="p_password">
      </div>
      <div class="d-grid">
        <button class="btn btn-primary" type="submit"><i class="fa-regular fa-key me-1"></i>Login</button>
      </div>
    </form>
  </div>
</div>
```

Notes for the engineer:
- The `[%_function_name=…_eqs;param=errormsg;value=;true=;false=…%]` helper is the existing Eisodos conditional that already drives the current red `<p>` error message. We are only changing what gets emitted on the `false=` branch (i.e. when `errormsg` is **non-empty**). The `true=` branch is empty so nothing renders when there is no error.
- The `<input type="hidden" name="action" value="login">` and the `name="p_user"` / `name="p_password"` attributes are unchanged — the PHP login handler reads those exact param names.

- [ ] **Step 2: Replace `#login*` styles in `TholosBuilder.css`**

In `assets/css/TholosBuilder.css`, delete lines 12–62 (the entire `#login`, `#login h1`, `#login p`, `#login p label`, `#login p input`, `#login button` block) and insert the following in their place:

```css
.login-page {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(180deg, #3a3f44 0%, #495057 100%);
}

.login-card {
  width: 320px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(0,0,0,.25);
  padding: 28px 28px 24px;
  font-size: 13px;
}

.login-card .login-head {
  text-align: center;
  margin-bottom: 18px;
}

.login-card .login-head .icon-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 8px;
  background: #f1f3f5;
  margin-bottom: 10px;
}

.login-card .login-head .icon-pill img {
  width: 30px;
  height: 30px;
}

.login-card .login-head h1 {
  font-size: 16px;
  font-weight: 600;
  color: #212529;
  margin: 0;
}

.login-card .login-head .subtitle {
  font-size: 11px;
  color: #6c757d;
  letter-spacing: .04em;
  text-transform: uppercase;
  margin-top: 4px;
}

.login-card .form-label {
  font-size: 12px;
  font-weight: 500;
  margin-bottom: 4px;
}

.login-card .form-control {
  font-size: 13px;
}

.login-card .alert-sm {
  font-size: 12px;
  padding: .4rem .6rem;
}

.login-card .btn-primary {
  font-size: 13px;
  padding: .4rem 1rem;
}
```

- [ ] **Step 3: Verify visually in the browser**

1. Log out of the builder (click the user dropdown → Logout).
2. The login URL renders the new card centered on a dark-gray gradient. The card shows:
   - Favicon in a 44×44 light-gray pill at top
   - "Tholos Builder" title (16px)
   - "SIGN IN TO CONTINUE" uppercase subtitle
   - Username + Password labelled inputs
   - Full-width blue Login button with a key icon
3. Submit with **wrong credentials** → page reloads with the same card; the `alert-danger` block appears between the head and the form, containing the `$errormsg` text.
4. Submit with **correct credentials** → login proceeds and the main app shell loads (with the new top navbar from Tasks 1–2).

- [ ] **Step 4: Commit**

```bash
git add assets/templates/tholosbuilder/login.main.template assets/css/TholosBuilder.css
git commit -m "Restyle login screen as a centered Bootstrap card"
```

---

## Self-Review Findings

Spec coverage (each section of the spec mapped to a task):

| Spec section | Implemented in |
|---|---|
| §3.1 application shell change | Task 1 (Steps 2 + 3) |
| §3.2 navframe simplification | Task 2 |
| §3.3 login screen | Task 4 |
| §3.4 JS handlers preserved 1:1 | Task 1 Step 3 (every navbar item carries the original handler), Task 2 Step 1 (Enter-key handler replaced by `<form onsubmit>`) |
| §3.5 removed actions (Wizards menu, Add component) | Task 1 Step 3 (omitted from navbar markup); Task 2 Step 2 verifies right-click context menu still serves them |
| §4.1 navbar visual spec | Task 1 Step 1 |
| §4.2 login card visual spec | Task 4 Step 2 |
| §5 file changes table | Tasks 1–4 cover every row |
| §6 behavior changes | Task 2 (hover-reveal removed; permanent height); Task 1 Step 4 (always-visible navbar) |
| §7 acceptance criteria | Task 1 Step 4, Task 2 Step 2, Task 4 Step 3 verifications collectively cover every criterion |

No placeholders, no TBDs, no "implement later". All file paths, line ranges, and exact code blocks are provided.
