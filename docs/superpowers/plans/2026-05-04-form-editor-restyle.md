# Property Form Editors Restyle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the six inline form-editor templates (`propframe.form.STRING/NUMBER/TEXT/BOOLEAN/LIST/COMPONENT`) loaded by the prop editor's pen-icon Edit action. Replace the BS3 grid + legacy `btn-default btn-sm btn-xs pull-right` chrome with a tight horizontal flex layout, borderless icon buttons.

**Architecture:** Each template wraps content in `<div class="pf-form">` (display:flex). Input/select grows; Save and any other action buttons sit inline at the right as borderless `.pf-form-btn` icons. Templates with a "Selector" override input get a second `.pf-form.pf-form-stack` row underneath with a small label.

**Tech Stack:** Bootstrap 5.3.8, FontAwesome 6, jQuery 3, Eisodos templates (`<%SQL%>`, `[%_function_name=...%]`), `textareafullscreen` jQuery plugin.

**Verification:** No automated tests. After each task, the user opens the prop editor for a component with the relevant property type, clicks the pen icon to enter edit mode, and confirms the new layout renders + Save + Enter-to-save + any extra action buttons all still work.

---

## File Structure

| File | Action |
|---|---|
| `assets/css/TholosBuilder.css` | Append `=== Property form editors ===` block (Task 1) |
| `assets/templates/tholosbuilder/propframe.form.STRING.template` | Rewrite (Task 2) |
| `assets/templates/tholosbuilder/propframe.form.NUMBER.template` | Rewrite (Task 2) |
| `assets/templates/tholosbuilder/propframe.form.TEXT.template` | Rewrite (Task 3) |
| `assets/templates/tholosbuilder/propframe.form.BOOLEAN.template` | Rewrite (Task 3) |
| `assets/templates/tholosbuilder/propframe.form.LIST.template` | Rewrite (Task 3) |
| `assets/templates/tholosbuilder/propframe.form.COMPONENT.template` | Rewrite (Task 4) |

---

## Task 1: Add shared `.pf-form` CSS

**Files:**
- Modify: `assets/css/TholosBuilder.css` (append at end)

- [ ] **Step 1: Append the CSS block**

Append exactly this content to the end of `assets/css/TholosBuilder.css`:

```css

/* === Property form editors ============================== */
.pf-form {
  display: flex;
  align-items: center;
  gap: .25rem;
}
.pf-form .form-control,
.pf-form .form-select {
  flex: 1;
  font-size: 12px;
  padding: .15rem .4rem;
  min-height: 0;
  height: auto;
}
.pf-form textarea.form-control {
  font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
  min-height: 60px;
}
.pf-form .pf-form-btn {
  --bs-btn-padding-x: .35rem;
  --bs-btn-padding-y: .15rem;
  --bs-btn-font-size: 12px;
  --bs-btn-line-height: 1;
  border: none;
  background: transparent;
  color: #6c757d;
}
.pf-form .pf-form-btn:hover {
  background: transparent;
  color: #236499;
}
.pf-form .pf-form-btn .saveIcon {
  color: #88bd21;
}
.pf-form.pf-form-stack {
  display: block;
  margin-top: .35rem;
}
.pf-form-stack-label {
  font-size: 11px;
  color: #6c757d;
  margin-bottom: .15rem;
  display: block;
}
```

- [ ] **Step 2: Commit**

```bash
git add assets/css/TholosBuilder.css
git commit -m "Form editors: add shared .pf-form CSS block"
```

---

## Task 2: Restyle STRING + NUMBER

**Files:**
- Modify: `assets/templates/tholosbuilder/propframe.form.STRING.template` (rewrite)
- Modify: `assets/templates/tholosbuilder/propframe.form.NUMBER.template` (rewrite)

- [ ] **Step 1: Rewrite STRING**

Replace the entire content of `propframe.form.STRING.template` with:

```html
<div class="pf-form">
  <input class="form-control form-control-sm" id="prop_edit_$p_property_id" value="$p_value">
  <button class="btn pf-form-btn" id="prop_button_$p_property_id" onclick="saveProperty($p_component_id,$p_property_id,'$p_link_id','$p_version',$('#prop_edit_$p_property_id').val(),'');" title="Save">
    <i class="fa-regular fa-check saveIcon"></i>
  </button>
  <button class="btn pf-form-btn" onclick="editProperty($p_component_id,$p_property_id,'TEXT','$p_link_id','$p_version');" title="Edit as large text">
    <i class="fa-regular fa-line-height"></i>
  </button>
</div>
<script type="text/javascript">
$("#prop_edit_$p_property_id").keyup(function (e) {
    if (e.keyCode == 13) $("#prop_button_$p_property_id").click();
});
</script>
```

Preserved exactly: `prop_edit_$p_property_id`, `prop_button_$p_property_id` ids; `saveProperty(...)` and `editProperty(..., 'TEXT', ...)` arguments byte-for-byte; the `saveIcon` class on the check icon; the keyup-Enter `<script>` block.

- [ ] **Step 2: Rewrite NUMBER**

Replace the entire content of `propframe.form.NUMBER.template` with:

```html
<div class="pf-form">
  <input class="form-control form-control-sm" id="prop_edit_$p_property_id" value="$p_value">
  <button class="btn pf-form-btn" id="prop_button_$p_property_id" onclick="saveProperty($p_component_id,$p_property_id,'$p_link_id','$p_version',$('#prop_edit_$p_property_id').val(),'');" title="Save">
    <i class="fa-regular fa-check saveIcon"></i>
  </button>
</div>
<script type="text/javascript">
$("#prop_edit_$p_property_id").keyup(function (e) {
    if (e.keyCode == 13) $("#prop_button_$p_property_id").click();
});
</script>
```

- [ ] **Step 3: User verification**

Ask the user to open the prop editor for a component with at least one STRING and one NUMBER property. For each:
- Click the pen icon to enter edit mode
- Confirm the input + Save (+ "Edit as TEXT" for STRING) renders inline with the new flex layout
- Type a new value, press Enter, confirm Save fires
- For STRING: click "Edit as TEXT" and confirm `editProperty(..., 'TEXT', ...)` triggers (loads the TEXT editor)

- [ ] **Step 4: Commit**

```bash
git add assets/templates/tholosbuilder/propframe.form.STRING.template assets/templates/tholosbuilder/propframe.form.NUMBER.template
git commit -m "Form editors: restyle STRING + NUMBER to .pf-form layout"
```

---

## Task 3: Restyle TEXT + BOOLEAN + LIST

**Files:**
- Modify: `assets/templates/tholosbuilder/propframe.form.TEXT.template` (rewrite)
- Modify: `assets/templates/tholosbuilder/propframe.form.BOOLEAN.template` (rewrite)
- Modify: `assets/templates/tholosbuilder/propframe.form.LIST.template` (rewrite)

- [ ] **Step 1: Rewrite TEXT**

Replace the entire content of `propframe.form.TEXT.template` with:

```html
<div class="pf-form">
  <textarea class="form-control form-control-sm" id="prop_edit_$p_property_id" spellcheck="false">$p_value</textarea>
  <button class="btn pf-form-btn" id="prop_button_$p_property_id" onclick="saveProperty($p_component_id,$p_property_id,'$p_link_id','$p_version',$('#prop_edit_$p_property_id').val(),'');" title="Save">
    <i class="fa-regular fa-check saveIcon"></i>
  </button>
</div>

<script type="text/javascript">
$(document).ready(function() {
    $('#prop_edit_$p_property_id').textareafullscreen({
        overlay: true,
        maxWidth: '90%',
        maxHeight: '90%'
    });
});
</script>
```

Preserved: textarea id, Save button id, `spellcheck="false"`, `saveProperty()` arguments, the `textareafullscreen()` plugin init with the same options. Monospace font moves into the CSS block (no longer inline).

- [ ] **Step 2: Rewrite BOOLEAN**

Replace the entire content of `propframe.form.BOOLEAN.template` with:

```html
<div class="pf-form">
  <select class="form-select form-select-sm" id="prop_edit_$p_property_id">
    <option value=""></option>
    <option value="true" [%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=p_value;value=true;true=selected;false=%]>true</option>
    <option value="false" [%_function_name=TholosBuilder\TholosBuilderCallback::_eqs;param=p_value;value=false;true=selected;false=%]>false</option>
  <select>
  <button class="btn pf-form-btn" id="prop_button_$p_property_id" onclick="saveProperty($p_component_id,$p_property_id,'$p_link_id','$p_version',$('#prop_edit_2_$p_property_id').val()==''?$('#prop_edit_$p_property_id').val():$('#prop_edit_2_$p_property_id').val(),'');" title="Save">
    <i class="fa-regular fa-check saveIcon"></i>
  </button>
</div>
<div class="pf-form pf-form-stack">
  <span class="pf-form-stack-label">Selector:</span>
  <input class="form-control form-control-sm" id="prop_edit_2_$p_property_id" value="$p_value2">
</div>
<script type="text/javascript">
$("#prop_edit_2_$p_property_id").keyup(function (e) {
    if (e.keyCode == 13) $("#prop_button_$p_property_id").click();
});
</script>
```

Preserved: ids `prop_edit_$p_property_id`, `prop_button_$p_property_id`, `prop_edit_2_$p_property_id`; both `[%_function_name=...%]` Eisodos directives byte-for-byte; the `<select>` typo (kept on its own line as in the original); the `saveProperty()` argument expression; the keyup-Enter handler targeting the secondary input.

- [ ] **Step 3: Rewrite LIST**

Replace the entire content of `propframe.form.LIST.template` with:

```html
<div class="pf-form">
  <select class="form-select form-select-sm" id="prop_edit_$p_property_id">
    <option value=""></option>
    $options
  <select>
  <button class="btn pf-form-btn" id="prop_button_$p_property_id" onclick="saveProperty($p_component_id,$p_property_id,'$p_link_id','$p_version',$('#prop_edit_2_$p_property_id').val()==''?$('#prop_edit_$p_property_id').val():$('#prop_edit_2_$p_property_id').val(),'');" title="Save">
    <i class="fa-regular fa-check saveIcon"></i>
  </button>
</div>
<div class="pf-form pf-form-stack">
  <span class="pf-form-stack-label">Selector or custom value:</span>
  <input class="form-control form-control-sm" id="prop_edit_2_$p_property_id" value="$p_value2">
</div>
<script type="text/javascript">
$("#prop_edit_2_$p_property_id").keyup(function (e) {
    if (e.keyCode == 13) $("#prop_button_$p_property_id").click();
});
</script>
```

Preserved: the `$options` Eisodos token (renders the option list); the `<select>` typo; the `saveProperty()` argument expression; the keyup-Enter handler.

- [ ] **Step 4: User verification**

Ask the user to open the prop editor for a component with TEXT, BOOLEAN, and LIST properties. For each:
- Click pen icon, confirm the new layout renders
- TEXT: confirm the textarea is monospace, Save fires, and the `textareafullscreen` plugin still works (e.g. on focus/keyboard shortcut)
- BOOLEAN: pick true/false, press Enter in the Selector input, confirm Save fires with the right value (selector takes precedence if non-empty)
- LIST: same as BOOLEAN

- [ ] **Step 5: Commit**

```bash
git add assets/templates/tholosbuilder/propframe.form.TEXT.template assets/templates/tholosbuilder/propframe.form.BOOLEAN.template assets/templates/tholosbuilder/propframe.form.LIST.template
git commit -m "Form editors: restyle TEXT + BOOLEAN + LIST to .pf-form layout"
```

---

## Task 4: Restyle COMPONENT

**Files:**
- Modify: `assets/templates/tholosbuilder/propframe.form.COMPONENT.template` (rewrite)

- [ ] **Step 1: Rewrite COMPONENT**

Replace the entire content of `propframe.form.COMPONENT.template` with:

```html
<div class="pf-form">
  <select class="form-select form-select-sm" id="prop_edit_$p_property_id">
<option value=""></option>
<%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/sqls.select;
SQL=
select id, path as text,
       case when q.value_component_id=atp.id then 'selected' else '' end as selected
  from app_tree_path_v atp
  left outer join (select value_component_id from app_component_properties ap where ap.id=$p_link_id~='-1';) q on 1=1
where atp.component_type_id in ( select ct.id
                                    from def_component_types ct
                                    connect by prior  ct.id = ct.ancestor_id
                                    start with ct.id=(select component_type_id from def_properties p where p.id=$p_property_id)
                                 )
      and (atp.route=(select route from app_tree_path_v atp2 where atp2.id=$p_component_id)
           or atp.id=q.value_component_id)
order by component_order
%SQL%>
<!-- <option value="">------------------------------------------</option>
%SQL%
DB=Tholos.DefinitionDBIndex;
ROW=tholosbuilder/sqls.select;
SQL=
select id, path as text,
       case when (select value_component_id from app_component_properties ap where ap.id=$p_link_id~='-1';)=atp.id then 'selected' else '' end as selected
  from app_tree_path_v atp
where atp.component_type_id in ( select ct.id
                                    from def_component_types ct
                                    connect by prior  ct.id = ct.ancestor_id
                                    start with ct.id=(select component_type_id from def_properties p where p.id=$p_property_id)
                                 )
      and atp.route!=(select route from app_tree_path_v atp2 where atp2.id=$p_component_id)
order by component_order
%SQL% -->
  <select>
  <button class="btn pf-form-btn" id="prop_button_$p_property_id" onclick="saveProperty($p_component_id,$p_property_id,'$p_link_id','$p_version',$('#prop_edit_2_$p_property_id').val(),$('#prop_edit_$p_property_id').val());" title="Save">
    <i class="fa-regular fa-check saveIcon"></i>
  </button>
  <button class="btn pf-form-btn" onclick="if (clipboardComponentID!='') saveProperty($p_component_id,$p_property_id,'$p_link_id','$p_version','',clipboardComponentID); else alert('Nothing to paste!');" title="Paste">
    <i class="fa-regular fa-clipboard"></i>
  </button>
</div>
<div class="pf-form pf-form-stack">
  <span class="pf-form-stack-label">Selector:</span>
  <input class="form-control form-control-sm" id="prop_edit_2_$p_property_id" value="$p_value2" onchange="$('#prop_edit_$p_property_id').val('');">
</div>
```

Preserved exactly:
- The live `<%SQL%>...%SQL%>` block byte-for-byte (DB, ROW, SQL clauses)
- The commented-out second `<%SQL%>` block (HTML comment + SQL inside) byte-for-byte
- The `<select>` typo
- The Save button's `onclick` &mdash; **note** COMPONENT uses opposite arg order: value=`$('#prop_edit_2_…')`, value2=`$('#prop_edit_…')`. Do not "harmonize" with other templates.
- The Paste button's `onclick` with the `clipboardComponentID` check and `alert('Nothing to paste!')` fallback
- The `onchange` on the secondary input that clears the main select

Note: COMPONENT does not have a keyup-Enter handler in the original; the rewrite does not add one.

- [ ] **Step 2: User verification**

Ask the user to open the prop editor for a component that has at least one COMPONENT property (e.g. a property linking to another tree node). Then:
- Click pen icon, confirm the new layout: SQL-loaded select fills the row, Save + Paste icons sit at the right
- Confirm the dropdown lists the expected components (the SQL still runs)
- Pick a component, click Save, confirm the property persists
- Type something in the Selector input, confirm the main select clears (the `onchange`)
- Copy a component to clipboard elsewhere, then click Paste here, confirm the property gets the clipboard component id

- [ ] **Step 3: Commit**

```bash
git add assets/templates/tholosbuilder/propframe.form.COMPONENT.template
git commit -m "Form editors: restyle COMPONENT to .pf-form layout"
```

---

## After all tasks

After Task 4 completes and is verified:

- Announce: "I'm using the finishing-a-development-branch skill to complete this work."
- **REQUIRED SUB-SKILL:** Use `superpowers:finishing-a-development-branch`
- Report no automated tests exist; proceed to the four-option prompt.
