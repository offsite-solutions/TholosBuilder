# Query Wizard — Selective Apply of Diff Results

**Date:** 2026-04-29
**Affected function:** `TholosBuilderApplication::QueryWizardRun()` (`src/TholosBuilder/TholosBuilderApplication.php:1892`)
**Affected JS:** `QueryWizardRun()` in `assets/js/TholosBuilder.js:1172`

## Problem

The Query wizard previews structural changes between a component's SQL result and its existing `TDBField` children, then applies all changes when the user clicks "Save changes". There is currently no way to opt out of individual changes — every property with `_status='modify'` is updated and every column with `status='new'` is created.

## Goal

Let the user selectively apply changes from the preview:

- Each property row marked `modify` shows a prechecked checkbox; unchecking skips that property's update.
- Each column with `status='new'` shows a prechecked checkbox in its heading; unchecking skips creation of the column and all of its properties.

Out of scope: `delete`-status columns (still informational, as today), `skip`-status properties (still suppressed by existing `p_skip_label` / DataType compatibility logic), `uptodate` rows.

## Architecture

The diff-rendering code path stays as-is. The result HTML is wrapped in a `<form id="wizardForm">` so the Save button can post the user's selections. Selections are flat-named POST parameters because `Eisodos::$parameterHandler` does not support PHP-style array URL parameters:

- `apply_prop_<linkid>=Y` — emitted only for properties with `_status='modify'`. `<linkid>` is the existing `o_<prop>_LinkId` (the property version primary key).
- `create_col_<index>=Y` — emitted only for columns with `status='new'`. `<index>` is the position of the column in `$o_columns` at render time.

`$o_columns` is rebuilt deterministically from the same SQL inputs on every call to `QueryWizardRun()`, so indices are stable across the preview→save round-trip.

## Wire format

Form submission via `$('#wizardForm').serialize()`. Hidden inputs preserve the existing wizard parameters:

```
action=QueryWizardRun
p_component_id=<id>
p_trans_root=<root>
p_skip_label=<Y|N>
todo=save
apply_prop_<linkid>=Y     (one per checked modify-property)
create_col_<index>=Y      (one per checked new-column)
```

Unchecked checkboxes are simply absent from the submission.

## Render-phase changes

### Template `wizards.query.result.main.template`

Add an empty leading `<th></th>` to the property table header. Add a `$create_checkbox` placeholder in the `<h4>` heading immediately before `$o_fieldname`:

```html
<h4><span class="$status">$create_checkbox$o_fieldname - $status</h4>
<table class="table">
<thead>
  <th></th>
  <th>Property</th>
  <th>Original Value</th>
  <th>New Value</th>
  <th>Status</th>
</thead>
<tbody>
  $properties
</tbody>
</table>
```

### Template `wizards.query.result.property.template`

Add a leading `<td>$apply_checkbox</td>`:

```html
<tr class="$status">
  <td>$apply_checkbox</td>
  <td>$prop_name</td>
  <td>$origvalue</td>
  <td>$value</td>
  <td>$status</td>
</tr>
```

### Template `wizards.query.result.foot.template`

Replace the JS-arg-style button with hidden form fields and a submit handler. Closes the form opened in PHP at the start of the result HTML:

```html
  <input type="hidden" name="action" value="QueryWizardRun">
  <input type="hidden" name="p_component_id" value="$p_component_id">
  <input type="hidden" name="p_trans_root" value="$p_trans_root">
  <input type="hidden" name="p_skip_label" value="$p_skip_label">
  <input type="hidden" name="todo" value="save">
  <div class="row" style="margin-top: 10px;">
    <div class="col-sm-12 text-center">
      <button type="button" class="button" onclick="QueryWizardRunSave();">Save changes</button>
    </div>
  </div>
</form>
```

### PHP — `QueryWizardRun()` preview branch

Around the existing render loop (currently lines 2018–2039), open the form once, iterate with index, build the two checkbox HTML strings, and pass them into the templates:

```php
$responseArray['html'] .= '<form id="wizardForm" onsubmit="return false;">';
foreach ($o_columns as $col_index => $o2) {
  $s = "";
  foreach ($props as $prop) {
    $prop_lc = strtolower($prop);
    $prop_status = $o2["o_" . $prop_lc . "_status"];
    $apply_checkbox = "";
    if ($prop_status === "modify") {
      $linkid = $o2["o_" . $prop_lc . "_linkid"];
      $apply_checkbox = '<input type="checkbox" name="apply_prop_' . $linkid . '" value="Y" checked>';
    }
    $s .= Eisodos::$templateEngine->getTemplate(
      $this->templateFolder . "wizards.query.result.property",
      array(
        "status" => $prop_status,
        "origvalue" => $this->safeHTML(Eisodos::$utils->safe_array_value($o2, "o_" . $prop_lc . "_origvalue")),
        "value" => $this->safeHTML($o2["o_" . $prop_lc]),
        "prop_name" => $prop,
        "apply_checkbox" => $apply_checkbox,
      ),
      false
    );
  }
  $create_checkbox = "";
  if (Eisodos::$utils->safe_array_value($o2, "status") === "new") {
    $create_checkbox = '<input type="checkbox" name="create_col_' . $col_index . '" value="Y" checked> ';
  }
  $responseArray['html'] .= Eisodos::$templateEngine->getTemplate(
    $this->templateFolder . "wizards.query.result.main",
    array_merge($o2, array("properties" => $s, "create_checkbox" => $create_checkbox)),
    false
  );
}
```

The form's closing `</form>` lives in `wizards.query.result.foot.template` (already added above).

### JS — `assets/js/TholosBuilder.js`

Add new helper that posts the form. The existing `QueryWizardRun(p_component_id_, p_trans_root_, p_skip_label_, todo_)` stays unchanged — it remains the entry point for the *initial* preview call from `wizards.query.main.template`'s "Run" button.

```js
function QueryWizardRunSave() {
  showLoading($('#wizard_result'));
  $.ajax({
    url: __TholosBuilderAppUrl,
    type: 'post',
    dataType: 'json',
    data: $('#wizardForm').serialize(),
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#wizard_result').html(data.html);
        loadAppTree('#app_tree');
      } else {
        if (data.html && data.html.length > 0) $('#wizard_result').html(data.html);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}
```

## Save-phase changes

In the `if (Eisodos::$parameterHandler->eq("todo", "save"))` block of `QueryWizardRun()`:

### Gate `new` columns

```php
foreach ($o_columns as $col_index => $o2) {
  $col_status = Eisodos::$utils->safe_array_value($o2, "status", "");
  if ($col_status === "new"
      && Eisodos::$parameterHandler->neq("create_col_" . $col_index, "Y")) {
    continue;
  }
  // ... existing component_insert block runs unchanged for new columns ...
}
```

### Gate `modify` properties

Inside the inner `foreach ($props as $prop)` loop, before the existing `property_insert` / `property_update` call:

```php
if ($o2["o_" . strtolower($prop) . "_status"] == "modify") {
  $linkid = $o2["o_" . strtolower($prop) . "_linkid"];
  if (Eisodos::$parameterHandler->neq("apply_prop_" . $linkid, "Y")) {
    continue;
  }
}
// ... existing property_insert / property_update bind+execute ...
```

`new` properties (those inside a `status='new'` column) need no extra per-property check — they are reached only when the column-level `create_col_<index>` gate has already passed.

## Behavior matrix

| Column status | Property `_status` | Render | Save behavior |
|---|---|---|---|
| `new` | (always `new`) | Column-level checkbox prechecked, no per-property checkbox | Column inserted + all properties inserted iff column checkbox=Y |
| `modify` | `modify` | Per-property checkbox prechecked | Property updated iff per-property checkbox=Y |
| `modify` | `uptodate`, `skip` | No checkbox | Unchanged (matches today) |
| `uptodate` | `uptodate` | No checkbox | Unchanged (matches today) |
| `delete` | (n/a) | No checkbox; existing "must be deleted manually" notice | Unchanged (matches today) |

## Verification

No PHP test suite exists in this project. Verify manually against a live builder instance:

1. Open the Query wizard on a component whose SQL produces both modifications and new columns.
2. Run the wizard → preview shows checkboxes prechecked for `modify` properties and `new` column headings; no checkboxes elsewhere.
3. Uncheck one modify-property and one new-column. Click Save.
4. In the DB / on re-run: the unchecked property's value remains unchanged; the unchecked column was not inserted; all checked items were applied.
5. Re-run the wizard; previously unchecked items reappear as `modify` / `new`, confirming they were not applied.

## Files touched

- `assets/templates/tholosbuilder/wizards.query.result.main.template`
- `assets/templates/tholosbuilder/wizards.query.result.property.template`
- `assets/templates/tholosbuilder/wizards.query.result.foot.template`
- `src/TholosBuilder/TholosBuilderApplication.php` — `QueryWizardRun()` only
- `assets/js/TholosBuilder.js` — add `QueryWizardRunSave()`
