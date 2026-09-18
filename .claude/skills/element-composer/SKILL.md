---
name: element-composer
description: Tune or create presets for the admin element composer (admin/element-composer.php) — animated SVG tiles built from SVG filters. Use when asked for a new element tile (water, lava, mud, fog, blood…), a better preset, or to check how a tile looks side by side on the board.
---

# Element composer presets

The page builds one 50×50 SVG from a parameter set, previews it and saves it to
`img/<couche>/<nom>.svg`. Presets live in the `PRESETS` object of the page's script.
A saved SVG carries its parameters in `data-composer`, so any file can be reloaded.

## Parameter model

| key | values | effect |
|---|---|---|
| `style` | clouds, turbulence, streaks_h, streaks_v, cells, cracks, relief | how the noise is shaped: soft / ridged / smeared on one axis / posterised / thresholded veins / embossed |
| `anim` | drift_h, drift_v, drift_d, sway, flicker | scroll one tile period (seamless loop), rock a few units there and back, or jump between seeds |
| `speed` | s, 0 = static | loop length; 8–16 s for slow liquids, 0.5 s flicker for fire |
| `shape` | square, round, blob, splash, drops, band_h, band_v, corner | `square` tiles with its neighbours; the others are per-cell patches |
| `freq` | 0.005–0.3 | grain; 0.03 large blobs, 0.12 fine grain |
| `octaves` | 1–5 | detail |
| `seed` | 0–99 | rerolls texture AND the random shapes' outline |
| `colorA` / `colorB` | hex | dark / light end of the colour map |
| `alphaLo` / `alphaHi` | 0–1 | opacity of the dark / light end |
| `gamma` | 0.3–3 | contrast: < 1 pushes to the light colour (glowing veins), > 1 to the dark |
| `blur` | 0–3 | softens; safe at tile edges |
| `edge` | 0–1 | softness of round shapes, coverage of splash / drops |
| `detail` | 0–1 | weight of a second noise at triple frequency blended in (grain, gravel, chop) |
| `light` | none, diffuse, specular, both | the luminance is read as a height map: diffuse multiplies (volume, crust), specular adds clipped glints (wet) |
| `relief` | 0.5–8 | surfaceScale of the light: 1–2 subtle, 4+ dramatic |
| `base` | catalog path or '' | a raster tile displaced by the noise instead of a coloured noise; `scale` and `hue` then apply |
| `opacity` | 0–1 | global |

## What is known to work

- Lava: `turbulence` (ridged) + `gamma` 0.55 + `detail` 0.35 + `diffuse` light 2.5, deep red to
  red-orange, slow `drift_v`. Water: `streaks_h` + `detail` 0.25 + `specular` 2. Stone: `clouds`
  + `detail` 0.5 + `diffuse` 4. Swamp: `clouds` + `detail` 0.4 + `specular`, slow diagonal drift.

- Full-cell liquids: `square` + a drift. Lava reads best as `turbulence` with `gamma` 0.6,
  deep red to red-orange, `drift_v` 12 s. Water: `streaks_h` + `drift_h`.
- Patches on the ground (blood, mud, poison): `splash` or `blob`, `speed` 0 or a slow drift.
- Fire: `streaks_v` + `flicker` 0.5 s + `round` with `edge` 0.5.
- Fog: `clouds`, pale colours, `alphaLo` 0.1, `blur` 1, slow `drift_h`.
- `type="turbulence"` is never emitted: Chrome's stitching leaves a hairline seam on it. The
  `turbulence` style folds fractal noise (|v-0.5|·2) instead, which tiles cleanly.
- Diffuse light is multiplied by 1.4 to compensate the cos(45°) darkening of flat areas.
- The "bords sur le damier" preview shows a 3×3 patch through the board's own edge masks
  (`View::elementEdgeDefs`): an element fades on the sides where the neighbour is not the
  same element. Judge patches (blood, mud) and liquids there, not only on the seamless grid.
- Never judge a pale or translucent tile on the checkerboard: pick a ground in the
  "Aperçu" select, ideally the plan it is meant for.

## Constraints the page already enforces

- Every texture is stitched to the tile and tiled over a 3-tile region, so it joins its
  neighbours in any direction. Don't add a style that stretches `baseFrequency` on one axis:
  Chrome's stitching leaves a band at the edge (that is why streaks are a smear).
- The SVG must stand alone: no script, no `on*`, hrefs only `#id` or `data:image/`.
  `TileAssetService::putSvg` refuses anything else.
- An element whose name matches an effect of the catalogue applies it when stepped on; with no
  such effect it is decor (a waterfall) and does nothing.

## Loop to tune a preset

1. Edit or add the preset in `PRESETS` (`admin/element-composer.php`).
2. Render it on a ground in a real browser and look at the 3×3 grid: copy
   `render.cy.js` from this folder to `cypress/e2e/_composer.cy.js`, adapt the preset names
   and the login (an admin with a known password on the local database), then from the HOST:
   ```bash
   CYPRESS_baseUrl=http://localhost:9000 npx cypress run --spec cypress/e2e/_composer.cy.js --browser electron --config video=false
   ```
   Screenshots land in `data_tests/cypress/screenshots/<timestamp>/_composer.cy.js/`.
   Stitch them into one sheet and view it with the Read tool.
3. Adjust, re-render. Two or three rounds are normal. Delete the scratch spec after.
   A direction (a river flowing north) is not a preset concern: the element is rotated at
   placement (`map_elements.rotation`, admin Éléments), one file serves every direction.
4. To ship a file rather than a preset, click the preset in the page and save, or POST the
   SVG to the page with the CSRF token; check it appears under Tuiles & images.

## Sources worth re-reading

The technique notes of `web-cloud-designer`, `web-wave-designer` and `web-weather-creator`
(erichowens/some_claude_skills, MIT), installed under `~/.claude/skills/`: layered noise via
`feComposite arithmetic`, `feDiffuseLighting` / `feSpecularLighting` on noise, caustics by
sharpening turbulence with a gamma curve, fog as heavy blur plus low alpha. They target full-page
backgrounds; the tiling constraint above is ours.
