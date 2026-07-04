<?php
use App\Factory\PlayerFactory;
use App\View\Hud\FeedRenderer;

require_once('config.php');

echo FeedRenderer::renderEvents(PlayerFactory::active());
