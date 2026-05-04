# Wizard Inner Results + Routes Filter + Search Results Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle every template that renders inside a wizard's `#wizard_result` area or as a sub-page (Query/SP diff tables, Grid columns sub-form, Commit pages, Routes filter, Search results). Replace bare `<table>` / `<table class="redmine-issues">` and legacy `<button class="button">` chrome with BS5 cards + `.table.table-sm.table-hover` + `.btn.btn-primary`. Delete the four unused `.redmine-issues` CSS rules.

**Architecture:** New CSS adds `.wiz-result-card`, `.diff-status` (status badge), `.diff-table tr.<status>` (status-tinted rows). Each top-level page becomes a wiz-card (matching the wizards sub-project pattern). Inner section headings reuse the existing `.form-section-title`.

**Tech Stack:** Bootstrap 5.3.8, FontAwesome 6, jQuery 3, jstree, Eisodos templates (`<%SQL%>`, `<%FUNC%>`, `[%_function_name=...%]`).

**Verification:** No automated tests. After each task, the user opens the relevant page in the browser, confirms the new layout, and confirms the existing JS callbacks still fire.

---

## File Structure

| File | Action |
|---|---|
| `assets/css/TholosBuilder.css` | Append `=== Wizard inner results ===` block, delete `.redmine-issues` rules (Task 1) |
| `assets/templates/tholosbuilder/wizards.query.result.main.template` | Rewrite (Task 2) |
| `assets/templates/tholosbuilder/wizards.query.result.property.template` | Rewrite (Task 2) |
| `assets/templates/tholosbuilder/wizards.query.result.foot.template` | Rewrite (Task 2) |
| `assets/templates/tholosbuilder/wizards.storedprocedure.result.main.template` | Rewrite (Task 3) |
| `assets/templates/tholosbuilder/wizards.storedprocedure.result.property.template` | Rewrite (Task 3) |
| `assets/templates/tholosbuilder/wizards.storedprocedure.result.foot.template` | Rewrite (Task 3) |
| `assets/templates/tholosbuilder/wizards.grid.columns.main.template` | Rewrite (Task 4) |
| `assets/templates/tholosbuilder/wizards.grid.columns.row.template` | Rewrite (Task 4) |
| `assets/templates/tholosbuilder/wizards.commit.opened.template` | Rewrite (Task 5) |
| `assets/templates/tholosbuilder/wizards.commit.uncommitted.all.template` | Rewrite (Task 5) |
| `assets/templates/tholosbuilder/wizards.commit.uncommitted.own.template` | Rewrite (Task 5) |
| `assets/templates/tholosbuilder/wizards.commit.multiuser.main.template` | Rewrite (Task 5) |
| `assets/templates/tholosbuilder/wizards.commit.history.main.template` | Rewrite (Task 5) |
| `assets/templates/tholosbuilder/wizards.commit.row.template` | Rewrite (Task 5) |
| `assets/templates/tholosbuilder/wizards.commit.status.template` | Rewrite (Task 5) |
| `assets/templates/tholosbuilder/wizards.commit.result.template` | Rewrite (Task 5) |
| `assets/templates/tholosbuilder/filter.main.template` | Rewrite (Task 6) |
| `assets/templates/tholosbuilder/filter.row.template` | Rewrite (Task 6) |
| `assets/templates/tholosbuilder/search.main.template` | Rewrite (Task 7) |
| `assets/templates/tholosbuilder/search.row.template` | Rewrite (Task 7) |
| `assets/templates/tholosbuilder/search.rowcomponent.template` | Rewrite (Task 7) |
| `assets/templates/tholosbuilder/search.rownull.template` | No change &mdash; one `<tr>` already works (Task 7 verifies) |

---

## Task 1: Add CSS, delete `.redmine-issues` rules

**Files:**
- Modify: `assets/css/TholosBuilder.css`

- [ ] **Step 1: Append the new CSS block**

Append exactly this content to the end of `assets/css/TholosBuilder.css`:

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

- [ ] **Step 2: Delete the `.redmine-issues` rules**

Find these four blocks in `assets/css/TholosBuilder.css` (currently around lines 438-453) and delete all four:

```css
table.redmine-issues {
  width: 100%;
}

table.redmine-issues th {
  background-color: lightgrey;
  font-weight: bold;
}

table.redmine-issues tr.issue-subject {
  border-bottom: 1px solid lightgrey;
}

table.redmine-issues tr.issue-subject td {
  padding: 5px;
}
```

- [ ] **Step 3: Commit**

```bash
git add assets/css/TholosBuilder.css
git commit -m "Inner results: add diff/result CSS, drop legacy .redmine-issues rules"
```

---

## Task 2: Query result templates

**Files:**
- Modify: `wizards.query.result.main.template`, `wizards.query.result.property.template`, `wizards.query.result.foot.template`

- [ ] **Step 1: Rewrite `wizards.query.result.main.template`**

Replace the entire content with:

```html
<div class="card mb-2 wiz-result-card">
  <div class="card-header py-2 px-3">
    <span class="diff-status diff-status-$status">$status</span>
    <span class="ms-2 fw-semibold">$create_checkbox$o_fieldname</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0 diff-table">
      <thead class="table-light">
        <tr>
          <th style="width:1%"></th>
          <th>Property</th>
          <th>Original Value</th>
          <th>New Value</th>
          <th style="width:1%">Status</th>
        </tr>
      </thead>
      <tbody>
        $properties
      </tbody>
    </table>
  </div>
</div>
```

Preserved: `$status` (used for badge class + text), `$create_checkbox`, `$o_fieldname`, `$properties` Eisodos tokens.

- [ ] **Step 2: Rewrite `wizards.query.result.property.template`**

Replace the entire content with:

```html
<tr class="$status">
  <td>$apply_checkbox</td>
  <td>$prop_name</td>
  <td>$origvalue</td>
  <td>$value</td>
  <td>$status</td>
</tr>
```

No structural change &mdash; just keeping the row template clean. The `class="$status"` is what the new `.diff-table tr.<status>` CSS targets.

- [ ] **Step 3: Rewrite `wizards.query.result.foot.template`**

Replace the entire content with:

```html
  <input type="hidden" name="action" value="QueryWizardRun">
  <input type="hidden" name="p_component_id" value="$p_component_id">
  <input type="hidden" name="p_trans_root" value="$p_trans_root">
  <input type="hidden" name="p_skip_label" value="$p_skip_label">
  <input type="hidden" name="todo" value="save">
  <div class="text-end mt-3">
    <button type="button" class="btn btn-primary" onclick="QueryWizardRunSave();">
      <i class="fa-regular fa-floppy-disk me-1"></i>Save changes
    </button>
  </div>
</form>
```

Preserved: every hidden input (`action`, `p_component_id`, `p_trans_root`, `p_skip_label`, `todo`); the `QueryWizardRunSave()` callback; the closing `</form>` tag (this template is the foot of a form opened elsewhere).

- [ ] **Step 4: User verification**

Ask the user to open the Query wizard, pick a TQuery component, click "Generate / Update TDBFields". For each component result:
- Confirm a card renders with status badge + component name in the header
- Confirm the BS5 table renders with status-colored rows
- Confirm "Save changes" still triggers `QueryWizardRunSave()` and persists

- [ ] **Step 5: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.query.result.main.template assets/templates/tholosbuilder/wizards.query.result.property.template assets/templates/tholosbuilder/wizards.query.result.foot.template
git commit -m "Inner results: restyle Query result cards"
```

---

## Task 3: Stored Procedure result templates

**Files:**
- Modify: `wizards.storedprocedure.result.main.template`, `wizards.storedprocedure.result.property.template`, `wizards.storedprocedure.result.foot.template`

- [ ] **Step 1: Rewrite `wizards.storedprocedure.result.main.template`**

Replace the entire content with:

```html
<div class="card mb-2 wiz-result-card">
  <div class="card-header py-2 px-3">
    <span class="diff-status diff-status-$status">$status</span>
    <span class="ms-2 fw-semibold">$o_parametername</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0 diff-table">
      <thead class="table-light">
        <tr>
          <th>Property</th>
          <th>Original Value</th>
          <th>New Value</th>
          <th style="width:1%">Status</th>
        </tr>
      </thead>
      <tbody>
        $properties
      </tbody>
    </table>
  </div>
</div>
```

Preserved: `$status`, `$o_parametername`, `$properties` tokens.

- [ ] **Step 2: Rewrite `wizards.storedprocedure.result.property.template`**

Replace the entire content with:

```html
<tr class="$status">
  <td>$prop_name</td>
  <td>$origvalue</td>
  <td>$value</td>
  <td>$status</td>
</tr>
```

- [ ] **Step 3: Rewrite `wizards.storedprocedure.result.foot.template`**

Replace the entire content with:

```html
<div class="text-end mt-3">
  <button type="button" class="btn btn-primary" onclick="StoredProcedureWizardRun($p_component_id,'save');">
    <i class="fa-regular fa-floppy-disk me-1"></i>Save changes
  </button>
</div>
```

Preserved: `StoredProcedureWizardRun($p_component_id,'save')` callback byte-for-byte.

- [ ] **Step 4: User verification**

Ask the user to open the Stored Procedure wizard, pick a TStoredProcedure, click "Generate / Update TDBParams". Confirm cards render with the same look as Query results minus the apply-checkbox column; confirm Save still triggers `StoredProcedureWizardRun(..., 'save')`.

- [ ] **Step 5: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.storedprocedure.result.main.template assets/templates/tholosbuilder/wizards.storedprocedure.result.property.template assets/templates/tholosbuilder/wizards.storedprocedure.result.foot.template
git commit -m "Inner results: restyle Stored Procedure result cards"
```

---

## Task 4: Grid columns sub-form

**Files:**
- Modify: `wizards.grid.columns.main.template`, `wizards.grid.columns.row.template`

- [ ] **Step 1: Rewrite `wizards.grid.columns.main.template`**

Replace the entire content with:

```html
<div class="form-section-title">Suggested columns</div>
<form id="gridForm">
  <input type="hidden" name="p_grid_id" value="$p_grid_id">
  <input type="hidden" name="action" value="GridWizardRun">
  <div class="ef-controls">
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/wizards.grid.columns.row;
SQL=
SELECT atp.id, atp.name
  FROM app_tree_path_v atp
 WHERE parent_id = (SELECT value_component_id
                      FROM app_component_properties_v acp
                     WHERE acp.component_id = $p_grid_id
                       AND acp.name = 'ListSource')
   AND atp.class_name = 'TDBField'
   AND atp.id NOT IN
       (SELECT value_component_id
          FROM app_component_properties_v acp
         WHERE acp.component_id IN (SELECT atp.id
                                      FROM app_tree_path_v atp
                                     WHERE parent_id = $p_grid_id
                                       AND atp.class_name = 'TGridColumn')
           AND acp.name = 'DBField'
           AND VALUE IS NOT NULL)
 ORDER BY atp.component_order
%SQL%>
  </div>
  <div class="form-check mt-3">
    <input class="form-check-input" type="checkbox" name="GridWizardSkipIDs" id="GridWizardSkipIDs" value="T" checked>
    <label class="form-check-label" for="GridWizardSkipIDs">Skip empty DBField's assign to ID column</label>
  </div>
</form>
<div class="text-end mt-3" id="gridFormPost">
  <button type="button" class="btn btn-primary" onclick="GridWizardRun();">
    <i class="fa-regular fa-circle-plus me-1"></i>Create grid columns and filters
  </button>
</div>
```

Preserved: `<form id="gridForm">`, both hidden inputs, the `<%SQL%>` block byte-for-byte, the `GridWizardSkipIDs` id and `value="T"`, the `gridFormPost` id, the `GridWizardRun()` callback.

- [ ] **Step 2: Rewrite `wizards.grid.columns.row.template`**

Replace the entire content with:

```html
<div class="row mb-2 align-items-center ef-controls-row">
  <div class="col-md-5"><b>$sqlname</b></div>
  <div class="col-md-7">
    <select class="form-select form-select-sm" name="option$sqlid">
      <option value="2">Column and Filter</option>
      <option value="1">Column only</option>
      <option value="3">Export only</option>
      <option value="0">Skip</option>
    </select>
  </div>
</div>
```

- [ ] **Step 3: User verification**

Ask the user to open Grid wizard, pick a TGrid. Confirm:
- "Suggested columns" heading appears
- Each row shows the column name + a per-column action select (Column and Filter / Column only / Export only / Skip)
- Skip-IDs checkbox toggles
- "Create grid columns and filters" still triggers `GridWizardRun()`

- [ ] **Step 4: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.grid.columns.main.template assets/templates/tholosbuilder/wizards.grid.columns.row.template
git commit -m "Inner results: restyle Grid columns sub-form"
```

---

## Task 5: Commit pages

**Files:**
- Modify: 8 templates listed below

- [ ] **Step 1: Rewrite `wizards.commit.opened.template`**

Replace the entire content with:

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
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/wizards.commit.row;
SQL=
select distinct at.task_number, at.subject, au.code, atp.route
  from app_tasks at
  join app_users au on au.id=at.created_by
  left outer join app_changes ac on at.id=ac.task_id
  left outer join app_tree_path_v atp on ac.component_id=atp.id
 where at.committed is null
       and at.closed='N'
 order by 3,1
%SQL%>
  </tbody>
</table>
```

- [ ] **Step 2: Rewrite `wizards.commit.uncommitted.all.template`**

Replace with:

```html
<div class="form-section-title">Uncommitted issues &mdash; all issues</div>
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
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/wizards.commit.row;
SQL=
select distinct at.task_number, at.subject, au.code, atp.route
  from app_tasks at
  join app_users au on au.id=at.created_by
  left outer join app_changes ac on at.id=ac.task_id
  left outer join app_tree_path_v atp on ac.component_id=atp.id
 where at.committed is null
       and at.closed='N'
 order by 3,1
%SQL%>
  </tbody>
</table>
```

- [ ] **Step 3: Rewrite `wizards.commit.uncommitted.own.template`**

Replace with:

```html
<div class="form-section-title">Uncommitted issues &mdash; your issues</div>
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
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/wizards.commit.row;
SQL=
select distinct at.task_number, at.subject, au.code, atp.route
  from app_tasks at
  join app_users au on au.id=at.created_by and au.id=app_session_pkg.user_id
  left outer join app_changes ac on at.id=ac.task_id
  left outer join app_tree_path_v atp on ac.component_id=atp.id
 where at.committed is null
       and at.closed='N'
 order by 3,1
%SQL%>
  </tbody>
</table>
```

- [ ] **Step 4: Rewrite `wizards.commit.multiuser.main.template`**

Replace with:

```html
<div class="form-section-title">Parallel edited uncommitted routes</div>
<table class="table table-sm table-hover mb-3">
  <thead class="table-light">
    <tr>
      <th style="width:20%">Issue #</th>
      <th style="width:50%">Route</th>
      <th>Owner</th>
    </tr>
  </thead>
  <tbody>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/wizards.commit.multiuser.row;
SQL=
select route,
       owner as code,
       (select listagg('#'||task_number, ', ') within group (order by task_number)
          from
       (SELECT distinct at.task_number
          FROM app_changes     ac,
               app_tree_path_v atp,
               app_tasks       at,
               app_users       au
         WHERE ac.component_id = atp.id
           AND at.id = ac.task_id
           AND au.id = at.created_by
           AND at.committed IS NULL
           AND at.closed='N'
           AND atp.route = tt.route)
         ) as task_number
  from (
SELECT route,
       listagg(code,
               ', ') within GROUP(ORDER BY route) AS owner
  FROM (SELECT DISTINCT atp.route,
                        au.code
          FROM app_changes     ac,
               app_tree_path_v atp,
               app_tasks       at,
               app_users       au
         WHERE ac.component_id = atp.id
           AND at.id = ac.task_id
           AND au.id = at.created_by
           AND at.committed IS NULL
           AND at.closed='N') t
 GROUP BY route
 ) tt
 where instr(owner,',')>0
 ORDER BY 1
%SQL%>
  </tbody>
</table>
```

- [ ] **Step 5: Rewrite `wizards.commit.history.main.template`**

Replace with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-clock-rotate-left"></i> Commit history</h5>
  </div>
  <div class="card-body">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:14%">Committed on</th>
          <th style="width:8%">Issue #</th>
          <th>Issue</th>
          <th style="width:30%">Route</th>
          <th style="width:8%">Owner</th>
        </tr>
      </thead>
      <tbody>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/wizards.commit.history.row;
SQL=
select distinct to_char(at.committed,'YYYY.MM.DD. hh24:mi:ss') as committed, at.task_number, at.subject, au.code, atp.route
  from app_tasks at
  join app_users au on au.id=at.created_by
  join app_changes ac on at.id=ac.task_id
  join app_tree_path_v atp on ac.component_id=atp.id
 where at.committed is not null
       and atp.route is not null
 order by 1 desc, 2, 5
%SQL%>
      </tbody>
    </table>
  </div>
</div>
```

- [ ] **Step 6: Rewrite `wizards.commit.row.template`**

Replace with (fixing the unclosed `<td>` from the original):

```html
<tr>
  <td><a href="$RedmineURL/issues/$sqltask_number" target="_blank">#$sqltask_number</a></td>
  <td><a href="$RedmineURL/issues/$sqltask_number" target="_blank">$sqlsubject</a></td>
  <td>$sqlroute</td>
  <td>$sqlcode</td>
</tr>
```

- [ ] **Step 7: Rewrite `wizards.commit.status.template`**

Replace with:

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

Preserved: the three `$templateabs_…` includes byte-for-byte and the `showCommitHistory()` callback.

- [ ] **Step 8: Rewrite `wizards.commit.result.template`**

Replace with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-circle-check"></i> Commit result</h5>
  </div>
  <div class="card-body">
    <div id="wizard_result">
      $result
    </div>
  </div>
</div>

<script type="text/javascript">
  reloadTaskFrame();
</script>
```

Preserved: the `#wizard_result` div id, the `$result` token, the `reloadTaskFrame()` script verbatim outside the card.

- [ ] **Step 9: User verification**

Ask the user to:
- Open the Issue status page (the menu link reachable via the Commit flow). Confirm one wiz-card with three stacked sections (Open issues, Uncommitted &mdash; all issues, Parallel edited uncommitted routes), history link in footer.
- Click "Show commit history" &mdash; confirm the Commit history card with the BS5 table.
- After a commit (if reachable in the test setup): confirm the Commit result card renders and `reloadTaskFrame()` fires.

- [ ] **Step 10: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.commit.opened.template assets/templates/tholosbuilder/wizards.commit.uncommitted.all.template assets/templates/tholosbuilder/wizards.commit.uncommitted.own.template assets/templates/tholosbuilder/wizards.commit.multiuser.main.template assets/templates/tholosbuilder/wizards.commit.history.main.template assets/templates/tholosbuilder/wizards.commit.row.template assets/templates/tholosbuilder/wizards.commit.status.template assets/templates/tholosbuilder/wizards.commit.result.template
git commit -m "Inner results: restyle Commit pages (status/history/issue tables)"
```

---

## Task 6: Routes filter

**Files:**
- Modify: `filter.main.template`, `filter.row.template`

- [ ] **Step 1: Rewrite `filter.main.template`**

Replace the entire content with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-filter"></i> Routes filter</h5>
  </div>
  <div class="card-body">
    <form id="setFilterRoute">
      <input type="hidden" name="action" value="showRoutes">
      <input type="hidden" name="todo" value="save">
      <div class="row row-cols-2 g-1">
        $ROWS
      </div>
    </form>
  </div>
  <div class="card-footer text-end">
    <button type="button" class="btn btn-primary" onclick="setFilterRoute()">
      <i class="fa-regular fa-filter me-1"></i>Show selected routes
    </button>
  </div>
</div>
```

Preserved: `<form id="setFilterRoute">`, both hidden inputs, the `$ROWS` token, the `setFilterRoute()` callback.

- [ ] **Step 2: Rewrite `filter.row.template`**

Replace the entire content with:

```html
<div class="col">
  <div class="form-check">
    <input class="form-check-input" type="checkbox" id="filter_route_$id" name="filter_route_$id" $checked value="T">
    <label class="form-check-label" for="filter_route_$id">$name</label>
  </div>
</div>
```

Preserved: the `name="filter_route_$id"` attribute, the `$checked` token (renders the literal `checked` keyword when applicable), the `value="T"`. Added the matching `id` so the `<label for>` works for click-to-toggle.

- [ ] **Step 3: User verification**

Ask the user to open the routes filter (likely a menu link or icon). Confirm:
- Card titled "Routes filter" renders
- Routes appear as a 2-column checkbox grid
- Clicking a row label toggles the checkbox
- "Show selected routes" still triggers `setFilterRoute()` and reloads with the filtered tree

- [ ] **Step 4: Commit**

```bash
git add assets/templates/tholosbuilder/filter.main.template assets/templates/tholosbuilder/filter.row.template
git commit -m "Inner results: restyle Routes filter as wiz-card with form-check grid"
```

---

## Task 7: Search results

**Files:**
- Modify: `search.main.template`, `search.row.template`, `search.rowcomponent.template`
- No change: `search.rownull.template` (one `<tr><td colspan>` works in BS5 tables as-is; verified in Step 4)

- [ ] **Step 1: Rewrite `search.main.template`**

Replace the entire content with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-magnifying-glass"></i> Search results</h5>
  </div>
  <div class="card-body">
    <p class="mb-3">Search term: <code>$searchfor</code></p>

    <div id="search_result">
      <div class="form-section-title">Components</div>
      <table class="table table-sm table-hover mb-3">
        <thead class="table-light">
          <tr>
            <th>Component</th>
            <th>Route</th>
            <th>Path</th>
          </tr>
        </thead>
        <tbody>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/search.rowcomponent;
ROWNULL=tholosbuilder/search.rownull;
SQL=
select tpv.id, tpv.name_with_type, tpv.path, null as component_name, null as value,
       tpv.route_id, tpv.route
  from app_tree_path_v tpv
 where lower(tpv.name_with_type) like lower('%$searchFor%')
 order by tpv.route, tpv.path
%SQL%>
        </tbody>
      </table>

      <div class="form-section-title">Properties</div>
      <table class="table table-sm table-hover mb-3">
        <thead class="table-light">
          <tr>
            <th style="width:30%">Property</th>
            <th>Component</th>
          </tr>
        </thead>
        <tbody>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/search.row;
ROWNULL=tholosbuilder/search.rownull;
SQL=
select tpv.id, tpv.name_with_type, tpv.path, cpv.name as component_name, '<pre>'||cpv.value||'</pre>' as value,
       tpv.route_id, tpv.route
  from app_component_properties_v cpv,
       app_tree_path_v tpv
 where lower(cpv.value) like lower('%$searchFor%')
       and tpv.id=cpv.component_id
 order by tpv.route, tpv.path, cpv.name
%SQL%>
        </tbody>
      </table>

      <div class="form-section-title">Event values</div>
      <table class="table table-sm table-hover mb-3">
        <thead class="table-light">
          <tr>
            <th style="width:30%">Event</th>
            <th>Component</th>
          </tr>
        </thead>
        <tbody>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/search.row;
ROWNULL=tholosbuilder/search.rownull;
SQL=
select tpv.id, tpv.name_with_type, tpv.path, cpv.name as component_name, '<pre>'||cpv.value||'</pre>' as value,
       tpv.route_id, tpv.route
  from app_component_events_v cpv,
       app_tree_path_v tpv
 where lower(cpv.value) like lower('%$searchFor%')
       and tpv.id=cpv.component_id
 order by tpv.route, tpv.path, cpv.name
%SQL%>
        </tbody>
      </table>

      <div class="form-section-title">Event parameters</div>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:30%">Event</th>
            <th>Component</th>
          </tr>
        </thead>
        <tbody>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/search.row;
ROWNULL=tholosbuilder/search.rownull;
SQL=
select tpv.id, tpv.name_with_type, tpv.path, cpv.name as component_name, '<pre>'||cpv.parameters||'</pre>' as value,
       tpv.route_id, tpv.route
  from app_component_events_v cpv,
       app_tree_path_v tpv
 where lower(cpv.parameters) like lower('%$searchFor%')
       and tpv.id=cpv.component_id
 order by tpv.route, tpv.path, cpv.name
%SQL%>
        </tbody>
      </table>
    </div>
  </div>
</div>
```

Preserved: every `<%SQL%>` block byte-for-byte (DB, ROW, ROWNULL, SQL clauses); the `$searchfor` and `$searchFor` Eisodos tokens; the `#search_result` div id.

- [ ] **Step 2: Rewrite `search.row.template`**

Replace with:

```html
<tr>
  <td><a href="javascript:$('#app_tree').jstree('deselect_all');$('#app_tree').jstree('select_node', '#$sqlid').trigger('select_node.jstree');">$sqlname_with_type</a></td>
  <td>
    <a href="javascript:addRoute('$sqlroute_id');" class="text-muted small me-2">
      <i class="fa-regular fa-plus me-1"></i>$sqlroute
    </a>
    <small class="text-muted">$sqlpath</small>
  </td>
</tr>
```

Wait &mdash; this row spans only 2 columns; the rowcomponent spans 3. This is the row template used by Properties / Event values / Event parameters tables (which have 2 columns). Confirm this is correct from the parent SQL queries.

Looking at the parent: Properties table has `<th>Property</th><th>Component</th>` (2 cols). The component name + path go in the second cell. Adjusting: the Properties rendering in the original showed just `name_with_type` and `route`+`path`. Both fit in 2 columns.

Actually the original `search.row.template` has 3 columns:

```html
<tr>
  <td>$sqlname_with_type</td>
  <td>$sqlroute</td>
  <td>$sqlpath</td>
</tr>
```

Wait, re-checking the original: it has `<td>name_with_type</td>` then `<td>route</td>` then `<td>path</td>` &mdash; three cells. But the parent table headers are only 2 cells (Property, Component). That's a markup mismatch in the original.

To keep behavior identical, replicate the original 3-cell row. The mismatch is a pre-existing bug; out of scope:

```html
<tr>
  <td><a href="javascript:$('#app_tree').jstree('deselect_all');$('#app_tree').jstree('select_node', '#$sqlid').trigger('select_node.jstree');">$sqlname_with_type</a></td>
  <td>
    <a href="javascript:addRoute('$sqlroute_id');" class="text-muted small">
      <i class="fa-regular fa-plus me-1"></i>$sqlroute
    </a>
  </td>
  <td><small class="text-muted">$sqlpath</small></td>
</tr>
```

Preserved: the jstree select trigger, the `addRoute('$sqlroute_id')` call, the icon.

- [ ] **Step 3: Rewrite `search.rowcomponent.template`**

Replace with:

```html
<tr>
  <td><span class="fw-semibold text-danger">$sqlcomponent_name</span></td>
  <td>
    <a href="javascript:addRoute('$sqlroute_id');" class="me-2">
      <i class="fa-regular fa-plus me-1"></i>$sqlroute
    </a>
    <a href="javascript:$('#app_tree').jstree('deselect_all');$('#app_tree').jstree('select_node', '#$sqlid').trigger('select_node.jstree');">$sqlname_with_type</a>
    <br><small class="text-muted">$sqlpath</small>
  </td>
  <td></td>
</tr>
<%FUNC%
_function_name=TholosBuilder\TholosBuilderCallback::_eqs
param=sqlvalue
value=
true=
false=<tr><td colspan="3">$sqlvalue</td></tr>
%FUNC%>
```

Preserved: the `<%FUNC%>` block byte-for-byte; the `addRoute()` and jstree callbacks; the `colspan="3"` value-row.

- [ ] **Step 4: User verification**

Ask the user to trigger a search (search box in navbar). Confirm:
- Card titled "Search results" with the search term shown in code style
- Four section headings (Components, Properties, Event values, Event parameters), each with a BS5 table
- Component-name search results show in red+bold
- Clicking a result link selects the node in the tree
- "Add route" links work
- "No result found" rows render correctly when no matches

- [ ] **Step 5: Commit**

```bash
git add assets/templates/tholosbuilder/search.main.template assets/templates/tholosbuilder/search.row.template assets/templates/tholosbuilder/search.rowcomponent.template
git commit -m "Inner results: restyle Search results page"
```

---

## After all tasks

After Task 7 completes and is verified:

- Announce: "I'm using the finishing-a-development-branch skill to complete this work."
- **REQUIRED SUB-SKILL:** Use `superpowers:finishing-a-development-branch`
- Report no automated tests exist; proceed to the four-option prompt.
