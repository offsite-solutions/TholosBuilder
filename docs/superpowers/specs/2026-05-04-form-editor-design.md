# Property Form Editors Restyle &mdash; Design Spec

**Date:** 2026-05-04
**Branch:** `feature/ui-redesign`
**Sub-project:** Form editors (sub-project 6 in the UI redesign series)

## Goal

Restyle the inline form-editor templates that the prop editor loads into the value cell when the user clicks the pen "Edit" icon on a property row. Replace the BS3 grid + legacy `btn-default btn-sm btn-xs pull-right` chrome with a tight horizontal flex layout (input grows; small borderless icon buttons sit to its right), matching the visual language already established by the prop-editor `.pf-row-actions` pattern. Keep every existing JavaScript callback, keyup handler, and id intact.

## Scope

**In scope** &mdash; six type-specific form templates loaded by the prop editor's `EditProperty()` flow via `propframe.form.<TYPE>`:

| Template | Type | Main control | Inline buttons | Secondary row |
|---|---|---|---|---|
| `propframe.form.STRING.template` | STRING | `<input>` | Save + "Edit as TEXT" | &mdash; |
| `propframe.form.NUMBER.template` | NUMBER | `<input>` | Save | &mdash; |
| `propframe.form.TEXT.template` | TEXT | `<textarea>` (`textareafullscreen` plugin) | Save | &mdash; |
| `propframe.form.BOOLEAN.template` | BOOLEAN | `<select>` true/false | Save | "Selector:" override input |
| `propframe.form.LIST.template` | LIST | `<select>` of `$options` | Save | "Selector or custom value:" input |
| `propframe.form.COMPONENT.template` | COMPONENT | `<select>` SQL-loaded | Save + Paste | "Selector:" override input |

Plus a small CSS addition to `assets/css/TholosBuilder.css` (`=== Property form editors ===` block) that introduces the `.pf-form`, `.pf-form-btn`, `.pf-form-stack`, and `.pf-form-stack-label` classes.

**Out of scope:**

- `propframe.form.ARRAY.template`, `propframe.form.JSON.template`, `propframe.form.PARAMETER.template` &mdash; these are currently empty (0 lines); the PHP loader presumably falls back to a default rendering. Leaving them empty.
- `propframe.form.title.main.template` &mdash; tooltip HTML for property labels, not a form editor; touching it is a different concern.
- `propframe.form.title.js.template` &mdash; tooltip init script; not a form editor.
- The `<select>` (instead of `</select>`) typo present in BOOLEAN, LIST, and COMPONENT &mdash; explicitly excluded per the brainstorming decision; tracked as a separate cleanup.
- Any change to PHP, AJAX endpoints, or JS callbacks (`saveProperty`, `editProperty`, `clipboardComponentID`, etc.).

## Architecture

Each in-scope template becomes:

```html
<div class="pf-form">
  <input class="form-control form-control-sm"
         id="prop_edit_$p_property_id"
         value="$p_value">
  <button class="btn pf-form-btn"
          id="prop_button_$p_property_id"
          onclick="saveProperty(...)" title="Save">
    <i class="fa-regular fa-check saveIcon"></i>
  </button>
  <!-- additional inline buttons (paste, edit-as-text) follow Save here -->
</div>

<!-- optional secondary row, only for BOOLEAN / LIST / COMPONENT -->
<div class="pf-form pf-form-stack">
  <span class="pf-form-stack-label">Selector or custom value:</span>
  <input class="form-control form-control-sm"
         id="prop_edit_2_$p_property_id"
         value="$p_value2">
</div>

<script type="text/javascript">
$("#prop_edit_$p_property_id").keyup(function (e) {
  if (e.keyCode == 13) $("#prop_button_$p_property_id").click();
});
</script>
```

Key points:

- `.pf-form` is `display: flex; align-items: center; gap: .25rem;` &mdash; the input/select fills available space, buttons sit at the end.
- Buttons use a new `.pf-form-btn` class &mdash; borderless, transparent background, dim grey by default, offsite-blue on hover. Visually consistent with `.pf-row-actions` from the prop editor restyle BUT **not** opacity-gated (the form is the active editing UI; buttons must always be visible).
- The Save check icon keeps its `saveIcon` class (referenced by other JS) and is tinted offsite-green via a `.pf-form .pf-form-btn .saveIcon { color: #88bd21; }` rule so it visually reads as the affirmative action.
- For templates with a "Selector" override input, the second row uses `.pf-form.pf-form-stack` (same flex container reset to `display: block` plus a small grey label).
- `<textarea>` keeps its monospace font (preserved from current inline `style`) but the inline `style` moves into the CSS block.

## Per-template treatment

### STRING

Drops the `<div class="row">` + two `<div class="col-sm-N">` cells. Becomes one `.pf-form` with the input, Save, and "Edit as TEXT" buttons inline. The keyup-Enter `<script>` is preserved verbatim.

### NUMBER

Same as STRING minus the "Edit as TEXT" button.

### TEXT

`.pf-form` with `<textarea>` and Save button. The `<textarea>` keeps `spellcheck="false"`; the monospace font moves to CSS. The `textareafullscreen()` plugin init script is preserved verbatim.

### BOOLEAN

`.pf-form` with the true/false `<select>` and Save button. Below it, `.pf-form.pf-form-stack` with "Selector:" label and the override `<input>`. The `[%_function_name=…_eqs;param=p_value;value=true/false;true=selected;false=%]` Eisodos directives are preserved verbatim. The keyup-Enter script (which targets `#prop_edit_2_$p_property_id`) is preserved verbatim.

### LIST

Same shape as BOOLEAN. The `$options` Eisodos token is preserved (renders the option list). The label reads "Selector or custom value:".

### COMPONENT

`.pf-form` with the SQL-loaded `<select>`, Save, and Paste buttons inline. Below it, `.pf-form.pf-form-stack` with "Selector:" label and the override `<input>` (which has the special `onchange` that clears the main select). The two `<%SQL%>` blocks (the live one and the commented-out one) are preserved byte-for-byte. The Save button's `onclick` uses `prop_edit_2_…` for value and `prop_edit_…` for value2 (opposite of other templates) &mdash; preserved verbatim.

## Shared CSS additions

A new block appended to `assets/css/TholosBuilder.css`:

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

## Brand palette

- Default button color: dim grey `#6c757d`
- Hover button color: offsite-blue `#236499`
- Save check icon: offsite-green `#88bd21` (via the existing `saveIcon` class)
- Stack label: grey `#6c757d`

## Testing

This project has no automated test suite. Verification is manual in the browser:

1. Open the prop editor for a component that has properties of each type (STRING, NUMBER, TEXT, BOOLEAN, LIST, COMPONENT).
2. For each property type, click the pen icon to enter edit mode, confirm the inline form renders with the new flex layout.
3. Confirm the Save button still triggers `saveProperty(...)` and persists the value.
4. For STRING, confirm the "Edit as TEXT" button still triggers `editProperty(..., 'TEXT', ...)`.
5. For COMPONENT, confirm the Paste button still works and the second `<input>`'s `onchange` still clears the main select.
6. For TEXT, confirm Enter inside the textarea behaves correctly and the `textareafullscreen` plugin still attaches.
7. For each Enter-on-input keyup handler, confirm pressing Enter in the input triggers Save.

## Risks &amp; mitigations

| Risk | Mitigation |
|---|---|
| Renaming any `id` would break the Save callback or keyup handler | Preserve every `id` exactly (`prop_edit_$p_property_id`, `prop_button_$p_property_id`, `prop_edit_2_$p_property_id`) |
| Changing `<%SQL%>` or `[%_function_name=…%]` directive bytes would break Eisodos rendering | Preserve every directive verbatim, do not reformat the SQL inside |
| Removing the `saveIcon` class from the Save check icon would break code that targets it | Keep `class="fa-regular fa-check saveIcon"` exactly |
| The `textareafullscreen()` plugin reads inline styles | Preserve the `<textarea>`'s `spellcheck="false"`; the monospace font moves to CSS but stays the same family |
| The COMPONENT template uses opposite value/value2 ordering in `saveProperty()` arguments | Preserve `onclick` arguments byte-for-byte; do not "harmonize" with other templates |
| Inline form is rendered into a property cell &mdash; if a prop-editor row-hover rule (e.g. `.pf-row-actions { opacity: 0 }`) cascades onto `.pf-form-btn`, the buttons will be hidden during edit | Use a different class name (`.pf-form-btn`, not `.pf-row-actions`); the new CSS block does not include any opacity rules |
| Three templates have a `<select>` typo (instead of `</select>`) &mdash; touching the surrounding markup might collide with browser auto-closing behavior | Leave the typo untouched per scope; copy the existing markup as-is including the typo |

## Decomposition into tasks

This spec maps to four tasks:

1. Add `=== Property form editors ===` CSS block to `TholosBuilder.css`
2. Restyle the simple two templates: STRING + NUMBER
3. Restyle TEXT + the two single-select templates: BOOLEAN + LIST
4. Restyle COMPONENT (most complex; SQL-loaded select + paste button + selector override)

Each task is one or more template edits + visual verification + commit. The implementation plan document expands these into bite-sized steps with full code.
