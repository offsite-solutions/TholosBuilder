# Query Wizard Selective Apply Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add prechecked checkboxes to the Query wizard preview so the user can opt out of individual `modify` properties and `new` columns before clicking Save changes.

**Architecture:** Wrap the wizard result in a `<form id="wizardForm">`. Emit `apply_prop_<linkid>` checkboxes for `modify` properties and `create_col_<index>` checkboxes for `new` columns (flat names because `Eisodos::$parameterHandler` doesn't decode array URL params). The Save button now serializes the form via a new JS helper. The save branch in PHP gates each `modify` property and each `new` column on the corresponding parameter being `Y`.

**Tech Stack:** PHP 8 (Tholos builder app), jQuery (`assets/js/TholosBuilder.js`), Eisodos template engine (`.template` files in `assets/templates/tholosbuilder/`). No PHP test framework — verification is manual against a live builder instance.

**Spec:** `docs/superpowers/specs/2026-04-29-querywizard-selective-apply-design.md`

**No test framework note:** This codebase has no PHPUnit / no `tests/` directory. The plan therefore omits unit-test steps; verification is captured in Task 4 as manual checks against a running instance. Each code task ends with a syntax check (`php -l` for PHP) and a commit.

---

## File Structure

| File | Change | Responsibility |
|---|---|---|
| `assets/js/TholosBuilder.js` | Modify | Add `QueryWizardRunSave()` helper that posts `#wizardForm`. |
| `assets/templates/tholosbuilder/wizards.query.result.main.template` | Modify | Add `$create_checkbox` to heading + leading empty `<th></th>` to property table. |
| `assets/templates/tholosbuilder/wizards.query.result.property.template` | Modify | Add leading `<td>$apply_checkbox</td>` to each property row. |
| `assets/templates/tholosbuilder/wizards.query.result.foot.template` | Modify | Replace button + add hidden form fields + `</form>` close tag. |
| `src/TholosBuilder/TholosBuilderApplication.php` | Modify | `QueryWizardRun()`: open `<form>` before render loop, emit checkbox HTML, gate save iteration on flat-named params. |

Tasks are ordered so each commit leaves the system in a consistent state:
- Task 1 adds the JS function that the new template will call (harmless until referenced).
- Task 2 ships the templates + PHP render branch atomically (so placeholders never render literally).
- Task 3 adds the save-side gates that make the checkboxes functionally meaningful.
- Task 4 is the manual verification checklist.

---

## Task 1: Add `QueryWizardRunSave()` JS helper

**Files:**
- Modify: `assets/js/TholosBuilder.js` (insert after the existing `QueryWizardRun` function, currently ending at line 1194)

- [ ] **Step 1: Read the existing `QueryWizardRun` function for reference**

Open `assets/js/TholosBuilder.js` and locate `QueryWizardRun(p_component_id_, p_trans_root_, p_skip_label_, todo_)` at line 1172. Confirm the surrounding pattern (showLoading → $.ajax → success/error). The new helper mirrors the structure but reads its data from `$('#wizardForm').serialize()` and always reloads the app tree on success (because the only caller is the post-save branch).

- [ ] **Step 2: Insert the new function**

Insert this function immediately after the closing `}` of `QueryWizardRun` (after the existing line 1194) and before `function showStoredProcedureWizard` (current line 1196):

```javascript
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

- [ ] **Step 3: Sanity-check the JS file loads**

Run: `node --check assets/js/TholosBuilder.js`
Expected: command exits 0 with no output. If `node` is unavailable, instead open the builder UI in a browser, run any wizard once, and confirm the dev console shows no `SyntaxError` from this file. (Functional behavior unchanged at this point; only a parse check.)

- [ ] **Step 4: Commit**

```bash
git add assets/js/TholosBuilder.js
git commit -m "Add QueryWizardRunSave JS helper for wizard form submission"
```

---

## Task 2: Update result templates and PHP render branch

This task atomically ships the three template changes and the PHP-side checkbox emission so placeholders never render literally.

**Files:**
- Modify: `assets/templates/tholosbuilder/wizards.query.result.main.template`
- Modify: `assets/templates/tholosbuilder/wizards.query.result.property.template`
- Modify: `assets/templates/tholosbuilder/wizards.query.result.foot.template`
- Modify: `src/TholosBuilder/TholosBuilderApplication.php` (`QueryWizardRun()`, currently lines 1892–2146; the render loop is around lines 2018–2039)

- [ ] **Step 1: Update `wizards.query.result.main.template`**

Replace the entire file with:

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

Changes vs. current: added `$create_checkbox` placeholder before `$o_fieldname`, and added a leading empty `<th></th>` to the table header so the property rows' new leading `<td>` aligns.

- [ ] **Step 2: Update `wizards.query.result.property.template`**

Replace the entire file with:

```html
<tr class="$status">
  <td>$apply_checkbox</td>
  <td>$prop_name</td>
  <td>$origvalue</td>
  <td>$value</td>
  <td>$status</td>
</tr>
```

Changes vs. current: added a leading `<td>$apply_checkbox</td>`.

- [ ] **Step 3: Update `wizards.query.result.foot.template`**

Replace the entire file with:

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

Changes vs. current: added five hidden inputs that reproduce the parameters previously passed inline to `QueryWizardRun()`; changed the button's `onclick` from `QueryWizardRun($p_component_id,'$p_trans_root','$p_skip_label','save');` to `QueryWizardRunSave();`; added `</form>` to close the form opened by PHP in Step 4.

- [ ] **Step 4: Update `QueryWizardRun()` render branch in PHP**

Open `src/TholosBuilder/TholosBuilderApplication.php` and locate the rendering loop in `QueryWizardRun()` (currently lines 2018–2039), which begins with:

```php
foreach ($o_columns as $o2) {
  $s = "";
  foreach ($props as $prop) {
    $s .= Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.query.result.property",
      array("status" => $o2["o_" . strtolower($prop) . "_status"],
        "origvalue" => $this->safeHTML(Eisodos::$utils->safe_array_value($o2, "o_" . strtolower($prop) . "_origvalue")),
        "value" => $this->safeHTML($o2["o_" . strtolower($prop)]),
        "prop_name" => $prop),
      false);
  }
  $responseArray['html'] .= Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.query.result.main",
    array_merge($o2, array("properties" => $s)),
    false);
}
```

Replace that block (the entire `foreach ($o_columns as $o2) { ... }` loop including its body) with:

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

Note the indentation (8 spaces) matches the surrounding method body. Key edits inside the loop:
- Added `$col_index` to the `foreach`.
- Computed `$prop_lc` once per iteration to avoid five `strtolower()` calls per row.
- Built `$apply_checkbox` only for `modify` rows; empty string otherwise (always passed so the placeholder is never literal).
- Built `$create_checkbox` only for `new` columns; empty string otherwise (always passed via `array_merge`).
- Prepended `<form id="wizardForm" onsubmit="return false;">` once before the loop. The matching `</form>` lives in `wizards.query.result.foot.template` (Step 3).

- [ ] **Step 5: Lint the PHP file**

Run: `php -l src/TholosBuilder/TholosBuilderApplication.php`
Expected output: `No syntax errors detected in src/TholosBuilder/TholosBuilderApplication.php`

- [ ] **Step 6: Smoke-test the preview render**

Open the builder in a browser. Pick any component that produces both modifications and a new column when the Query wizard runs (or any component at all — checkboxes only appear on modify/new rows; absence elsewhere is also useful to confirm). Open the Query wizard, run it.

Expected:
- Each `modify` property row has a checkbox in the leading cell, prechecked.
- Each `new` column has a checkbox in its `<h4>` heading immediately before the field name, prechecked.
- All other rows have an empty leading cell; non-`new` column headings show no checkbox.
- The "Save changes" button is present at the bottom; clicking it submits the form (verify in browser dev tools' network tab — the request body contains `apply_prop_*=Y` and/or `create_col_*=Y` keys for checked items). The save itself still applies everything for now (Task 3 fixes that); just confirm the request payload reaches the server.
- No literal `$apply_checkbox` or `$create_checkbox` text appears in the rendered page.

If literal `$apply_checkbox` or `$create_checkbox` text appears: the Eisodos template engine's parameter caching or namespacing differs from expectation. Switch to passing both keys as scalar values (already done) and clear any template cache (`_templateCache` in `TemplateEngine`). Re-run.

- [ ] **Step 7: Commit**

```bash
git add assets/templates/tholosbuilder/wizards.query.result.main.template \
  assets/templates/tholosbuilder/wizards.query.result.property.template \
  assets/templates/tholosbuilder/wizards.query.result.foot.template \
  src/TholosBuilder/TholosBuilderApplication.php
git commit -m "Render Query wizard preview inside form with per-change checkboxes"
```

---

## Task 3: Gate save-branch updates on the new flat parameters

After this task the wizard fully respects the user's selection: unchecked items are skipped on save.

**Files:**
- Modify: `src/TholosBuilder/TholosBuilderApplication.php` (`QueryWizardRun()`, save branch — currently lines 2041–2122; the outer save-iteration `foreach ($o_columns as $o2)` is around line 2050; the inner property loop is around line 2089)

- [ ] **Step 1: Add `$col_index` to the save-branch outer loop and gate `new` columns**

Locate this block inside the `if (Eisodos::$parameterHandler->eq("todo", "save"))` branch (currently around line 2050):

```php
          foreach ($o_columns as $o2) {
            if (Eisodos::$utils->safe_array_value($o2, "status", "") == "new") { // create new component
```

Replace with:

```php
          foreach ($o_columns as $col_index => $o2) {
            $col_status = Eisodos::$utils->safe_array_value($o2, "status", "");

            if ($col_status === "new"
                && Eisodos::$parameterHandler->neq("create_col_" . $col_index, "Y")) {
              continue;
            }

            if ($col_status == "new") { // create new component
```

This adds the `$col_index` to the iteration, computes `$col_status` once, and short-circuits the iteration with `continue` if the column is `new` and the user unchecked its `create_col_<index>` checkbox. The next `if ($col_status == "new")` line remains the existing branch that performs `component_insert`.

Note: do **not** rename the existing `$o2["status"]` reads inside the `new`-column body to `$col_status`. The existing code lower in the loop also uses `Eisodos::$utils->safe_array_value($o2, "status", "")` (e.g., `if (... != "delete")` around line 2087). Leave those untouched — they keep reading from `$o2` directly. Only the two new conditions added above use the cached `$col_status`.

- [ ] **Step 2: Gate `modify` properties on the per-row checkbox**

Locate the inner property loop inside the same save branch (currently around line 2089):

```php
              foreach ($props as $prop) {
                
                if ($o2["o_" . strtolower($prop) . "_status"] == "modify" || $o2["o_" . strtolower($prop) . "_status"] == "new") {
                  
                  $boundVariables = [];
```

Replace with:

```php
              foreach ($props as $prop) {

                $prop_status = $o2["o_" . strtolower($prop) . "_status"];

                if ($prop_status == "modify" || $prop_status == "new") {

                  if ($prop_status == "modify") {
                    $linkid = $o2["o_" . strtolower($prop) . "_linkid"];
                    if (Eisodos::$parameterHandler->neq("apply_prop_" . $linkid, "Y")) {
                      continue;
                    }
                  }

                  $boundVariables = [];
```

This caches `$prop_status` once per inner iteration (cleaner than three string-builds), and adds the `apply_prop_<linkid>` gate only for `modify` rows. `new` rows remain unconditional within their column because the outer `create_col_<index>` gate has already ensured the user wants the column.

- [ ] **Step 3: Lint the PHP file**

Run: `php -l src/TholosBuilder/TholosBuilderApplication.php`
Expected output: `No syntax errors detected in src/TholosBuilder/TholosBuilderApplication.php`

- [ ] **Step 4: Smoke-test the save flow**

In the browser, run the Query wizard on a component that produces at least one `modify` property and one `new` column.

a. Leave all checkboxes prechecked. Click Save changes. Verify (via DB inspection or by re-running the wizard) that all changes were applied — the post-save re-run should show all rows as `uptodate`.

b. Reset the component (or choose a fresh one). Run the wizard. Uncheck one `modify` property's checkbox and one `new` column's checkbox. Click Save changes. Verify (via DB inspection or by re-running the wizard):
- The unchecked `modify` property's value is **unchanged** in the DB; re-running the wizard shows the same property still flagged `modify`.
- The unchecked `new` column was **not** inserted; re-running the wizard shows the same column still flagged `new`.
- Every other checked item was applied — the corresponding rows show `uptodate` on re-run.

- [ ] **Step 5: Commit**

```bash
git add src/TholosBuilder/TholosBuilderApplication.php
git commit -m "Honor per-property and per-column selection on Query wizard save"
```

---

## Task 4: Final manual verification checklist

This task adds no code — it confirms the user-facing behavior matches the spec end-to-end before declaring the feature done. Walk through every row and tick each box.

**Files:** none.

- [ ] **Verification 1: Preview render — visual sanity**

Open Query wizard. Confirm:
- `modify` rows: checkbox prechecked in leading cell.
- `new` columns: checkbox prechecked in heading, before the field name.
- `uptodate` / `skip` / `delete` rows / non-`new` column headings: no checkbox, leading cell empty.

- [ ] **Verification 2: Save with all default-checked**

Run wizard. Save without unchecking anything. All `modify` and `new` items applied; re-running the wizard shows everything `uptodate`.

- [ ] **Verification 3: Save with one `modify` unchecked**

Run wizard against a component with a `modify` row. Uncheck exactly one `modify` checkbox. Save. Re-run wizard. Confirm: the unchecked property still shows as `modify`; all other modify rows applied.

- [ ] **Verification 4: Save with one `new` column unchecked**

Run wizard against a component with a `new` column. Uncheck exactly one `new` column's heading checkbox. Save. Re-run wizard. Confirm: the unchecked column still shows as `new` (not inserted); other `new` columns were inserted (now show `uptodate`).

- [ ] **Verification 5: Save with everything unchecked**

Run wizard. Uncheck every checkbox. Save. Confirm: response shows the empty/up-to-date message (the existing fallback `"All components and properties are up to date."` text or similar); no DB changes occurred. Re-run wizard: the diff state is unchanged from before the save.

- [ ] **Verification 6: `delete` and `skip` are still informational only**

If your test component produces a `delete`-status column (column exists in DB but not in the SQL output) or `skip`-status property (e.g., a `Label` change with `p_skip_label=Y`), confirm the existing behavior is unchanged: `delete` columns still show the "must be deleted manually" notice on save; `skip` properties remain unchanged. Neither has a checkbox.

---

## Self-Review

**Spec coverage:**
- Goal/Problem/Architecture: covered by Tasks 1–3 collectively.
- Wire format (`apply_prop_<linkid>`, `create_col_<index>`, hidden inputs): Tasks 1 (helper) + 2 (templates + PHP render).
- Render-phase template changes: Task 2 Steps 1–3.
- Render-phase PHP changes: Task 2 Step 4.
- Save-phase PHP changes: Task 3 Steps 1–2.
- Behavior matrix (status combinations): Task 4 Verifications 1, 3, 4, 6.
- Verification section in spec: Task 3 Step 4 (smoke test) and Task 4 (full checklist).
- "Files touched" list in spec: matches the `File Structure` table above.

**Placeholder scan:** No "TBD"/"TODO"/"implement later"/"appropriate error handling" present. Each step contains exact code or exact commands.

**Type/name consistency:**
- Param naming `apply_prop_<linkid>` and `create_col_<index>` consistent across templates, JS, render PHP, save PHP.
- `QueryWizardRunSave` (no args) is the only caller name; foot template's `onclick` references it.
- `$col_index` introduced in Task 2 render branch is mirrored in Task 3 save branch.
- `$col_status` and `$prop_status` are local caches introduced in Task 3, consistent within their scopes.
- Existing identifiers untouched: `QueryWizardRun` (initial preview entry), `o_<prop>_linkid` (linkid lookup), `Eisodos::$parameterHandler->eq/neq/getParam`, `Eisodos::$utils->safe_array_value`.

No issues found.
