<?php
/* The biome brush: each clicked cell (or each cell of the zone) takes the
   ground its top biome's palette gives it, transitions included. */
$brushIcon = 'data:image/svg+xml,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50"><text x="6" y="38" font-size="34">🖌️</text></svg>'
);

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Pinceau de biome</h3></summary>';
echo '<div>
    <img class="map select-name" data-type="biome_brush" data-name="biome_brush" src="' . $brushIcon . '" title="Peindre le sol avec la palette du biome de la case" />
    <p style="font-size: 0.85em;">Chaque case prend le sol que lui donne la palette de son biome (Le monde &gt; Biomes) : poids des sols, taille des plaques et graine du biome. Une zone se peint d\'un coup, et donne le même résultat que case par case.</p>
</div>';
echo '</details>';
