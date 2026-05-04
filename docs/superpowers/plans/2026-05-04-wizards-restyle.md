# Wizards Restyle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply one shared Bootstrap 5 card-wrapped layout to every `wizards.*.main.template` so wizards match the rest of the redesigned application. Drop legacy `<button class="button">`, BS3 `text-right`, and inline `style="margin-top: …"` chrome. Preserve every existing JavaScript callback and form `id`.

**Architecture:** Each wizard becomes `<div class="wiz-card card">` with a small `card-header h5` (icon + title), horizontal form rows (`col-md-3` label / `col-md-9` control) inside `card-body`, and a primary action button in `card-footer.text-end`. The `#wizard_result` area stays outside the card under a `.wiz-result` wrapper. Help wizard wraps CKEditor in `card-body p-0` with Save/Delete in a flex footer.

**Tech Stack:** Bootstrap 5.3.8 (vanilla, vendored), FontAwesome 6 (regular bundle), jQuery 3, Eisodos template engine (`<%SQL%>`, `<%FUNC%>`, `[%_function_name=…%]`).

**Verification:** No automated test suite. After each task, the user verifies in the browser that the wizard renders and its action button still triggers the existing JS callback without console errors.

---

## File Structure

| File | Action |
|---|---|
| `assets/css/TholosBuilder.css` | Append `=== Wizards ===` block (Task 1) |
| `assets/templates/tholosbuilder/wizards.query.main.template` | Rewrite (Task 2) |
| `assets/templates/tholosbuilder/wizards.storedprocedure.main.template` | Rewrite (Task 3) |
| `assets/templates/tholosbuilder/wizards.grid.main.template` | Rewrite (Task 3) |
| `assets/templates/tholosbuilder/wizards.editform.main.template` | Rewrite (Task 4) |
| `assets/templates/tholosbuilder/wizards.editform.controls.main.template` | Rewrite (Task 4) |
| `assets/templates/tholosbuilder/wizards.editform.controls.row.template` | Light touch &mdash; drop inline style + add `form-select` (Task 4) |
| `assets/templates/tholosbuilder/wizards.commit.main.template` | Rewrite (Task 5) |
| `assets/templates/tholosbuilder/wizards.userprofile.main.template` | Rewrite (Task 6) |
| `assets/templates/tholosbuilder/wizards.userprofile.row.template` | Light touch &mdash; convert `<p><label>` to BS5 row (Task 6) |
| `assets/templates/tholosbuilder/wizards.help.main.template` | Rewrite (Task 7) |

Each task is one commit. Tasks are ordered so that the shared CSS lands first (Task 1), then small wizards prove the pattern (Tasks 2-3), then the trickier multi-section / sub-form / CKEditor wizards (Tasks 4-7).

---

## Task 1: Add shared wizard CSS

**Files:**
- Modify: `assets/css/TholosBuilder.css` (append at end of file)

- [ ] **Step 1: Locate the end of the file**

Run: `wc -l assets/css/TholosBuilder.css`
Note the last line number; the new block goes at the very end.

- [ ] **Step 2: Append the wizard CSS block**

Append exactly this content to the end of `assets/css/TholosBuilder.css`:

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
.wiz-card .btn-primary {
  --bs-btn-bg: #236499;
  --bs-btn-border-color: #236499;
  --bs-btn-hover-bg: #1d527d;
  --bs-btn-hover-border-color: #1d527d;
  --bs-btn-active-bg: #1d527d;
  --bs-btn-active-border-color: #1d527d;
  font-size: 13px;
}
.wiz-result {
  max-width: 900px;
  margin: 1rem auto 0 auto;
}
```

- [ ] **Step 3: Commit**

```bash
git add assets/css/TholosBuilder.css
git commit -m "Wizards: add shared .wiz-card CSS block"
```

No visual change yet (no template uses `.wiz-card` until Task 2).

---

## Task 2: Restyle Query wizard

**Files:**
- Modify: `assets/templates/tholosbuilder/wizards.query.main.template` (rewrite)

- [ ] **Step 1: Read the current template**

Run: `cat assets/templates/tholosbuilder/wizards.query.main.template`
Note the existing `<%SQL%>` block (lines 10-19) &mdash; it must be preserved byte-for-byte.

- [ ] **Step 2: Rewrite the template**

Replace the entire file content with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-database"></i> Query wizard</h5>
  </div>
  <div class="card-body">
    <div class="row mb-3">
      <label class="col-md-3 col-form-label text-md-end" for="QueryWizardComponentId">Query:</label>
      <div class="col-md-9">
        <select class="form-select" id="QueryWizardComponentId">
          <option value="">--- Please select TQuery component ---</option>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/sqls.select;
SQL=
select id, path as text, case when id=$p_id~='-1'; then 'selected' else '' end as selected
from app_tree_path_v atp
where atp.class_name='TQuery'
      and atp.route_id in ($route_filter~='-1';)
order by path
%SQL%>
        </select>
      </div>
    </div>
    <div class="row mb-3">
      <label class="col-md-3 col-form-label text-md-end" for="QueryWizardTransRoot">Translate root:</label>
      <div class="col-md-9">
        <input class="form-control" id="QueryWizardTransRoot" type="text">
        <div class="help-text">component route is the default</div>
      </div>
    </div>
    <div class="row mb-3">
      <div class="col-md-9 offset-md-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="QueryWizardSkipLabel">
          <label class="form-check-label" for="QueryWizardSkipLabel">Skip Label overwrite</label>
        </div>
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <button type="button" class="btn btn-primary" onclick="QueryWizardRun($('#QueryWizardComponentId').val(),$('#QueryWizardTransRoot').val(),($('#QueryWizardSkipLabel').is(':checked')?'Y':'N'),'');">
      <i class="fa-regular fa-bolt me-1"></i>Generate / Update TDBFields
    </button>
  </div>
</div>

<div id="wizard_result" class="wiz-result"></div>
```

Preserved exactly: every `id` attribute (`QueryWizardComponentId`, `QueryWizardTransRoot`, `QueryWizardSkipLabel`, `wizard_result`), the `onclick` handler arguments, the `<%SQL%>...%SQL%>` block, the `$p_id~='-1';` and `$route_filter~='-1';` Eisodos directives.

- [ ] **Step 3: User verification**

Pause and ask the user to open the Query wizard in the browser and confirm:
- Card renders with "Query wizard" header
- Component dropdown lists components
- "Generate / Update TDBFields" button still triggers `QueryWizardRun`
- Result area still appears below the card after a run

- [ ] **Step 4: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.query.main.template
git commit -m "Wizards: restyle Query wizard to BS5 card layout"
```

---

## Task 3: Restyle Stored Procedure + Grid wizards

**Files:**
- Modify: `assets/templates/tholosbuilder/wizards.storedprocedure.main.template` (rewrite)
- Modify: `assets/templates/tholosbuilder/wizards.grid.main.template` (rewrite)

- [ ] **Step 1: Rewrite Stored Procedure wizard**

Replace the entire content of `wizards.storedprocedure.main.template` with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-gears"></i> Stored Procedure wizard</h5>
  </div>
  <div class="card-body">
    <div class="row mb-3">
      <label class="col-md-3 col-form-label text-md-end" for="SPWizardComponentId">Stored procedure:</label>
      <div class="col-md-9">
        <select class="form-select" id="SPWizardComponentId">
          <option value="">--- Please select TStoredProcedure component ---</option>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/sqls.select;
SQL=
select id, path as text, case when id=$p_id~='-1'; then 'selected' else '' end as selected
from app_tree_path_v atp
where atp.class_name='TStoredProcedure'
      and atp.route_id in ($route_filter~='-1';)
order by path
%SQL%>
        </select>
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <button type="button" class="btn btn-primary" onclick="StoredProcedureWizardRun($('#SPWizardComponentId').val(),'');">
      <i class="fa-regular fa-bolt me-1"></i>Generate / Update TDBParams
    </button>
  </div>
</div>

<div id="wizard_result" class="wiz-result"></div>
```

- [ ] **Step 2: Rewrite Grid wizard**

Replace the entire content of `wizards.grid.main.template` with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-table"></i> Grid wizard</h5>
  </div>
  <div class="card-body">
    <div class="row mb-3">
      <label class="col-md-3 col-form-label text-md-end" for="GridWizardComponentId">Grid:</label>
      <div class="col-md-9">
        <select class="form-select" id="GridWizardComponentId">
          <option value="">--- Please select TGrid component ---</option>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/sqls.select;
SQL=
select id, path as text, case when $p_grid_id~='-1';=id then 'selected' else '' end as selected
from app_tree_path_v atp
where atp.class_name='TGrid'
order by component_order
%SQL%>
        </select>
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <button type="button" class="btn btn-primary" onclick="showGridWizard($('#GridWizardComponentId').val(),'');">
      <i class="fa-regular fa-bolt me-1"></i>Generate / Update TGridColumns
    </button>
  </div>
</div>

[%_function_name=TholosBuilder\TholosBuilderCallback::_eq;param=p_grid_id;value=;true=;false=tholosbuilder/wizards.grid.columns.main%]

<div id="wizard_result" class="wiz-result"></div>
```

The `[%_function_name=...%]` callback include is preserved verbatim. The grid columns sub-template renders between the card and the result area &mdash; its current behavior is unchanged.

- [ ] **Step 3: User verification**

Ask the user to open both wizards from the menu. Confirm headers, dropdowns populate, action buttons trigger `StoredProcedureWizardRun` and `showGridWizard` respectively, no JS console errors.

- [ ] **Step 4: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.storedprocedure.main.template assets/templates/tholosbuilder/wizards.grid.main.template
git commit -m "Wizards: restyle Stored Procedure + Grid wizards"
```

---

## Task 4: Restyle Edit Form wizard + controls sub-form

**Files:**
- Modify: `assets/templates/tholosbuilder/wizards.editform.main.template` (rewrite)
- Modify: `assets/templates/tholosbuilder/wizards.editform.controls.main.template` (rewrite)
- Modify: `assets/templates/tholosbuilder/wizards.editform.controls.row.template` (light touch)

- [ ] **Step 1: Rewrite the main Edit Form wizard**

Replace the entire content of `wizards.editform.main.template` with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-rectangle-list"></i> Edit Form wizard</h5>
  </div>
  <div class="card-body">
    <div class="row mb-3">
      <label class="col-md-3 col-form-label text-md-end" for="EditFormWizardComponentId">Form:</label>
      <div class="col-md-9">
        <select class="form-select" id="EditFormWizardComponentId">
          <option value="">--- Please select TForm component ---</option>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/sqls.select;
SQL=
select id, path as text, case when $p_form_id~='-1';=id then 'selected' else '' end as selected
from app_tree_path_v atp
where atp.class_name='TForm'
      and atp.route_id in ($route_filter~='-1';)
order by path
%SQL%>
        </select>
      </div>
    </div>
    <div class="row mb-3">
      <label class="col-md-3 col-form-label text-md-end" for="QueryComponentId">Query:</label>
      <div class="col-md-9">
        <select class="form-select" id="QueryComponentId">
          <option value="">--- Please select TQuery component ---</option>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/sqls.select;
SQL=
select id, path as text, case when $p_query_id~='-1';=id then 'selected' else '' end as selected
from app_tree_path_v atp
where atp.class_name='TQuery'
      and atp.route_id in ($route_filter~='-1';)
order by path
%SQL%>
        </select>
      </div>
    </div>
    <div class="row mb-3">
      <label class="col-md-3 col-form-label text-md-end" for="Blacklist">Excluded columns:</label>
      <div class="col-md-9">
        <input class="form-control" id="Blacklist" type="text" value="$editformwizardblacklist">
      </div>
    </div>

    [%_function_name=TholosBuilder\TholosBuilderCallback::_eq;param=p_form_id;value=;true=;false=tholosbuilder/wizards.editform.controls.main%]
  </div>
  <div class="card-footer text-end">
    <button type="button" class="btn btn-primary" onclick="showEditFormWizard($('#EditFormWizardComponentId').val(),$('#QueryComponentId').val(),$('#Blacklist').val());">
      <i class="fa-regular fa-bolt me-1"></i>Generate / Update inputs
    </button>
  </div>
</div>

<div id="wizard_result" class="wiz-result"></div>
```

The conditional include of `wizards.editform.controls.main` now sits inside `card-body`, below the three form rows. Preserved exactly: `EditFormWizardComponentId`, `QueryComponentId`, `Blacklist` ids; `showEditFormWizard()` arguments; `$editformwizardblacklist` Eisodos token; both `<%SQL%>` blocks; the `[%_function_name=...%]` directive.

- [ ] **Step 2: Rewrite the controls sub-form template**

Replace the entire content of `wizards.editform.controls.main.template` with:

```html
<div class="form-section-title">Suggested controls</div>
<form id="editformForm">
  <input type="hidden" name="p_form_id" value="$p_form_id">
  <input type="hidden" name="p_query_id" value="$p_query_id">
  <input type="hidden" name="action" value="EditFormWizardRun">
  <div class="ef-controls">
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/wizards.editform.controls.row;
SQL=
SELECT atp.id, atp.name||':'||acp_datatype.value||case when acp_size.value is null then '' else '('||acp_size.value||')' end as name,
       CASE
         WHEN atp.name = 'ID' or atp.name = 'VERSION' THEN
          'THidden'
         WHEN acp_datatype.value = 'integer' THEN
          'TEdit'
         WHEN acp_datatype.value = 'bool' THEN
          'TCheckbox'
         WHEN acp_datatype.value = 'string' and acp_size.value='30' THEN
          'TLOV'
         WHEN acp_datatype.value = 'string' THEN
          'TEdit'
         WHEN acp_datatype.value = 'text' THEN
          'TText'
         WHEN acp_datatype.value = 'date' THEN
          'TDateTimePicker'
         WHEN acp_datatype.value = 'datetime' THEN
          'TDateTimePicker'
         WHEN acp_datatype.value = 'float' THEN
          'TEdit'
         ELSE
          NULL
       END AS preferred_component
  FROM app_tree_path_v atp
  LEFT OUTER JOIN app_component_properties_v acp_datatype ON acp_datatype.component_id = atp.id
                                                         AND acp_datatype.l_name = 'datatype'
  LEFT OUTER JOIN app_component_properties_v acp_size ON acp_size.component_id = atp.id
                                                     AND acp_size.l_name = 'size'
 WHERE parent_id = $p_query_id~='-1';
   AND atp.class_name = 'TDBField'
   AND atp.id NOT IN
       (SELECT value_component_id
          FROM app_component_properties_v acp
         WHERE acp.component_id IN (SELECT atp.id
                                      FROM app_tree_path_v atp
                                     WHERE parent_id = $p_form_id)
           AND acp.name = 'DBField'
           AND VALUE IS NOT NULL)
   AND NOT exists(SELECT 1 FROM table(SPLIT('$p_editformwizardblacklist')) WHERE atp.name LIKE COLUMN_VALUE)
 ORDER BY atp.component_order
%SQL%>
  </div>
</form>
<div class="text-end mt-3" id="editformFormPost">
  <button type="button" class="btn btn-primary" onclick="EditFormWizardRun();">
    <i class="fa-regular fa-circle-plus me-1"></i>Create form controls
  </button>
</div>
```

Preserved exactly: `<form id="editformForm">`, all three hidden inputs, the entire `<%SQL%>` block (DB, ROW, SQL clauses), the `editformFormPost` id, `EditFormWizardRun()` callback. The `<form>` element keeps wrapping the rows so existing JS form-serialization works.

The new `.ef-controls` wrapper is just a container for the rows &mdash; styled inline by Task 4 Step 4 below.

- [ ] **Step 3: Light-touch the controls row template**

Replace the entire content of `wizards.editform.controls.row.template` with:

```html
<div class="row mb-2 align-items-center ef-controls-row">
  <div class="col-md-5"><b>$sqlname</b></div>
  <div class="col-md-7">
    <select class="form-select form-select-sm" name="option$sqlid">
<option value="">Skip</option>
<option value="TEdit"
[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=sqlpreferred_component;value=TEdit;true=selected;false=%]>Edit</option>
<option value="TDateTimePicker"
[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=sqlpreferred_component;value=TDateTimePicker;true=selected;false=%]>Date/time picker</option>
<option value="TCheckbox"
[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=sqlpreferred_component;value=TCheckbox;true=selected;false=%]>Checkbox</option>
<option value="TLOV"
[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=sqlpreferred_component;value=TLOV;true=selected;false=%]>LOV</option>
<option value="TText"
[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=sqlpreferred_component;value=TText;true=selected;false=%]>Text</option>
<option value="THTMLEdit"
[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=sqlpreferred_component;value=THTMLEdit;true=selected;false=%]>HTML Editor</option>
<option value="TLabel"
[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=sqlpreferred_component;value=TLabel;true=selected;false=%]>Label</option>
<option value="TRadio"
[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=sqlpreferred_component;value=TRadio;true=selected;false=%]>Radio group</option>
<option value="TStatic"
[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=sqlpreferred_component;value=TStatic;true=selected;false=%]>Static</option>
<option value="THidden"
[%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=sqlpreferred_component;value=THidden;true=selected;false=%]>Hidden field</option>
    </select>
  </div>
</div>
```

Changed: dropped `style="margin-top: 5px;"`, swapped `text-right` for the BS5 grid (label moves to `col-md-5` left, select to `col-md-7` right), added `form-select form-select-sm`. Preserved exactly: every `[%_function_name=...%]` Eisodos directive, the `name="option$sqlid"` attribute, all option values.

- [ ] **Step 4: User verification**

Ask the user to:
- Open the Edit Form wizard, pick a Form + Query
- Confirm the "Suggested controls" section appears below the three form rows inside the same card
- Confirm each row shows the column name + a select with the preferred component preselected
- Click "Create form controls" and confirm `EditFormWizardRun` runs without JS errors

- [ ] **Step 5: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.editform.main.template assets/templates/tholosbuilder/wizards.editform.controls.main.template assets/templates/tholosbuilder/wizards.editform.controls.row.template
git commit -m "Wizards: restyle Edit Form wizard + controls sub-form"
```

---

## Task 5: Restyle Commit wizard

**Files:**
- Modify: `assets/templates/tholosbuilder/wizards.commit.main.template` (rewrite)

- [ ] **Step 1: Rewrite the Commit wizard**

Replace the entire content of `wizards.commit.main.template` with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-code-commit"></i> Committing changes</h5>
  </div>
  <div class="card-body">
    <form id="issues">
      <input type="hidden" name="action" value="saveCommitChanges">
      <input type="hidden" name="p_routes" value="$routes">
      <input type="hidden" name="p_rm_current_status" value="$rmstatus">
      <input type="hidden" name="p_rm_current_assigned_to" value="$rmassigned_to">

      <p class="text-muted mb-2"><small>Committing routes: <code>$routes</code></small></p>

      <div class="mb-3">
        <label class="form-label fw-semibold">Commit message</label>
        <textarea class="form-control" name="p_message" rows="4">$tasks
        </textarea>
      </div>

      <div class="form-section-title">Redmine</div>
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Issue new status</label>
          <select class="form-select" name="p_rm_status">
            $rmstatuses
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Assign to</label>
          <select class="form-select" name="p_rm_assigned_to">
            $rmmembers
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Note</label>
        <textarea class="form-control" name="p_rm_note" rows="2"></textarea>
      </div>

      <div class="form-section-title">Time tracking</div>
      <div class="row">
        <div class="col-md-6">
          <label class="form-label">Time spent (hours)</label>
          <input class="form-control" name="p_rm_time_spent" type="text" value="">
        </div>
        <div class="col-md-6">
          <label class="form-label">Activity</label>
          <select class="form-select" name="p_rm_time_activity">
            $rmactivities
          </select>
        </div>
      </div>
    </form>
  </div>
  <div class="card-footer text-end">
    <button type="button" class="btn btn-primary" onclick="saveCommitChanges();">
      <i class="fa-regular fa-cloud-arrow-up me-1"></i>Commit changes
    </button>
  </div>
</div>
```

Preserved exactly: `<form id="issues">`, every hidden input (`action`, `p_routes`, `p_rm_current_status`, `p_rm_current_assigned_to`), every `name=` attribute on visible inputs (`p_message`, `p_rm_status`, `p_rm_assigned_to`, `p_rm_note`, `p_rm_time_spent`, `p_rm_time_activity`), every Eisodos token (`$routes`, `$tasks`, `$rmstatus`, `$rmassigned_to`, `$rmstatuses`, `$rmmembers`, `$rmactivities`), the `saveCommitChanges()` callback.

The two `<table>` blocks (Redmine + Time tracking) become BS5 form-grid rows. The `<h3>` headings become `.form-section-title` mini-headings.

- [ ] **Step 2: User verification**

Ask the user to open Commit wizard. Confirm:
- Card renders with "Committing changes" header
- Routes line shows correct routes
- Commit message textarea pre-populated with `$tasks`
- Redmine status/assignee dropdowns populated
- Time tracking section shows time + activity
- "Commit changes" button still triggers `saveCommitChanges()` and posts the form correctly

- [ ] **Step 3: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.commit.main.template
git commit -m "Wizards: restyle Commit wizard with form-grid sub-sections"
```

---

## Task 6: Restyle User Profile wizard

**Files:**
- Modify: `assets/templates/tholosbuilder/wizards.userprofile.main.template` (rewrite)
- Modify: `assets/templates/tholosbuilder/wizards.userprofile.row.template` (rewrite)

- [ ] **Step 1: Rewrite the main User Profile template**

Replace the entire content of `wizards.userprofile.main.template` with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-user-gear"></i> User profile</h5>
  </div>
  <div class="card-body">
    <form id="userprofile" onsubmit="javascript:void();">
      <input type="hidden" name="action" value="saveUserProfile">
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/wizards.userprofile.row;
SQL=
select au.svn_username,
       au.svn_password,
       au.rm_secretkey,
       au.rm_project_id,
       au.rm_subprojects
  from app_users au
 where au.id=app_session_pkg.user_id
%SQL%>
    </form>
  </div>
  <div class="card-footer text-end">
    <button type="button" class="btn btn-primary" onclick="saveUserProfile();">
      <i class="fa-regular fa-floppy-disk me-1"></i>Save
    </button>
  </div>
</div>
```

Preserved exactly: `<form id="userprofile">` with `onsubmit="javascript:void();"`, the hidden `action` input, the entire `<%SQL%>` block, the `saveUserProfile()` callback.

- [ ] **Step 2: Rewrite the User Profile row template**

Replace the entire content of `wizards.userprofile.row.template` with:

```html
<div class="row mb-3">
  <label class="col-md-3 col-form-label text-md-end">SVN username:</label>
  <div class="col-md-9"><input class="form-control" type="text" name="p_svn_username" value="$sqlsvn_username"></div>
</div>
<div class="row mb-3">
  <label class="col-md-3 col-form-label text-md-end">SVN password:</label>
  <div class="col-md-9"><input class="form-control" type="text" name="p_svn_password" value="$sqlsvn_password"></div>
</div>
<div class="row mb-3">
  <label class="col-md-3 col-form-label text-md-end">Redmine secret key:</label>
  <div class="col-md-9"><input class="form-control" type="text" name="p_rm_secretkey" value="$sqlrm_secretkey"></div>
</div>
<div class="row mb-3">
  <label class="col-md-3 col-form-label text-md-end">Redmine project ID:</label>
  <div class="col-md-9"><input class="form-control" type="text" name="p_rm_project_id" value="$sqlrm_project_id"></div>
</div>
<div class="row mb-3">
  <label class="col-md-3 col-form-label text-md-end">Redmine subprojects:</label>
  <div class="col-md-9"><input class="form-control" type="text" name="p_rm_subprojects" value="$sqlrm_subprojects"></div>
</div>
```

Preserved exactly: every `name=` attribute (`p_svn_username`, `p_svn_password`, `p_rm_secretkey`, `p_rm_project_id`, `p_rm_subprojects`) and every `$sql…` Eisodos token. Field labels are slightly normalized in casing ("SVN Password" → "SVN password", "Redmine secretkey" → "Redmine secret key", "Redmine Subprojects" → "Redmine subprojects") for consistency &mdash; cosmetic only, no JS impact.

- [ ] **Step 3: User verification**

Ask the user to open the User Profile wizard. Confirm:
- Card renders with "User profile" header
- All five field rows render with current values
- "Save" button still triggers `saveUserProfile()`

- [ ] **Step 4: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.userprofile.main.template assets/templates/tholosbuilder/wizards.userprofile.row.template
git commit -m "Wizards: restyle User Profile wizard"
```

---

## Task 7: Restyle Help editor wizard

**Files:**
- Modify: `assets/templates/tholosbuilder/wizards.help.main.template` (rewrite)

- [ ] **Step 1: Rewrite the Help template**

Replace the entire content of `wizards.help.main.template` with:

```html
<div class="wiz-card card">
  <div class="card-header">
    <h5><i class="fa-regular fa-circle-question"></i> User help editor</h5>
  </div>
  <div class="card-body p-3">
    <form id="helpForm" action="javascript:saveHelp();">
      <input type="hidden" name="action" value="saveHelp">
      <input type="hidden" name="p_component_id" id="p_component_id" value="$p_component_id">
      <input type="hidden" name="p_id" id="p_id" value="$id">
      <input type="hidden" name="p_version" id="p_version" value="$version">
      <textarea name="p_text" id="helpHTML" style="width: 100%; height: 600px;">$text</textarea>
    </form>
  </div>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <a href="javascript:deleteHelp($('#helpForm').find('#p_id').val(),$('#helpForm').find('#p_version').val(),$('#helpForm').find('#p_component_id').val());" class="btn btn-link text-danger p-0">
      <i class="fa-regular fa-trash me-1"></i>Delete
    </a>
    <button type="submit" form="helpForm" class="btn btn-primary">
      <i class="fa-regular fa-floppy-disk me-1"></i>Save
    </button>
  </div>
</div>

<script type="text/javascript">
  CKEDITOR.replace('helpHTML', {
    language: 'hu',
    entities_latin: false,
    disableNativeTableHandles: false,
    enterMode: CKEDITOR.ENTER_P,
    forcePasteAsPlainText: true,
    pasteFromWordPromptCleanup: true,
    shiftEnterMode: CKEDITOR.ENTER_BR,
    undoStackSize: 50,
    stylesSet: '/tholos/assets/js/ckeditor-assets/help_styles.js'
  });
</script>
```

Preserved exactly: `<form id="helpForm" action="javascript:saveHelp();">`, every hidden input with its `id` and `name`, the `<textarea name="p_text" id="helpHTML">` (CKEDITOR.replace targets it by id), the `$p_component_id`, `$id`, `$version`, `$text` Eisodos tokens, the entire `<script>` block byte-for-byte.

The Save button is `type="submit" form="helpForm"` so submitting it triggers the form's `action="javascript:saveHelp();"`. The Delete link keeps its existing `deleteHelp(...)` call.

- [ ] **Step 2: User verification**

Ask the user to open Help editor for a component (or via the prop editor's "Create one" link from the empty Help tab). Confirm:
- Card renders with "User help editor" header
- CKEditor toolbar + body load inside the card
- Save button submits the form (triggers `saveHelp()`)
- Delete link still calls `deleteHelp()` with the right parameters

- [ ] **Step 3: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.help.main.template
git commit -m "Wizards: restyle Help editor wizard"
```

---

## After all tasks

After Task 7 completes and is verified:

- Announce: "I'm using the finishing-a-development-branch skill to complete this work."
- **REQUIRED SUB-SKILL:** Use `superpowers:finishing-a-development-branch`
- Note: this project has no automated test suite &mdash; report that explicitly when the skill asks for test results, then proceed to the four-option prompt.
