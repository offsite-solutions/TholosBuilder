# Property Editor Restyle (read-only tab content)

**Date:** 2026-05-04
**Branch:** `feature/ui-redesign`
**Status:** Design

---

## 1. Background

The middle pane of the builder (`#prop_frame`) is the densest UI surface in the
project. It has five tabs — Properties, Events, Methods, References, Help —
each rendering its own SQL-driven content into one of two layout idioms:

- **Table layout** (`<table class="table_props">`) — used by Properties and Events tabs.
- **Row-of-divs layout** (`<div class="row" style="…inline…">`) — used by Methods and References tabs.

The tab strip is a jQuery UI tabs widget (`.tabs(...)` initialized at
`TholosBuilder.js:855–875`) styled with `#prop_frame .ui-tabs-*` overrides in
`TholosBuilder.css` to look "Bootstrap-ish". The active tab uses legacy blue
`#8cb0cb`. Row markup carries Bootstrap-3 era classes (`btn btn-default btn-sm
btn-xs pull-right`, `table table-condensed`) that survive thanks to shim rules
shipped at the bottom of `TholosBuilder.css`.

This sub-project restyles **read-only tab content only** — it does not touch
the form-editor templates (`propframe.form.STRING.template`, etc.) that pop up
when a user clicks Edit on a row. Those nine templates remain on the BS3 shims
and become a follow-up sub-project on the same branch.

## 2. Scope

**In scope**

- Migrate the tab strip from jQuery UI tabs → Bootstrap 5 `nav-tabs` (template + JS).
- Restyle `table.table_props` (Properties + Events tab tables): light-gray header, alternating row stripes, offsite-green hover/select, mandatory rows kept bold.
- Restyle the `event_type` group headers ("GUI" / "PHP") inside the Events table as soft uppercase section headers.
- Replace the inline-styled `<div class="row" style="…">` blocks in Methods and References tabs with a uniform `.pf-list-row` pattern that shares the same hover/strip behavior as the table.
- Restyle the empty Help tab as a centered empty-state card.
- Modernize row-action button markup in the in-scope row templates: `btn btn-default btn-sm btn-xs pull-right` → grouped under a `.pf-row-actions` span with neutral `.btn` styling. (Underlying BS3 shims stay in CSS because the out-of-scope form-editor templates still use them.)
- Drop the dead `#prop_frame .ui-tabs-*` overrides and the commented-out `.nav-tabs > li.active` block from `TholosBuilder.css`.

**Out of scope (deferred to follow-up specs on the same branch)**

- Form-editor templates (`propframe.form.STRING / NUMBER / BOOLEAN / COMPONENT / TEXT / LIST / JSON / PARAMETER / ARRAY.template`) — the popup edit forms invoked by the row Edit buttons.
- `propframe.event.form.template` (the event-edit popup).
- The `.btn-default`, `.btn-xs`, `.pull-right`, `.well`, `.well-sm` shim rules at the end of `TholosBuilder.css` — kept until form editors stop using them.
- Wizards (separate sub-project).
- jQuery UI tabs library removal — only the prop-editor instance migrates; jstree still depends on jQuery UI's positioning utilities.

## 3. Architecture

### 3.1 Tab strip — jQuery UI tabs → Bootstrap 5 nav-tabs

**Markup change (in `propframe.main.template`):** the existing markup
already uses Bootstrap nav-tabs class names (`<ul id="prop_tabs" class="nav nav-tabs">`)
but with Bootstrap-3 attributes (`<li class="active">`, `data-toggle="tab"`).
We rewrite it with Bootstrap-5 conventions:

```html
<ul id="prop_tabs" class="nav nav-tabs pf-tabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" type="button" role="tab"
            data-bs-toggle="tab" data-bs-target="#tab_prop_tree">Properties</button>
  </li>
  <!-- … 4 more tabs, only the first is .active …  -->
</ul>
<div class="tab-content">
  <div id="tab_prop_tree" class="tab-pane fade show active" role="tabpanel" tabindex="0">
    …
  </div>
  <!-- … 4 more tab panes, none with .show.active …  -->
</div>
```

The five panel `id`s (`tab_prop_tree`, `tab_event_tree`, `tab_methods_tree`,
`tab_referers_tree`, `tab_user_help`) and their inner content-holding ids
(`prop_tree`, `event_tree`, `methods_tree`, `referers_tree`, `user_help`) are
preserved unchanged so the AJAX handlers in `TholosBuilder.js` keep working.

**JS change (in `TholosBuilder.js` around line 855–875):**

```js
// Before — jQuery UI tabs
$('#prop_frame .content').tabs({active: 0});
$('#prop_frame .content').tabs({
  activate: function (event, ui) {
    showPropertiesAndEvents('', '', '');
  }
});

// After — Bootstrap 5 nav-tabs
//   • markup already self-activates the first tab (.show.active class)
//   • bind the same refresh hook on Bootstrap's shown event
$('#prop_tabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
  showPropertiesAndEvents('', '', '');
});
```

`propframe.container.template` carries the same legacy idiom and gets the same
treatment (its 5 placeholder divs `#tab_prop_0` … `#tab_prop_4` already exist
and only need `class="tab-pane fade"` plus the first one `.show.active`).

### 3.2 Table restyle (Properties + Events tabs)

A single new class block — `.pf-table` — replaces the visual contributions of
the existing `table_props` rules. The legacy class name `.table_props` stays in
the markup as a hook so we don't have to touch every row template selector,
but its rule body is rewritten:

```css
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
table.table_props.pf-table .prop_name  { color: #212529; border-right: 1px solid #f1f3f5; width: 30%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
table.table_props.pf-table .prop_value { color: #212529; }
table.table_props.pf-table .prop_type  { color: #adb5bd; font-size: 11px; }
table.table_props.pf-table .method_path { color: #6c757d; font-style: italic; font-size: 11px; }
table.table_props.pf-table tbody td.event_type {
  background: #e9ecef !important;
  color: #495057; font-weight: 600;
  font-size: 11px; letter-spacing: .04em; text-transform: uppercase;
  padding: .25rem .55rem; border-bottom: 1px solid #dee2e6;
}
table.table_props.pf-table pre {
  background: #f8f9fa; border: 1px solid #e9ecef; border-radius: .25rem;
  padding: .35rem .5rem; font-size: 11px; color: #495057;
  margin: .25rem 0 0 0; max-height: 100px; overflow: auto;
}
```

The table markup gets `pf-table` added alongside `table_props` in the row
templates' parent (Properties + Events) so the new selectors win. We also
switch `table-condensed` (BS3) → `table-sm` (BS5) at that point.

### 3.3 List-row pattern (Methods + References tabs)

Replaces the inline-styled `<div class="row" style="padding-top:5px;
padding-bottom:5px; border-bottom:1px solid #ddd; background-color:#f7f7f7;">`
with a uniform CSS-only pattern:

```css
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
```

Templates change:

| Template | Before (excerpt) | After |
|---|---|---|
| `propframe.method.row.template` | `<div class="row" style="…inline…"><div class="col-sm-10">$sqlmethod_name</div><div class="col-sm-2"><button class="btn …">Copy</button></div>` + nested SQL block | `<div class="pf-list-row"><span class="pf-list-main">$sqlmethod_name</span><span class="pf-list-actions"><button class="btn pf-row-actions" …>Copy</button></span></div>` + the SQL block stays as-is |
| `propframe.method.referer.template` | `<div class="col-sm-12" style="border-top:1px solid #eee; margin-top:3px; padding-top:3px;"><a href="…">…<b>onSubmitSuccess</b></a></div>` | `<div class="pf-list-sub"><a href="…">…<b>onSubmitSuccess</b></a></div>` |
| `propframe.referers.row.template` | `<div class="row" style="…inline…"><div class="col-sm-12"><b>$sqlname</b>:$sqltype_desc<br/><a href="…">$sqlshort_path</a>:$sqlclass_name</div></div>` | `<div class="pf-list-row"><span class="pf-list-main"><span class="ref-kind">$sqlname</span>:$sqltype_desc · <a href="…">$sqlshort_path</a>:$sqlclass_name</span></div>` |

### 3.4 Row-action button modernization

Current row markup (in property/event/method row templates):

```html
<button class="btn btn-default btn-sm btn-xs pull-right" onclick="…" title="Edit">
  <i class="fa-regular fa-edit"></i>
</button>
```

After:

```html
<span class="pf-row-actions">
  <button class="btn" onclick="…" title="Edit">
    <i class="fa-regular fa-pen-to-square"></i>
  </button>
</span>
```

Plus this CSS:

```css
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

The Bootstrap-3 shims (`.btn-default`, `.btn-xs`, `.pull-right`) stay in CSS
because the out-of-scope form-editor templates still rely on them.

### 3.5 Help empty state

`propframe.help.empty.template` currently renders two raw links. After:

```html
<div class="pf-help-empty">
  <i class="fa-regular fa-circle-info" aria-hidden="true"></i>
  <div>No help defined for this component.</div>
  <div class="pf-help-empty-actions">
    <a href="javascript:EditHelp('',$p_component_id);">
      <i class="fa-regular fa-circle-plus me-1"></i>Create one
    </a>
    ·
    <a href="javascript:pasteHelp($p_component_id);">
      <i class="fa-regular fa-clipboard me-1"></i>Paste reference
    </a>
  </div>
</div>
```

```css
.pf-help-empty {
  text-align: center; padding: 2rem 1rem; color: #6c757d; font-size: 12px;
}
.pf-help-empty > .fa-circle-info { font-size: 24px; color: #adb5bd; display: block; margin-bottom: .5rem; }
.pf-help-empty a { display: inline-block; margin: .25rem .5rem; color: #236499; text-decoration: none; }
.pf-help-empty a:hover { text-decoration: underline; }
```

The two existing JS calls (`EditHelp(…)`, `pasteHelp(…)`) and the
`$p_component_id` template variable are preserved 1:1.

### 3.6 Tab strip styling

```css
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
```

## 4. Files changed

| File | Change |
|---|---|
| `assets/templates/tholosbuilder/propframe.main.template` | Replace jQuery-UI/BS3 tab markup with Bootstrap-5 `nav-tabs` (`<button>` triggers + `data-bs-toggle="tab"` + `role="tablist"` etc.). Add `pf-tabs` class to `<ul>` and `pf-table` to both `<table>` blocks; switch `table-condensed` → `table-sm`. |
| `assets/templates/tholosbuilder/propframe.container.template` | Same Bootstrap-5 tab markup conversion (this template owns the same 5-tab structure for re-renders). |
| `assets/templates/tholosbuilder/propframe.property.row.template` | Group Edit/Paste/Copy buttons under `<span class="pf-row-actions">`; drop `btn-default btn-sm btn-xs pull-right` from each button (they keep just `btn`). Update `fa-edit` → `fa-pen-to-square`. |
| `assets/templates/tholosbuilder/propframe.event.row.template` | Same button modernization. |
| `assets/templates/tholosbuilder/propframe.method.row.template` | Replace inline-styled `<div class="row">` wrapper with `<div class="pf-list-row">` + `<span class="pf-list-main">` / `<span class="pf-list-actions">`; modernize Copy button. |
| `assets/templates/tholosbuilder/propframe.method.referer.template` | Wrap output in `<div class="pf-list-sub">…</div>` (drop the inline-styled `col-sm-12` wrapper). |
| `assets/templates/tholosbuilder/propframe.referers.row.template` | Replace inline-styled `<div class="row">` with `<div class="pf-list-row"><span class="pf-list-main"><span class="ref-kind">…</span></span></div>`. |
| `assets/templates/tholosbuilder/propframe.help.empty.template` | Wrap the two existing links in `<div class="pf-help-empty">…</div>` with the icon + label + actions structure. |
| `assets/js/TholosBuilder.js` | In `showPropertiesAndEvents()` (around line 855–875): replace the two `$('#prop_frame .content').tabs(…)` calls with a single `$('#prop_tabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(){ showPropertiesAndEvents('','',''); });` binding. |
| `assets/css/TholosBuilder.css` | Add `.pf-tabs`, `.pf-table` (replacing `table.table_props` rule body), `.pf-list-row`, `.pf-list-sub`, `.pf-help-empty`, `.pf-row-actions` rule blocks. Delete the `#prop_frame .ui-tabs-nav / .ui-tabs-panel / .ui-tabs-anchor / .ui-state-focus / .ui-tabs-active` overrides and the commented-out `.nav-tabs > li.active …` block. Keep `.btn-default`, `.btn-xs`, `.pull-right`, `.well`, `.well-sm` shims (still needed by form-editor templates). |

No JS handler signatures change. The existing AJAX endpoints (`showPropertiesAndEventsHead`, `showProperties`, `showEvents`, `showMethods`, `showReferers`, `showHelp`) and the property/event/method-edit click handlers (`editProperty`, `editEvent`, `saveProperty`, etc.) are untouched.

## 5. Behavior changes

1. **Tab strip activation** moves from jQuery UI's `activate` event to Bootstrap's `shown.bs.tab` event. Functionally equivalent — the same `showPropertiesAndEvents('','','')` refresh fires after a user clicks a tab.
2. **Tab visual** changes from a colored block-style active tab (`#8cb0cb` background) to an underline-style active indicator in offsite blue (`#236499`). Smaller, more readable tab text (12px).
3. **Row striping** is new — alternating `#fff` and `#f8f9fa` backgrounds replace the uniform `#f7f7f7` row background and the hover-changes-only-prop_value behavior.
4. **Hover and selected** rows use the offsite-green tint family established by the treeview restyle (15% / 22% alpha) for visual consistency across the whole app shell.
5. **Mandatory rows** keep their bold-only treatment as requested — no border or color emphasis added in this spec; user said "I will add styling after I confirmed the others."
6. **List-rows** in Methods/References tabs share the same hover/striping behavior as the table rows, replacing the previous uniform grey rows. The `pf-list-sub` referer rows stay tucked underneath their parent method.
7. **Empty Help tab** gains a discoverable empty-state card with an icon and clearer affordances, in place of two raw inline links.
8. **Row buttons** become slightly tighter (~22px tall vs the previous BS3 `btn-xs` + shim `padding: 1px 5px`) and live in a flex group rather than three separate floated `<button>`s. Click handlers are unchanged.

## 6. Acceptance criteria

1. Selecting a component in the tree loads the property pane with the new Bootstrap-5 tab strip; clicking each of the five tabs activates the matching panel and triggers a refresh fetch as before.
2. Properties tab renders the table with light-gray header, alternating row backgrounds, and offsite-green hover/select. Mandatory rows are bold. The SQL property displays inside a soft-bordered `<pre>` block scoped to the table cell (max-height 100px with scroll).
3. Events tab renders the same table look; `event_type` group headers ("GUI", "PHP") render as soft uppercase section headers spanning both columns.
4. Methods tab renders each method as a `pf-list-row` with a Copy button on the right; referer links appear as indented `pf-list-sub` items under the method.
5. References tab renders each reference as a `pf-list-row` showing kind (bold), type, separator, linked component path (offsite-blue), and class.
6. Help tab when no help is defined renders the empty-state card; the two action links still call `EditHelp(…)` and `pasteHelp(…)` correctly.
7. Every existing row-action click (`editProperty`, `editEvent`, `saveProperty`, `saveEvent`, `copyMethod`, etc.) still fires its handler with the same arguments.
8. No JavaScript console errors on tab switch, panel load, or button click.
9. The dead `#prop_frame .ui-tabs-*` CSS rules and the commented-out `.nav-tabs > li.active` block are removed from `TholosBuilder.css`.

## 7. Decisions log

- **Migrate tab widget vs CSS-restyle jQuery UI.** Chose migration because: (a) the markup already uses `nav-tabs` class names — it's been "almost Bootstrap" for two generations of Bootstrap and never finished; (b) the only JS coupling is one `activate` callback that maps cleanly to `shown.bs.tab`; (c) it removes one `.ui-tabs` widget instance, simplifying the path to dropping jQuery UI entirely later.
- **Keep `table_props` class as a hook, scope new rules with `pf-table`.** Avoids a multi-template find/replace for `class="table table-condensed table_props"` and lets the new style co-exist with anything that may rely on the legacy hook.
- **Don't remove BS3 shims yet.** The form-editor templates (out of scope) still use them; removing them now would break the popup edit forms. They get cleaned up when form-editors are restyled.
- **Mandatory rows stay bold-only.** Per user direction during brainstorming: "keep it bold right now, I will add styling after I confirmed the others."
- **List-row pattern instead of converting Methods/References to tables.** The Methods tab in particular has a 1-to-N relationship (method → many referer links) that fits naturally in a parent/sub-row idiom but would force `colspan` gymnastics in a table.
- **Help empty state restyled in scope, populated Help out of scope (deferred).** The populated-Help rendering uses `propframe.help.editor.template` and `propframe.help.referencingsql.template` which are large and have their own structural concerns; keeping them out keeps this spec focused.
