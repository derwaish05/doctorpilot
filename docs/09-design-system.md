# 09 — Design System

Scope note: this document currently covers the **doctor card design token layer** introduced with the premium Doctor Grid / List upgrade. Brand direction, the wider component inventory, motion language, and the icon set are not yet documented here; they are tracked as open work in §7.

---

## 1. Principle: controls set tokens, components read tokens

Every visual control on the Doctor Grid / List widget writes a **CSS custom property on `{{WRAPPER}}`**. No control writes a concrete declaration on a component selector.

```
Elementor control  →  {{WRAPPER}} { --dpc-radius: 12px }
Component          →  .dp-card { border-radius: var(--dpc-radius, 16px) }
```

Three consequences follow, and they are the reason the layer is built this way:

- **Presets cost nothing to add.** A preset is a class that re-points tokens. It never has to out-specify a control, because a control is written at the wrapper and a preset at a class.
- **One token drives every consumer.** `--dpc-radius` styles the card, its loading skeleton and its overlay together. There is no way for them to drift apart.
- **An untouched widget still looks finished.** The fallback in the `var()` call is the premium default. "Unstyled" and "default-styled" are the same state.

The token defaults live in `assets/css/directory-premium.css` §1. That file is registered as `doctorpilot-directory-premium` and loaded only through `DoctorGridList::get_style_depends()`, so pages without the widget never download it.

### Where the baseline lives

`templates/card-doctor.php` is shared by the widget, the doctor archive, the search template and the `[doctorpilot_doctors]` shortcode. Only the widget loads the premium sheet. Any class the card emits therefore needs a **floor in `frontend.css`** — without one, a screen-reader-only span renders as visible text and an inline SVG icon falls back to its 300×150 intrinsic size. When adding a card class, add its baseline rule to `frontend.css` in the same change.

---

## 2. Palette

| Token | Default | Role |
|---|---|---|
| `--dp-accent` | `#136888` | Deep medical teal. Primary actions, links, specialty text, metadata icons. |
| `--dp-ink` | `#0b2942` | Dark navy. Doctor names, metadata labels. |
| `--dp-ink-muted` | `#5a6f82` | Credentials, headline. |
| `--dp-line` | `#e3eaf1` | Card borders, dividers. |
| `--dp-surface` | `#f5f8fb` | Soft blue-grey page surface. |
| `--dp-card-bg` | `#ffffff` | Card surface. |
| `--dp-positive` | `#0f766e` | "Accepting new patients". |

`--dp-accent` matches the pre-existing `--dp-primary` in `frontend.css` so the card layer does not introduce a second, slightly different teal.

---

## 3. Token groups

**Card box** — `--dpc-bg`, `--dpc-border-width`, `--dpc-border-color`, `--dpc-radius` (16px), `--dpc-pad` (20px), `--dpc-gap` (8px), `--dpc-shadow`, `--dpc-shadow-hover`, `--dpc-transition` (240ms), `--dpc-hover-lift` (-4px), `--dpc-hover-scale`, `--dpc-actions-rule`.

**Media** — `--dpc-media-ratio` (4/5), `--dpc-media-fit`, `--dpc-media-pos` (`center top`), `--dpc-media-bg`, `--dpc-media-radius`, `--dpc-media-zoom`, `--dpc-overlay`, `--dpc-overlay-hover`.

**Text** — `--dpc-name-color`, `--dpc-name-hover`, `--dpc-headline-color`, `--dpc-cred-color`, `--dpc-spec-color`, `--dpc-meta-color`, `--dpc-meta-icon`.

**Badges** — `--dpb-radius`, `--dpb-pad`, `--dpb-gap`, `--dpb-offset-x`, `--dpb-offset-y`, plus `bg`/`color` pairs for `featured`, `intl` and `avail`.

**Buttons** — `--dpbtn-radius`, `--dpbtn-pad`, `--dpbtn-gap`, `--dpbtn-icon-gap`, plus `bg`/`color`/`border` and hover variants for `primary` and `secondary`.

**Control-only tokens** (no default in the token block; the fallback lives at the point of use): `--dpc-row-gap`, `--dpc-col-gap`, `--dpc-list-media-w`, `--dpc-list-media-ratio`, `--dp-clamp`.

---

## 4. Type scale

| Element | Size | Weight | Notes |
|---|---|---|---|
| Doctor name | 18px / 1.3 | 650 | `-0.01em` tracking |
| Headline (job title) | 13.5px / 1.45 | 500 | Optionally line-clamped |
| Credentials | 12.5px | 500 | Uppercase, `0.03em` tracking |
| Specialty / department | 13.5px / 1.4 | 600 | Accent colour |
| Metadata | 13px / 1.45 | 400 | Icon + value rows |
| Button | 13.5px / 1.2 | 600 | |
| Badge | 11.5px / 1.4 | 600 | |

Hierarchy is carried by size and weight before colour. The pre-upgrade card ran name 17px against credentials and specialty both at 13px, which is why it read flat.

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
