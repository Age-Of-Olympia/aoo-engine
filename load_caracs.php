<?php
use App\Factory\PlayerFactory;
use App\View\CaracsPanelRenderer;

require_once('config.php');

echo CaracsPanelRenderer::render(PlayerFactory::active());
