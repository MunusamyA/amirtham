# Customer Theme Guide

## Final three-file rule

Do not customize:

- `assets/css/core.css`
- `assets/css/components.css`

Customize for each customer:

- `assets/css/theme.css`

## What `theme.css` controls

The theme file controls the complete visual design contract:

- primary/accent/status colors
- Light and Dark appearance surfaces/text/borders
- primary, heading and brand fonts
- font sizes and weights
- spacing and page density
- card/button/input/table/modal radius
- sidebar expanded/collapsed widths
- topbar height
- submenu width and viewport gaps
- button/input/control heights
- table row height
- icon size
- shadows and overlays
- transition speeds
- z-index design layers

This means one customer can be green/rounded/spacious and another can be blue/square/compact without changing PHP or JavaScript functions.

## Semantic variables only

Reusable components should use names that describe purpose, for example:

```css
.btn-primary {
    background: var(--primary);
    color: var(--text-on-primary);
    border-radius: var(--button-radius);
}

.card {
    background: var(--surface);
    border-color: var(--border);
    border-radius: var(--card-radius);
    box-shadow: var(--shadow-md);
}
```

Do not add customer values to `components.css`, and do not add value-named variables such as `--color-047857`.

## Light / Dark

Both appearances use the same semantic component names. `theme.css` changes the source values under `html[data-appearance="dark"]`; component selectors do not need separate hardcoded color palettes.

## Sidebar flyout safety

`layout.js` reads these theme variables:

```text
--topbar-height
--submenu-width
--submenu-gap
--viewport-gap
```

The collapsed desktop flyout is positioned below the actual topbar and clamped inside the viewport. Expanded desktop and mobile sidebars use inline submenus instead.

## Theme Settings

`assets/css/theme.css` is the default customer design. Theme Settings stores optional runtime color overrides in `app_settings`. Reset removes the DB override and returns to the values in `theme.css`.

Typography/radius/spacing/density/layout tokens remain directly customer-editable in the single `theme.css` file, so no extra DB schema is required for a new visual design.


## Built-in Theme Settings presets

`theme-settings.php` exposes 10 complete design presets: Forest Classic, Ocean Corporate, Royal Violet, Ruby Executive, Amber Warm, Teal Modern, Indigo Pro, Rose Soft, Slate Compact and Midnight Premium.

Presets are defined only in `assets/css/theme.css` using `html[data-theme-preset="..."]`. They may change typography, radius, density, spacing, sidebar/topbar/submenu dimensions, control sizes, table density, shadows and semantic palette. JavaScript only selects the preset ID and never owns the design values.

Optional color edits are saved as semantic `app_settings` overrides. A branch with its own preset is a complete branch theme and does not inherit platform color overrides; a branch without its own preset continues inheriting the platform theme.
