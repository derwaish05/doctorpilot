# 09 — Design System

Scope note: §2 is the plugin-wide palette — every widget, every stylesheet. §§1, 3 and 5 cover the **doctor card token layer** from the premium Doctor Grid / List upgrade. Brand direction, the wider component inventory, motion language and the icon set are still open work (§7).

---

## 1. Principle: controls set tokens, components read tokens

Every visual control on the Doctor Grid / List and Doctor Carousel widgets writes a **CSS custom property on `{{WRAPPER}} .dp-dir`**. No control writes a concrete declaration on a component selector.

```
Elementor control  →  {{WRAPPER}} .dp-dir { --dpc-radius: 12px }
Token default      →  .dp-dir        { --dpc-radius: 16px }
Component          →  .dp-card { border-radius: var(--dpc-radius, 16px) }
```

**The `.dp-dir` in that selector is load-bearing.** The defaults and the presets are declared on the directory root element itself, and a custom property declared on an element always beats one inherited from an ancestor — specificity does not enter into it. A control written to `{{WRAPPER}}` alone reaches `.dp-dir` and is overwritten there before any card can read it, which is exactly the bug fixed in 0.2.1: 46 of 51 card controls did nothing. A new control must target `{{WRAPPER}} .dp-dir`, not `{{WRAPPER}}`.

Three consequences follow, and they are the reason the layer is built this way:

- **Presets cost nothing to add.** A preset is a second class on the same root element that re-points tokens. A control at `{{WRAPPER}} .dp-dir` is (0,2,0) against a preset's (0,1,0), so an explicit choice beats its preset without either side having to know about the other.
- **Two card-level guards deliberately outrank both.** `.dp-dir--preset-overlay .dp-card--no-media` and `.dp-dir--preset-overlay .dp-card--row` re-declare their tokens on the card, which is a descendant of the element the control writes to, so they win. That is intended — the overlay preset's white type is legible only over the photo's scrim — but it means about ten text and secondary-button colour controls are silently inert in those two configurations. If that ever needs to change, the guard should switch to its own fallback token rather than re-declaring the token the control owns.
- **One token drives every consumer.** `--dpc-radius` styles the card, its loading skeleton and its overlay together. There is no way for them to drift apart.
- **An untouched widget still looks finished.** The fallback in the `var()` call is the premium default. "Unstyled" and "default-styled" are the same state.

The token defaults live in `assets/css/directory-premium.css` §1. That file is registered as `doctorpilot-directory-premium` and loaded only through `DoctorGridList::get_style_depends()`, so pages without the widget never download it.

### Where the baseline lives

`templates/card-doctor.php` is shared by the widget, the doctor archive, the search template and the `[doctorpilot_doctors]` shortcode. Only the widget loads the premium sheet. Any class the card emits therefore needs a **floor in `frontend.css`** — without one, a screen-reader-only span renders as visible text and an inline SVG icon falls back to its 300×150 intrinsic size. When adding a card class, add its baseline rule to `frontend.css` in the same change.

---

## 2. Palette

Declared once, on `:root` at the top of `assets/css/frontend.css`. It used to live
on a handful of widget classes, which is why the widgets that were not on that
list — the hero, the timeline, the index block — could not see it and re-invented
each value: before this was consolidated the plugin held **96 distinct colours,
twelve of them muted greys and eight of them hairlines**. Nothing outside `:root`
should be a literal colour, radius or shadow. If a rule needs a value that is not
here, the value is missing from the palette.

### Colour

| Token | Default | Role |
|---|---|---|
| `--dp-accent` | `#136888` | Deep medical teal. Primary actions, links, headings, focus rings. |
| `--dp-accent-hover` | `#0f5670` | The teal, darkened, for hover on filled controls. |
| `--dp-accent-2` | `#16b47f` | The second brand colour: metadata icons, timeline nodes, stat values, the booking button. |
| `--dp-ink` | `#0d3b5c` | Dark navy. Headings, names, body text on light. |
| `--dp-ink-soft` | `#1a4f74` | The ink, lifted — the second stop of a navy gradient. |
| `--dp-ink-muted` | `#5a6f82` | Secondary text: labels, meta lines, organizations. |
| `--dp-on-accent` | `#ffffff` | Text and icons sitting on accent or ink. |
| `--dp-card-bg` | `#ffffff` | Card and panel surface. |
| `--dp-surface` | `#f5f8fb` | Neutral pale surface. |
| `--dp-surface-alt` | `#eef5f9` | Blue-tinted surface: hovers, inset panels, placeholders. |
| `--dp-surface-accent` | `#e9f6f0` | Mint tint. Pairs with `--dp-accent-2` behind an icon. |
| `--dp-line` | `#e3eaf1` | Every hairline: borders, dividers, rules. |
| `--dp-line-strong` | `#c7e2f5` | The heavier blue hairline, on the search band and its controls. |
| `--dp-line-accent` | `#bfe3d5` | The mint rule — the timeline's connector. |
| `--dp-bar-bg` | `--dp-surface-alt` | The search band, shared by the directory toolbar and the index block. An alias, not a colour: both bars read it and nothing else, so they cannot drift apart. The band frames the controls on it rather than competing with them, so it is a tinted surface — never an accent. |
| `--dp-bar-border` | `--dp-line-strong` | That band's edge. |
| `--dp-positive` | `#0f766e` | The International badge. |
| `--dp-danger` | `#b91c1c` | Error text. |
| `--dp-highlight` | `#fffabf` | The pale yellow on the hero's buttons and badge. |

`--dp-primary` still exists, pointed at `--dp-accent`, because saved Elementor CSS
and older templates reference it. Do not add new uses.

### Radius — four steps, nothing between them

| Token | Default | Applies to |
|---|---|---|
| `--dp-radius-sm` | `6px` | A–Z tiles, chips, small controls, focus-ring corners. |
| `--dp-radius` | `12px` | Panels, cards, inputs, list rows. |
| `--dp-radius-lg` | `16px` | Large cards, photos, the booking card. |
| `--dp-radius-pill` | `999px` | Buttons, badges, tags, the search bar and its tabs. |

### Elevation — one ink, three heights

`--dp-shadow-ink` is an RGB triple (`13, 59, 92`) so a rule can pick its own alpha
with `rgba(var(--dp-shadow-ink), 0.3)`. `--dp-shadow-sm` is a resting card,
`--dp-shadow-md` a hover lift, `--dp-shadow-lg` a floating panel or dropdown.

### Motion

`--dp-transition` (`240ms`) is the one duration.

---

## 3. Token groups

**Card box** — `--dpc-bg`, `--dpc-border-width`, `--dpc-border-color`, `--dpc-radius` (16px), `--dpc-pad` (20px), `--dpc-gap` (8px), `--dpc-shadow`, `--dpc-shadow-hover`, `--dpc-transition` (240ms), `--dpc-hover-lift` (-4px), `--dpc-hover-scale`, `--dpc-actions-rule`.

**Media** — `--dpc-media-ratio` (4/5), `--dpc-media-fit`, `--dpc-media-pos` (`center top`), `--dpc-media-bg`, `--dpc-media-radius`, `--dpc-media-zoom`, `--dpc-overlay`, `--dpc-overlay-hover`.

**Text** — `--dpc-name-color`, `--dpc-name-hover`, `--dpc-headline-color`, `--dpc-spec-color`, `--dpc-meta-color`, `--dpc-meta-icon`.

**Badges** — `--dpb-radius`, `--dpb-pad`, `--dpb-gap`, `--dpb-offset-x`, `--dpb-offset-y`, plus `bg`/`color` pairs for `featured`, `intl` and `avail`.

**Buttons** — `--dpbtn-radius`, `--dpbtn-pad`, `--dpbtn-gap`, `--dpbtn-icon-gap`, plus `bg`/`color`/`border` and hover variants for `primary` and `secondary`.

**Control-only tokens** (no default in the token block; the fallback lives at the point of use): `--dpc-row-gap`, `--dpc-col-gap`, `--dpc-list-media-w`, `--dpc-list-media-ratio`, `--dp-clamp`.

---

## 4. Type scale

| Element | Size | Weight | Notes |
|---|---|---|---|
| Doctor name | 18px / 1.3 | 650 | `-0.01em` tracking |
| Headline (job title) | 13.5px / 1.45 | 500 | Optionally line-clamped |
| Specialty / department | 13.5px / 1.4 | 600 | Accent colour |
| Metadata | 13px / 1.45 | 400 | Icon + value rows |
| Button | 13.5px / 1.2 | 600 | |
| Badge | 11.5px / 1.4 | 600 | |

Hierarchy is carried by size and weight before colour. The pre-upgrade card ran name 17px against the credentials and specialty lines both at 13px, which is why it read flat. (Credentials was removed under ADR-0024; the observation is kept because it is why the scale looks the way it does.)

---

## 5. Presets

| Preset | Character |
|---|---|
| `classic` | The pre-upgrade card exactly: 8px radius, no shadow, 14px padding. The restore path. |
| `modern` | **Default.** 16px radius, soft shadow, subtle border, 20px padding, divider above buttons. |
| `elevated` | Borderless, stronger shadow, 6px lift. |
| `minimal` | No surface, no border, no shadow. Spacing and rules only. |
| `overlay` | Full-bleed portrait with a gradient scrim; content sits over the image in white. |
| `compact` | 16:10 media, 12px radius, tighter padding and type. |

Two interactions are constrained deliberately: `overlay` combined with the image-card list falls back to the standard split, because a scrim needs the photo to fill the card and a list needs a fixed media column — the two cannot both hold. And `overlay`'s photoless fallback is dark rather than pale, because white text over the pale default would be unreadable.

---

## 6. Non-negotiables

These are product requirements, not styling preferences, and a change that breaks one is a defect:

- **No layout shift.** The media box carries `aspect-ratio` and the `<img>` carries intrinsic `width`/`height`. Height is reserved before the photo loads and when no photo exists at all.
- **No empty rows.** The card builds its metadata list before printing it and omits the entire `<ul>` when nothing qualifies. The actions row is skipped entirely when neither button applies, so its divider and padding never render around nothing.
- **PHP and JS must agree.** `assets/js/directory.js` hand-mirrors `templates/card-doctor.php`. A field added to one and not the other vanishes on the first AJAX refresh. Presentation flags travel as `data-*` on the widget root (`data-card-classes`, `data-meta-icons`, `data-clamp`, `data-list-style`, `data-badge-avail`) so both renderers read one source.
- **Reduced motion is honoured** regardless of the transition-duration control.
- **Focus is always visible** — a 2px accent outline with 2px offset.
- **The cover link is not a keyboard stop.** Whole-card click is `aria-hidden` with `tabindex="-1"`; the visible name link already reaches the same destination.
- **Contrast targets WCAG 2.2 AA** at the default palette.

---

## 7. Open work

- Brand direction, logo usage, and the wider component inventory beyond the doctor card.
- Toolbar, filter and pagination tokens — these still use hardcoded values in `frontend.css` (`--dp-dir-*` at the `.dp-dir` block) and are not yet control-driven.
- A documented icon set. The card currently ships three inline metadata icons defined twice, in `card-doctor.php` and `directory.js`.
- Motion language beyond the card hover.
- Resolution of the Free/Pro widget boundary (see `12-packaging-and-tiers.md` §9), which determines which of these tokens belong to which tier.
