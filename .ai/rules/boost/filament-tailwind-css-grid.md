# Filament Custom Views & CSS Grid Blowouts

When creating or editing custom Blade views in a Filament panel (e.g. `pos-terminal.blade.php`), you MUST adhere to the following layout constraints:

1. **Do not rely on uncompiled Tailwind grid/spacing classes.** Filament's default Tailwind build does not include all arbitrary spacing (like `space-y-5`) or complex grid classes. If elements are overlapping or lacking gaps, write explicit CSS block definitions `<style>` with standard CSS instead of attempting to guess which Tailwind classes are compiled.
2. **Prevent CSS Grid Blowouts.** When defining CSS Grid columns using fractional units (`fr`), the default minimum width is `auto`. If a child element refuses to wrap (e.g. a row of category buttons or horizontal scrolling list), the column will blow out horizontally, breaking the layout. 
   - **Always** use `minmax(0, Xfr)` instead of `Xfr` (e.g. `grid-template-columns: minmax(0, 8fr) minmax(0, 4fr)`).
   - **Always** set `min-width: 0 !important;` and `max-width: 100% !important;` on the grid container and its flex/grid children that contain scrollable or non-wrapping content.
