# Wizards Restyle &mdash; Design Spec

**Date:** 2026-05-04
**Branch:** `feature/ui-redesign`
**Sub-project:** Wizards (sub-project 5 in the UI redesign series)

## Goal

Restyle every `wizards.*.main.template` to one shared Bootstrap 5 card-wrapped layout that matches the rest of the redesigned application. Drop the legacy `<button class="button">`, inline `style="margin-top: …"` chrome, and BS3-era `text-right` markup. Keep all existing JavaScript entry points (`QueryWizardRun`, `EditFormWizardRun`, `saveCommitChanges`, `saveUserProfile`, `saveHelp`, etc.) unchanged.

## Scope

**In scope** &mdash; one shared card layout applied to:

| Template | Wizard | Card body shape |
|---|---|---|
| `wizards.query.main.template` | Query wizard | Component select + Translate root + Skip Label checkbox |
| `wizards.storedprocedure.main.template` | Stored Procedure wizard | Component select |
| `wizards.grid.main.template` | Grid wizard | Component select |
| `wizards.editform.main.template` | Edit Form wizard | Form select + Query select + Excluded columns |
| `wizards.editform.controls.main.template` | Edit Form sub-form | Embedded controls list (no card of its own) |
| `wizards.commit.main.template` | Commit wizard | Commit message + Redmine + Time tracking sub-sections |
| `wizards.userprofile.main.template` | User Profile | Credential fields |
| `wizards.help.main.template` | Help editor | CKEditor full-bleed in `card-body p-0`, Save + Delete in footer |

**Out of scope:**

- Inner result/list templates that render *inside* `#wizard_result` after the wizard runs:
  - `wizards.query.result.*.template` (already polished by the recent QueryWizard selective-apply work)
  - `wizards.storedprocedure.result.*.template`
  - `wizards.grid.columns.row.template`
  - `wizards.editform.controls.row.template`
  - `wizards.commit.history.*.template`, `wizards.commit.row.template`, `wizards.commit.opened.template`, `wizards.commit.uncommitted.*.template`, `wizards.commit.result.template`, `wizards.commit.sql.template`, `wizards.commit.status.template`, `wizards.commit.multiuser.*.template`
  - `wizards.userprofile.row.template`
- Form-editor templates (`propframe.form.*`) &mdash; deferred to a separate sub-project.
- Any change to PHP/Eisodos callback handlers or AJAX endpoints.

Sub-form templates that render *inside* the wizard card (`wizards.editform.controls.main.template`, `wizards.commit.history.main.template` if it appears inline) get tiny adjustments only to fit the card &mdash; full restyle of their inner rows is out of scope.

## Architecture

Every in-scope `wizards.X.main.template` gets the same shape:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5 class="mb-0"><i class="fa-regular fa-…"></i> <wizard name></h5>
  </div>
  <div class="card-body">
    <form id="…">
      <div class="row mb-3">
        <label class="col-md-3 col-form-label text-md-end">Field:</label>
        <div class="col-md-9">
          <select class="form-select" id="…">…</select>
        </div>
      </div>
      … additional fields …
    </form>
  </div>
  <div class="card-footer text-end">
    <button type="button" class="btn btn-primary" onclick="…">
      <i class="fa-regular fa-…"></i> Action label
    </button>
  </div>
</div>

<div id="wizard_result" class="wiz-result"></div>
```

Key points:

- The `<h1 class="text-center">Tholos :: Foo wizard</h1>` title goes away. The wizard name moves to a small `<h5>` in `.card-header` with a contextual FA icon.
- Form layout is **horizontal** (preserved from current): `.col-md-3` label + `.col-md-9` control, label `text-md-end col-form-label`.
- Action button is `.btn.btn-primary`, footer-right (`.card-footer.text-end`).
- `#wizard_result` stays **outside** the card, below it, with a `.wiz-result` wrapper for max-width alignment.
- Inline `style="…"` attributes are removed; spacing comes from BS5 utility classes (`mb-3`, `mt-3`).
- Bare `<select>` becomes `<select class="form-select">`.
- Bare `<input>` becomes `<input class="form-control">`.
- Bare `<input type="checkbox">` becomes `<input class="form-check-input">` inside `<div class="form-check">` with `<label class="form-check-label">`.
- `<button class="button">` becomes `<button type="button" class="btn btn-primary">`.

## Per-wizard treatment

### Query wizard (`wizards.query.main.template`)

- Card header icon: `fa-regular fa-database`
- Three rows: Query select, Translate root input + help-text "component route is the default", Skip Label checkbox
- Action button: "Generate / Update TDBFields"

### Stored Procedure wizard (`wizards.storedprocedure.main.template`)

- Card header icon: `fa-regular fa-gears`
- One row: Stored procedure select
- Action button: "Generate / Update TDBParams"

### Grid wizard (`wizards.grid.main.template`)

- Card header icon: `fa-regular fa-table`
- One row: Grid select
- Action button: "Generate / Update TGridColumns"
- The conditional `[%_function_name=…_eq;param=p_grid_id;false=tholosbuilder/wizards.grid.columns.main%]` template-include stays; the included sub-template renders inside `#wizard_result` (or its own area &mdash; preserve current behavior).

### Edit Form wizard (`wizards.editform.main.template`)

- Card header icon: `fa-regular fa-rectangle-list`
- Three rows: Form select, Query select, Excluded columns input
- Action button: "Create form controls" *or* re-label to "Generate / Update inputs" to match the existing button text &mdash; keep current text **"Generate / Update inputs"**.
- The conditional include of `wizards.editform.controls.main` renders the sub-form. The sub-form gets a `.form-section-title` heading "Suggested controls" inside the same card-body.

### Edit Form controls sub-form (`wizards.editform.controls.main.template`)

- Drops the wrapping `<form id="editformForm" style="margin-top: 20px;">` outer styling but keeps the `<form>` element (existing JS posts that form).
- Wraps the SQL-rendered rows in a BS5 `<table class="table table-sm align-middle">` with `<thead class="table-light">` (Column / Preferred component / Include checkbox).
- The "Create form controls" button moves to a footer area inside the same card-body (a `.text-end.mt-3` div).
- **Note:** This task only restyles the outer wrapper. The `wizards.editform.controls.row.template` (per-row markup) stays untouched per scope &mdash; if its current `<tr>` markup doesn't fit the table, fall back to a `<div>` list with the same `pf-list-row` pattern from the prop editor.

### Commit wizard (`wizards.commit.main.template`)

- Card header icon: `fa-regular fa-code-commit`
- Layout inside one `card-body`:
  - Plain text: "Committing routes: $routes" (small, muted)
  - Commit message: full-width `<textarea class="form-control" rows="4">`
  - `.form-section-title` "Redmine"
  - Two-column row: Issue new status select + Assign to select
  - Note `<textarea class="form-control" rows="2">`
  - `.form-section-title` "Time tracking"
  - Two-column row: Time spent input + Activity select
- Action button: "Commit changes"
- Hidden inputs (`p_rm_current_status`, `p_rm_current_assigned_to`) stay inside the form unchanged.

### User Profile (`wizards.userprofile.main.template`)

- Card header icon: `fa-regular fa-user-gear`
- The `<%SQL%>` block and `wizards.userprofile.row.template` are out of scope &mdash; the row template renders the field rows. This template only restyles the outer wrapper:
  - `<form>` becomes the card-body content
  - The `<h1 class="text-center">User profile</h1>` becomes the `.card-header h5`
  - The "Save" button moves to `.card-footer.text-end`

### Help editor (`wizards.help.main.template`)

- Card header icon: `fa-regular fa-circle-question`
- `card-body p-0` wraps the existing `<textarea name="p_text" id="helpHTML">` (CKEDITOR.replace stays untouched)
- Footer is `d-flex justify-content-between align-items-center`:
  - Left: `<a class="btn btn-link text-danger p-0">` Delete (preserves existing onclick)
  - Right: `<button type="button" class="btn btn-primary">` Save (new &mdash; calls existing `saveHelp()` which is wired via `<form action="javascript:saveHelp();">`; this button triggers form submit or calls saveHelp directly)
- The existing `<form action="javascript:saveHelp();">` keeps its action; the explicit Save button replaces the implicit submit-on-enter pattern.

## Shared CSS additions

A new `=== Wizards ===` section appended to `assets/css/TholosBuilder.css`:

```css
/* === Wizards =========================================== */
.wiz-card {
  max-width: 900px;
  margin: 1rem auto;
}
.wiz-card .card-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
  padding: .55rem .9rem;
}
.wiz-card .card-header h5 {
  font-size: .9rem;
  font-weight: 600;
  color: #236499;
  margin: 0;
  display: flex;
  align-items: center;
}
.wiz-card .card-header h5 .fa-solid,
.wiz-card .card-header h5 .fa-regular {
  color: #88bd21;
  margin-right: .55rem;
  font-size: 14px;
}
.wiz-card .card-body { padding: 1rem 1.1rem; }
.wiz-card .card-footer {
  background-color: #f8f9fa;
  padding: .55rem .9rem;
}
.wiz-card .col-form-label { font-size: 13px; }
.wiz-card .form-select,
.wiz-card .form-control { font-size: 13px; }
.wiz-card .form-check-label { font-size: 13px; }
.wiz-card .help-text {
  font-size: 12px;
  color: #6c757d;
  font-style: italic;
  margin-top: .25rem;
}
.wiz-card .form-section-title {
  font-size: .7rem;
  letter-spacing: .06em;
  color: #6c757d;
  text-transform: uppercase;
  margin: 1.1rem 0 .55rem 0;
  padding-bottom: .35rem;
  border-bottom: 1px solid #e9ecef;
  font-weight: 600;
}
.wiz-result {
  max-width: 900px;
  margin: 1rem auto 0 auto;
}
```

The legacy `.button` selector (if any rule targets it specifically) gets cleaned up only if the cleanup is local to this CSS file &mdash; otherwise leave it alone (it may still be referenced by out-of-scope templates).

## Brand palette

- Header text: offsite-blue `#236499`
- Header icon: offsite-green `#88bd21`
- Primary button background: offsite-blue `#236499` (BS5 `--bs-btn-bg` override already applied globally; if not, scope it under `.wiz-card`)
- Pane background: `#f8f9fa` (matches tree pane and prop pane)

If the global `.btn-primary` doesn't already use offsite-blue, override `--bs-btn-bg` and `--bs-btn-border-color` inside the `.wiz-card` scope as part of this CSS block.

## Testing

This project has no automated test suite. Verification is manual in the browser:

1. Open each wizard from the UI menu and visually confirm it renders with the new card layout.
2. Click the action button on each wizard and confirm the existing JavaScript callback still executes (no JS console errors).
3. Confirm the result area still appears in the right place (below the card) with no visual regression.
4. Confirm that on Help wizard, both Save and Delete still work and CKEditor still loads.

## Risks &amp; mitigations

| Risk | Mitigation |
|---|---|
| Renaming a select's `id` would break JS callbacks | Preserve every existing `id` attribute exactly (`QueryWizardComponentId`, `EditFormWizardComponentId`, `QueryComponentId`, `Blacklist`, `GridWizardComponentId`, `SPWizardComponentId`, `helpHTML`, etc.) |
| Removing a `<form>` wrapper would break form-serialization on submit | Keep all `<form>` elements; only restyle their inner markup |
| Eisodos `<%SQL%>` and `[%_function_name=…%]` directives are syntax-sensitive | Preserve every directive byte-for-byte; restyle only the surrounding HTML |
| Sub-form templates (`*.row.template`) are out of scope but rendered inline | Their existing markup may not fit a BS5 table; if it doesn't, fall back to a div-list pattern in the parent's wrapper instead of forcing a table |
| Global `.btn-primary` may already be styled elsewhere | Scope brand color overrides under `.wiz-card` if a global rule isn't already in place |

## Decomposition into tasks

This spec maps to seven tasks (one per template family + a shared CSS task), executed in order so each commit is self-contained:

1. Add shared `.wiz-card` CSS block to `TholosBuilder.css`
2. Restyle Query wizard
3. Restyle Stored Procedure + Grid wizards (small, similar)
4. Restyle Edit Form wizard + its `controls.main` sub-form
5. Restyle Commit wizard (multi-section)
6. Restyle User Profile wizard
7. Restyle Help editor wizard (CKEditor card)

Each task is a single template edit + visual verification. The implementation plan document expands these into bite-sized steps.
