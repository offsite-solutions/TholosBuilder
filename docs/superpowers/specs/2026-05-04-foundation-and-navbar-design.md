# UI Redesign — Foundation, Top Navbar, Login Screen

**Date:** 2026-05-04
**Branch:** `feature/ui-redesign`
**Status:** Design

---

## 1. Background

The current Tholos Builder UI puts its primary controls inside an `options_container`
nested in the left navigation pane (`nav_frame`). The container is collapsed to
~23px and only expands on hover (`.hovered` mechanic in `TholosBuilder.css`),
which surprises new users and limits the discoverability of actions like Build,
Compile, Routes, and the wizard menu. The login screen uses a hand-rolled
`#login` block with inline gradients and IE-era vendor prefixes.

This is the first sub-project under a broader UI overhaul. The goal of this spec
is to land a permanent, always-visible top navbar and a clean Bootstrap-card
login screen, while leaving the tree, property editor, and wizards untouched
(those become later specs on the same branch).

## 2. Scope

**In scope**
- Replace `options_container` with a top-level `<nav class="navbar tb-navbar">` in the application shell.
- Restructure `main.template` so the navbar sits above the existing 3-pane resizable layout.
- Restyle `login.main.template` as a centered Bootstrap card.
- Add `.tb-navbar` styling rules to `assets/css/TholosBuilder.css` and adjust `#wrapper` height.
- Remove the `.hovered` hover-to-reveal mechanic and the now-unused `#options_container` rules.

**Out of scope (deferred to follow-up specs on the same branch)**
- jstree theme / tree view restyle.
- Property editor tabs (Properties / Events / Methods / References / Help) — both `propframe.main.template` and the jQuery UI tab styling.
- All eight wizards (Query, Stored Procedure, Grid, Edit Form, Commit, Userprofile, Language, Help).
- jQuery UI base theme cleanup.
- The `assets/css/v3/` Bootswatch Sandstone files — already deleted as part of this spec's prep work; we are staying on the bower-asset vanilla Bootstrap 5.3.8.

## 3. Architecture

### 3.1 Application shell change

The navbar is owned by the application shell (`main.template`), not by the
left-pane navframe.

```
main.template
└── <nav class="navbar tb-navbar">      ← NEW: full-width top navbar (~38px)
└── <div id="wrapper">                  ← height: calc(100% - 38px)
    └── <div id="container">
        ├── <div id="task_frame">
        ├── <div id="nav_frame">       ← contains app_tree_container ONLY
        ├── <div id="prop_frame">
        └── <div id="edit_frame">
```

Reasoning: the alternative — keeping the navbar inside `navframe.main.template`
with `position: fixed` — would couple the navbar lifecycle to the tree-pane
load (visible flash on `loadAppTree()`) and require ad-hoc z-index work. Moving
it into the shell is a one-time edit and matches conceptual ownership.

### 3.2 Navframe simplification

`navframe.main.template` keeps `#app_tree_container` and the script that
auto-loads the tree, and drops `#options_container` entirely.

### 3.3 Login screen

`login.main.template` replaces the bespoke `#login` div with a Bootstrap card
markup. The page-level CSS link tags are unchanged (still `bootstrap.min.css`
+ `TholosBuilder.css`).

### 3.4 JS handlers — preserved 1:1

Every action in the current `options_container` keeps its existing JavaScript
function. Only the host markup changes:

| Action | Current trigger | New host |
| --- | --- | --- |
| Search | `searchApp($('#searchText').val())` | navbar input-group |
| Search-on-Enter | `$("#searchText").keyup` handler | unchanged (binds to same `#searchText` id) |
| History back | `historyStepBack()` | navbar btn-group |
| History forward | `historyStepForward()` | navbar btn-group |
| Refresh tree | `loadAppTree('#app_tree')` | navbar refresh button (id `globalLoading` preserved on the icon) |
| Generate language file | `showTranslate()` | navbar Language link |
| Component type docs | `showComponentTypeDocumentation()` | navbar Docs dropdown |
| Download component type docs | `downloadComponentTypeDocumentation()` | navbar Docs dropdown |
| Generate user guide | `generateUserHelp()` | navbar Docs dropdown |
| Routes | `showRoutes()` | navbar Routes link |
| Build | `compile('T')` | navbar Build button (`btn-primary`) |
| Compile | `compile('F')` | navbar Compile button (`btn-success`) |
| User profile | `showUserProfile()` | navbar user dropdown → Profile |
| Logout | `$cgi?action=logout` | navbar user dropdown → Logout |

### 3.5 Removed actions

These items disappear from the navbar because they are reachable through the
tree's right-click context menu:

- Wizards menu group (Query, Stored Procedure, Grid, Edit Form wizards).
- Add component button (`addComponent('','')`).

The underlying JS functions (`showQueryWizard`, `showStoredProcedureWizard`,
`showGridWizard`, `showEditFormWizard`, `addComponent`) remain in
`TholosBuilder.js` — only the navbar entry points are removed.

## 4. Visual specification

### 4.1 Navbar (`.tb-navbar`)

| Property | Value |
| --- | --- |
| Background | `#3a3f44` (custom dark gray; applied via the `.tb-navbar` class — no `bg-dark` class on the `<nav>`) |
| Theme variant | `navbar-dark navbar-expand-lg` |
| Min height | `38px` |
| Padding-y (`--bs-navbar-padding-y`) | `.25rem` |
| Brand font size | `13px`, weight `600` |
| Brand mark | `assets/images/favicon.png` at 18px, inside a 24×24 white rounded pill (border-radius 4px) |
| Nav link / button font | `12px` |
| Control height | `26px` (inputs and buttons) |
| Search field width | `280px` |
| User dropdown | `fa-user` icon + name; **no avatar circle badge** |

Slot order, left → right:
`brand` · `search input-group` · `‹ ›` history · `⟳` refresh · `ms-auto` ·
`Language` · `Docs ▾` · `Routes` · `Build` (primary) · `Compile` (success) ·
`User ▾`.

### 4.2 Login card

| Property | Value |
| --- | --- |
| Page background | linear-gradient `#3a3f44 → #495057` (top to bottom) |
| Card width | `320px`, centered horizontally and vertically |
| Card | `background: #fff`, `border-radius: 8px`, `box-shadow: 0 10px 30px rgba(0,0,0,.25)` |
| Card padding | `28px 28px 24px` |
| Brand mark | favicon at 30px in a 44×44 light gray (`#f1f3f5`) pill above the title |
| Title | "Tholos Builder", 16px, weight 600 |
| Subtitle | "Sign in to continue", 11px uppercase, `color: #6c757d`, letter-spacing `.04em` |
| Inputs | Bootstrap `form-control`, 13px |
| Submit | `btn btn-primary`, full-width via `d-grid`, "Login" with `fa-key` icon |
| Error slot | `alert alert-danger` rendered only when `$errormsg` is non-empty — preserve the existing `[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=errormsg;value=;true=;false=…%]` conditional pattern, swapping its `false=` branch from the current `<p style="color:#ff0000">$errormsg</p>` to the new `<div class="alert alert-danger alert-sm"><i class="fa fa-circle-exclamation me-1"></i>$errormsg</div>` |

## 5. Files changed

| File | Change |
| --- | --- |
| `assets/templates/tholosbuilder/main.template` | Insert `<nav class="navbar tb-navbar">…</nav>` block above `#wrapper`. |
| `assets/templates/tholosbuilder/navframe.main.template` | Remove the entire `<div id="options_container">…</div>` block (lines 1–53 of the current file). Keep `<div id="app_tree_container">…</div>` and below. |
| `assets/templates/tholosbuilder/login.main.template` | Replace the `<div id="login">…</div>` body with the new Bootstrap-card markup. |
| `assets/css/TholosBuilder.css` | Add `.tb-navbar` rule block. Change `#wrapper` `height: 100%` → `height: calc(100% - 38px)`. Remove `.hovered`, `#options_container`, `#options_container:hover .hovered`, and `#options_container .info` rules. Replace the `#login*` rule block with new card-supporting rules (or scope them under a fresh class). Optionally remove `#portalinfo_container*` and `#task_frame*` if unreferenced — to be confirmed during implementation. |

No changes to `TholosBuilder.js`, `vendor/`, or any other template.

## 6. Behavior changes

1. **Hover-to-reveal removed.** The current 23px collapsed bar that expands to ~auto-height on hover is gone. The navbar is permanently 38px tall. Net loss of vertical real estate ≈ 15px when not hovering, but the controls are always discoverable.
2. **Add-component and wizard menu items leave the navbar.** Users invoke these through the tree's right-click context menu (existing behavior, simply no longer duplicated in the top bar).
3. **User name and logout leave the brand area.** Both move into a dropdown on the right end of the navbar, mirroring conventional app-shell patterns.

## 7. Acceptance criteria

1. Application loads with the new compact navbar at the top; viewport below it contains the existing 3-pane resizable layout with no body scrollbar.
2. Every preserved action behaves identically — clicking Build still calls `compile('T')`; pressing Enter in the search input still triggers the search; the user-dropdown logout link still navigates to `$cgi?action=logout`; etc.
3. The tree still loads and refreshes correctly via the navbar refresh button (icon retains `id="globalLoading"`).
4. Login page renders as the Bootstrap card; submitting valid credentials proceeds; submitting invalid credentials renders the `$errormsg` text inside the `alert-danger` block.
5. No console errors. No visual regression in the three resizable panes (jQuery UI resizable handles still work).
6. The `assets/css/v3/` directory is gone; no template references it.

## 8. Decisions log

- **Bootstrap base.** Stay on `vendor/bower-asset/bootstrap` (vanilla Bootstrap 5.3.8). Do not adopt Bootswatch Sandstone — the screenshot palette uses vanilla `--bs-primary` / `--bs-success`, not Sandstone's muted blue/lime, and Sandstone forces uppercase nav text which the screenshot does not show.
- **Navbar background.** Custom dark gray `#3a3f44` rather than Bootstrap's `bg-dark` (`#212529`), to soften contrast.
- **Approach A vs B.** Navbar lives in `main.template`, not in `navframe.main.template` with `position: fixed` — chosen to avoid lifecycle coupling with tree reloads and z-index workarounds.
- **Brand mark on white pill.** The favicon's green-on-transparent design loses contrast on dark gray; a 24×24 white pill keeps it legible without redrawing the asset.
