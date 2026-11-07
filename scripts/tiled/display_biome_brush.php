<?php
/* The biome brush: each clicked cell (or each cell of the zone) takes the
   ground its top biome's palette gives it, transitions included. */
$brushIcon = 'data:image/svg+xml,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50"><text x="6" y="38" font-size="34">🖌️</text></svg>'
);

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Pinceau de biome</h3></summary>';
echo '<div>
    <img class="map select-name" data-type="biome_brush" data-name="biome_brush" src="' . $brushIcon . '" title="Peindre tout le biome de la case avec sa palette" />
    <p style="font-size: 0.85em;">Un clic sur une case peint tout le biome du dessus à cet endroit, sur ce niveau, avec sa palette (Le monde &gt; Biomes) : parts des sols, taille des plaques et graine du biome. Au bord du biome, le pinceau raccorde ses tuiles au sol déjà posé à côté, avec des transitions générées au besoin. Repeindre redonne le même dessin ; pour un autre tirage, cliquez « Nouveau tirage » sur la fiche du biome.</p>
</div>';
echo '</details>';
