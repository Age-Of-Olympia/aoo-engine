<?php

echo '<textarea style="width: 100vw; height: 50vw;">';

$effects = (new \App\Service\EffectService())->getAllEffects();

usort($effects, static fn ($a, $b) => strcmp($a->getLabel(), $b->getLabel()));

foreach($effects as $effect){

    if($effect->isMapMarker() || $effect->getDescription() === ''){

        continue;
    }

echo '
===== '. $effect->getLabel() .' =====
'. $effect->getDescription() .'';

}


echo '</textarea>';
