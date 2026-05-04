# Treeview Restyle

**Date:** 2026-05-04
**Branch:** `feature/ui-redesign`
**Status:** Design

---

## 1. Background

The component tree (`#app_tree`) is rendered by jstree's default theme in `small`
variant. Each node displays a FontAwesome icon (color-coded per component type
via classes like `color-green`, `color-purple`, `color-grid`) followed by the
component name and a uniform grey pill (`.tree_class_name`) carrying the PHP
class name (e.g. `TQuery`, `TGridColumn`, `TRoute`).

Three things detract from the current rendering:

1. The grey-on-grey pill carries no type information — every badge looks the same regardless of the node it is on, so the eye gets no extra signal beyond the icon.
2. Long node names (e.g. `gcSTART_STOP_ENABLED`, `filterSEAT_BELT_PASSENGER`) overflow horizontally inside the narrow left pane, pushing the badge off-screen.
3. The pane background is pure white, which fights the navbar's dark gray and gives the rest of the chrome no place to "rest" against.

Plus, dead CSS for an unused `#search_result_tree` and unused `.folder`/`.file`
selectors (and the sprite image they reference) lingers in `TholosBuilder.css`
from a feature that no longer renders results into a jstree.

This spec covers the third sub-project under the broader UI overhaul (after the
foundation+navbar and the login screen). The property editor and the wizards
remain out of scope for follow-up specs on the same branch.

## 2. Scope

**In scope**

- Tree pane background: change from white to light gray.
- Type badge (`.tree_class_name`): drop the pill (white-on-grey rect) and color the text to match its node's icon color. No casing transform — data is already PascalCase.
- Long node labels: truncate with `…` ellipsis while keeping the type badge anchored on the right.
- Dead-code cleanup in `TholosBuilder.css`:
  - `#search_result_tree`, `#search_result_tree .folder`, `#search_result_tree .file` (3 rules).
  - `#app_tree .folder`, `#app_tree .file` (2 rules).
  - Delete `assets/images/file_sprite.png` — no remaining references after the CSS cleanup.

**Out of scope (deferred to later specs on the same branch)**

- Property editor tabs (Properties / Events / Methods / References / Help) and the jQuery UI tab styling.
- All wizards (Query, Stored Procedure, Grid, Edit Form, Commit, Userprofile, Language, Help).
- jstree expand/collapse arrow restyle, jstree selected-node background, jstree hover background.
- Search result rendering (currently posts plain HTML into `#edit_frame > .content` via `searchApp()` — out of scope).

## 3. Architecture

### 3.1 Why CSS-only badge coloring works

FontAwesome 6 in SVG mode replaces `<i class="fa-regular fa-database color-green">`
with an `<svg class="svg-inline--fa fa-database color-green ...">` element. The
existing tree node markup, after FA replacement, looks like:

```html
<a class="jstree-anchor">
  <svg class="svg-inline--fa fa-database color-green ...">…</svg>
  <!-- comment FA injected -->
  qTransactions
  <span class="tree_class_name">TQuery</span>
</a>
```

Because the SVG and the `<span>` are siblings inside the same anchor, CSS's
general-sibling combinator (`~`) lets the badge inherit the icon's color
without any JavaScript hook, post-render walk, or data round-trip:

```css
.jstree-anchor .svg-inline--fa.color-green ~ .tree_class_name { color: green; }
```

One such rule per existing `color-*` class (14 total). All current color-class
declarations stay where they are; we only add the matching badge-color rules.

### 3.2 Why the SQL needs a tiny change for ellipsis

The current `loadAppTree()` query in `TholosBuilderApplication.php:651–652`
emits the node label as a bare text node followed by the badge span:

```sql
"       t.name||'<span class=\"tree_class_name\">'||t.class_name||'</span>' as text, \n"
```

This places three children inside `.jstree-anchor`: the icon SVG, a raw
**text node** with the label, and the badge span. CSS cannot ellipsize a raw
text node in the middle of a flex container without also clipping the badge
on the right. The cleanest fix is to wrap the label in its own span so flexbox
can stretch the label cell, ellipsize it on overflow, and let the badge keep
its intrinsic width pushed to the right:

```sql
"       '<span class=\"tree_node_label\">'||t.name||'</span><span class=\"tree_class_name\">'||t.class_name||'</span>' as text, \n"
```

This is a one-line change inside an existing string literal. No PHP logic,
schema, or jstree config moves.

### 3.3 Layout rules

```css
#app_tree_container { background-color: #f8f9fa; }    /* was #fff */

.jstree-anchor {
  display: inline-flex;
  align-items: center;
  min-width: 0;
  max-width: 100%;
}

.tree_node_label {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tree_class_name {
  flex-shrink: 0;
  margin-left: auto;
  font-size: 9px;
  /* explicitly drop: color: #fff; background-color: #999; padding; border-radius */
}
```

`min-width: 0` on both the anchor and the label is what unlocks the ellipsis
inside a flex item — without it, the flex item refuses to shrink below its
intrinsic content width.

### 3.4 Color → badge mapping (14 rules)

Mirrors the existing `.color-*` declarations in `TholosBuilder.css` (lines
~315–380 originally; positions may shift after this spec's deletions).

| Icon class | Badge color rule |
|---|---|
| `color-grey` | `#c8c8c8` |
| `color-green` | `green` |
| `color-lightgreen` | `limegreen` |
| `color-green3` | `mediumseagreen` |
| `color-blue` | `dodgerblue` |
| `color-lightblue` | `deepskyblue` |
| `color-maroon` | `maroon` |
| `color-purple` | `purple` |
| `color-purple2` | `mediumpurple` |
| `color-brown` | `sandybrown` |
| `color-red` | `red` |
| `color-control` | `#ca7841` |
| `color-grid` | `goldenrod` |
| `color-gridcolumn` | `darkgoldenrod` |

Each rule has the form
`.jstree-anchor .svg-inline--fa.<color-class> ~ .tree_class_name { color: <value>; }`.

### 3.5 Dead-code cleanup

Verified via repo-wide grep at design time:

- `#search_result_tree` selector — 0 references in PHP, JS, templates; 3 references in `TholosBuilder.css` only.
- `#app_tree .folder`, `#app_tree .file` — neither `class="folder"` nor `class="file"` appears anywhere in the rendered tree DOM (jstree uses `jstree-leaf`, `jstree-open`, `jstree-closed` etc.).
- `searchApp()` in `assets/js/TholosBuilder.js:501–521` posts results into `#edit_frame > .content` as raw HTML, confirming no jstree is ever instantiated for search results.
- `assets/images/file_sprite.png` is referenced only by the four rules being deleted. After their removal, the PNG is unreferenced and can be deleted.

## 4. Files changed

| File | Change |
|---|---|
| `src/TholosBuilder/TholosBuilderApplication.php` | In `loadAppTree()` (around line 652), wrap the label expression: change `t.name\|\|'<span class="tree_class_name">'\|\|...` to `'<span class="tree_node_label">'\|\|t.name\|\|'</span><span class="tree_class_name">'\|\|...`. No other PHP changes. |
| `assets/css/TholosBuilder.css` | (a) Change `#app_tree_container` background from `#fff` to `#f8f9fa`. (b) Replace the `.tree_class_name` rule body — keep `font-size: 9px`, drop `color: #fff`, `background-color: #999`, `padding`, `margin-left`, `border-radius`. (c) Add 14 `.jstree-anchor .svg-inline--fa.color-* ~ .tree_class_name { color: …; }` rules. (d) Add `.jstree-anchor` flex rules and the `.tree_node_label` rule. (e) Delete `#search_result_tree`, `#search_result_tree .folder`, `#search_result_tree .file` rules. (f) Delete `#app_tree .folder` and `#app_tree .file` rules. |
| `assets/images/file_sprite.png` | Delete. |
| `assets/js/TholosBuilder.js` | No change. |
| `assets/templates/tholosbuilder/*.template` | No change. |

## 5. Behavior changes

1. **Type badges become readable as type information**, not chrome. Every node now has a colored type label that visually matches its icon, so users can scan the tree by category (queries green, routes purple, dialog containers brown, grid columns dark-goldenrod, etc.) without parsing the text.
2. **Long names no longer overflow.** Names exceeding the column width truncate with `…`; the badge stays aligned right.
3. **Pane background change** is the only purely visual difference; row density, indent, expand/collapse arrows, hover and selected backgrounds, and icon sizes are unchanged.

## 6. Acceptance criteria

1. The tree pane (`#app_tree_container`) renders with a light-gray background (`#f8f9fa`).
2. Every visible node's `.tree_class_name` text uses the same color as that node's FontAwesome icon — verifiable for at least one node per icon color class present in the tree.
3. A node whose label exceeds its container's width (e.g. `filterSEAT_BELT_PASSENGER` under a deeply nested grid filter) renders as `filterSEAT_BELT_PASS…` followed immediately by its `TGridFilter` badge on the right edge.
4. The type label appears in the original PascalCase as stored in the database (e.g. `TQuery`, not `tquery` or `TQUERY`).
5. Right-click context menu still opens with Edit, Create child, and the four wizard entries; selection still works; expand/collapse still works; refresh button still reloads the tree.
6. After the cleanup commit, no template or script references `#search_result_tree`, `#app_tree .folder`, `#app_tree .file`, or `file_sprite.png`. The PNG file is gone from `assets/images/`.
7. No console errors during tree load or interaction.

## 7. Decisions log

- **CSS-only badge coloring vs. JS post-render walk vs. SQL color injection.** Chose CSS-only because FontAwesome's SVG-mode output makes the icon and the badge siblings, so the general-sibling selector reaches the badge with zero JS and zero data churn. JS would have required a hook on `loaded.jstree` and a re-walk on every refresh; SQL injection would have hard-coded the color → class mapping into PHP.
- **Wrap the label in a span (SQL change) vs. CSS-only ellipsis.** A bare text node inside a flex container can't be ellipsized without also clipping later siblings (the badge). The wrap is one tweak to one string literal, and it makes the layout idiomatic flex.
- **No casing transform.** "Not capitalized" was clarified to mean "do not add CSS uppercase". The database stores PascalCase identifiers, so badges naturally render mixed-case (`TQuery`, `TGridColumn`).
- **Delete `file_sprite.png` and the `.folder`/`.file` rules now.** They are unambiguously dead (verified via grep), and we are touching the same `TholosBuilder.css` section anyway. Leaving them would dilute the cleanup commit.
- **Search result tree (`#search_result_tree`) deleted from CSS without further investigation of `searchApp()`.** Verified at design time that `searchApp()` posts HTML into `#edit_frame > .content` and never instantiates jstree on a search result, so the selector has no live consumer.
