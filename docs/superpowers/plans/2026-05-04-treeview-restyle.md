# Treeview Restyle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the uniform-grey type pill in the component tree with a per-icon-color text badge, give the tree pane a light-gray background, ellipsize long node names without clipping the badge, restyle the right-click context menu to match the Bootstrap-5 chrome, and remove dead `#search_result_tree` / `.folder` / `.file` CSS plus the unreferenced `file_sprite.png`.

**Architecture:** All visual work is CSS — added or modified inside `assets/css/TholosBuilder.css`. Badge coloring relies on a CSS general-sibling selector (`~`) reaching the `.tree_class_name` from the FontAwesome SVG icon, so no JavaScript hook is needed. Ellipsis requires the node label be wrapped in its own span, which is a one-line change to the SQL string in `loadAppTree()`. Right-click menu styling overrides jstree's `.vakata-context*` rules.

**Tech Stack:** Vanilla Bootstrap 5.3.8 (already installed at `vendor/bower-asset/bootstrap`), FontAwesome 6 in SVG mode (already loaded via `assets/js/fontawesome/`), jstree 3 (`vendor/bower-asset/jstree`) with the contextmenu plugin, Eisodos template engine, `assets/css/TholosBuilder.css` for project styling. No PHP test framework — verification is manual in a browser against a live builder instance.

**Spec:** `docs/superpowers/specs/2026-05-04-treeview-restyle-design.md`

**No test framework note:** This codebase has no PHPUnit / no `tests/` directory and no JS test harness. The plan therefore omits automated test steps; each task ends with a manual visual/functional check in the browser, then a commit.

---

## File Structure

| File | Change | Responsibility |
|---|---|---|
| `assets/css/TholosBuilder.css` | Modify (add + replace + delete) | Background change on `#app_tree_container`, replace `.tree_class_name` body, add 14 sibling color rules, add `.jstree-anchor` flex + `.tree_node_label` rules, add `.vakata-context*` overrides, delete dead `#search_result_tree*` and `#app_tree .folder/.file` rules. |
| `src/TholosBuilder/TholosBuilderApplication.php` | Modify (one line) | Wrap node label in `<span class="tree_node_label">…</span>` inside the `loadAppTree()` SQL string so flexbox can ellipsize it. |
| `assets/images/file_sprite.png` | Delete | Unreferenced after the CSS deletions in Task 1. |

Tasks are ordered so each commit leaves the system in a working state:

- Task 1 ships every CSS change in one commit. Between Task 1 and Task 2 the badges are recolored, the pane is gray, the context menu looks right, and the dead rules are gone — but long names don't yet ellipsize because the SQL still emits a raw text node (browsers wrap text-node children of an inline-flex container as anonymous flex items, so the layout still works visually, just without `text-overflow: ellipsis`).
- Task 2 ships the SQL one-line change — ellipsis now works.
- Task 3 deletes the now-unreferenced PNG.

---

### Task 1: CSS overhaul

**Files:**
- Modify: `assets/css/TholosBuilder.css`
  - Change `#app_tree_container` background (currently line 174)
  - Delete `#app_tree .folder` and `#app_tree .file` (currently lines 178–179)
  - Delete `#search_result_tree`, `#search_result_tree .folder`, `#search_result_tree .file` (currently lines 181–183)
  - Replace the body of `.tree_class_name` (currently lines 257–264)
  - Append a new "Treeview restyle" block at end of the file (after the existing `.tb-navbar` block) containing the 14 sibling color rules, the `.jstree-anchor` flex rules, the `.tree_node_label` rule, and the `.vakata-context*` overrides

- [ ] **Step 1: Change `#app_tree_container` background**

In `assets/css/TholosBuilder.css`, locate the `#app_tree_container` rule (currently line 174) and change `background-color: #fff;` to `background-color: #f8f9fa;`. The full rule should read:

```css
#app_tree_container { position: absolute; top: 0; height: 100%; width: 100%; padding: 0px; background-color: #f8f9fa;}
```

- [ ] **Step 2: Delete the dead `#app_tree .folder` / `.file` rules**

In `assets/css/TholosBuilder.css`, delete the two lines that follow `#app_tree`:

```css
#app_tree .folder { background:url('/assets/images/file_sprite.png') right bottom no-repeat; }
#app_tree .file { background:url('/assets/images/file_sprite.png') 0 0 no-repeat; }
```

The `#app_tree` rule on the line before stays. After deletion, the next non-blank line should be the (now also-deleted-in-step-3) `#search_result_tree` rule.

- [ ] **Step 3: Delete the dead `#search_result_tree*` rules**

In `assets/css/TholosBuilder.css`, delete the three consecutive lines:

```css
#search_result_tree { height: 100%; width: 100%; overflow: auto; padding-left: 0px;}
#search_result_tree .folder { background:url('/assets/images/file_sprite.png') right bottom no-repeat; }
#search_result_tree .file { background:url('/assets/images/file_sprite.png') 0 0 no-repeat; }
```

After this deletion, the next block should be the `/* .nav-tabs > li > a {` commented-out block.

- [ ] **Step 4: Replace `.tree_class_name` body**

In `assets/css/TholosBuilder.css`, replace the existing `.tree_class_name` rule (currently lines 257–264) with this stripped-down version (drops white text, grey pill, padding, margin, radius — keeps font size; new layout properties enable flex right-anchoring):

```css
.tree_class_name {
  font-size: 9px;
  flex-shrink: 0;
  margin-left: auto;
}
```

- [ ] **Step 5: Append the new "Treeview restyle" block at end of file**

At the very end of `assets/css/TholosBuilder.css` (after the existing `.tb-navbar` rule block from the previous spec), append:

```css

/* === Treeview restyle ==================================== */

/* Anchor turns into a flex row so the label can ellipsize and the badge stays right-anchored. */
.jstree-anchor {
  display: inline-flex;
  align-items: center;
  min-width: 0;
  max-width: 100%;
}

/* Node label wrapper emitted by loadAppTree() — see TholosBuilderApplication::loadAppTree(). */
.tree_node_label {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Badge text inherits the node icon's color via general-sibling selector — one rule per existing color-* class. */
.jstree-anchor .svg-inline--fa.color-grey       ~ .tree_class_name { color: #c8c8c8; }
.jstree-anchor .svg-inline--fa.color-green      ~ .tree_class_name { color: green; }
.jstree-anchor .svg-inline--fa.color-lightgreen ~ .tree_class_name { color: limegreen; }
.jstree-anchor .svg-inline--fa.color-green3     ~ .tree_class_name { color: mediumseagreen; }
.jstree-anchor .svg-inline--fa.color-blue       ~ .tree_class_name { color: dodgerblue; }
.jstree-anchor .svg-inline--fa.color-lightblue  ~ .tree_class_name { color: deepskyblue; }
.jstree-anchor .svg-inline--fa.color-maroon     ~ .tree_class_name { color: maroon; }
.jstree-anchor .svg-inline--fa.color-purple     ~ .tree_class_name { color: purple; }
.jstree-anchor .svg-inline--fa.color-purple2    ~ .tree_class_name { color: mediumpurple; }
.jstree-anchor .svg-inline--fa.color-brown      ~ .tree_class_name { color: sandybrown; }
.jstree-anchor .svg-inline--fa.color-red        ~ .tree_class_name { color: red; }
.jstree-anchor .svg-inline--fa.color-control    ~ .tree_class_name { color: #ca7841; }
.jstree-anchor .svg-inline--fa.color-grid       ~ .tree_class_name { color: goldenrod; }
.jstree-anchor .svg-inline--fa.color-gridcolumn ~ .tree_class_name { color: darkgoldenrod; }

/* Right-click context menu — match Bootstrap dropdown look. Overrides jstree default theme. */
.vakata-context,
.vakata-context ul {
  background: #fff;
  border: 1px solid rgba(0,0,0,.15);
  border-radius: .375rem;
  box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
  padding: .25rem 0;
  font-size: 12px;
}
.vakata-context li > a {
  padding: .3rem .9rem;
  line-height: 1.5;
  color: #212529;
  text-shadow: none;
}
.vakata-context li > a:hover,
.vakata-context .vakata-context-hover > a {
  background-color: #e9ecef;
  box-shadow: none;
  color: #212529;
}
.vakata-context .vakata-context-separator > a,
.vakata-context .vakata-context-separator > a:hover {
  border-top-color: #dee2e6;
  margin: .25rem 0;
}
.vakata-context .vakata-contextmenu-disabled a,
.vakata-context .vakata-contextmenu-disabled a:hover {
  color: #adb5bd;
}
```

- [ ] **Step 6: Verify visually in the browser**

Hard-reload the running builder app (Cmd-Shift-R / Ctrl-Shift-R to bypass CSS cache). Expected:

1. The component-tree pane background is light gray (`#f8f9fa`), no longer pure white.
2. Type badges on the right of every node are no longer grey pills with white text. They now appear as plain text in the **same color as the node's icon**:
   - Application root → `TApplication` in green
   - `TRoute` items → purple text
   - `TQuery` items → green text
   - `TGridColumn` items → dark-goldenrod text
   - `TGridFilter` items → mediumseagreen text
   - `TDBField` items → limegreen text
   - `TContainer` / `TWidget` items → sandybrown text
3. Long names like `gcSTART_STOP_ENABLED` and `filterSEAT_BELT_PASSENGER` still overflow horizontally — **ellipsis is not yet expected** (Task 2 enables it via the SQL wrap).
4. Right-click any tree node: the context menu now renders with a white background, subtle rounded border, soft shadow, compact rows (~28px tall, not the previous 2.4em ≈ 38px), and a neutral light-grey hover (no blue glow). All entries (Edit, Create child, Run Query/StoredProc/Grid/EditForm wizards) still trigger their actions when clicked.
5. Tree expand/collapse, selection (single click highlights row), and the navbar refresh button still work as before.

- [ ] **Step 7: Commit**

```bash
git add assets/css/TholosBuilder.css
git commit -m "Restyle treeview — pane bg, per-icon badge color, contextmenu look + dead-rule cleanup"
```

---

### Task 2: Wrap node label so flexbox can ellipsize it

**Files:**
- Modify: `src/TholosBuilder/TholosBuilderApplication.php` (one line, around line 652 inside `loadAppTree()`)

- [ ] **Step 1: Edit the SQL string in `loadAppTree()`**

In `src/TholosBuilder/TholosBuilderApplication.php`, locate the `loadAppTree()` method (around line 646). The query string contains this exact line (currently line 652):

```php
"       t.name||'<span class=\"tree_class_name\">'||t.class_name||'</span>' as text, \n" .
```

Replace it with:

```php
"       '<span class=\"tree_node_label\">'||t.name||'</span><span class=\"tree_class_name\">'||t.class_name||'</span>' as text, \n" .
```

The change wraps `t.name` in `<span class="tree_node_label">…</span>`. Everything else in the SQL string is unchanged.

- [ ] **Step 2: Syntax-check the PHP file**

Run:

```bash
php -l src/TholosBuilder/TholosBuilderApplication.php
```

Expected output:

```
No syntax errors detected in src/TholosBuilder/TholosBuilderApplication.php
```

- [ ] **Step 3: Verify visually in the browser**

Hard-reload the builder app and click the navbar refresh button (⟳) so `loadAppTree()` re-runs against the database. Expected:

1. Long node names — for example deeply nested grid columns like `gcSTART_STOP_ENABLED`, `gcLAST_UPDATE_DATE`, `gcSEAT_BELT_PASSENGER`, or grid filters like `filterSERVICE_CARD_NUMBER`, `filterSTART_STOP_ENABLED` — now truncate with `…` instead of overflowing the pane.
2. The colored type badge (e.g. `TGridColumn`, `TGridFilter`) remains visible, anchored to the right edge of each row.
3. Short names render exactly as before — no truncation, badge sitting after the label with one space's worth of margin.
4. Hovering or selecting a node still works; the right-click menu still works.
5. Inspect a long-name node in DevTools and confirm the `<a class="jstree-anchor">` now contains a `<span class="tree_node_label">` wrapping the name, followed by `<span class="tree_class_name">` carrying the type.

- [ ] **Step 4: Commit**

```bash
git add src/TholosBuilder/TholosBuilderApplication.php
git commit -m "Wrap tree node label in <span class=\"tree_node_label\"> for ellipsis layout"
```

---

### Task 3: Delete the unreferenced `file_sprite.png`

**Files:**
- Delete: `assets/images/file_sprite.png`

- [ ] **Step 1: Verify no remaining references**

Run from the repo root:

```bash
grep -rn "file_sprite" /Users/baxi/Work/_tholos_builder/Base 2>/dev/null | grep -v "/vendor/" | grep -v "/\.superpowers/" | grep -v "/docs/superpowers/"
```

Expected: no output (no remaining references in code or CSS).

If any line is returned, STOP and investigate before deleting the PNG.

- [ ] **Step 2: Delete the PNG**

```bash
git rm assets/images/file_sprite.png
```

- [ ] **Step 3: Verify visually in the browser**

Hard-reload the builder app. Expected:

1. The tree still renders correctly; no broken-image icons appear anywhere.
2. The browser DevTools Network tab shows no 404 for `file_sprite.png`.
3. Tree expand/collapse, hover, selection, right-click menu — all unchanged from end of Task 2.

- [ ] **Step 4: Commit**

```bash
git commit -m "Delete unreferenced assets/images/file_sprite.png"
```

(`git rm` already staged the deletion in Step 2, so `git add` is not needed.)

---

## Self-Review Findings

Spec coverage (each section of the spec mapped to a task):

| Spec section | Implemented in |
|---|---|
| §3.1 CSS-only badge coloring | Task 1, Step 5 (the 14 `~ .tree_class_name` rules) |
| §3.2 SQL label wrap for ellipsis | Task 2 |
| §3.3 layout rules (background, .jstree-anchor flex, .tree_node_label, .tree_class_name layout) | Task 1, Steps 1, 4, 5 |
| §3.4 color → badge mapping (14 rules) | Task 1, Step 5 |
| §3.5 context menu restyle | Task 1, Step 5 |
| §3.6 dead-code cleanup | Task 1, Steps 2 & 3; PNG deletion in Task 3 |
| §6 acceptance criteria | Task 1 Step 6 verifies criteria 1, 2, 5 (partially); Task 2 Step 3 verifies criteria 3, 4 (PascalCase preserved naturally — no transform applied); Task 3 Step 1 verifies criterion 6 |

No placeholders, no TBDs, no "implement later". All file paths are absolute, all CSS/PHP code blocks contain the actual final text. The general-sibling selector `~ .tree_class_name` is consistent across Task 1 Step 5 and the spec's §3.1 / §3.4. The wrapper class name `tree_node_label` is consistent between the SQL change in Task 2 Step 1 and the CSS rule in Task 1 Step 5.
