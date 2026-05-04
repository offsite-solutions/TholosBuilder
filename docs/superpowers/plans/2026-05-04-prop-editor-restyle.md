# Property Editor Restyle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate the property-editor tab strip from jQuery UI tabs to Bootstrap 5 nav-tabs, restyle the Properties/Events tab tables and the Methods/References list rows with the offsite palette already established by the navbar/notch/treeview work, and modernize row-action buttons inside the in-scope row templates.

**Architecture:** All visual changes ride on three new CSS class families — `.pf-tabs`, `.pf-table`, `.pf-list-row` (+ `.pf-list-sub`), `.pf-help-empty`, `.pf-row-actions` — added to `assets/css/TholosBuilder.css` and applied via small markup tweaks in the relevant `propframe.*.template` files. The tab widget swap is a coupled template + JS change; everything else is independent and ships as its own commit.

**Tech Stack:** Vanilla Bootstrap 5.3.8 (already installed at `vendor/bower-asset/bootstrap`), FontAwesome 6 in SVG mode, jQuery + Eisodos template engine. No PHP test framework — verification is manual in a browser against a live builder instance.

**Spec:** `docs/superpowers/specs/2026-05-04-prop-editor-design.md`

**No test framework note:** This codebase has no PHPUnit / no `tests/` directory and no JS test harness. The plan therefore omits automated test steps; each task ends with a manual visual/functional check in the browser, then a commit.

---

## File Structure

| File | Change | Responsibility |
|---|---|---|
| `assets/css/TholosBuilder.css` | Modify (add + delete) | Add new `.pf-tabs / .pf-table / .pf-list-row / .pf-list-sub / .pf-help-empty / .pf-row-actions` rule blocks. Delete the dead `#prop_frame .ui-tabs-*` overrides and the long-commented `.nav-tabs > li.active` block. |
| `assets/templates/tholosbuilder/propframe.container.template` | Modify (replace) | Replace the jQuery-UI-style `<ul id="prop_tabs">…</ul>` + `<div class="tab-content">…</div>` with Bootstrap-5 `nav-tabs` markup (`<button data-bs-toggle="tab">` triggers + `role="tablist"` etc.). |
| `assets/templates/tholosbuilder/propframe.tab.0.template` | Modify | Add `pf-table` class to the `<table>` element; switch `table-condensed` → `table-sm`. |
| `assets/templates/tholosbuilder/propframe.tab.1.template` | Modify | Same as tab.0. |
| `assets/templates/tholosbuilder/propframe.property.row.template` | Modify | Wrap row-action buttons in `<span class="pf-row-actions">`; on each `<button>` drop `btn-default btn-sm btn-xs pull-right`, keeping just `btn`; rename `fa-regular fa-edit` → `fa-regular fa-pen-to-square`. |
| `assets/templates/tholosbuilder/propframe.event.row.template` | Modify | Same row-action button modernization. |
| `assets/templates/tholosbuilder/propframe.method.row.template` | Modify | Replace the inline-styled `<div class="row" style="…">` outer wrapper with `<div class="pf-list-row">` + `<span class="pf-list-main">` / `<span class="pf-list-actions">`. Modernize the Copy button. The embedded `<%SQL%…%SQL%>` block (which iterates referers) stays untouched. |
| `assets/templates/tholosbuilder/propframe.method.referer.template` | Create (new content; file currently empty per `wc -l` = 0) | Render each referer link inside a `<div class="pf-list-sub">…</div>` wrapper. |
| `assets/templates/tholosbuilder/propframe.referers.row.template` | Create (new content; file currently empty per `wc -l` = 0) | Render each reference inside a `<div class="pf-list-row"><span class="pf-list-main">…</span></div>` wrapper. |
| `assets/templates/tholosbuilder/propframe.help.empty.template` | Modify (replace) | Wrap the two existing action links in the new `<div class="pf-help-empty">` empty-state structure (icon + label + actions). |
| `assets/js/TholosBuilder.js` | Modify (lines ~860–875 inside `showPropertiesAndEvents()`) | Replace the two `$('#prop_frame .content').tabs(…)` calls with a single `$('#prop_tabs button[data-bs-toggle="tab"]').on('shown.bs.tab', …)` binding. |

**Out-of-scope-but-noted:**

- `assets/templates/tholosbuilder/propframe.main.template` — confirmed dead via `grep -rn 'propframe\.main' src assets` (zero references). Not updated by this plan; flagged for a separate cleanup commit at some later point.
- `propframe.method.referer.template` and `propframe.referers.row.template` were both 0-byte stubs in the working tree at design time but produce visible rendered HTML in production (per the user's pasted DOM). Treating them as Create rather than Modify because their current on-disk content is empty.

Tasks are ordered so each commit leaves the system in a consistent state:

- Task 1 ships every new CSS rule additively. No template uses the new classes yet → no visual change → safe to land alone.
- Task 2 atomically swaps the tab widget (template markup + JS init + dropping the now-dead `.ui-tabs-*` CSS overrides). After this commit the tab strip uses Bootstrap-5 styling and works.
- Tasks 3–6 each apply one new visual concern (tables, row buttons, list-rows, empty Help) by adding the matching `pf-*` class and minor markup wrap. Each is independently committable.

---

### Task 1: Add the new CSS rules

**Files:**
- Modify: `assets/css/TholosBuilder.css` (append a new block at end of file; later sub-step deletes the dead `#prop_frame .ui-tabs-*` rules)

- [ ] **Step 1: Append the new prop-editor CSS block at end of `assets/css/TholosBuilder.css`**

After the existing `/* === Treeview restyle ===` block and before any later content, append:

```css

/* === Prop editor restyle ================================== */

/* Tab strip */
.pf-tabs.nav-tabs {
  font-size: 12px;
  --bs-nav-tabs-link-active-color: #236499;
  --bs-nav-tabs-link-active-border-color: transparent transparent #236499;
  --bs-nav-tabs-border-color: #dee2e6;
  padding: 0 6px;
}
.pf-tabs .nav-link {
  padding: .35rem .7rem;
  color: #495057;
  border: 1px solid transparent;
  border-bottom: 2px solid transparent;
  border-radius: 0;
  background: transparent;
}
.pf-tabs .nav-link.active {
  font-weight: 600;
  border-bottom: 2px solid #236499;
  background: transparent;
  color: #236499;
}
.pf-tabs .nav-link:hover {
  background: #f1f3f5;
  color: #212529;
  border-color: transparent;
  border-bottom-color: #dee2e6;
}
.pf-tabs .nav-link.active:hover { border-bottom-color: #236499; }

/* Property / Events tables (applied alongside the legacy .table_props hook) */
table.table_props.pf-table { font-size: 12px; margin: 0; table-layout: fixed; border-collapse: collapse; }
table.table_props.pf-table thead th {
  background: #e9ecef; color: #495057; font-weight: 600;
  border-bottom: 1px solid #dee2e6; padding: .35rem .55rem;
}
table.table_props.pf-table tbody td {
  padding: .25rem .55rem; vertical-align: top;
  border-bottom: 1px solid #f1f3f5;
  overflow: hidden; word-break: break-word;
}
table.table_props.pf-table tbody tr:nth-child(odd) td  { background: #fff; }
table.table_props.pf-table tbody tr:nth-child(even) td { background: #f8f9fa; }
table.table_props.pf-table tbody tr:hover td           { background: rgba(136,189,33,0.15); }
table.table_props.pf-table tbody tr.is-selected td     { background: rgba(136,189,33,0.22); box-shadow: inset 2px 0 0 #88bd21; }
table.table_props.pf-table tbody tr.prop_mandatory td  { font-weight: 600; }
table.table_props.pf-table .prop_name  {
  color: #212529; border-right: 1px solid #f1f3f5; width: 30%;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
table.table_props.pf-table .prop_value { color: #212529; }
table.table_props.pf-table .prop_type  { color: #adb5bd; font-size: 11px; }
table.table_props.pf-table .method_path { color: #6c757d; font-style: italic; font-size: 11px; }

/* Events tab GUI / PHP group header rows */
table.table_props.pf-table tbody td.event_type {
  background: #e9ecef !important;
  color: #495057; font-weight: 600;
  font-size: 11px; letter-spacing: .04em; text-transform: uppercase;
  padding: .25rem .55rem; border-bottom: 1px solid #dee2e6;
}

/* Inline SQL preview inside a property value cell */
table.table_props.pf-table pre {
  background: #f8f9fa; border: 1px solid #e9ecef; border-radius: .25rem;
  padding: .35rem .5rem; font-size: 11px; color: #495057;
  margin: .25rem 0 0 0; max-height: 100px; overflow: auto;
}

/* Methods / References list rows */
.pf-list-row {
  display: flex; align-items: flex-start;
  padding: .25rem .55rem;
  border-bottom: 1px solid #f1f3f5;
  font-size: 12px;
}
.pf-list-row:nth-child(odd)  { background: #fff; }
.pf-list-row:nth-child(even) { background: #f8f9fa; }
.pf-list-row:hover           { background: rgba(136,189,33,0.15); }
.pf-list-row .pf-list-main   { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pf-list-row .pf-list-actions{ flex-shrink: 0; margin-left: .5rem; display: inline-flex; gap: .15rem; }
.pf-list-sub {
  padding: .15rem .55rem .35rem 1.5rem;
  font-size: 11px; color: #6c757d;
  border-bottom: 1px solid #f1f3f5;
  background: inherit;
}
.pf-list-sub a { color: #236499; text-decoration: none; }
.pf-list-sub a:hover { text-decoration: underline; }
.pf-list-sub .ref-kind { color: #495057; font-weight: 600; }

/* Empty Help tab */
.pf-help-empty {
  text-align: center; padding: 2rem 1rem; color: #6c757d; font-size: 12px;
}
.pf-help-empty > .fa-circle-info { font-size: 24px; color: #adb5bd; display: block; margin-bottom: .5rem; }
.pf-help-empty a { display: inline-block; margin: .25rem .5rem; color: #236499; text-decoration: none; }
.pf-help-empty a:hover { text-decoration: underline; }

/* Row-action button group used inside table cells and list rows */
.pf-row-actions {
  float: right;
  display: inline-flex;
  gap: .15rem;
  margin-left: .35rem;
}
.pf-row-actions .btn {
  --bs-btn-padding-x: .4rem;
  --bs-btn-padding-y: .1rem;
  --bs-btn-font-size: 11px;
  --bs-btn-line-height: 1;
  color: #495057;
  background: #fff;
  border: 1px solid #dee2e6;
}
.pf-row-actions .btn:hover {
  background: #e9ecef;
  border-color: #ced4da;
  color: #212529;
}
```

- [ ] **Step 2: Verify visually in the browser**

Hard-reload the running builder app. Expected: nothing visible has changed. The new CSS is additive — no element in the DOM yet uses the `pf-*` classes, so the page renders identically.

- [ ] **Step 3: Commit**

```bash
git add assets/css/TholosBuilder.css
git commit -m "Add prop-editor restyle CSS — pf-tabs, pf-table, pf-list-row, pf-help-empty, pf-row-actions"
```

---

### Task 2: Migrate the tab strip to Bootstrap 5 nav-tabs

**Files:**
- Modify: `assets/templates/tholosbuilder/propframe.container.template`
- Modify: `assets/js/TholosBuilder.js` (lines ~862–870)
- Modify: `assets/css/TholosBuilder.css` (delete `#prop_frame .ui-tabs-*` rules and the commented-out `.nav-tabs > li.active` block)

- [ ] **Step 1: Replace the contents of `propframe.container.template`**

Replace the entire file with this Bootstrap-5 `nav-tabs` structure:

```html
<ul id="prop_tabs" class="nav nav-tabs pf-tabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" type="button" role="tab"
            data-bs-toggle="tab" data-bs-target="#tab_prop_0">Properties</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" type="button" role="tab"
            data-bs-toggle="tab" data-bs-target="#tab_prop_1">Events</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" type="button" role="tab"
            data-bs-toggle="tab" data-bs-target="#tab_prop_2">Methods</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" type="button" role="tab"
            data-bs-toggle="tab" data-bs-target="#tab_prop_3">References</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" type="button" role="tab"
            data-bs-toggle="tab" data-bs-target="#tab_prop_4">Help</button>
  </li>
</ul>

<div class="tab-content">
  <div id="tab_prop_0" class="col-sm-12 tab-pane fade show active" role="tabpanel" tabindex="0"></div>
  <div id="tab_prop_1" class="col-sm-12 tab-pane fade" role="tabpanel" tabindex="0"></div>
  <div id="tab_prop_2" class="col-sm-12 tab-pane fade" role="tabpanel" tabindex="0"></div>
  <div id="tab_prop_3" class="col-sm-12 tab-pane fade" role="tabpanel" tabindex="0"></div>
  <div id="tab_prop_4" class="col-sm-12 tab-pane fade" role="tabpanel" tabindex="0"></div>
</div>
```

The five panel `id`s (`tab_prop_0` through `tab_prop_4`) are unchanged so the existing AJAX content loaders keep targeting the right divs.

- [ ] **Step 2: Update the tab init JS in `assets/js/TholosBuilder.js`**

Locate this block in `showPropertiesAndEvents()` (lines ~862–870):

```javascript
        if (data.success == 'OK') {
          $('#prop_frame').find('.content').html(data.html);
          $('#prop_frame .content').tabs({active: 0});
          $('#prop_frame .content').tabs({
            activate: function (event, ui) {
              showPropertiesAndEvents('', '', '');
            }
          });
          showPropertiesAndEvents(component_id_, property_id_, event_id_);
```

Replace it with:

```javascript
        if (data.success == 'OK') {
          $('#prop_frame').find('.content').html(data.html);
          // Bootstrap 5 nav-tabs: first tab is .show.active in the template;
          // refresh the panel content whenever the user switches tabs.
          $('#prop_tabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
            showPropertiesAndEvents('', '', '');
          });
          showPropertiesAndEvents(component_id_, property_id_, event_id_);
```

- [ ] **Step 3: Delete the dead jQuery-UI tab CSS overrides in `assets/css/TholosBuilder.css`**

Locate and delete this block (the rules look approximately like this; line numbers shift after Task 1's append):

```css
/* .nav-tabs > li > a {
  padding: 5px !important;
  font-size: 12px !important;
}

.nav-tabs > li.active > a,
.nav-tabs > li.active > a:hover,
.nav-tabs > li.active > a:focus{
    color: #fff !important;
    background-color: #8cb0cb !important;
} */

#prop_frame .ui-tabs-nav {
  padding-left: 0px;
  background: transparent;
  border-width: 0px 0px 1px 0px;
  -moz-border-radius: 0px;
  -webkit-border-radius: 0px;
  border-radius: 0px;
}
#prop_frame .ui-tabs-panel {
  background: #fff;
  border: 0;
  padding: 0;
}

#prop_frame .ui-tabs-anchor {
  padding: 5px;
  font-size: 12px;
}

#prop_frame .ui-state-focus {
  border: 0;
}

#prop_frame .ui-tabs-active {
  background-color: #8cb0cb;
  border-style: none;
}
```

After deletion the next nearby surviving rule should be `.tab-content { height: 100%; }`.

- [ ] **Step 4: Verify visually in the browser**

Hard-reload, then click a node in the tree. Expected:

1. The middle pane shows the new Bootstrap-5 nav-tabs strip — underline-style with offsite-blue active indicator (`#236499`), 12px text, no `#8cb0cb` blue background block.
2. Clicking each of the five tabs (Properties / Events / Methods / References / Help) switches the visible panel and triggers the existing AJAX refresh (the network tab should show `showProperties`, `showEvents`, etc. fetches firing on tab change).
3. Properties tab still displays its rows (will look the same as before because Task 3 hasn't applied `pf-table` yet — only the tab strip changed in this task).
4. No console errors. No `TypeError: $(...).tabs is not a function` or similar.

- [ ] **Step 5: Commit**

```bash
git add assets/templates/tholosbuilder/propframe.container.template \
        assets/js/TholosBuilder.js \
        assets/css/TholosBuilder.css
git commit -m "Migrate prop-editor tab strip from jQuery UI tabs to Bootstrap 5 nav-tabs"
```

---

### Task 3: Apply pf-table class to Properties + Events tab tables

**Files:**
- Modify: `assets/templates/tholosbuilder/propframe.tab.0.template`
- Modify: `assets/templates/tholosbuilder/propframe.tab.1.template`

- [ ] **Step 1: Update `propframe.tab.0.template` (Properties tab table)**

Open `assets/templates/tholosbuilder/propframe.tab.0.template`. It currently starts with:

```html
<table class="table table-condensed table_props">
```

Change to:

```html
<table class="table table-sm table_props pf-table">
```

(`table-condensed` is BS3; `table-sm` is the BS5 equivalent. `pf-table` activates the new visual rules from Task 1.)

The rest of the file is unchanged.

- [ ] **Step 2: Update `propframe.tab.1.template` (Events tab table)**

Same change at the top of `assets/templates/tholosbuilder/propframe.tab.1.template`:

```html
<table class="table table-condensed table_props">
```

→

```html
<table class="table table-sm table_props pf-table">
```

- [ ] **Step 3: Verify visually in the browser**

Reload the app and select a component in the tree. Expected:

1. Properties tab table now renders with: light-gray header bar (`#e9ecef`) with darker text (no more `#999` dark-grey-on-white), alternating row backgrounds (`#fff` / `#f8f9fa`), thinner row dividers, slightly tighter padding.
2. Hovering any row tints it light offsite-green (`rgba(136,189,33,0.15)`).
3. Mandatory rows (e.g. CacheID, CacheMode, Name, SQL on the qVehicleList sample) are bold.
4. The SQL property's `<pre>` block renders inside a soft-bordered scroll container with max-height 100px.
5. Switch to Events tab and confirm the same table look applies. Group headers (GUI / PHP) render as soft uppercase bars (`#e9ecef` background, small letterspaced uppercase text).
6. All Edit/Paste buttons still function (clicking opens the existing edit dialogs).

- [ ] **Step 4: Commit**

```bash
git add assets/templates/tholosbuilder/propframe.tab.0.template \
        assets/templates/tholosbuilder/propframe.tab.1.template
git commit -m "Apply pf-table class to Properties and Events tab tables"
```

---

### Task 4: Modernize row-action buttons in property + event row templates

**Files:**
- Modify: `assets/templates/tholosbuilder/propframe.property.row.template`
- Modify: `assets/templates/tholosbuilder/propframe.event.row.template`

- [ ] **Step 1: Update `propframe.property.row.template`**

Open `assets/templates/tholosbuilder/propframe.property.row.template`. Locate the value-cell content block (the `<td class="prop_value" id="prop_$sqlproperty_id">…</td>` body).

Inside that `<td>`, three button kinds may render: Edit (always when not runtime), Paste (when type=COMPONENT), Copy (when value_component_id is non-empty). Currently each button looks like:

```html
<button class="btn btn-default btn-sm btn-xs pull-right" onclick="…" title="Edit">
  <i class="fa-regular fa-edit"></i>
</button>
```

Wrap all three buttons inside a single `<span class="pf-row-actions">…</span>` (the wrapper goes immediately inside the `<td>`, before the value text), and on each button drop the legacy classes — keeping only `btn`. Also rename `fa-edit` → `fa-pen-to-square` (FA6 free) on the Edit button.

The resulting value-cell structure should look like (variable expansions from the Eisodos `<%FUNC%…%FUNC%>` blocks shown in pseudo-form):

```html
<td class="prop_value" id="prop_$sqlproperty_id">
  <span class="pf-row-actions">
    <button class="btn" onclick="editProperty($sqlcomponent_id,$sqlproperty_id,'$sqltype','$sqllink_id','$sqlversion');" title="Edit"><i class="fa-regular fa-pen-to-square"></i></button>
    <!-- Paste (only when sqltype = COMPONENT) -->
    <button class="btn" onclick="if (clipboardComponentID!='') saveProperty($sqlcomponent_id,$sqlproperty_id,'$sqllink_id','$sqlversion','',clipboardComponentID); else alert('Nothing to paste!');" title="Paste"><i class="fa-regular fa-clipboard"></i></button>
    <!-- Copy (only when sqlvalue_component_id is non-empty) -->
    <button class="btn" onclick="clipboardComponentID='$sqlvalue_component_id';" title="Copy"><i class="fa-regular fa-copy"></i></button>
  </span>
  …existing value-rendering block (type label, html-escaped value, COMPONENT path link, etc)…
</td>
```

The Eisodos `<%FUNC%…_eqs;param=sqlruntime;value=Y;true=…%FUNC%>` conditional that decides whether to emit any buttons at all stays intact — only its `false=` branches now emit buttons inside the `pf-row-actions` span instead of three separate floated buttons. Likewise the inner `<%FUNC%>` blocks that gate the Paste and Copy buttons are kept in place; they just emit their `<button class="btn" …>` markup inside the same span.

- [ ] **Step 2: Update `propframe.event.row.template`**

Open `assets/templates/tholosbuilder/propframe.event.row.template`. The value-cell block currently emits Edit + Paste like:

```html
<button class="btn btn-default btn-sm btn-xs pull-right" onclick="editEvent($sqlcomponent_id,$sqlevent_id,'$sqllink_id','$sqlversion');" title="Edit"><i class="fa-regular fa-edit"></i></button>
<button class="btn btn-default btn-sm btn-xs pull-right" onclick="if (clipboardMethodID!='') saveEvent($sqlcomponent_id, $sqlevent_id, '$sqllink_id', '$sqlversion', '', clipboardMethodComponentID, clipboardMethodID, $('#event_parameters_$sqlevent_id').val());" title="Paste"><i class="fa-regular fa-clipboard saveIcon"></i></button>
```

Wrap the two buttons in `<span class="pf-row-actions">`, drop `btn-default btn-sm btn-xs pull-right`, and rename `fa-edit` → `fa-pen-to-square`. The `saveIcon` class on the Paste icon stays (it's used by other JS for save-state animation).

Final value-cell structure:

```html
<td class="prop_value" id="event_$sqlevent_id">
  <span class="pf-row-actions">
    <button class="btn" onclick="editEvent($sqlcomponent_id,$sqlevent_id,'$sqllink_id','$sqlversion');" title="Edit"><i class="fa-regular fa-pen-to-square"></i></button>
    <button class="btn" onclick="if (clipboardMethodID!='') saveEvent($sqlcomponent_id, $sqlevent_id, '$sqllink_id', '$sqlversion', '', clipboardMethodComponentID, clipboardMethodID, $('#event_parameters_$sqlevent_id').val());" title="Paste"><i class="fa-regular fa-clipboard saveIcon"></i></button>
  </span>
  $sqlvalue
  …existing method-name + path link + parameters block…
</td>
```

- [ ] **Step 3: Verify visually in the browser**

Reload, select a component, look at Properties and Events tabs. Expected:

1. Each row's action buttons render as a tight grouped pill on the right (~22px tall, 11px font, `#fff` background with `#dee2e6` 1px border, light grey hover).
2. Clicking Edit on any property row still opens the Edit dialog (existing `editProperty()` flow). Clicking Paste / Copy still triggers their handlers.
3. Same for Events tab — Edit and Paste buttons still work.
4. The Edit icon should now be the FA6 `pen-to-square` (the page already loads FA6, so the rename is just a class change).
5. No layout breakage — the `prop_value` text wraps cleanly to the left of the button group; long property names ellipsize in the `prop_name` column.

- [ ] **Step 4: Commit**

```bash
git add assets/templates/tholosbuilder/propframe.property.row.template \
        assets/templates/tholosbuilder/propframe.event.row.template
git commit -m "Modernize property/event row buttons — pf-row-actions group, drop BS3 shim classes"
```

---

### Task 5: List-row pattern for Methods and References tabs

**Files:**
- Modify: `assets/templates/tholosbuilder/propframe.method.row.template`
- Create: `assets/templates/tholosbuilder/propframe.method.referer.template` (currently 0-byte stub)
- Create: `assets/templates/tholosbuilder/propframe.referers.row.template` (currently 0-byte stub)

- [ ] **Step 1: Update `propframe.method.row.template`**

Replace the file's contents with:

```html
<div class="pf-list-row">
  <span class="pf-list-main">$sqlmethod_name</span>
  <span class="pf-list-actions">
    <span class="pf-row-actions">
      <button class="btn" onclick="copyMethod($p_component_id,$sqlid);" title="Copy"><i class="fa-regular fa-copy"></i></button>
    </span>
  </span>
</div>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/propframe.method.referer;
SQL=
select tp.short_path as referer_component, ce.name as event_name, tp.id as referer_id
  from app_tree_path_v tp,
       app_component_events_v ce
 where ce.value_component_id=$p_component_id
       and ce.value_method_id=$sqlid
       and tp.id=ce.component_id
%SQL%>
```

The outer `<div class="pf-list-row">` replaces the previous `<div class="row" style="padding-top:5px; padding-bottom:5px; border-bottom:1px solid #ddd; background-color:#f7f7f7;">` wrapper. The Copy button uses the same `pf-row-actions` group introduced in Task 4. The `<%SQL%…%SQL%>` block that loops referers via `propframe.method.referer.template` is unchanged.

- [ ] **Step 2: Create content in `propframe.method.referer.template`**

Write this content into `assets/templates/tholosbuilder/propframe.method.referer.template`:

```html
<div class="pf-list-sub">
  <a href="javascript:$('#app_tree').jstree('deselect_all');$('#app_tree').jstree('select_node','#$sqlreferer_id');">$sqlreferer_component.<b>$sqlevent_name</b></a>
</div>
```

The column names (`$sqlreferer_component`, `$sqlevent_name`, `$sqlreferer_id`) match the SELECT in the parent template's `<%SQL%>` block.

- [ ] **Step 3: Create content in `propframe.referers.row.template`**

Identify the columns the parent SQL emits for this row template. Open `assets/templates/tholosbuilder/propframe.referers.sql.template` to confirm:

```bash
cat assets/templates/tholosbuilder/propframe.referers.sql.template
```

That SQL emits `$sqlname` (kind, e.g. "DBField", "MasterDBField"), `$sqltype_desc`, `$sqlshort_path`, `$sqlclass_name`, `$sqlcomponent_id`. Write this content into `assets/templates/tholosbuilder/propframe.referers.row.template`:

```html
<div class="pf-list-row">
  <span class="pf-list-main">
    <span class="ref-kind">$sqlname</span>:$sqltype_desc ·
    <a href="javascript:$('#app_tree').jstree('deselect_all');$('#app_tree').jstree('select_node','#$sqlcomponent_id');">$sqlshort_path</a>:$sqlclass_name
  </span>
</div>
```

The inline-styled `<div class="row" style="…">` and `<div class="col-sm-12">` wrappers from the production rendering go away; the new `pf-list-row` flex layout handles spacing.

- [ ] **Step 4: Verify visually in the browser**

Reload, select a component (preferably one with several methods + several references — `qVehicleList` from the user's prior DOM paste is a good test target).

Methods tab (expected):
1. Each method renders as a `pf-list-row`: method name on the left, single Copy button (in `pf-row-actions` group) on the right.
2. Where a method has referers, each appears as an indented `pf-list-sub` row directly underneath, with the referer path linked in offsite-blue and the bound event name in bold.
3. Hovering a method row tints it light offsite-green; alternating rows show striped backgrounds.
4. Clicking the Copy button still triggers `copyMethod($p_component_id, $sqlid)`.
5. Clicking a referer link still selects the corresponding tree node (existing jstree calls).

References tab (expected):
1. Each reference renders as a single `pf-list-row` with: kind (bold), `:`, type description, separator dot, linked component path (offsite-blue), `:`, class name.
2. Hover/striping behaves the same as Methods tab.
3. Clicking the linked path still navigates the tree.

- [ ] **Step 5: Commit**

```bash
git add assets/templates/tholosbuilder/propframe.method.row.template \
        assets/templates/tholosbuilder/propframe.method.referer.template \
        assets/templates/tholosbuilder/propframe.referers.row.template
git commit -m "Restyle Methods + References tabs with pf-list-row pattern"
```

---

### Task 6: Restyle the empty Help tab as a centered empty-state card

**Files:**
- Modify: `assets/templates/tholosbuilder/propframe.help.empty.template`

- [ ] **Step 1: Replace the file contents**

Replace `assets/templates/tholosbuilder/propframe.help.empty.template` with:

```html
<div class="pf-help-empty">
  <i class="fa-regular fa-circle-info" aria-hidden="true"></i>
  <div>No help defined for this component.</div>
  <div class="pf-help-empty-actions">
    <a href="javascript:EditHelp('',$p_component_id);"><i class="fa-regular fa-circle-plus me-1"></i>Create one</a>
    ·
    <a href="javascript:pasteHelp($p_component_id);"><i class="fa-regular fa-clipboard me-1"></i>Paste reference</a>
  </div>
</div>
```

The two `javascript:` links keep their existing JS handler calls (`EditHelp('', $p_component_id)` and `pasteHelp($p_component_id)`) and the `$p_component_id` Eisodos variable substitution.

- [ ] **Step 2: Verify visually in the browser**

Select a component that has no help defined (any newly-created component, or a component you can confirm has an empty help body). Click the Help tab. Expected:

1. The tab renders the empty-state card: a 24px FA `circle-info` icon centered at top in `#adb5bd`, then "No help defined for this component." in `#6c757d` 12px text, then two action links separated by a `·`.
2. The "Create one" link still opens the EditHelp flow when clicked.
3. The "Paste reference" link still triggers the pasteHelp flow.
4. Tab background and surrounding panel chrome match the rest of the prop-editor look.

- [ ] **Step 3: Commit**

```bash
git add assets/templates/tholosbuilder/propframe.help.empty.template
git commit -m "Restyle empty Help tab as centered empty-state card"
```

---

## Self-Review Findings

Spec coverage (each section of the spec mapped to a task):

| Spec section | Implemented in |
|---|---|
| §3.1 tab strip migration (template + JS) | Task 2 |
| §3.2 table restyle (header, striping, hover/select, mandatory bold, prop_name/value/type, method_path) | Task 1 (CSS) + Task 3 (apply class) |
| §3.2 event_type group header | Task 1 (CSS) — markup unchanged, applies once `pf-table` is on the parent table from Task 3 |
| §3.3 list-row pattern (Methods + References + sub-rows) | Task 1 (CSS) + Task 5 (templates) |
| §3.4 row-action button modernization | Task 1 (CSS) + Task 4 (templates) |
| §3.5 Help empty state | Task 1 (CSS) + Task 6 (template) |
| §3.6 tab strip styling | Task 1 (CSS) + Task 2 (apply via `pf-tabs` class on the new `<ul>`) |
| §4 file changes table | Tasks 1–6 cover every row; the spec did not enumerate `propframe.tab.0/1.template`, those are added in Task 3 (noted in this plan's File Structure section); `propframe.main.template` is confirmed dead and intentionally skipped (noted in this plan's File Structure section) |
| §6 acceptance criteria | Verification steps in Tasks 2 / 3 / 4 / 5 / 6 collectively check every criterion |

No placeholders, no TBDs. All template snippets contain the actual final markup with the real Eisodos variable names from the surrounding files. The `pf-*` class names are consistent across CSS (Task 1), templates (Tasks 2–6), and the spec.

One spec wording to flag for the engineer: the spec's §4 says "Update `propframe.main.template`" — that template is dead code (`grep -rn 'propframe\.main' src assets` returns zero results); this plan omits it. If the engineer encounters confusion about which template controls the rendered tab strip, the answer is `propframe.container.template` (Task 2), because `showPropertiesAndEventsHead` in `TholosBuilderApplication.php:951` returns `propframe.container`, not `propframe.main`.
