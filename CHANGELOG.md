# Changelog

All notable changes to DoctorPilot are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/) and the project uses semantic
versioning.

## [Unreleased]

### Added
- **Upcoming visit dates for international doctors.** The doctor editor's
  Listing flags box takes **Upcoming visit dates** — free text such as
  *16-17-18 May, 2025* — under the International doctor checkbox. When that
  checkbox is on, the Page Header shows the dates as an **Upcoming Visit** chip
  above the buttons, with its own label, icon, typography and color controls
  (Style → Upcoming visit). The header is not restyled for international
  doctors: it looks like every other doctor's, and the buttons keep the styling
  from the Buttons section. A doctor without the flag is not affected, and a
  flagged doctor with no dates gets no chip, never an empty label.
- **Button links on the Page Header and the Doctor Grid / List.** The Page
  Header's Book and Call buttons each take an optional link, and the Doctor Grid
  / List's Booking button takes one in its Card elements section, under the
  Booking button switch. Left blank, nothing changes: Book goes to the doctor's
  booking destination, Call dials their phone number, and a card's Booking
  button goes wherever that doctor's booking method points. Set a link and the
  button goes there instead — an appointment form page, a `tel:` number, a page
  anchor — with the usual open-in-new-window and nofollow switches. A button
  with a link set shows even for a doctor who has no booking method or phone
  number, which is the point: the page decides. Links go through the same
  scheme check as booking destinations, so an unsafe one is dropped rather than
  rendered, and cards the grid rebuilds after a filter change keep the link the
  page rendered with.
- **`/doctors/` is now a working search page on its own.** The doctor archive
  renders the same search bar, tabs, A–Z row and filtered grid the Doctor Grid /
  List widget renders — through the same assembly, not a copy of it — so a link
  like `/doctors/?department=cardiology` from the search index block (or from
  anywhere else) lands on real, filtered results with no page to build and no
  Elementor involved. A department, specialty or symptom archive
  (`/doctor-department/cardiology/`) bridges its own term into the matching
  filter, so the two URLs agree.

  A site that would rather design its own search page still can: set it as the
  Search page in DoctorPilot → Settings and every link points there instead.
  With nothing set, links now fall back to the archive rather than back to the
  page they were clicked on.
- **Society memberships, as a third group on the Certifications & Publications
  widget.** A membership record holds the society, a role or grade, a year and
  an optional link, and renders in the widget's existing list markup:
  *Royal College of Radiologists — Fellow · 2015*. The group has its own Show
  switch, title, icon (with the library dropdown and pixfort picker like the
  other two), and switches for the role-and-year line and the link.

  Memberships work the way certifications and publications do — both ways. A new
  **Society Memberships** library sits under Library in the admin, so a society
  is entered once and assigned to any number of doctors, and each doctor's
  Certifications & Publications tab also takes a list of their own. Assigned
  records render first, then the doctor's own. Unpublished or deleted library
  records are skipped rather than breaking the list, and the type is registered
  for WPML like the rest of the library.

  The group's Show switch defaults to on, like the other two, so a widget
  already on a page picks the section up as soon as that doctor has a
  membership — and shows nothing at all until then. Turn it off per widget to
  keep a placement to certifications and publications only. The no-Elementor
  profile template lists memberships too, under its own heading (its
  Certifications heading used to promise memberships and show none).

  page.** Search box, tabs (By Doctor Name / Department / Specialty / Symptom),
  an A–Z row and multi-column lists of names and terms — and no results grid.
  It reuses the directory's own classes, so it inherits that styling, and it has
  its own Search bar / A–Z bar / Entries style sections driving CSS custom
  properties.

  Every entry is a real link rendered server-side, so the block works with
  JavaScript off: a doctor goes to their profile, a department, specialty or
  symptom goes to the search page carrying `?department=…` — the same parameters
  `SearchRequestFactory` reads, so the landing page filters as if the visitor had
  picked the term there. JavaScript only switches tabs, hides rows as you type or
  pick a letter, and adds the suggestion dropdown.

- **Suggestions under the search box.** Typing queries the existing
  `/doctors/suggest` route and groups the matches by doctor, department,
  specialty and symptom, each with a "View all" link. Arrow keys move through
  them, Enter opens one, Escape closes. Suggestions gained a `slug` and a `url`
  so a match can be linked at all — a doctor suggestion previously returned only
  its name twice.

- **A Search page setting** (DoctorPilot → Settings → General) naming the page
  the index links to, with a per-widget override for a block that should point
  somewhere else. With neither set, entries link to the page they are on and the
  Elementor panel says so.

### Changed
- **One palette for the whole plugin.** The stylesheets had grown widget by
  widget into 96 distinct colours — twelve different greys for secondary text,
  eight for a hairline, seventeen pale surfaces, five card radii and three
  unrelated shadow inks. Every one of them now resolves to a single token set
  declared on `:root`, and there is no longer a colour, radius or shadow literal
  anywhere in `frontend.css`, `directory-premium.css` or `carousel.css`.

  The palette sat on a handful of widget classes before, which is why the hero,
  the timeline and the search block could not see it and re-invented each value.
  Widget tokens (`--dpc-*`, `--dpi-*`, `--dp-dir-*`, `--dpbtn-*`, `--dpcar-*`)
  keep their names — the Elementor style controls write to them — but every
  default now points at the palette, as do the widget control defaults, so a
  colour set in one place reaches every widget that uses it.

  Visible changes where a value was an outlier: the Featured badge is navy
  rather than royal blue on archive pages, secondary text is one grey
  throughout, the directory toolbar takes the same blue band as the search
  block, borders are one hairline, and a few radii move a step onto the scale.
  Anything set by hand in Elementor is untouched.

- **Three contrast and state bugs the sweep surfaced.** The Heading Block's
  subheading was pale yellow on white — about 1.1:1, invisible. The hero's ghost
  button had a solid yellow fill, which is not a ghost button; it is now
  outlined, with readable text on the banner. And a focus ring in the directory
  sat at a 3px offset where every other ring in the plugin uses 2px.

- **"Apply filters from the page URL" on the Doctor Grid / List widget.** A grid
  that shows its own search bar or filter panel has always read the query
  string. One with neither — a results-only grid, which is what a page pairing
  the new index block with a plain list of results wants — ignored it, so a link
  carrying `?department=cardiology` landed on an unfiltered list. The new switch
  turns that on. It only appears when the search bar is off, and it defaults off,
  so no page already built changes: a fixed list stays fixed.

- Index building moved into a `BrowseIndex` trait — the A–Z bucketing that
  strips honorifics, the 500-entry cap, the term and doctor catalogues. The
  Doctor Grid / List widget still carries its own copies; the trait documents
  that the two must be merged rather than edited in parallel, and it already
  takes the sort argument that merge will need.

## [0.7.0] - 2026-08-24

### Added
- **Icon sizes default to 1em.** Every icon-size slider — Certifications,
  Certifications & Publications, Doctor Facts, Specialties, the achievement
  lists, the Experience & Education checklist, the Booking Card's button icons
  and the Page Header's chip and button icons — now starts at `1em` and offers
  em and rem alongside px, so an icon tracks the text beside it instead of
  sitting at a fixed pixel size. Each one sets width and height as well as
  font-size, so inline SVGs (pixfort's included) and webfont glyphs land on the
  same size.

  Icons on already-built pages change size only where nobody set one: the sliders
  that used to default to a fixed size (Page Header 16px and 20px, Booking Card
  20px, Specialties 18px) now follow their text. The achievement list's icon
  floor in `frontend.css` moved from 18px to 1em to match.

- **Default colours for the widget text and headings.** Every heading control
  now starts on the brand teal `#136888` — the built-in widget Heading, the
  Heading Block's heading, and each widget's section or group title
  (Certifications, Publications, the achievement lists, the stat block, the
  profile section headings). Every body text control starts on black `#000000` —
  the biography, item names and organizations, meta and description lines, fact
  labels and values, timeline entries, checklist items, specialty labels and the
  Heading Block's description.

  The heading default matches what the stylesheet already paints, so headings do
  not move. The text defaults do change what a page shows: the greys and teals
  that the stylesheet used for body copy become black wherever an author has not
  set a colour by hand. Anything set by hand is untouched.

  Left as they were: the Page Header hero and the Booking Card, whose text is
  white on a dark banner; the doctor directory and carousel cards, which have
  their own colour-token layer; and accent elements — icons, timeline nodes,
  stat values, the Heading Block's subheading — which keep their accent.

- **Item styling on the Certifications & Publications widget.** A new **Items**
  style section turns each certification and publication row into a box you can
  design: padding, border radius, the gap after the icon, icon alignment,
  transition duration, and Background, Border and Box-shadow across Normal and
  Hover tabs. One section covers both groups, the way the widget's Icon and Text
  sections already do.

  "Gap between items" moved out of the Layout section into this one. It keeps its
  control ID, so whatever is already set stays set, and no page changes: every
  new control starts empty, which is the plain list the widget renders today.

## [0.6.0] - 2026-08-24

### Added
- **pixfort icons on every icon option.** All twenty icon controls across the
  widgets — Page Header's book, call and certification icons, Booking Card's
  three, Doctor Facts' per-row icons, Certifications, Certifications &
  Publications, Specialties, the achievement lists and Experience & Education —
  now start with a small **library dropdown**: *Elementor icon library* or
  *pixfort icon*. Choose pixfort and the panel swaps the Elementor picker for
  pixfort's own, with its Line, Duotone and Solid tabs and search; switch back
  and the Font Awesome icon you had returns untouched, because the two are
  separate settings and neither overwrites the other.

  Nothing already built changes: a page saved before this release has no library
  choice stored, which resolves to the Elementor picker it is already using. The
  dropdown appears only while `pixfort-core` is active — with one library there
  is nothing to choose — and a page that asks for a pixfort icon on a site where
  that plugin has since been switched off falls back to its Elementor icon
  rather than rendering blank.

- **A Design choice on the Experience & Education widget: Timeline or
  Checklist.** The timeline — the rail of icon nodes with a card per entry — is
  still what the widget does, and every page already built keeps it: pages saved
  before this release carry no Design setting, so they resolve to Timeline.

  Checklist renders the same entries as a compact icon-marked list, one entry per
  line, with the organization and years joined onto that line by a separator you
  can set (an en dash by default). It emits the same `dp-stats__ach*` markup the
  achievement lists use, so a profile that mixes the two reads as one design, and
  it falls back to the built-in circle-check mark when no icon is chosen. Its own
  List style section covers item gap, columns, text and icon; the Rail, Card and
  Text sections now show only for the timeline, and the node/organization icon
  controls only appear where they apply.

### Changed
- **The Doctor Index block now opens showing only its search bar.** No browse
  list is selected by default — a wall of names used to greet anyone who arrived
  meaning to type. Pressing a browse button opens its list; pressing the open
  one closes it again, so the bare state stays reachable. With nothing selected
  at rest the buttons are disclosure controls (`aria-expanded`) rather than a
  tablist, which must always have exactly one tab selected and whose roving
  tabindex would otherwise have left no focusable button at all while everything
  was closed. **More Filters** is enabled only while the doctor-name list is
  open, which now covers the closed state too.
- **The A–Z row's `#` is now "show all", not a bucket.** It sits where an "All"
  option sits in every other filter, is highlighted whenever no letter is
  applied, is never dimmed, and clicking it clears the letter. Its label is
  translatable, so a site that prefers the word "All" can say so without
  touching the filtering. Consequence worth knowing: a name that does not begin
  with A–Z no longer has a letter that selects it, and appears only in the
  unfiltered list — a row of letters where one of them silently also meant
  "everything else" was the more confusing of the two. Applies to the Doctor
  Grid's browse index, the Doctor Index block, and the directory shortcode.

### Added
- **The Doctor Index block gains a real filter row: Sort By, Gender and
  Languages Spoken**, behind the same collapsible **More Filters** button the
  Doctor Grid uses. Sort reorders the lists (A → Z / Z → A), and Gender and
  Languages narrow the **By Doctor Name** list as you pick them.

  On the Department, Specialty and Symptom tabs a term has no gender or language
  to match, so the row is unavailable there: **More Filters** is disabled and
  dimmed rather than hidden, which keeps the bar from reflowing and reads as
  "belongs to another tab" instead of "something broke". Your choices are kept,
  not reset, so switching back to **By Doctor Name** restores them. Links stay
  honest too — a department entry carries only its department, and a doctor
  entry is still a plain profile permalink; neither smuggles in a filter you
  cannot see at the moment you click.

  All three are read back from the query string on the server, so the block
  sorts and narrows identically with JavaScript off — where the disabled state
  is already correct on the first paint — and a filter with no options to offer
  is not rendered at all.

### Removed
- The Doctor Index block's **Show side button** control, with its Button text,
  Button link and Button icon. It was a plain link to the search page that
  happened to be labelled "More Filters" while filtering nothing; the real
  filter row replaces it under the same label.

### Fixed
- **The Sort By / Gender / Languages dropdown arrows no longer sit in the pill's
  curve.** The arrow was the browser's own, painted outside the box model where
  padding cannot reach it. The selects now draw their own chevron — the same
  shape as the More Filters one — inset from the trailing edge, and flipped for
  RTL.
- **A blanket focus ring reshaped every control in the directory it touched.**
  One `.dp-dir :focus-visible` rule set `border-radius` alongside the outline —
  which does not round the ring, it overwrites the element's own radius — so
  focusing a pill select or the pill search field squared it off to 6px and
  floated a rounded rectangle 2px outside a round control. The rule is gone.
  Focus is now stated per control in that control's own shape: bordered
  controls recolour their border, everything else gets a tight outline with no
  offset that traces the pill exactly.
- **Focusing a search field no longer stacks two rings around it.** The bars
  drew an offset `outline` outside the pill, and the raw input inside drew a
  second, square ring of its own from the browser or the active theme. Both are
  gone. Focus is now a single change of colour on the field's existing pill
  border — no extra box, no offset, no reflow. The state is kept rather than
  removed outright, because a search field that shows no focus strands keyboard
  users and fails WCAG 2.2 AA.
- **The two search bars on a page no longer look like different components.**
  The Doctor Index block painted a saturated `#7ec8e8` band with a fully rounded
  pill edge, while the Doctor Grid / List bar used the pale tinted surface with
  a panel radius. Both now read the shared `--dp-bar-bg` / `--dp-bar-border`
  tokens and the same panel radius, so they cannot drift apart again; `#7ec8e8`
  is removed. The pill shape stays where it belongs — on the controls inside the
  band (search field, tabs, actions), not on the band itself.
- **An icon chosen from Elementor's uploaded-SVG library warned on every
  render.** That library stores the attachment data as an array, and the widgets
  compared it as a string — `Array to string conversion` on each page view, into
  the log or, with display errors on, into the markup. Icon resolution now lives
  in one place and tests the value without casting it.

## [0.5.1] - 2026-08-21

### Fixed
- **The About Doctor widget rendered the whole page inside itself.** Editing
  anything on the widget — even just its Eyebrow text — made it come back
  containing the Page Header: breadcrumbs, photo, name, key facts, booking
  buttons, and a second copy of its own heading below them.

  `DoctorView::rendered_biography()` runs the doctor's biography through
  `the_content` so shortcodes and embeds work. Elementor hooks that filter at
  priority 9 and `apply_builder_in_content()` does not *filter* the content — it
  discards it and substitutes the entire rendered document for the current post.
  Called from a widget that is itself part of that document, it rendered the
  document inside the widget. Elementor drops its own filter for the nested
  pass, so it stopped after one level rather than hanging, which is why this
  looked like a display glitch instead of the near-infinite loop it was.

  The biography pass now suspends that one filter through Elementor's own public
  `remove_content_filter()` / `add_content_filter()` API, and restores it only
  when it was registered to begin with — Elementor removes it during its own
  nested renders, and re-adding it there would have caused the opposite bug. A
  re-entrancy guard backs it up, so nothing reached from `the_content` can
  recurse through the biography again. Every other `the_content` filter still
  runs, so shortcodes, embeds and other plugins are unaffected.

## [0.5.0] - 2026-08-21

### Added
- **Separate style sections for the Eyebrow and the Heading**, on every widget
  that has the built-in heading — 19 of them, from Doctor Grid / List and Doctor
  Carousel to Certifications, Booking Card and Page Header. They used to share
  one "Heading" section offering colour and typography and nothing else.

  Each line now gets its own Style section with the same full set: typography,
  padding, margin, border radius, transition duration, and Normal / Hover tabs
  carrying text colour, background (classic or gradient) and box shadow. The two
  are independent — a tinted pill above a plain heading, or a banded heading
  under a plain eyebrow, without either choice constraining the other.

- **"Fit background to text"**, one switcher per line. Both lines are
  block-level, so a background colour paints the full column width — right for a
  banded heading, wrong for the label an eyebrow usually wants. Off by default.

### Changed
- The old single "Heading" style section is now two: **Eyebrow** and **Heading**.
  Nothing you have already set moves or resets — Elementor stores settings by
  control ID, and every existing ID (`eyebrow_color`, `eyebrow_typography`,
  `wh_title_color`, `wh_title_typography`, `wh_spacing`) is unchanged. A widget
  nobody has touched emits exactly the CSS it emitted before.
- "Spacing below" stays in the Heading section, with its description clarifying
  what it has always done: the gap below the whole eyebrow-and-heading block,
  not below the heading line.


## [0.4.0] - 2026-08-20

Schema version 5. **The upgrade permanently deletes stored data — see below.**

### Removed
- **The Credentials field is gone, and so are its saved values.** The request was
  to replace "Credentials" with "Headline" everywhere; on inspection the plugin
  already had a field called **Headline** — `post_excerpt`, the line under the
  doctor's name on cards — so a straight rename would have put two identically
  labelled fields in the same editor tab. The field the client wanted already
  existed. Credentials was surplus, and is removed instead (ADR-0024).

  It leaves every layer at once: the editor row, the card line in both the PHP
  template and the JavaScript renderer, the profile header line, the legacy
  search renderer, `.dp-card__credentials` / `.dp-profile__credentials` /
  `--dpc-cred-color` in both stylesheets, the `credentials_show` switcher and the
  Credentials colour and typography group in the Doctor Grid / List and Doctor
  Carousel panels, the Elementor dynamic tag, the search index, the completeness
  check, the CSV export column, the CSV import branch, the migration mapping and
  its `qualifications` / `degrees` synonyms, and `wpml-config.xml`.

  **Export your doctors before upgrading if you want these values.** Migration 5
  deletes every `_doctorpilot_credentials` row, in every language. Reverting the
  plugin restores the field but not the strings, and after the upgrade the
  exporter no longer has the column — so the export has to happen first.

  Affected doctors are reindexed afterwards, because the credentials text had
  also been copied into each doctor's search document and keyword index, where
  it would otherwise have gone on matching searches for a qualification the site
  no longer stores. That reindex runs in batches of 100 on `shutdown` rather
  than inside the migration itself, so a large directory spreads the work over a
  few page loads instead of stalling whichever request happens to trip the
  upgrade.

### Changed
- **The "Short bio" card switcher is now labelled "Headline".** It always
  controlled the Headline; only the label said otherwise. The setting key
  `bio_show` is unchanged, so pages already built keep whatever the author chose
  — renaming the key would have quietly reset the switch to its default on every
  one of them.

### Compatibility
- The REST doctor payload no longer contains `credentials`.
- The exported CSV loses its third column; a downstream script reading columns by
  position is off by one from that point on.
- Theme overrides of `card-doctor.php` or `profile-content.php` copied from 0.3.0
  read a key that no longer exists — a notice and a blank line, not a fatal.
  Re-copy them.
- Saved Elementor widgets keep orphaned `credentials_show`, `cred_color` and
  `cred_typography` settings; Elementor ignores controls it cannot find. Custom
  CSS targeting `--dpc-cred-color` or `.dp-card__credentials` now matches
  nothing.
- **Re-point any Doctor Field dynamic tag bound to Credentials before you save
  that template again.** The tag prints nothing after the upgrade, which is
  obvious enough. The trap is the next save: the control falls back to its
  default, so the tag is silently rewritten to **Name** and the page starts
  printing the doctor's name twice.
- Completeness scores move slightly — the check is gone, so the denominator
  drops from 22 to 21 weight points. Nothing is gated on the score.
- A search that matched only on a credentials token — an abbreviation like
  "FRCR" — now returns nothing unless that text also appears in the biography,
  headline or clinical interests.



## [0.3.0] - 2026-08-19

Highlights, achievement list widgets, a single practice location, site-wide
booking defaults, and a menu of their own for the taxonomies. Schema version 4;
the upgrade migrates doctors off the removed BookPilot provider and corrects two
booking settings that were in the schema but never read.


- **Every doctor taxonomy now has its own top-level Taxonomies menu.** Department
  and Specialty used to be two items inside the DoctorPilot menu and the other
  five were reachable only from a doctor's edit screen or by URL — a trade-off
  made because listing all of them pushed Health, Analytics and Settings below
  the fold. A menu of their own removes the trade-off: all eight vocabularies,
  Insurance included, are one click away, and the DoctorPilot menu is back to
  the screens that are not term lists.

  The list is built from what is actually registered against the doctor post
  type, so an add-on's taxonomy appears without editing `Menu`. The parent's
  slug is the first taxonomy's own screen rather than a placeholder page, and it
  follows the first surviving taxonomy — pinning it to Department would break
  the top-level link on a site that unregisters Department.

  `Menu::MENU_TAXONOMIES` is removed. `parent_file` is now filtered only on
  `edit-tags.php` and `term.php`: `$taxnow` is set on every admin request, so a
  broader check also fired on the doctor list, where a term's post-count link
  carries `taxonomy=` for any taxonomy without a `query_var`.

### Fixed
- **Card style controls now actually do something.** Almost every control in the
  Doctor Grid / List and Doctor Carousel style panels — card background, border,
  radius, padding, shadow, hover lift, image ratio and fit, every text colour,
  badge colours and shape, button colours and shape — was silently ignored.
  46 of the 51 card controls did nothing at all. Only the gaps, the list media
  width, the actions rule and the bio clamp worked, which is why the panel
  looked partly alive.

  The controls wrote their CSS variable to the Elementor widget wrapper, while
  `directory-premium.css` declares the same variables on the directory root
  inside it. A CSS custom property declared on an element beats one inherited
  from an ancestor no matter how specific the ancestor's selector is, so every
  value reached the directory root and was overwritten there before a card could
  read it. The controls now write to `{{WRAPPER}} .dp-dir` — the same element as
  the defaults, one specificity step above them and above the presets.

  **Expect a visible change on upgrade.** Any value set while the control was
  dead — including values set, seen to do nothing, and abandoned — takes effect
  the moment this ships. Look over the affected widgets after updating.

  Elementor's cached per-post CSS is now discarded automatically when the plugin
  version changes, because a page otherwise keeps serving CSS compiled from the
  previous release's selectors and the fix would reach nobody until someone
  happened to re-save that page. Running from an unversioned working copy, do it
  by hand once: **Elementor → Tools → Regenerate Files & Data**.

  The one deliberate exception: under the Image overlay preset, a card with no
  photo and an image-card list still force their own dark text colours over
  whatever the colour controls say, so white type never lands on a white card.
  The preset's description now states this.

### Added
- **WPML compatibility.** The plugin had none: a multilingual site got
  translated doctors that were invisible to search, Elementor sections that
  silently rendered nothing on a translated page, empty results from any shared
  or language-switched URL, and cards showing an English department under an
  Arabic name.

  All of it now goes through one bridge class built on WPML's public filters, so
  saved term slugs resolve to the current language at render time — existing
  widgets keep working with no re-saving — stored term and post IDs convert when
  read, the doctor profile uses the translated Elementor template, and the search
  cache is keyed per language. A `wpml-config.xml` declares the post types,
  taxonomies, custom fields and term meta so WPML's own setup screen can offer
  them. Both public REST routes accept a `lang` parameter and switch language
  before reading anything, because whether a REST URL carries WPML's language
  prefix depends on the site's URL mode.

  **A single-language site is unaffected** — every call is an identity function
  behind one `has_filter()` check, verified by a harness that asserts identical
  results and zero extra queries with no multilingual plugin present.

  **Not yet signed off on a real WPML install.** WPML is not installed on the
  development site, so this is verified against a simulated implementation of
  its documented filters. That proves this plugin's half of the contract, not
  WPML's. See ADR-0023 for what to test first.
- **Every doctor now gets a search index, however it was created.** Previously
  only a save through the plugin's own editor form rebuilt the derived search
  values, so a doctor created by WP-CLI, by the REST API, by Quick Edit or by a
  translation plugin was dropped from every search result rather than ranked
  low. This was a latent bug on single-language sites too.

  Rebuilds are collected and flushed once per doctor at the end of the request,
  after every field and term is written, so the cost is one rebuild per doctor —
  unchanged from before for an ordinary save or an import row, and no longer
  zero for everything else.
- **Departments and Specialties now have admin menu items**, directly under
  Certifications in the DoctorPilot menu. They had none before — not in the
  wrong place, but missing entirely, reachable only from a doctor's edit screen
  or by typing the URL. WordPress builds taxonomy submenus only for post types
  whose `show_in_menu` is literally `true`, and the doctor post type points at
  the DoctorPilot menu instead, so its taxonomies were skipped. Opening either
  screen now also keeps the DoctorPilot menu open, which it did not do for any
  taxonomy screen before.

  The other five doctor taxonomies — Symptom, Condition, Treatment, Language,
  Location — are deliberately left out, so the menu does not push Health and
  Settings below the fold.

  **Superseded:** every doctor taxonomy now lives in its own top-level
  Taxonomies menu, and `Menu::MENU_TAXONOMIES` no longer exists. See the
  Taxonomies menu entry below.
- **The doctor card's specialty and department are two separate lines.** The
  single "Specialty or department" switcher becomes three: **Primary specialty**
  (on), **Fall back to department** (on) — which puts the department on the
  specialty line only when a doctor has no specialty — and **Department** (off),
  which gives the department a line of its own below the specialty.

  The two lines resolve together so the department can never print twice: once
  it has a line of its own, the specialty line stops borrowing it. The compact
  list layout joins the two with a separator rather than showing only the first,
  because collapsing would have made the Department switch do nothing for any
  doctor who has a specialty.

  Every default is chosen so an already-published page renders byte-identically
  after the upgrade. Verified by diffing old against new output across every
  combination of the three switches and every shape of doctor data, in both the
  PHP templates and the JavaScript renderer.

### Removed
- **"Accepting new patients" is gone from the whole plugin** — the card
  metadata row, the availability badge over the photo, the **Card elements →
  "Accepting new patients" row** switcher, the **Show availability badge**
  control and its Availability badge style group, the directory and search
  filters, the REST `accepting_new` argument, the `accepting_new_patients` key
  in the REST doctor payload, and the `_doctorpilot_accepting_new_patients`
  field itself — which also removes the checkbox from the doctor editor's
  Listing flags box. See ADR-0021.

  **Breaking.** A REST client reading `accepting_new_patients` no longer
  receives it; a request still passing `accepting_new` is ignored rather than
  rejected, so old links keep working but stop filtering.

  **If your theme overrides `directory.php` or `search.php`, re-copy those two
  templates before upgrading.** Both called a method that no longer exists, so
  an old copy fatals the page. Overrides of `card-doctor.php` or
  `row-doctor.php` are safe to upgrade with — they simply keep showing the row
  until the first search, then lose it.

  **No data is deleted.** Existing `_doctorpilot_accepting_new_patients` post
  meta stays in the database, unread. Deleting a field definition is reversible;
  deleting a hospital's rows is not.

## [0.2.0] — 2026-08-15

### Added
- **The optional widget heading is now on every widget that can carry one.** It
  was already on Doctor Grid/List, Doctor Carousel/Grid, About Doctor,
  Experience Timeline and Specialties; it is now also on Academic Highlights,
  Booking Button, Booking Card, Certifications, Certifications & Publications,
  Department Grid, Doctor Facts, Doctor Profile, Doctor Search, Page header,
  Symptom Directory and Related Doctors.

  Each gets the same four content controls (show, eyebrow, heading, heading tag
  h1–h6/div) and the same Heading style section (eyebrow and heading colour and
  typography, spacing below). **Off by default**, so no existing widget changes
  appearance until an editor switches it on.

  The heading prints only after a widget has confirmed it has something to show.
  A Certifications widget on a doctor with no certifications, a Booking Button
  with no booking destination, a Related Doctors block with no matches — each
  still renders nothing at all rather than a heading standing over an empty
  space. In several widgets that meant moving the call below the point where the
  content is assembled rather than putting it first.

  Heading Block is the one widget left out: it *is* the heading widget, and it
  already registers its own `heading_show` and `heading_text`, so adding the
  shared set would have collided on those control IDs.

  Doctor Profile's style section named "Headings" is renamed "Profile sections",
  because it styles the `h2` that opens each section inside the profile — a
  different thing from the widget heading, and two panel sections called
  "Heading" would have been a guessing game.
- **Doctor Carousel / Grid widget.** A second way to place doctors: the same
  card, the same six design presets, the same style panel and the same Card
  elements switches as Doctor Grid / List, with the directory apparatus removed
  — no search bar, no filter row, no A–Z index, no pagination. The editor
  chooses who appears; the visitor scrolls or reads.

  **Choosing doctors.** One multi-select per registered filter taxonomy —
  Department, Specialty, Symptom and Language always, plus Condition, Treatment,
  Location and Insurance Providers when Pro is active. The controls are built
  from `doctorpilot/search/filter_taxonomies`, so a taxonomy an add-on registers
  gains a control with no change here, and a taxonomy that is not registered
  never produces a control that would silently match nothing. Several terms in
  one taxonomy widen the selection; terms in more than one narrow it. Leave
  everything empty for all published doctors. **Follow the current archive**
  makes one widget serve every department, specialty or location archive by
  taking the term from the page instead of the panel.

  Selection runs through the same `SearchRequestFactory` and `SearchService` as
  the directory, so ranking, ordering, the request cache and the card payload
  are shared rather than reimplemented.

  **Carousel.** Built on CSS scroll snapping, not a slider library — nothing is
  loaded from a CDN and no third-party asset handle is depended on. The browser
  owns the physics, so touch flicks, trackpad swipes and momentum behave the way
  the platform behaves. Because the track is a real scroll container, the strip
  is scrollable and keyboard-reachable **before the script runs and without it**;
  the arrows, dots and autoplay ship `hidden` and are revealed only once the
  script is there, so a control that does nothing is never shown.

  Arrows can sit over the cards, outside them, or on the control row beside the
  dots, with colour, hover and size controls. Wrap-around is optional; with it
  off the arrows disable at each end. Autoplay is off by default and, when on,
  adds a real pause button and stops on hover, on keyboard focus, while the tab
  is in the background, and for anyone whose system asks for reduced motion.
  Arrow keys, Home and End page the track. A carousel whose cards already fit
  hides its own controls.
- **Per-element show/hide switches for the doctor card.** A new **Card
  elements** section in the Doctor Grid / List and Related Doctors (Pro)
  widgets turns any part of the card off: photo, "Featured" badge,
  "International" badge, credentials, specialty or department, short bio, years
  of experience, languages, the "Accepting new patients" row, the View Profile
  button and the booking button. The doctor name always renders — it is the
  card's link to the profile.

  A hidden part is omitted from the markup rather than hidden with CSS, so it
  never reaches a screen reader, a crawler or the wire. The switches are honoured
  identically by `templates/card-doctor.php` and by the JavaScript card renderer,
  so cards rebuilt after a filter change match the ones rendered on load. Turning
  both buttons off removes the actions row and its divider instead of leaving an
  empty strip; turning the photo off adds `dp-card--no-media`, which drops the
  Image overlay preset back to its light surface so white type never lands on
  white. The "Specialty or department" switch also governs the single metadata
  line of the compact list row.

  Every switch defaults to on, so existing widgets render exactly as before.
- **The doctor card has a premium default design.** White cards on a soft
  blue-grey surface, deep medical teal accents, dark navy text, a 16px radius
  and a soft professional shadow, with a 240ms hover lift. It looks finished
  the moment the widget is dropped on a page; every colour and metric remains
  editable.
- **Six card design presets** — Classic, Modern, Elevated, Minimal, Image
  overlay and Compact. Modern is the default; **Classic reproduces the previous
  card exactly** for anyone who wants the old look back.
- **A full card style panel**, replacing the three controls the card used to
  have. New sections cover the card box (background, border, radius, padding,
  content gap, divider, shadow, hover shadow, lift, scale, transition), the
  doctor image (aspect ratio, object fit and position, radius, placeholder
  colour, overlay, hover overlay, hover zoom), per-element text colour and
  typography for name, headline, credentials, specialty and metadata, three
  badge types, and independent primary and secondary buttons with normal and
  hover states.
- **Initials fallback for doctors without a photo.** A doctor with no image now
  shows their initials on a soft gradient instead of a blank grey block. The
  honorific is stripped first, so "Dr. Aisha Rahman" reads AR rather than DA.
- **Metadata icons.** Experience, languages and availability each get an inline
  icon, replacing the unstyled bulleted list.
- **Job title on the card.** The doctor's Headline (`post_excerpt`) is now part
  of the card, with an optional line clamp.
- **Optional availability badge** over the image for doctors accepting new
  patients. Off by default, since the same fact already appears in the metadata.
- **Whole-card click option.** The cover link is hidden from the keyboard path,
  so the name and buttons stay the only tab stops.
- **Image-card list style.** The list layout can now render full doctor cards
  laid out horizontally, with the image left, right or on top and a settable
  width. The original compact rows remain the default.
- **Skeleton loading cards** while an AJAX request is in flight, sized from the
  same tokens as the real card so nothing moves when results arrive.
- **National / International filter.** A tri-state filter — All, National,
  International — driven by the existing "International doctor" flag, so no new
  field or taxonomy was added. Available in the Doctor Grid / List Filters
  control, in the public `/doctors` REST route as `international`, and in the
  shareable URL. Doctors with no stored flag count as national, so imported
  profiles are not dropped from that result. It is off by default: existing
  widgets are unchanged until the filter is selected.
- The **Doctor Grid / List** search tabs now open a **browse index above the
  doctor results, with the results still on screen**. **By Doctor Name** lists
  every published doctor (name plus specialty); **By Department**, **By
  Specialty**, and **By Symptom** list that taxonomy's entries. Only terms at
  least one published doctor uses are listed, so no entry leads to an empty
  result. The widget renders every enabled index at once, so switching tabs is
  instant and needs no request.
- **Picking an entry filters the doctors below it.** The chosen name, department
  or symptom is outlined and highlighted, the results underneath narrow to it,
  and clicking it again clears it. Switching tabs clears the previous tab's
  selection, so a filter can never stay active with no visible control for it.
  The selection is an ordinary search parameter, so it appears in the URL, the
  active-filter count, and the **Clear** button, and it works as a plain link
  with JavaScript disabled.
- New **`doctor` search parameter** (comma-separated doctor slugs, max 10),
  accepted by `GET /doctors` and the widget's GET form, restricting results to
  those doctors before ranking.
- Every filter now stays usable while browsing, because the results never go
  away. A filter a visible index already controls (for example Department while
  the Department tab is open) is no longer duplicated as a select in the **More
  Filters** row.
- The **A–Z index** is a filter rather than a set of headings, and works without
  JavaScript: each letter is a real `?letter=` link, the active letter is
  highlighted, and letters with no entries are dimmed and non-clickable. It now
  applies to the browse index on every tab, updates as you type, and is **on by
  default**.
- New theme-overridable template `doctorpilot/row-term.php` for a browse-index
  entry (doctor name or taxonomy term).
- **Milestone 1 — Foundation** (Free):
  - `doctorpilot.php` bootstrap with version/runtime/path constants and a
    direct-access guard.
  - Runtime guard enforcing WordPress 6.4+ and PHP 8.1+ with actionable admin
    notices; activation is refused safely on unsupported runtimes.
  - Lifecycle: ordered idempotent activation (version seeding, migrations,
    capability grant, maintenance scheduling, one-time rewrite flush),
    data-preserving deactivation, and opt-in `uninstall.php`.
  - PSR-4 autoloader mapping `BrainStudioz\DoctorPilot\` to `includes/`, with the
    approved Domain/Application/Infrastructure/Support package layout.
  - Versioned `ExtensionRegistry` for guarded Pro/add-on registration against a
    base API version.
  - Capability primitives and idempotent administrator grant
    (`Support/Capabilities.php`).
  - Versioned `bstdz_doctorpilot_settings` object with defaults, range/enum
    validation, and unknown-key discarding (`Admin/Settings.php`).
  - REST foundation (`doctorpilot/v1`) with an explicit-permission route
    registrar and an admin-only health route.
  - Single admin navigation owner with Health and Settings screens
    (`Admin/Menu.php`).
  - Observability: redacting logger + interface and a stable `ErrorFactory`.
  - Ordered, idempotent schema `Migrator` with a version-1 baseline.
  - Quality gates: `composer.json`, `phpcs.xml.dist`, `phpunit.xml.dist`, test
    bootstrap, and initial settings/lifecycle tests.
- **Milestone 2 — Doctor Directory** (Free):
  - `doctorpilot_doctor` custom post type (`show_in_rest` false, mapped
    capabilities, `/find-a-doctor/{post_name}/` permalinks, nested under the
    single DoctorPilot admin menu).
  - Seven prefixed Free taxonomies (Department, Specialty, Symptom, Condition,
    Treatment, Language, Location) with Symptom/Condition/Treatment kept
    separate; curated Symptom term metadata with capability + nonce checks.
  - Canonical `FieldRegistry` (single schema authority) with recursive
    `Sanitizer`, private meta registration, bounded repeaters, and primary-term
    invariants; derived completeness, search document, and sort name.
  - Guided tabbed doctor editor with review/completeness side panel, media
    selection, and a nonce + capability-guarded save through the registry
    (`Application/Doctors/SaveDoctor`).
  - Free booking adapters (External URL, Phone, Enquiry) behind
    `BookingProviderInterface` with a safety-revalidated `BookingResolver` and
    immutable `BookingCta`; single Free location record with public-email
    consent.
  - Public allowlisted view model (`DoctorView`), native single/archive/card
    templates with theme overrides, `[doctorpilot_directory]` and
    `[doctorpilot_profile]` shortcodes, and conditionally-loaded frontend CSS.
  - Single SEO owner (`SeoOwner`) that yields to Yoast/Rank Math/AIOSEO/SEOPress
    and otherwise emits canonical, meta description, Open Graph, per-doctor
    indexing, and Physician + MedicalOrganization JSON-LD (`Schema`).
- **Milestone 3 — Search & Frontend** (Free):
  - Immutable, bounded `SearchRequest` with a shared factory/normalizer
    (Unicode-safe, prefix-stripping) used by both REST and the GET fallback.
  - Deterministic `Ranker` implementing the documented score tiers and tie
    order, fed by a rebuildable `SearchIndexBuilder` (normalized name/specialty/
    department/symptom/condition/keyword buckets) so scoring needs no per-request
    term queries; `CandidateRepository` applies visibility + filters before
    scoring.
  - `SearchService` (pagination + active-filter chips) plus `SuggestionService`,
    `FacetService`, and a non-diagnostic `NoResultService`.
  - Public REST under `doctorpilot/v1`: `/doctors`, `/doctors/{id}`,
    `/doctors/suggest`, `/facets` — explicit public permission callbacks,
    bounded args, published-only allowlisted output, stable error envelope,
    pagination headers, and short-window `RateLimiter` abuse control.
  - Cache generation counter with invalidation on doctor/term/settings changes
    and a generation-keyed `SearchCache` (correctness independent of TTL).
  - `[doctorpilot_search]`: server-rendered, shareable GET fallback progressively
    enhanced into no-reload search with History API state, debounced input,
    stale-request cancellation, `aria-live` result count, filters, sort, and
    accessible pagination; filtered query-string combinations are `noindex`.
  - Performance/verification harness: 1,000-doctor fixture generator, service
    benchmark (p50/p95 + query count), ranking query set, and accessibility
    checklist under `tests/perf/`.
- **Milestone 4 — Elementor** (Free):
  - Guarded integration bootstrap: `Compatibility` detects a supported Elementor
    (≥ 3.5) before any Elementor class is referenced; `Integration` registers the
    DoctorPilot widget category, a shared style handle, and the widgets. When
    Elementor is absent or outdated, nothing loads and native output stands
    (with an actionable admin notice for outdated versions).
  - Six Free widgets rendering through the shared view/search/booking services
    (no parallel query engine, no raw meta): Doctor Search, Doctor Grid / List,
    Department Grid, Symptom Directory, Doctor Profile, and Booking Button — with
    responsive column, layout, query, and scoped style controls.
  - Scoped widget styles reuse `assets/css/frontend.css`, loaded only where a
    widget is present via `get_style_depends`; the Doctor Search widget also
    declares the search script dependency.
- **Milestone 6 — Pro extension hooks** (Free base, additive, API v1):
  - `doctorpilot/search/filter_taxonomies` lets add-ons register extra search
    filter taxonomies (condition/treatment/location/insurance) with full parity.
  - `doctorpilot/locations/max` lets Pro raise the single-location limit.
  - `doctorpilot/search/performed` fires after a public search so add-ons can
    record anonymized aggregates (no identifying data passed).
  - These are the only Free changes for M6; all Pro features ship in the separate
    `doctorpilot-pro` add-on (schema/roles/scope, advanced filters + multi-location,
    Pro Elementor tags/widgets, BookPilot provider, and opt-in aggregate analytics).

### Notes
- Milestones 2–4 render accessible, published-only doctor profiles, a directory,
  no-reload search, and Elementor widgets. M4-TASK-004 (complete dynamic tags and
  Theme Builder conditions) and all Milestone 5–6 Pro features live in the
  separate DoctorPilot Pro add-on, which registers against the base extension API.

### Changed
- **DoctorPilot Pro is now part of this plugin.** One artifact contains the
  directory, search, the Elementor widget set, the advanced taxonomies and
  filters, CSV migration, legacy-URL redirects, reconciliation, scoped roles and
  opt-in analytics. The add-on's bootstrap, its own autoloader, its `Plugin`
  singleton and the `maybe_boot()` registration handshake are gone; its modules
  are booted directly from `Infrastructure\Plugin::boot()`.

  **A leftover `doctorpilot-pro` is deactivated for you** on the first admin
  page load, through the silent form of `deactivate_plugins()`. That detail is
  the point: the add-on's own deactivation handler clears
  `bstdz_doctorpilot_pro_retention` and `bstdz_doctorpilot_pro_run_import`,
  which are the hook names this plugin now uses. Deactivating it the ordinary
  way — which is what the upgrade instructions would have asked for — would have
  stopped retention cleanup for good and killed any import waiting on its next
  chunk. The silent form never runs that handler.

  An admin notice then explains how to remove the old folder safely. Its
  `uninstall.php` reads *this* plugin's "delete data on uninstall" setting and
  drops the redirect and import tables, so on a site with that setting on,
  deleting it from the Plugins screen would take every legacy-URL redirect with
  it. Switch the setting off first, or remove the folder over FTP.

  Recurring schedules are also re-created on any admin request that finds them
  missing, so a schedule cleared by anything at all repairs itself on the next
  page load rather than staying gone until someone reactivates the plugin.

  Nothing is moved, recreated or lost. Every table name, option key, capability,
  role slug and cron hook keeps its existing string — including the ones with a
  `_pro_` segment, which identify rows already sitting in your database. Schema
  migration 3 adopts them on the first admin page load after the update; it is
  idempotent, so it is a no-op on a site that already had them and a first
  install on one that did not. No re-activation, no re-import, no re-mapping.

  Public contracts are unchanged: same REST routes, shortcodes, template names
  and override paths, Elementor widget names, table schema, capabilities and
  cron hooks. Saved widget settings survive.

  `ExtensionRegistry` and the `doctorpilot/registered` action are kept even
  though nothing here consumes them now — they are the published contract a
  third-party add-on would be built against.

  The reasoning, including what this gives up, is recorded in ADR-0020; the
  commercial detail is in `docs/12-packaging-and-tiers.md`.

- **DoctorPilot Pro no longer advertises bulk operations.** The Pro plugin
  description and `readme.txt` listed bulk operations as a shipped feature, but
  no bulk-action code exists (FR-046 is unimplemented). The description now
  names what Pro actually ships: CSV migration, legacy-URL redirects,
  reconciliation, advanced filters, and opt-in analytics.
- **Commercial packaging recorded (ADR-0019).** DoctorPilot now has a documented
  three-tier model — Free on WordPress.org, a one-time **Migration** entitlement,
  and an annual **Pro** subscription — shipped across the existing two plugins
  rather than merged into one paid module. No runtime, schema, or hook change;
  see `docs/12-packaging-and-tiers.md`.
- The compact list layout's **A–Z doctor list** — which paged through up to 480
  matches to group them by letter — is replaced by the **By Doctor Name** index,
  which is available in both layouts and can be clicked to filter. The doctor
  results now always paginate, so **Doctors per page** applies at all times.
- **Doctor Grid / List**: the A–Z index defaults to on, and the compact doctor
  list no longer prints a heading per letter — it is one flat, multi-column list
  that the A–Z bar filters. With the A–Z index on, the compact doctor list shows
  every match instead of paginating (bounded to 10 pages, with a notice when a
  larger directory is truncated); switch **Show A–Z index** off to paginate.
- **Doctor Grid / List** now defaults to showing its search bar and the Sort By /
  Gender / Languages Spoken filters. Existing placements of the widget will start
  showing the search toolbar; switch **Show search bar** off and clear the
  **Filters** control to restore the previous plain-grid output.
- The widget's **List** layout now renders compact name + specialty rows instead
  of one-column cards. Use the **Card grid** layout with Columns set to 1 for the
  previous appearance.
- `GET /doctors` now honours the documented `per_page` parameter instead of
  always using the site's per-page setting. The value is still clamped to 1–48
  and is part of the cache key, so cached responses stay correct.
- Shared (global) **Publications** now use the same field set and labels as the
  doctor-only Publications repeater — the last field is labelled **Description**
  (was "Summary") so both forms match. Fields: Title, Journal, Published date,
  URL, DOI, Description.
- Icon style sections in **Doctor Facts**, **Specialties**, **Certifications**, and
  the **Experience & Education** timeline node now include full box controls:
  padding, border radius, transition, and Background / Border / Box-shadow groups
  with Normal + Hover tabs.
- Content widgets (**About Doctor**, **Specialties**, **Experience & Education**)
  gained an optional built-in **eyebrow + heading** that renders only when the
  widget has data — so empty sections (and their headings) disappear for doctors
  without that content, instead of leaving a stray heading. Use it in place of a
  separate Heading Block.
- **Page Header** now has a **Placeholder image** control shown when a doctor has
  no featured photo (defaults to the Elementor placeholder).
- Frontend theme primary color set to `#136888` (via a `--dp-primary` CSS
  variable), applied to primary buttons, the single-doctor hero background/accent,
  profile headings (h1–h6), and links.
- SEO defaults now derive from the doctor's own post: the **SEO title** defaults to
  the post title (previously name + specialty), and the **meta description**
  defaults to the doctor's description — the headline (excerpt) when present,
  otherwise a trimmed excerpt of the biography. The editor placeholders and search
  preview, and the front-end `<title>`, meta description, and Open Graph tags all
  reflect these defaults; explicit SEO overrides still win.
- Global content records (Experience, Education, Procedures, Publications, FAQs)
  now show an editable **title** field. Leaving it blank auto-composes the title
  from the record's fields (as before); a typed title is preserved.
- Global **FAQ**, **Procedure**, and **Publication** records now use the title
  field as their single identifier (question / procedure name / publication
  title), removing the duplicate field. FAQ and Procedure have no other fields, so
  their Details box is removed entirely; Publication's box keeps journal, date,
  URL, and DOI. Experience and Education are unchanged (their title is composed
  from two distinct fields).
- Doctor profiles now show separate **Experience** and **Education** sections
  (previously one combined "Experience & education" list). Schema version 2 migrates
  any existing combined entries into the two new lists automatically.
- Admin menu: the DoctorPilot submenu is sorted into a fixed order — All Doctors,
  Education, Experience, Locations, Procedures, Publications, FAQs, Health,
  Analytics, Migration, Settings. (The earlier "Global content" heading was
  removed.)
- SEO tab now derives from the post's own data when overrides are blank: the SEO
  title placeholder/preview shows the doctor's display name plus primary specialty
  (and credentials in the preview), the meta description falls back to the
  headline/excerpt, and the canonical URL placeholder shows the actual permalink.
  The live search preview reflects these defaults, and the front-end output
  already inherits them (canonical → permalink, description → excerpt).
- Doctor editor: the main-editor term chips and the default sidebar taxonomy
  metaboxes now stay in sync — selecting or deselecting a term in either updates
  the other, and the save merges both sources so a term added from the sidebar's
  "Add New" control is never dropped.
- Doctor editor: reworked the tabbed meta box to the native WordPress admin tab
  pattern (active tab merges into the panel, grey inactive tabs with hover),
  added per-tab count badges and a pane header (title, help, and section meta).
  Styling moved to the enqueued stylesheet; markup escaped and internationalized.
- Doctor editor fields redesigned to match the approved mockup: taxonomy terms are
  now selectable chips (blue when selected) with a live "N selected" count and an
  empty-taxonomy "Add terms" link; repeaters gained numbered rows, a drag handle
  (jQuery UI sortable, indices renumber on reorder/add/remove), a red Remove link,
  and an "Add row" action with an inline hint; checkboxes render as title + help
  flags; private fields show a "Private" badge; SEO title/description have live
  character counters and a Google-style search preview. Admin CSS/JS now
  cache-bust by file modification time so edits apply immediately.
- Doctor editor aligned to the approved mockups across every tab: sentence-case
  pane titles with exact help text and a right-aligned "N rows" pane meta;
  per-term-field counts with the correct noun ("3 symptoms selected"); an
  "Add <taxonomy>" empty-state link; side-by-side rows (Prefix · First · Last,
  Credentials · Gender, Primary department · Primary specialty, Years · Manual
  order, Canonical · Legacy URL); a bordered Licensing box with a PRIVATE badge
  and note; repeater rows with per-column widths and placeholders; and
  placeholder text on SEO and other inputs. Layout is driven by generic
  row/width/box/placeholder/count_noun hints in the field registry.
- Doctor name is now entered as separate First name / Last name fields; the post
  title is composed from them (via `wp_insert_post_data`, so no recursive save),
  the default title box is removed, and existing doctors pre-fill their name parts
  from the current title. Leadership positions is now a simple one-per-line
  textarea (older structured rows are flattened for display on first edit).

### Design / documentation
- **Global content library (ADR-0017, accepted; implementation gated)** — approved
  design for reusable global Procedures, Experience & Education, Publications, and
  FAQs (Free CPTs `doctorpilot_proc`/`doctorpilot_edu_exp`/`doctorpilot_pub`/`doctorpilot_faq`)
  with per-doctor ordered references, retained doctor-specific local repeaters, a
  single `DoctorContentResolver`, a rebuildable reverse-reference index with
  cache-generation/rebuild invalidation, an authenticated `GET /library/search`
  route, computed read-only education titles, tightened duplicate handling, and
  Free blast-radius visibility. Documented in `docs/11-global-content-library.md`,
  ADR-0017, FR-058–FR-063, AC-025–AC-035, tech-spec §5.4/§7/§10–11, and
  `tasks/milestone-7-global-content-library.md`. No code yet.

### Removed
- The **Tabs browse an index** widget switch. Its off state claimed the tabs
  would "only narrow what the doctor search matches", but the search mode was
  never used for ranking, so a widget with it off showed tabs that did nothing.
  Every configured tab now browses; to have no browse tabs, clear the
  **Search-mode tabs** control. Widgets that had the switch off gain working
  tabs on upgrade.

### Fixed
- **Uninstall now removes what the setting says it removes.** With "delete data
  on uninstall" enabled, the handler previously dropped three tables and two
  options and stopped there — leaving every doctor, all prefixed meta, the
  taxonomy terms, the analytics table, the cached search responses, both roles
  and all fifteen capabilities on the site, while the checkbox promised
  "permanently remove all DoctorPilot data".

  It now removes content, terms, orphan meta, all four tables, the options, the
  transient cache, the cron events, the capabilities and the roles, in
  dependency order so an interruption leaves the site consistent. The table,
  role and capability lists are read from `Tables`, `Roles` and `Capabilities`
  rather than restated, which is what let the analytics table be created but
  never dropped: the old handler named `bstdz_doctorpilot_analytics` while the
  table has always been `bstdz_doctorpilot_analytics_daily`, so its `DROP TABLE
  IF EXISTS` matched nothing and reported success.

- **Four-column grids no longer produce five or six columns.** The column
  classes used `repeat(auto-fill, minmax(Npx, 1fr))`, which ignores the chosen
  count and fits as many tracks as the canvas allows. Track counts are now
  explicit.
- **Buttons no longer sit against the card edge**, and cards in a row line their
  CTAs up regardless of how much metadata each doctor has.
- **Empty metadata rows and empty button rows are no longer rendered.** The card
  builds both lists before printing them and omits the container entirely when
  nothing qualifies, so no orphan bullet, stray divider or blank line appears.
- **Missing or slow-loading photos no longer shift the layout.** The media box
  reserves its height through `aspect-ratio`, and images carry intrinsic
  dimensions.
- **`tel:` and `mailto:` booking buttons survive filtering.** The client-side
  URL allowlist accepted only `http(s)`, so a Call button rendered on first
  paint and then disappeared on the first keystroke.
- Clicking a pagination link on a freshly loaded page no longer silently
  re-fetched page 1 on sites using pretty permalinks.
- A `?letter=` in the URL no longer filtered the index on widgets with the A–Z
  bar switched off, where nothing could clear it.
- Letters emptied by a text search are now dimmed and non-clickable instead of
  leading to an empty index, and an index with no entries at all keeps its
  explanation instead of showing a bare count.
- The active-filter badge no longer jumped by one the first time a control was
  touched on a widget with a non-default sort.
- Array-shaped query parameters (`?mode[]=x`) no longer raise a PHP warning.
- The **Doctor Grid / List** Elementor widget now has a built-in **search
  option**. Alongside the existing card grid it can render a search bar with
  search-mode tabs (**By Doctor Name / By Department / By Specialty / By
  Symptom**), a collapsible **More Filters** row (**Sort By**, **Gender**,
  **Languages Spoken**, plus Department, Specialty, Symptom, Accepting new
  patients, and Online consultation), an active-filter badge, a **Clear**
  button, a live result count, and pagination. Everything is toggleable per
  widget, and taxonomy filters only appear when the site has matching terms and
  the filter is enabled in DoctorPilot settings.
- The widget's **Compact list** layout renders name + specialty rows in
  responsive columns and gains an optional **A–Z index**: results are grouped by
  first letter (honorifics ignored), unavailable letters are dimmed, and picking
  a letter narrows the view. The A–Z view lists every match rather than a single
  page, bounded to 10 pages of results with an honest notice when a larger
  directory is truncated.
- New style sections for the widget: **Search bar** (bar background, padding,
  radius, input colors/border, mode-tab typography and active colors, filter
  label and button colors), **A–Z index**, and **List row** (name and specialty
  typography/color, row gap). The Columns control now drives the list rows as
  well as the card grid.
- New theme-overridable templates `doctorpilot/directory.php` and
  `doctorpilot/row-doctor.php`, and a new `doctorpilot-directory` script that is
  enqueued only on pages where the widget is present.
- New **`name_desc` (Z → A)** sort value, available to the widget, the
  `[doctorpilot_search]` shortcode, and `GET /doctors`.
- **Booking Card** buttons gained the full button style treatment (matching the
  Page header): shared Typography, Icon size, Icon spacing, Padding, and Border
  radius, plus per-button (Book / Call) **Normal + Hover** tabs with Text color,
  Background color, and Icon color, and Border and Box Shadow group controls.
- New **shared/global Certifications** library type (`doctorpilot_cert`), mirroring
  Publications: a central, reusable Certifications post type (Name, Organization,
  Issued date, Expiry date, Credential URL) managed under the DoctorPilot menu and
  assignable to many doctors via a new **Shared certifications** field on the doctor
  editor. The doctor's certifications now merge assigned shared records first, then
  the doctor-only repeater (renamed **Certifications (this doctor only)**). Wired
  through the content resolver, REST assignment picker, and reverse index.
- Certification widgets now surface **all fields** on the frontend: the standalone
  **Certifications** widget and the **Certifications & Publications** widget can
  show the **issued / expiry dates** (behind a toggle) in addition to name,
  organization, and the credential link.
- **Page header** credential chips now have a configurable **Certification icon**
  (content tab, defaults to the award icon) and a **Certifications** style section
  with icon color (default `#136888`), icon size, chip text/background color,
  typography, gap, padding, and radius — plus a Show/Hide toggle. The icons render
  from the doctor's dynamic certification data. Icon color scopes `fill` to Font
  Awesome SVGs so the built-in line icon isn't filled solid.
- New per-doctor **Academic highlights** field (repeater of value + label) in the
  doctor editor; the Academic Highlights widget gains a **Data source** option
  (doctor field / manual) so the numbers can be dynamic per doctor.
- Importable Elementor page template (`tools/single-doctor.elementor.json`)
  assembling the header plus a two-column layout with a `dp-sticky` sidebar.
- New sidebar Elementor widgets (Single DoctorPilot category): **Booking Card**
  (dark CTA with title, `{name}` subtitle, Book + Call), **Doctor Facts**
  (icon-led Languages/Availability/Department/Email/Phone), **Academic Highlights**
  (manual stat grid via a repeater), and **Certifications** (from the doctor's
  certifications, optionally linked to the credential URL). All dynamic and fully
  styleable. Added a **`dp-sticky`** utility CSS class (with `dp-sticky--scroll`)
  so any Elementor column can be made sticky without Elementor Pro.
- New **Heading Block** Elementor widget (DoctorPilot category): a main heading
  (optionally linked), subheading, and description, each with its own HTML tag,
  show/hide toggle, and display order. Full Style controls per element —
  typography, text color (normal/hover), gradient text, text shadow, spacing,
  padding — plus a Box section (background/gradient/image, overlay, padding,
  radius, border and box-shadow with normal/hover tabs, transition) and an
  optional decorative line (above/below/left/right). Uses core Elementor control
  APIs, so it works with or without Pro; all output escaped and translation-ready.
- New **Experience & Education** timeline Elementor widget (Single DoctorPilot
  category): a vertical timeline of the doctor's experience and/or education
  entries — icon nodes on a connecting rail with a card per entry (role/
  qualification + organization/institution, optional year range). Configurable
  node/meta icons and full rail, card, and text style controls.
- New **Certifications & Publications** Elementor widget (Single DoctorPilot
  category): renders both the doctor's certifications (name, organization,
  credential link) and publications (title, journal · date, URL/DOI link) as two
  titled, icon-led lists in one widget. Each group toggles independently and hides
  when empty; full layout, icon (with box options), and text style controls.
  Publications can now show the **description/summary** and certifications the
  **issued/expiry dates** (each behind its own toggle), with a Description text
  style control — so every stored field can be surfaced on the frontend.
- New **Specialties** Elementor widget (Single DoctorPilot category): a
  check-marked, multi-column "scope of practice" list built from the doctor's
  procedures, treatments, specialties, conditions, symptoms, or clinical
  interests. Selectable icon and columns, with item/label/icon style controls
  (background, border, radius, hover, typography, colors).
- New **About Doctor** Elementor widget (Single DoctorPilot category): outputs the
  doctor's biography (or headline) for the selected/current doctor, with body-text
  typography/color/link/spacing and box style controls. Pair it with a Heading
  Block for the eyebrow/heading above it.
- New Elementor widget category **Single DoctorPilot** with a **Page header with
  dynamic data** widget. It renders a single-doctor hero from canonical data —
  breadcrumb, name, role, primary specialty/department/languages/email/phone,
  credential chips, photo with a specialty badge, and Book / Call actions — for
  the selected or currently viewed doctor. Style controls include Elementor's full
  **Background** group (Classic color/image with position, repeat, size,
  attachment; Gradient; and Video/Slideshow when Pro is active), an overlay color,
  and text/name/accent colors. An **Image** section provides width, aspect ratio,
  object-fit, border radius, border, box shadow, opacity, CSS filters (normal +
  hover), a hover animation, and transition. A **Photo badge** section styles the
  badge (typography, colors, padding, radius, border). An optional Availability
  text field is provided; Availability otherwise falls back to the doctor's
  location office hours.
- New **Single doctor layout** setting (DoctorPilot → Settings). Pick an Elementor
  template or page to render on every single-doctor view. Because the Doctor
  Profile widget resolves the currently viewed doctor, you can design the layout
  once with **free** Elementor — no Elementor Pro / Theme Builder needed. Defaults
  to the built-in native template, and falls back to it if the chosen template is
  empty or Elementor is inactive.
- New **International doctor** listing flag (checkbox in the doctor editor's
  Listing flags box). When enabled, an "International" tag shows on the doctor's
  card (top-right badge) and profile header.
- Extended the global content library to **Procedures, Publications, and FAQs**
  (in addition to Experience and Education). Each is now an admin-managed record
  type that can be created once and assigned to many doctors from the doctor
  editor's shared-record selectors, and each renders on the public profile merged
  with the doctor's own entries. `LibraryPostTypes` is now schema-driven, so all
  five types share one registration, editor, save, resolver, and REST path.
  Procedures/Publications/FAQs reuse their existing per-doctor repeaters as the
  "this doctor only" store, so no data migration is required. All five types are
  grouped under the "Global content" admin menu heading.
- Global reusable content library for Experience and Education (ADR-0017). Two new
  admin-managed record types — Experience (`doctorpilot_exp`) and Education
  (`doctorpilot_edu`) — let a hospital create a record once and assign it to many
  doctors; editing the record updates it everywhere it is used. Each record has a
  position/qualification, organization/institution, start/end year, country, and an
  optional description, with a read-only title composed from the first two. The
  doctor editor's Experience and Education boxes now offer shared-record selectors
  (chips) alongside the existing doctor-specific rows. A new authenticated
  `GET doctorpilot/v1/library/search` route backs the picker, a `DoctorContentResolver`
  merges shared and doctor-specific entries for display, and a reverse-reference
  index cleans up assignments when a shared record is deleted. Managed by a new
  `manage_doctorpilot_library` capability (granted to administrators).
- Department and specialty names containing an ampersand rendered as
  `Hepatology &amp; Endoscopy` on cards, list rows, and in the `/doctors` REST
  payload. WordPress stores term names HTML-encoded, and every consumer escapes
  again for its output context, so the entity survived to the screen. Term names
  are now decoded once in the doctor view model, which fixes the templates and
  the frontend script together.
- A stray radio button appeared next to the **Doctor Grid / List** search bar
  when a search-mode tab (By Doctor Name / By Department / By Symptom) was
  selected. The tabs are a real radio group styled through their labels, and the
  inputs were hidden with `.screen-reader-text` plus `clip` — but themes commonly
  un-hide that class on `:focus` (the skip-link pattern) and clicking a tab
  focuses its radio, while `clip` does not reliably clip a native control's own
  rendering in every browser. The inputs now carry a self-contained
  visually-hidden rule built on `appearance: none`, so no theme or browser can
  paint them. They remain keyboard-focusable, and the focus ring still shows on
  the tab label.
- The **Book** call-to-action on doctor cards was invisible: `.dp-directory a`
  out-specified `.dp-btn--primary`, painting the label the same colour as the
  button's own background. Link colouring now skips `.dp-btn`.
- **Elementor single-doctor templates now load Elementor's base frontend CSS** on
  the doctor page. Because the queried object is a doctor (not an Elementor
  document), Elementor previously skipped its own asset loading, so flexbox
  container styles (`.e-con`) never loaded and multi-column template layouts
  collapsed/overlapped on the frontend while looking correct in the editor. The
  plugin now registers (early, priority 1) and enqueues Elementor's frontend
  styles/scripts and the template's generated CSS on single-doctor pages that use
  a template. Registering the `elementor-frontend` handles early also resolves the
  WordPress 6.9 "dependencies that are not registered: elementor-frontend" notices
  raised by the theme's Elementor mega-menu templates on doctor pages.
- Doctor-specific ("this doctor only") **publications now display** alongside
  shared library publications. Library types whose title is a dedicated field
  (publications) have no composed `title_parts`, so the local-entry resolver was
  overwriting each row's real title with an empty string — blanking it and causing
  the item to be filtered out. The composed title is now only applied when it is
  non-empty, otherwise the field-derived title is kept. Affects any library type
  without `title_parts`.
- Single-doctor Elementor widgets no longer show the same doctor on every doctor
  page. The **current queried doctor now takes precedence** over a widget's
  Doctor ID, so one template renders each doctor correctly. The Doctor ID is now
  a fallback only — for editor preview or placing a widget off a doctor page.
- Doctor editor: **Primary specialty** (and Primary department) would silently
  revert on save when the chosen term wasn't one of the doctor's assigned terms —
  the dropdown listed every term, but a primary must be an assigned term, so it
  was cleared. The dropdowns now list only the doctor's assigned specialties /
  departments, and update live as you check/uncheck the term chips, so the
  selection always saves.
- Doctor editor: the location repeater's **Location** and **Department at
  location** fields are now taxonomy dropdowns instead of text boxes. They store
  term IDs, so a typed name was previously coerced to `0` on save; you now pick a
  term from the Location / Department taxonomy.
- Translation loading no longer runs before `init` (WordPress 6.7
  `_load_textdomain_just_in_time` notice). Both plugins load their text domains on
  `init`, and the Pro add-on registers with the base using an untranslated brand
  name so no `__()` call fires during the early `plugins_loaded` boot.
