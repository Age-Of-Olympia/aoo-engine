<?php
use App\Factory\PlayerFactory;
use App\View\Hud\FeedRenderer;

// Reads the session only: its lock is released at once (config.php)
define('SESSION_READ_ONLY', true);
require_once('config.php');

echo FeedRenderer::renderMdj(PlayerFactory::active());
