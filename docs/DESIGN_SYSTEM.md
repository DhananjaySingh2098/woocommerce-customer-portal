# Design System

The portal ships one stylesheet, [`assets/css/portal.css`](../assets/css/portal.css), organised
as a token layer followed by components. Every colour, size, radius, shadow and
duration resolves through a CSS custom property, so retheming is a token swap
rather than a rewrite.

## Scoping rules

Three rules keep the plugin and the host theme out of each other's way:

1. **Tokens live on `.wcp-portal`, not `:root`.** A plugin has no business
   defining global variables on a site it does not own, and scoping means two
   portals could render side by side with different palettes.
2. **Every selector is namespaced `wcp-`.** A scoped reset inside `.wcp-portal`
   normalises the handful of elements themes reliably restyle (`a`, `button`,
   `ul`, headings, `dl`) without touching anything outside the container. There
   is no bare element selector anywhere in the stylesheet.
3. **`!important` is a last resort, and it is audited.** The stylesheet contains
   fourteen, in exactly three places: the visually-hidden utility (which must
   beat any theme rule to stay hidden), the reduced-motion block (which must
   override every component transition to do its job), and the print block. None
   exists to win a layout argument with a theme — where the portal needs to
   escape a theme's content column it uses the theme's own `alignfull`
   mechanism instead. See **Full-bleed layout** below.

The one intentionally unscoped rule is `.wcp-scroll-lock`, set on `<html>` while
the mobile drawer is open; it is inert at every other moment.

### The reset out-ranks bare component classes

The scoped reset zeroes margins and padding on the elements a theme is most
likely to style:

```css
.wcp-portal h1, .wcp-portal h2, .wcp-portal p,
.wcp-portal dl, .wcp-portal dd, .wcp-portal dt, .wcp-portal figure { margin: 0; padding: 0; }
.wcp-portal ul, .wcp-portal ol { margin: 0; padding: 0; list-style: none; }
```

Those selectors are `(0,1,1)`. A bare component class is `(0,1,0)`, so **a class
rule that sets `margin` or `padding` on one of those elements silently loses**
— no warning, no visual error until someone measures it.

The fix is to element-qualify the component selector so it matches the reset's
specificity and wins on source order:

```css
dl.wcp-detail-list { padding: var(--wcp-space-2) var(--wcp-space-5); }
ol.wcp-timeline    { margin-top: var(--wcp-space-6); }
```

**Headings are the widest case, and the easiest to miss.** The reset gives
`h1`–`h4` typography as well as spacing — `font-weight`, `letter-spacing`,
`color`, `text-transform` — because themes restyle headings heavily. That means
every heading component asking for its own weight or tracking was silently
rendering at the reset's `600 / -0.011em` until Phase 4, so the portal's
intended `600 → 700` scale existed only in the source. Those are written
`.wcp-portal .wcp-hero__title`, which is `(0,2,0)` and wins.

The same trap has now appeared for spacing (`dl`, `ol`), for links (`color`,
`box-shadow` — a primary button rendering near-black on purple), for buttons
(the first real `<button>`) and for headings. **A precise audit is cheap and
worth keeping**: build a map of what the reset forces per element, read which
classes sit on which elements from the templates, and flag any bare-class rule
whose properties intersect. Eyeballing does not find these — they look almost
right.

Lowering the reset with `:where()` would be the tidier fix and is the wrong one
here: the reset needs that specificity to beat theme rules such as
`.entry-content ul`, which is itself `(0,1,1)`. The reset is high-specificity on
purpose.

Rule of thumb: **if a component class sets spacing and its element appears in
the reset, qualify it.**

## Colour

Two layers of token, and the distinction matters:

- **Preset tokens** — `--wcp-bg`, `--wcp-surface`, `--wcp-text`, `--wcp-accent*`
  and the rest. A visual theme sets *these and only these*, always the complete
  list, never a partial one.
- **Derived tokens** — `--wcp-primary*`. Components consume these and never read
  `--wcp-accent*` directly. They alias the accent by default, which is what lets
  an administrator's custom accent override every preset without a specificity
  contest.

```
preset accent  →  --wcp-accent*   (a theme's own colour)
                       ↓ aliased by
admin accent   →  --wcp-primary*  (printed inline after the stylesheet)
                       ↓ consumed by
components     →  var(--wcp-primary)
```

Read top to bottom that is the precedence: **preset accent → administrator's
custom accent → the customer's appearance choice** (light or dark, which selects
*which* set of preset values is in play). A customer can change the theme and
the scheme; they can never change the accent an administrator has pinned.

### The contract a preset fills in

| Token | Role |
| --- | --- |
| `--wcp-bg`, `--wcp-bg-elevated`, `--wcp-bg-subtle` | The ground, and recessed fills |
| `--wcp-surface`, `--wcp-surface-raised`, `--wcp-surface-hover`, `--wcp-surface-tint` | Card surfaces and the bottom stop of their gradient |
| `--wcp-surface-blur` | Translucent sticky header |
| `--wcp-veil`, `--wcp-highlight`, `--wcp-grid-line` | Hover wash, inner top highlight, background grid |
| `--wcp-text`, `--wcp-text-strong`, `--wcp-muted`, `--wcp-muted-soft` | Type, in four weights of emphasis |
| `--wcp-border`, `--wcp-border-strong` | Hairlines, and hover/secondary edges |
| `--wcp-accent`…`--wcp-accent-contrast`, `--wcp-accent-rgb`, `--wcp-accent-2-rgb` | The theme's colour, plus a second hue for ambient washes |
| `--wcp-success`, `--wcp-info`, `--wcp-warning`, `--wcp-danger`, `--wcp-neutral` (+ `-soft`) | Status tones |
| `--wcp-shadow-color`, `--wcp-wash-a/b/c` | Shadow tint and the three ambient washes |
| `--wcp-avatar-base` | The hue monograms are built from |

**Contrast.** Every text/background pair on every screen is measured against
WCAG AA in both schemes and in every preset. `--wcp-muted` clears 4.5:1 on both
`--wcp-surface` and `--wcp-bg` everywhere; `--wcp-muted-soft` is reserved for
large text, icons and labels that repeat information available elsewhere.

**Monogram avatars.** Rather than calling Gravatar — an external request on
every page view, and a privacy consideration in the EU — avatars are initials on
a gradient. The preset owns the base hue (`--wcp-avatar-base`); the account
contributes a stable 0–18° shift derived from its identity and printed inline as
`--wcp-avatar-shift`. Two customers are told apart, and no monogram wanders out
of the theme it is sitting in.

## Visual themes

Five presets ship. Each is a block of preset tokens in section 21 of the
stylesheet — no second stylesheet, no duplicated components, no per-theme
classes on any element.

| Preset | Character | Accent |
| --- | --- | --- |
| **Aurora** (default) | Clean SaaS surfaces, soft blue-violet ambient glow | Violet / indigo |
| **Obsidian** | Graphite and charcoal, cool neutrals, metallic depth | Violet-blue |
| **Pearl** | Warm near-white, ivory, restrained and luxurious | Quiet blue-violet |
| **Midnight** | Deep navy, sophisticated night interface | Cobalt / indigo |
| **Emerald** | Charcoal with a forest undertone, financial calm | Emerald |

Each preset supplies a **complete** light block and a **complete** dark block,
so the two axes combine in any order without one theme's leftovers bleeding
into another:

```html
<html data-wcp-theme="dark" data-wcp-visual="midnight">
```

`data-wcp-theme` is `light` or `dark`; `data-wcp-visual` is one of the five
slugs. Both are stamped on `<html>` before first paint by `WCP_Theme`, and both
are validated in PHP against a fixed list before they reach the page. Neither
attribute ever restyles anything outside `.wcp-portal`.

"System" is stored as *nothing at all*: the absence of a stored appearance is
what lets the operating system — and the administrator's default — decide.

## Spacing

A 4px base scale. Components use tokens, never raw pixel values, so vertical
rhythm stays consistent as sections are added.

| Token | Value | Typical use |
| --- | --- | --- |
| `--wcp-space-1` | 4px | Icon gaps |
| `--wcp-space-2` | 8px | Button gaps, tight stacks |
| `--wcp-space-3` | 12px | Nav item padding, inline gaps |
| `--wcp-space-4` | 16px | Grid gutters, panel padding |
| `--wcp-space-5` | 20px | Card padding, sidebar padding |
| `--wcp-space-6` | 24px | Mobile section padding |
| `--wcp-space-7` | 32px | Main padding, section rhythm, hero padding |
| `--wcp-space-8` | 40px | Empty-state padding |
| `--wcp-space-9` | 48px | Main bottom padding, gate padding |
| `--wcp-space-10` – `--wcp-space-12` | 56 / 64 / 80px | Large empty states |

## Radius

| Token | Value | Applied to |
| --- | --- | --- |
| `--wcp-radius-xs` | 6px | Micro elements |
| `--wcp-radius-sm` | 9px | Buttons, icon buttons, small tiles |
| `--wcp-radius-md` | 12px | Nav items, avatars, icon tiles |
| `--wcp-radius-lg` | 16px | Cards, panels |
| `--wcp-radius-xl` | 22px | Portal shell, hero, gate |
| `--wcp-radius-full` | 999px | Pills, status dots, account chip |

Radii step up with surface size, which is what keeps nested corners looking
concentric rather than arbitrary.

## Typography

The system font stack (`-apple-system`, `Segoe UI`, `Roboto`, …). No webfont is
loaded: it costs a render-blocking request, and on every target platform the
system UI face is the one users already read all day.

Sizes are **px, not rem**, deliberately. A plugin renders inside themes it did
not write, and a theme that sets `html { font-size: 62.5% }` would otherwise
shrink the entire shell by 37.5%. Browser zoom scales px normally.

| Token | Size | Used for |
| --- | --- | --- |
| `--wcp-text-2xs` | 11px | Eyebrows, pills, nav headings (uppercase, tracked) |
| `--wcp-text-xs` | 12px | Metadata, feature captions |
| `--wcp-text-sm` | 13px | Secondary text, card body, detail rows |
| `--wcp-text-base` | 14px | Body default, nav labels, buttons |
| `--wcp-text-md` | 15px | Card titles, section titles, hero body |
| `--wcp-text-lg` | 17px | Page title in the header |
| `--wcp-text-xl` | 20px | Large empty-state titles |
| `--wcp-text-2xl` | 25px | Hero and gate titles |
| `--wcp-text-3xl` | 31px | Reserved |

Headings tighten as they grow — `-0.011em` at body size down to `-0.026em` on
the hero — which is what stops large type from looking loose. Weights run
500 / 550 / 600 / 650 / 680; variable-font-aware stacks use the intermediate
steps, and others round to the nearest available weight.

## Elevation

Layered, low-alpha shadows. Two soft layers read as depth; one hard layer reads
as a drop shadow. The tint comes from `--wcp-shadow-color`, so a warm preset
casts a warm shadow.

| Token | Use |
| --- | --- |
| `--wcp-shadow-xs` | Avatars, chip hover, action tiles at rest |
| `--wcp-shadow-sm` | Cards and panels at rest |
| `--wcp-shadow-md` | Elevated panels, status cards, stuck header |
| `--wcp-shadow-lg` | Hero, feature panels, card hover |
| `--wcp-shadow-xl` | Mobile drawer, appearance popover, gate card |
| `--wcp-shadow-3d` | A card while it is being tilted (adds an accent-tinted layer) |
| `--wcp-glow` | Accent halo on an interactive card's hover |
| `--wcp-inset-highlight` | `inset 0 1px 0 var(--wcp-highlight)` — the lip of light on every raised surface |
| `--wcp-ring` | `0 0 0 3px rgba(--wcp-primary-rgb, .24)` — reserved for form focus |

### Card tiers

Not every panel on a screen is equally important, so a page is composed from
three weights rather than one surface repeated:

| Class | Weight | Built from |
| --- | --- | --- |
| `.wcp-panel` | Secondary | Surface, hairline, `--wcp-shadow-sm`, inner highlight |
| `.wcp-panel--elevated` | Secondary, raised | The same at `--wcp-shadow-md` |
| `.wcp-panel--feature` | Primary | 22px radius, an accent wash off the top-left corner, `--wcp-shadow-lg` |
| `.wcp-panel--accent` | Attention | Accent-tinted fill and ring — for a small card that has something to say |
| `.wcp-metric` | Status card | Compact, gradient surface, icon tile, tabular value |
| `.wcp-action` | Action tile | One line, icon container, arrow revealed on hover |
| `.wcp-product` | Feature tile | Media tile plus name and price |

### Depth and 3D

Tasteful, CSS-only, and gated three ways. There is no 3D library.

```css
@media (hover: hover) and (pointer: fine) and (min-width: 1025px) {
  .wcp-portal[data-wcp-3d="on"] [data-wcp-tilt] { … }
}
```

| Token / hook | Value | Meaning |
| --- | --- | --- |
| `--wcp-perspective` | 1100px | The stage depth a tilting card is viewed through |
| `--wcp-tilt-max` | 5deg | Ceiling for the tilt; per-card `data-wcp-tilt-max` may lower it (4–6deg in practice) |
| `--wcp-lift` | -3px | Hover lift where the 3D system is switched off |
| `.wcp-depth-1/2/3` | translateZ 8 / 14 / 20px | Children that sit forward of the tilting surface |

`data-wcp-tilt` marks the stage. Script reads `pointermove` at most once per
animation frame, writes `--wcp-tilt-x` / `--wcp-tilt-y`, and the compositor does
the rest — there is no continuous loop and nothing is measured during the move.
It refuses to bind on touch, under reduced motion, below 1025px, when the
administrator has switched 3D off, and while a field inside the card has focus,
so a form never tilts under someone who is typing in it.

**3D never carries information.** Every card states everything it has to say
flat; depth is a finish, not a channel.

### Pointer spotlight

`data-wcp-spotlight` tracks the pointer across a surface and exposes
`--wcp-spot-x` / `--wcp-spot-y`, which a `.wcp-…__spot` child turns into a very
soft radial highlight — light moving over a premium surface. It is applied to
chosen cards only (hero, status cards, action tiles, product tiles), listens
passively, and is not bound at all on touch or under reduced motion.

## Motion

| Token | Value | Use |
| --- | --- | --- |
| `--wcp-duration-fast` | 160ms | Colour, background, border, press feedback |
| `--wcp-duration-normal` | 240ms | Transform, shadow, icon micro-movement |
| `--wcp-duration-slow` | 420ms | Entrance animations, progress fill |
| `--wcp-duration-button` | 200ms | Button and text-link interactions |
| `--wcp-stagger-step` | 40ms | Delay multiplier between staggered items |
| `--wcp-ease` | `cubic-bezier(.32,.72,0,1)` | Default: fast start, long settle |
| `--wcp-ease-out` | `cubic-bezier(.16,1,.3,1)` | Entrances |
| `--wcp-ease-in-out` | `cubic-bezier(.65,0,.35,1)` | Symmetric moves, ambient drift |

Everything interactive sits inside the 160–420ms band. The exceptions are
deliberate: the nav indicator travels for 360ms because it crosses a longer
distance, a tilting card tracks the pointer at 80ms linear so it does not lag
behind it, and the section transition is 180ms out / 240ms in.

**Dashboard entrance order.** Set in markup, not script: each animated element
carries `style="--wcp-stagger: N"` and the delay is
`calc(var(--wcp-stagger) * var(--wcp-stagger-step))`.

| Block | N | Delay |
| --- | --- | --- |
| Hero | 0 | 0ms |
| Status cards | 1–4 | 40–160ms |
| Main panel (Recent orders / Getting started) | 5 | 200ms |
| Rail (Account setup, Quick actions) | 6 | 240ms |
| Account health, Latest products | 7–8 | 280–320ms |
| Recent activity | 9 | 360ms |

Nothing measures the DOM, so there is no layout thrash and no flash of unstyled
content. Entrances animate `translate`/`scale` — the individual properties — so
`transform` stays free for hover lift and tilt.

**Ambient drift.** The canvas washes drift on 22–52s loops at ~3% amplitude —
slow and small enough to register as depth rather than movement. They run only
behind the hero and the sign-in screen, and are switched off entirely under
reduced motion or when the administrator turns motion off.

**Administrator switches.** `data-wcp-motion` and `data-wcp-3d` are rendered on
the portal root from settings, so a store can turn either off without waiting
for script and without JavaScript enabled at all.

**Reduced motion.** `@media (prefers-reduced-motion: reduce)` collapses every
duration and delay to ~0, cancels entrance animations and stagger, forces
`.wcp-animate` elements to `opacity: 1`, stops the ambient drift and the
skeleton shimmer, holds the progress bar at its value rather than drawing it
arriving, and removes the pointer spotlight outright (`display: none`) rather
than leaving a gradient that jumps between positions — script also declines to
bind tilt or spotlight at all. Affordances that relied on movement are replaced
rather than dropped: a card's hover lift becomes an accent border, and revealed
arrows are simply present. No content is ever hidden, and focus rings are never
touched.

## The ambient canvas

Both portal states share one background so they read as a single product. Four
layers, all CSS, all on composited elements:

```
.wcp-canvas
├── .wcp-canvas__wash    three accent-hued washes, 22s drift
├── .wcp-canvas__grid    64px grid, radially masked to fade out downward
├── .wcp-canvas__glow    a/b/c localised glows, 32s / 42s / 52s drift
└── .wcp-canvas__noise   a whisper of SVG noise so large flats never band
```

Every layer is a CSS gradient on a positioned element — no images (bar the
inline noise), no `filter`, no blur. Each preset re-tints the whole stack
through `--wcp-wash-a/b/c` and `--wcp-grid-line`. The authenticated shell uses
the same markup at reduced opacity with the wash held still, since content
carries that screen and the background should recede.

## Full-bleed layout

The portal is an application surface, not an article, so by default it escapes
the theme's content column (`layout="contained"` opts out).

Getting this right without harming the theme took two mechanisms:

1. **Themes that understand wide alignment** get the core `alignfull` class,
   added by `WCP_Helper::full_width_classes()`. Block themes constrain content
   children with `margin-left: auto !important` — deliberately, so nothing can
   out-shout it — and Twenty Twenty-One states the same idea literally, as
   `.entry-content > *:not(.alignfull):not(…)`. Both are the theme saying which
   elements are exempt from its column, so the plugin answers in that
   vocabulary rather than trying to out-specify a rule the theme wrote on
   purpose. Block themes are detected with `wp_is_block_theme()`; classic themes
   with `current_theme_supports( 'align-wide' )` — block themes report `false`
   for that flag, so both signals are needed.
2. **Every other theme** gets a measured fallback: script reads the portal's
   distance to each viewport edge and writes it back as
   `--wcp-bleed-left/right`, which CSS applies as negative margin. The offsets
   are signed, so a theme that *overshoots* — `width: 100vw` includes the
   scrollbar — is pulled back in rather than left with a horizontal scrollbar.

The fallback is gated behind a `data-wcp-bleed` attribute that script sets
*only* when the portal is not already full width, so the two mechanisms can
never fight each other. Measurement uses `documentElement.clientWidth`, which
excludes the scrollbar — `100vw` would overflow by its width. Negative margins
are used rather than `transform: translateX(-50%)` because a transform would
make the portal a containing block for fixed positioning and break the mobile
drawer. With JavaScript disabled the fallback simply never applies and the
portal renders inside the theme column, fully usable.

## Chromeless page framing

A full-width portal is an application, so the page around it should stop
behaving like an article: no duplicate page title above the portal's own
heading, and no editorial whitespace between the site header and the app.

`WCP_Page_Layout` resolves this before any output, by reading the shortcode out
of the post content rather than waiting for it to render — in a block template
the title block is emitted *before* post content, so by the time the shortcode
runs it is already too late.

**The title** is removed at its source, never merely hidden:

- **Block themes** — a `render_block` filter returns an empty string for
  `core/post-title` on that page. `render_block` is core API, so this works with
  every block theme without the plugin knowing anything about the theme's
  markup. A title rendered inside a query loop is left alone.
- **Classic themes** — there is no equivalent hook (`the_title` also feeds
  menus, feeds and the document title), so the fallback is CSS. It is printed
  inline on the portal page only, so every other page receives zero extra bytes,
  and every selector is prefixed with the `wcp-portal-chromeless` body class.
  Extend it through `wcp_classic_title_selectors`. A theme that names things
  differently simply keeps its title — the failure mode is "no change".

**The whitespace** is closed by the portal moving itself, never by rewriting
spacing on the theme's own nodes. Script measures the empty gap between whatever
actually sits above the portal and the portal's top edge, and feeds it back as
`--wcp-pull-top` (and `--wcp-pull-bottom` below), applied as negative margin
behind a `data-wcp-fit` attribute.

Two details make that measurement trustworthy:

- The ceiling is the *lowest visible preceding sibling* at any ancestor level,
  so only genuinely empty space is ever reclaimed. A paragraph above the
  shortcode stops the pull; the site header stops it too.
- Wrappers left behind after the title is removed do not count. Twenty
  Twenty-Four keeps a padded group where the title block used to be, so the
  script skips siblings that are visually empty — no text, no media, no
  background, no border. Anything a visitor could actually see counts as
  content and stops the portal there.

`--wcp-chrome-top` records the height of that chrome, letting the portal claim
`100svh` minus the site header so the shell fills the viewport instead of
floating in a band of white. With JavaScript disabled nothing moves and the
portal keeps the theme's spacing.

### The theme footer

The same argument applies at the bottom of the page, and more sharply: the
portal prints [its own application footer](#the-portal-footer), so a theme footer
underneath it is a second ending — two sets of links, two copyright lines, and a
tall band of editorial whitespace after the application has already closed.

So a full-width portal page ends with the portal's footer. That decision is
`should_suppress_footer()`, it tracks the chromeless decision, and it is carried
on the body as `wcp-portal-standalone`:

- **Block themes** — a `pre_render_block` filter returns an empty string for the
  template part whose `area` is `footer`. Area is core vocabulary, not a theme's
  markup, so this holds for Twenty Twenty-Three, -Four, -Five and anything else.
  Short-circuiting before the render means the navigation block inside the footer
  never costs a query on the portal's page. When a part states no area, the
  template part's own registration is asked, and the slug is the last resort as
  an exact match — a theme's `footer-newsletter` part is never mistaken for the
  footer.
- **Classic themes** — `get_footer()` has nothing to intercept, so the same
  inline-CSS fallback used for the title carries a second, short selector list.
  Extend it through `wcp_theme_footer_selectors`.

The selector list never says bare `footer`. The portal's own ending is a
`<footer>` too, so every selector names a theme footer specifically — the block
template's footer area, `.site-footer`, `#colophon`, `.entry-footer`, a footer
widget area anchored behind `#content` so a real sidebar can never match — and
`:not(.wcp-footer)` guards the generic ones on top of that.

Once nothing follows the portal, `measureFloor()` has no sibling to find and
falls back to the end of the document, so the trailing padding an ancestor holds
below the content is reclaimed as `--wcp-pull-bottom` too. The measured result is
that the portal footer's bottom edge *is* the bottom of the document.

A site whose theme footer carries something the portal does not — a cookie
notice, a legally required disclosure — keeps it by returning false from
`wcp_suppress_theme_footer`. The title decision is independent.

None of this applies to `layout="contained"`, which stays an ordinary piece of
page content, page title and theme footer and all.

## Components

**Status cards** (`.wcp-metric`) — a compact card: icon tile, uppercase label,
tabular value, and a foot that carries either one line of context or a status
pill plus an arrow that appears on hover. The value drops a size when it is a
word rather than a figure, so "Needs attention" does not shout over "12".

**Steps** (`.wcp-steps`) — a numbered list joined by a rail. Each row is done,
current or outstanding, and says so in a word as well as in colour; a completed
row swaps its number for a check, and an outstanding row is a link to the screen
that completes it.

**Progress** (`.wcp-progress`) — a track, a gradient fill sized by
`--wcp-progress`, and the same count in words beside it. `role="progressbar"`
with `aria-valuetext` carries it to assistive technology. The fill animates from
empty once, and holds still under reduced motion.

**Action tiles** (`.wcp-action`) — icon container, one line of label, an arrow
revealed on hover, and a pointer spotlight. Compact by design: it is a shortcut,
not a card.

**Product tiles** (`.wcp-product`) — a 52px media tile (image, or a tinted glyph
where the product has no photograph), name clamped to two lines, price, and an
outbound arrow. Tilts at 4deg, one step gentler than a status card.

**Health rows** (`.wcp-health`) — a dense list: tone-coded icon, label, and a
state chip whose *word* carries the state. The theme row is the one client-side
value on the screen; the server renders the configured default and the portal
script corrects it on init.

**Buttons** —**Cards** — surface, hairline border, `shadow-sm`. Action cards lift 3px on
hover, deepen to `shadow-lg`, tint and scale their icon tile, and translate the
arrow. Every transition is on `transform`/`opacity`/`box-shadow`, so nothing
triggers layout.

**Panels** — same surface treatment, but structured: a bordered head, a body,
and an optional full-width footer link.

**Empty states** — concentric ring behind a bordered glyph, a title, a sentence
of explanation, and where useful an action. They enter with a `scale(.965)` pop
at 220ms, after the surrounding card has settled.

**Brandmark** (`.wcp-brandmark`) — a pill pairing a gradient mark with the store
name and a `Customer portal` label. Store identity comes from
`get_bloginfo( 'name' )`; the plugin ships no branding of its own.

**Benefit rows** (`.wcp-benefit`) — icon tile, title, one line of description.
Hover lifts the surface, tints the tile border and slides the row 3px.

**Auth card** (`.wcp-authcard`) — the most worked surface in the system: a
vertical surface gradient, a 1px inset top highlight that reads as a real
material edge, `shadow-xl`, a lock mark ringed in 6px of translucent accent, and
a pointer-tracked radial wash. The wash is bound only for fine pointers and
never under reduced motion; it repositions a gradient and touches no layout.

**Text link** (`.wcp-textlink`) — underline drawn with a `scaleX` transform from
the left on hover *and* on `:focus-visible`, so keyboard users get the same
affordance as mouse users.

## Orders

### The list/table hybrid

Order history is tabular data that has to become cards on a phone, so it is
built as a grid rather than a `<table>`:

- `.wcp-orders__head` is presentational and `aria-hidden`. It names the columns
  for sighted desktop users and nothing else.
- The semantics live in `ul.wcp-orders__list`, where each order is one `<li>`
  containing one `<a>`. That gives one tab stop per order, a target the size of
  the row, and a structure that restacks without changing meaning.
- Both the header and the row read the same `--wcp-orders-cols` template, which
  is what keeps the columns aligned.

Below `900px` of *container* width the template is redefined to two columns and
the per-cell labels — hidden on desktop because the header already names them —
take over. Nothing is duplicated for mobile; the same markup means two things.

Order number and status are placed explicitly at `grid-row: 1`, because the
status cell follows the date in the DOM (correct for the table) and
auto-placement would otherwise strand it on a row of its own.

### Status pills

Tones are assigned in PHP by `WCP_Order_Status`, from a closed list of five:
`success`, `info`, `warning`, `danger`, `neutral`. A store that registers its own
statuses — and plugins do this constantly — gets `neutral` and WooCommerce's own
label, so an unknown status always renders correctly, just without a bespoke
colour. `wcp_order_status_tone` assigns one if wanted.

The dot is decorative and `aria-hidden`; the status is conveyed as text.

### The timeline

Three fixed steps, and only for orders that actually progressed linearly
(`pending → processing → completed`). WooCommerce does not store a status
history on the order, so a richer delivery tracker would be invented rather than
derived. Cancelled, refunded and failed orders render no timeline at all, which
is more honest than drawing one that implies a journey they did not take.

### Skeletons

Orders are server-rendered, so opening one is a real page load. The skeleton
bridges that gap and only that gap:

- Script reveals it **180ms** after activation. A skeleton that flashes for 40ms
  on a fast connection is worse than none.
- It mirrors the row's grid geometry, so the panel does not resize.
- The shimmer is a moving `background-position`, not a moving element.
- Under `prefers-reduced-motion` it becomes a flat placeholder. It is a loading
  indicator, not decoration, so it still appears — only the motion goes.
- `aria-busy` and a `role="status"` line announce it.

Returning through the bfcache restores the old DOM verbatim, skeleton included,
so `pageshow` clears it — otherwise a visitor pressing Back lands on a loading
screen that will never finish.

## Forms

### Field anatomy

Every control in the portal renders through `partials/field.php`, so the label
association, the `aria-describedby` wiring and the error markup are written once
and are correct everywhere rather than re-derived per form.

The error element is **always in the DOM and always referenced** by
`aria-describedby`, even while empty and hidden. A `display: none` element is
outside the accessibility tree, so nothing is announced until it has something
to say — and when it does, no one has to rewrite `aria-describedby` at that
moment. Wiring it up front is more reliable than wiring it during the failure it
describes.

Required is marked three ways, because colour alone is not a signal: an asterisk
beside the label, a visually hidden "(required)", and the native `required`
attribute. The asterisk sits *next to the label text*; pushed to the far edge it
reads as a separate control. The "Optional" marker is the one that belongs on
the right, because it qualifies the field rather than naming it.

### The form-control reset

Themes style inputs heavily, so `input`, `select` and `textarea` are reset at
`(0,1,1)` alongside links and buttons — high enough to beat `.entry-content
input`. The same rule from the scoping section applies: a component class that
restyles a control must be written `.wcp-portal .wcp-input`, not `.wcp-input`.

This bit twice more in Phase 3. `.wcp-button` was a bare class, which was
invisible while every button in the portal was an `<a>`; the first real
`<button>` would have rendered with the reset's padding and border. Same fix.

### Feedback

One status region per form, rendered even when empty, with `role="status"` and
`aria-live="polite"`. A live region created at the moment it gains content is
frequently missed; one that already exists and changes is announced reliably.
`status` rather than `alert` because the message follows an action the customer
just took — it does not need to interrupt.

On failure, focus moves to the first invalid field. A keyboard or screen-reader
user is taken to the problem rather than told one exists.

### Progressive enhancement

The forms are real `<form method="post">` elements with a real action and a
nonce. Without JavaScript they post, `WCP_Form_Handler` validates and redirects
(Post/Redirect/Get, so a refresh never resubmits), and the outcome travels in a
short-lived transient — not the URL, because field errors and typed values are
too big for a query string, and a URL reading "your password was wrong" ends up
in browser history.

With JavaScript the same forms are sent to the REST routes and the result is
rendered in place. Both paths call the same validators, so they cannot disagree.

A failed address save returns to the *form*, not the list: field errors are only
actionable where the fields are.

### No optimistic UI

Deliberately. An optimistic save shows success before the server has confirmed
it, and a rollback then has to un-tell the customer something. Saves show a busy
state and report only what came back.

## Dashboard

### A composition, not a list of widgets

The dashboard is built from modules that each answer for themselves whether
they have anything real to show. A module with nothing behind it does not
render — it is never a placeholder and never a zero dressed up as a metric.

```
Hero                    identity, and the one thing worth saying
Status cards            four compact cards of real account state
Main + rail             Recent orders │ Account setup + Quick actions
  (or)                  Getting started │ Quick actions
Account health          what checkout will use  │  Latest products
Recent activity         only when WooCommerce recorded something
```

Row 3 is `minmax(0, 1.7fr) minmax(280px, 1fr)`; the rail stretches to the
height of the main panel and its last card takes up the slack, so the row ends
level. Row 4 is two even columns that keep their own heights. Below 900px every
grid collapses to one column and the rail becomes two side-by-side cards; below
620px everything is a single column and the status cards stay two-up.

### Both states are designed

A customer with no orders is not shown an empty screen with one large "nothing
here" panel. They get the same composition, with **Getting started** where
Recent orders would be:

| Module | Where the data comes from |
| --- | --- |
| Total orders | `WCP_Orders` — a real `0`, stated plainly |
| Saved addresses | `WCP_Addresses::has_address()` per type |
| Profile | Is there a first *and* last name on the account |
| Member since | `wp_users.user_registered` |
| Getting started / Account setup | The five conditions below |
| Quick actions | Sections that are actually enabled |
| Latest products | The store's own newest catalog-visible products |

### The one percentage

`WCP_Dashboard::setup_state()` counts five real conditions — account created, a
name on file, a billing address, a shipping address, a first order — and the
percentage is completed over offered. Nothing is weighted or projected, so
"60% complete" always means "three of these five are done", and every
outstanding row links to the screen that finishes it. Steps belonging to a
section an administrator has switched off are not offered at all rather than
counted against the customer forever.

The bar carries `role="progressbar"` with a spoken value, and the same count is
printed beside it in words, so the measurement never lives in colour alone.

### Store discovery, not recommendation

The Latest products panel queries the most recently published,
catalog-visible products — the same set the shop page would show, so the portal
can never surface something the store has hidden. Nothing about it looks at the
customer, which is exactly why it is called **Latest products** and not
"Recommended for you". With an empty catalog it becomes an invitation to the
store rather than an empty grid, and a product without a photograph gets a
tinted glyph tile rather than a blank square.

### Metrics without fiction

There are **no percentage deltas**. A "+12% this month" needs a prior-period
figure that nothing in this plugin records, so it would be decoration shaped
like data. Lifetime value comes from `wc_get_customer_total_spent()` rather than
being summed here, so it agrees with WooCommerce about refunds and unpaid
orders instead of quietly disagreeing.

### Activity, and what it is not

WooCommerce does not keep a status history on an order — established in Phase 2,
when the order timeline was deliberately limited for the same reason. So there
is no "status changed to X" feed, because reconstructing one would mean
inventing it.

What WooCommerce *does* record is three timestamps per order: placed, paid,
completed. Those are real, they belong to the customer, and they are enough.
Orders missing a timestamp contribute fewer entries rather than guessed ones,
and milestones an order reached in the same minute collapse to the most advanced
of them — a store that marks an order paid and completed in one action would
otherwise produce two entries seconds apart saying nearly the same thing.

## The portal footer

The application has its own footer, and it is not the theme's. One component —
`templates/partials/portal-footer.php` — is rendered by the shell after
`.wcp-main`, so every authenticated section ends the same way and no section
template carries footer markup of its own. The signed-out screen renders the
same component in its compact form.

**It exists to finish the page.** A short section (Addresses, a saved form) used
to trail off into empty canvas. `.wcp-main` carries `flex: 1` inside the
column, so the content absorbs the slack and the footer settles at the bottom
of the shell — near the bottom of the viewport on a short page, directly after
the content on a long one. It is never fixed, never overlaps, and never appears
mid-form.

**Four groups, all real:**

| Group | Source |
| --- | --- |
| Brand + one sentence | `get_bloginfo( 'name' )` |
| Quick actions | Shop, Orders, Addresses, Profile — only where the destination exists |
| Account | The navigation model, so a disabled section is absent and the current one is marked |
| Store | `wc_get_page_id()` per page; a store with no Cart page gets no Cart link |
| Account status | `WCP_Addresses::has_address()` per type, the active theme, and the order count *only* on a screen that already loaded it |

Nothing here costs the page a query it was not already making: the footer never
runs an order query of its own, which is why the order count appears on the
dashboard and nowhere else.

**The bottom bar** is `© {year} {store}` plus whichever of Privacy
(`get_privacy_policy_url()`), Terms (the WooCommerce terms page) and My account
actually exist. A missing page is omitted, never linked into nothing, and the
copyright is the store's own name rather than a hardcoded company.

**Surface.** Deliberately not a card: a recessed ground
(`--wcp-bg-elevated` → `--wcp-bg-subtle`), a hairline of accent light fading
out across the top edge, and one soft accent glow in the corner. It reads as
the end of the page rather than as one more panel on it, and every value is a
token, so all five themes and both schemes come out right without a single
per-theme rule.

**Depth.** The footer never tilts. Its action pills lift 2px and their icon
tiles lift 1px on hover; state rows gain a surface and a border. That is the
whole interaction budget.

**Motion.** The footer fades and rises 10px the first time it reaches the
viewport, through an `IntersectionObserver` — with two rules that matter: the
hidden state is applied *by script*, so no-JS and reduced-motion visitors see
it plainly, and an element already on screen at bind time is revealed without
animating rather than being hidden first. Links move 2px on hover with an arrow
that fades in; there is no per-link stagger.

**Responsive.** The footer is its own container (`@container wcp-footer`), so it
adapts to the width it has rather than the viewport's: three columns, then two
with the brand spanning, then a single column at 560px where the quick actions
become a two-column grid of tap targets and the bar stacks.

**Semantics.** A `<footer>` containing a `<nav aria-label="Customer portal
footer">`; every link is a word, icons only ever accompany one; the current
section carries `aria-current="page"`.

## Client-side section switching

An enhancement over the server-rendered portal, not a replacement for it. Every
link is a real, shareable URL that renders the same page on its own; script
intercepts the click, fetches that URL, and swaps the content column.

The response is a whole HTML page, parsed with `DOMParser`. That is deliberately
dumber than a JSON API: there is one renderer, on the server, and no second
template layer in the browser that could drift away from it.

What the swap keeps in sync: the content column, the header title and subtitle,
the sidebar's active class and `aria-current`, the portal's `data-wcp-section`,
and the sliding indicator. Focus moves to the main region so a keyboard or
screen-reader user lands in the new section rather than being left where they
were with no signal anything changed.

Anything cross-origin, or pointing at a different path, is left to the browser.
A failed or non-OK fetch falls back to a real navigation rather than stranding
the customer on a half-updated screen, and a newer click supersedes an in-flight
older one by token.

Swapped-in markup carries its own controls, so forms and pending-navigation
links are rebound after each swap.

**The fallback is invisible, which is the point.** In headless Chrome a
same-document Back discards the document and reloads — verified with a control
page carrying no plugin code at all — and the customer still lands on exactly
the right section, because the URL was always a real deep link.

## Layout and breakpoints

The shell is a two-column grid: a `272px` sidebar and a fluid content column
capped at `--wcp-content-max` (1120px) and centred. The header shares that cap
so the page title aligns with the cards beneath it.

Two independent breakpoint systems, each answering the question it is actually
suited to:

**Container queries** (`@container wcp-content`) drive the content grids. A
portal dropped into a theme's 650px content column is *not* a mobile layout even
on a 1440px screen — asking the container is the only way to get that right.

| Container width | Behaviour |
| --- | --- |
| ≤ 1040px | Status cards → 2 columns |
| ≤ 900px | Orders table → cards; every dashboard grid → 1 column; the rail becomes two side-by-side cards; quick actions → 2 columns |
| ≤ 760px | Hero stacks (identity card first); order facts → 2 columns |
| ≤ 620px | Single column throughout, except status cards which stay 2-up; steps lose their arrow and tighten; form grids → 1 column |

The logged-out screen queries its own container (`wcp-gate`):

| Container width | Behaviour |
| --- | --- |
| ≤ 980px | Split hero → single column, capped at 620px |
| ≤ 620px | Tighter row padding and gaps |

**Viewport media queries** drive chrome, which genuinely is a viewport concern.

| Viewport | Behaviour |
| --- | --- |
| ≤ 1024px | Sidebar becomes a fixed off-canvas drawer with scrim; toggle appears; tilt and spotlight are never bound |
| ≤ 900px | Grid fallback for browsers without container query support |
| ≤ 760px | Hero stacks (avatar first); gate features stack; account chip drops its name |
| ≤ 640px | Smaller radii and padding; header subtitle hidden; detail rows stack; buttons go full width |

At 390px the dashboard is a real phone layout rather than a shrunken desktop:
a compact hero with the identity card above the greeting, status cards two-up,
stacked steps, single-column action tiles, and no pointer effects of any kind.

Verified with no horizontal scrolling at 1680, 1440, 1280, 1024, 768 and 390px,
in both full-bleed and contained modes, logged in and logged out.

## Light and dark

Appearance is one of the two theming axes (the other is the visual theme). The
component layer never references a literal colour, so dark mode is a token swap:
the same names redefined for each preset's dark ground.

**Three things have to line up.**

*The attributes must be set before first paint.* The preference lives in
`localStorage`, which CSS cannot read, so `WCP_Theme` prints a small synchronous
script in `<head>` — before the stylesheet — that resolves both choices and
stamps `data-wcp-theme` and `data-wcp-visual` on `<html>`. Without it a customer
who chose dark sees a white flash first. It is inline rather than a file because
a second request between markup and paint is exactly the window it exists to
close, and it is printed only on pages that actually host the portal. Every
value interpolated into it is a class constant or a string validated against a
fixed list, and the stored visual theme is re-checked in the browser against a
regular expression built from that same list.

*The system decides by default.* With nothing stored, `prefers-color-scheme`
wins, and keeps winning: the script listens for changes, so a customer who has
never picked a theme sees the portal follow their machine. An explicit choice
outranks it from then on, and choosing "System" again deletes the stored key
rather than storing the word "system".

*The site is never restyled.* The attributes go on `<html>`, but the palette is
scoped to `.wcp-portal` descendants. Turning the portal dark must not turn
someone's theme dark. The consequence is that the portal has to paint its own
`--wcp-bg`, or a light page would show through behind a dark shell — and the
theme's header and footer stay light above and below it. That seam is the
correct trade: the alternative is a plugin that recolours a site it does not own.

**Every palette is audited, not assumed.** Every text/background pair on all
eight screens is measured against WCAG AA in both schemes. Two bugs came out of
first running that audit against dark: `--wcp-muted-soft` measured 3.58:1 on
`--wcp-surface-hover`, and the line-item quantity badge was a literal `#fff` on
`--wcp-text-strong` — which in dark mode is itself near-white, so it measured
1.06:1 and was invisible. Both are fixed; the badge now uses the accent pair,
which inverts with the theme.

### The appearance switcher

A trigger in the header opens a popover holding two radio groups: Appearance
(System / Light / Dark) and Theme (the five presets, each with a miniature
swatch of its ground, card and accent). The selected preset carries an accent
ring, a glow and a check — three signals, so the choice never rests on colour
alone.

It is rendered unchecked on the server, because the server cannot know the
preference; the portal script corrects `aria-checked` on init, so the control is
never wrong for longer than a frame. Keyboard: the trigger opens the dialog and
focus moves to the checked option, arrow keys move within a group and select,
Escape closes and restores focus to the trigger, as does a click outside or Tab
leaving the dialog. Nothing here depends on being signed in, and nothing here is
sent to the server.

## Configurable accent

An administrator picks one colour; the design system needs seven values — the
accent, a hover, an active, two soft backgrounds, an rgb triplet for alpha
compositing, and a text colour that reads on top. `WCP_Settings::accent_palette()`
derives all of them from the single validated hex, so the component layer stays
coherent whatever is chosen.

Two decisions worth knowing:

- **Button text is chosen, not assumed.** White on a pale accent is unreadable.
  The palette compares WCAG contrast against both white and near-black and takes
  the better; the settings screen reports the ratio and warns below 4.5:1.
- **Dark mode gets its own derivation.** The shipped dark accent is lighter than
  the light one for the same reason: a saturated colour that reads on white sinks
  into a dark surface. A custom accent is lifted 28% towards white for dark, and
  its soft backgrounds blend towards the dark canvas rather than towards white.

The palette is printed with `wp_add_inline_style()` only when the accent differs
from the default, as `.wcp-portal { … }` and `[data-wcp-theme="dark"] .wcp-portal
{ … }`. Every value is re-checked against a strict pattern at output: nothing an
administrator typed reaches CSS as a string.

**Where it sits in the order.** The palette is printed with
`wp_add_inline_style()` as `--wcp-primary*` — the derived tokens — *after* the
stylesheet. Presets only ever set `--wcp-accent*`. So an administrator's accent
overrides every preset in every scheme without a specificity contest, and the
documented precedence holds: **preset accent → administrator's custom accent →
the customer's appearance choice**.

## Other display modes

`@media print` cancels animations, forces content visible and drops the sidebar
and scrim. `@media (forced-colors: active)` hands borders back to system
colours so Windows High Contrast users get real edges instead of tinted fills.
