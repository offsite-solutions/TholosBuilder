# Wizard Inner Results Restyle &mdash; Design Spec

**Date:** 2026-05-04
**Branch:** `feature/ui-redesign`
**Sub-project:** Wizard inner results (sub-project 7 in the UI redesign series)

## Goal

Restyle the inner templates that wizards render inside their `#wizard_result` area or as full sub-pages: Query/SP diff tables, Grid columns sub-form, and Commit issue/status/history pages. Replace the bare `<table>` / `<table class="redmine-issues">`, legacy `<button class="button">`, and `<h1 class="text-center">Tholos :: …</h1>` chrome with BS5 cards, `.table.table-sm.table-hover`, BS5 buttons, and small section headings consistent with the wiz-card scheme. Delete the now-unused `.redmine-issues` CSS rules.

## Scope

**Group A &mdash; Query/SP diff tables** (rendered after Generate inside `#wizard_result`):

| Template | Action |
|---|---|
| `wizards.query.result.main.template` | Wrap in card per component result; convert to BS5 table; add status badge in header |
| `wizards.query.result.property.template` | Keep `<tr class="$status">` shape; row colors come from new CSS |
| `wizards.query.result.foot.template` | Replace legacy button with `.btn.btn-primary` in `.text-end.mt-3` |
| `wizards.storedprocedure.result.main.template` | Same as query.result.main (no checkbox column &mdash; SP rows have no apply-checkbox) |
| `wizards.storedprocedure.result.property.template` | Same as query.result.property minus the apply-checkbox cell |
| `wizards.storedprocedure.result.foot.template` | Same as query.result.foot |

**Group B &mdash; Grid columns sub-form** (rendered after picking a Grid):

| Template | Action |
|---|---|
| `wizards.grid.columns.main.template` | Drop `<form style="margin-top: …">` inline style; wrap rows in `.ef-controls`; Skip-IDs becomes `.form-check`; submit button in `.text-end.mt-3`; section title via `.form-section-title` |
| `wizards.grid.columns.row.template` | Adopt the same flex-row pattern as `editform.controls.row` (label-left + select-right with `form-select form-select-sm`) |

**Group C &mdash; Commit issue/status/history pages**:

| Template | Action |
|---|---|
| `wizards.commit.status.template` | Wrap in `wiz-card` titled "Issue status"; stack the three sub-includes inside card-body; history link in card-footer |
| `wizards.commit.opened.template` | `<h3>` &rarr; `<h6 class="form-section-title">`; table class `redmine-issues` &rarr; `table table-sm table-hover`; `<thead class="table-light">` |
| `wizards.commit.uncommitted.all.template` | Same as `commit.opened` |
| `wizards.commit.uncommitted.own.template` | Same as `commit.opened` |
| `wizards.commit.multiuser.main.template` | Same as `commit.opened` |
| `wizards.commit.history.main.template` | Drop `<h1>`; wrap in `wiz-card` titled "Commit history"; convert table to BS5 |
| `wizards.commit.row.template` | Wrap the existing `<tr>` in proper `<tr>` markup (current row has unbalanced `<td>` tag &mdash; missing `</td>`); style stays driven by parent table |
| `wizards.commit.multiuser.row.template` | No structural change; rows already balanced |
| `wizards.commit.history.row.template` | No structural change; rows already balanced |
| `wizards.commit.result.template` | Drop `<h1>`; wrap in `wiz-card` titled "Commit result"; preserve `reloadTaskFrame()` script |

**Group D &mdash; Routes filter** (the route-selector checkbox grid):

| Template | Action |
|---|---|
| `filter.main.template` | Wrap in `wiz-card` titled "Routes filter"; convert button to `.btn.btn-primary` in `card-footer.text-end`; replace inline column-count CSS with a Bootstrap `.row.row-cols-2` grid |
| `filter.row.template` | Wrap each route in a `.form-check` with proper `.form-check-input` + `.form-check-label`; preserve `name="filter_route_$id"` and `$checked` token |

**Group E &mdash; Search results**:

| Template | Action |
|---|---|
| `search.main.template` | Drop the `<h1>`; wrap the four sections in a single `wiz-card` titled "Search results"; keep the four `<%SQL%>` blocks; replace bare tables with `.table.table-sm.table-hover` and `<thead class="table-light">`; section headings via `.form-section-title`; preserve the `<p>Search term: <b>$searchfor</b></p>` line styled |
| `search.row.template` | Drop inline styles; the `<small>` text moves to a `.text-muted small` class |
| `search.rowcomponent.template` | Drop inline `border-top` style (BS5 table borders take over); drop the `color:#aa0000;font-weight: bold;` style on the component name &mdash; replace with `.fw-semibold.text-danger` (or keep red as a meaningful "no value yet" semantic, brand-agnostic); preserve the `<%FUNC%>` block byte-for-byte |
| `search.rownull.template` | No structural change &mdash; spans 3 columns; ensure the wrapping `<tr>` works with the new `.table` class (it does) |

**Out of scope:**

- `wizards.commit.sql.template` &mdash; bare `<%SQL%>` block, no chrome to restyle
- Any PHP/AJAX handler changes
- Any change to status enum names (`new`, `modify`, `delete`, `unchanged`) &mdash; purely styled by CSS
- Renaming any element id or callback (`#setFilterRoute`, `#search_result`, `addRoute()`, `setFilterRoute()`, etc.)

## Architecture

### Diff-card pattern (Group A)

Each component result becomes:

```html
<div class="card mb-2 wiz-result-card">
  <div class="card-header py-2 px-3">
    <span class="diff-status diff-status-$status">$status</span>
    <span class="ms-2 fw-semibold">$o_fieldname</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0 diff-table">
      <thead class="table-light">
        <tr>
          <th style="width:1%"></th>            <!-- omitted in SP variant -->
          <th>Property</th>
          <th>Original Value</th>
          <th>New Value</th>
          <th style="width:1%">Status</th>
        </tr>
      </thead>
      <tbody>$properties</tbody>
    </table>
  </div>
</div>
```

Property rows (`<tr class="$status"><td>...$apply_checkbox / $prop_name / $origvalue / $value / $status</td></tr>`) keep their `class="$status"` so the new CSS can color them by status.

### Section pattern (Group C inside wiz-card)

Each commit sub-section becomes:

```html
<div class="form-section-title">Open issues</div>
<table class="table table-sm table-hover mb-3">
  <thead class="table-light">
    <tr>
      <th style="width:10%">Issue #</th>
      <th>Issue</th>
      <th style="width:30%">Route</th>
      <th style="width:10%">Owner</th>
    </tr>
  </thead>
  <tbody>
<%SQL%...ROW=tholosbuilder/wizards.commit.row;...%SQL%>
  </tbody>
</table>
```

The `<table class="redmine-issues">` (used by all five Commit table templates) becomes `<table class="table table-sm table-hover">`. The four legacy `.redmine-issues` rules in `TholosBuilder.css` are deleted in the same task that adds the new CSS.

### Page-level wiz-card pattern (Group C top-level pages)

`wizards.commit.status.template`:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-clipboard-list"></i> Issue status</h5>
  </div>
  <div class="card-body">
    $templateabs_tholosbuilder__wizards_commit_opened
    $templateabs_tholosbuilder__wizards_commit_uncommitted_all
    $templateabs_tholosbuilder__wizards_commit_multiuser_main
  </div>
  <div class="card-footer text-end">
    <a href="javascript:showCommitHistory()" class="btn btn-link">
      <i class="fa-regular fa-clock-rotate-left me-1"></i>Show commit history
    </a>
  </div>
</div>
```

`wizards.commit.history.main.template`: same wiz-card shape with the history table in card-body.

`wizards.commit.result.template`: same wiz-card shape, `$result` in card-body, the existing `<script>reloadTaskFrame();</script>` preserved verbatim **outside** the card.

## Shared CSS additions

A new block appended to `assets/css/TholosBuilder.css`:

```css
/* === Wizard inner results ============================== */
.wiz-result-card {
  max-width: 900px;
  margin-left: auto;
  margin-right: auto;
}
.wiz-result-card .card-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
  font-size: 13px;
}
.diff-status {
  display: inline-block;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .03em;
  text-transform: uppercase;
  padding: .1rem .4rem;
  border-radius: 3px;
  background: #e9ecef;
  color: #495057;
}
.diff-status-new       { background: #d1e7dd; color: #0a3622; }
.diff-status-modify    { background: #fff3cd; color: #664d03; }
.diff-status-delete    { background: #f8d7da; color: #58151c; }
.diff-status-unchanged { background: #e9ecef; color: #6c757d; }

.diff-table tr.new       { background-color: rgba(25, 135, 84, .06); }
.diff-table tr.modify    { background-color: rgba(255, 193, 7, .08); }
.diff-table tr.delete    { background-color: rgba(220, 53, 69, .06); }
.diff-table tr.unchanged { color: #6c757d; }
.diff-table td,
.diff-table th { font-size: 12px; }
```

And the four `.redmine-issues` rules at lines 438-453 of `TholosBuilder.css` are **deleted** in the same task:

```css
table.redmine-issues { … }
table.redmine-issues th { … }
table.redmine-issues tr.issue-subject { … }
table.redmine-issues tr.issue-subject td { … }
```

## Brand palette

- Card header background: `#f8f9fa` (matches wiz-card scheme)
- Status badges: green/amber/red/grey tints (low-saturation, work on light backgrounds)
- Row tints: very subtle (5-8% alpha) so the table still reads as a table; only `unchanged` rows lose their normal text color
- Section heading: `.form-section-title` (already defined in wizards CSS)

## Testing

This project has no automated test suite. Verification is manual in the browser:

**Group A:**
1. Open Query wizard, pick a TQuery component, click Generate &mdash; confirm each component result renders as a card with status badge in header and BS5 table inside; confirm row colors match status; confirm Save button still triggers `QueryWizardRunSave()` and persists.
2. Open Stored Procedure wizard, repeat with a TStoredProcedure &mdash; confirm same look minus the apply-checkbox column; confirm Save button still triggers `StoredProcedureWizardRun(..., 'save')`.

**Group B:**
3. Open Grid wizard, pick a TGrid &mdash; confirm the columns sub-form renders with the new flex layout; confirm Skip-IDs checkbox toggles; confirm "Create grid columns and filters" still triggers `GridWizardRun()`.

**Group C:**
4. Open the Issue status page (the menu link, or however it's reached) &mdash; confirm one wiz-card with three stacked sections (Open issues, Uncommitted, Multiuser) and history link in footer; confirm tables render with the new style.
5. Click Show commit history &mdash; confirm a card titled "Commit history" with the BS5 table.
6. Trigger a commit (or manually invoke the result page if reachable) &mdash; confirm the result card renders and `reloadTaskFrame()` fires.

## Risks &amp; mitigations

| Risk | Mitigation |
|---|---|
| Removing the `.redmine-issues` CSS rules might break a template I missed | Grep for `redmine-issues` across all templates first; if any survivor exists, leave the CSS rules in place |
| Status enum names from PHP might include values I haven't styled (e.g. "remove" vs "delete") | Add a fallback rule: `.diff-table tr` keeps its default style; specific status classes override. Worst case, an unknown status renders unstyled but still readable |
| The Query result row template includes an `$apply_checkbox` cell; the SP row template does not | Use **separate** templates &mdash; do not try to share. Each row's columns must match its parent table's headers exactly |
| The `wizards.commit.row.template` has an unclosed `<td>` tag (line 3 in current file) | Treat as a separate cleanup-or-ignore decision: this spec includes the fix in Group C since the table headers/cells alignment matters for hover styling |
| The `commit.status.template` uses `$templateabs_…` token includes that may have side effects on whitespace | Preserve the include tokens byte-for-byte; only wrap them in the new card markup |
| Inline `style="margin-top: …"` removal might collapse spacing somewhere | Use BS5 `mt-N` / `mb-N` utilities on the equivalent containers; inspect the rendered page to confirm no jarring layout shift |

## Decomposition into tasks

This spec maps to seven tasks:

1. CSS &mdash; add `.wiz-result-card` / `.diff-status` / `.diff-table` block; delete the `.redmine-issues` rules
2. Group A &mdash; Query result (main + property + foot)
3. Group A &mdash; Stored Procedure result (main + property + foot)
4. Group B &mdash; Grid columns sub-form (main + row)
5. Group C &mdash; Commit pages (status + opened + uncommitted &times; 2 + multiuser.main + history.main + commit.row + commit.result; multiuser.row and history.row stay structural)
6. Group D &mdash; Routes filter (filter.main + filter.row)
7. Group E &mdash; Search results (search.main + search.row + search.rowcomponent + search.rownull)

Each task is one or more template edits + visual verification + commit.
