<?php
use Classes\Ui;
use Classes\Str;
use App\View\InfosView;
use App\View\MainView;
use App\View\MenuView;
use App\View\NewTurnView;
use Classes\Player;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

if(isset($_GET['logout'])){

    ob_start();
}


define('NO_LOGIN', true);


require_once('config.php');


$request = Laminas\Diactoros\ServerRequestFactory::fromGlobals(
    $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);

$router   = (new League\Route\Router);
$responseFactory = new Laminas\Diactoros\ResponseFactory();
$jsonStrategy = new League\Route\Strategy\JsonStrategy($responseFactory);
$router->group('/admin2', function (\League\Route\RouteGroup $route) {
    $route->map('GET', '/', function (ServerRequestInterface $request): ResponseInterface {
    $response = new Laminas\Diactoros\Response;
    $response->getBody()->write('<h1>Hello, World!</h1>');
    return $response;
});
    // $route->map('GET', '/acme/route1', 'AcmeController::actionOne');
    // $route->map('GET', '/acme/route2', 'AcmeController::actionTwo');
    // $route->map('GET', '/acme/route3', 'AcmeController::actionThree');
});
$router->group('/api', function (\League\Route\RouteGroup $route) {
    $route->map('GET', '/', 
    function (ServerRequestInterface $request): ResponseInterface {
    $response = new Laminas\Diactoros\Response;
    $response->getBody()->write('<h1>Hello, World!</h1>');
    return $response;
}); 
    //$route->map('GET', '/acme/route1', 'AcmeController::actionOne');
    // $route->map('GET', '/acme/route2', 'AcmeController::actionTwo');
    // $route->map('GET', '/acme/route3', 'AcmeController::actionThree');
});//->setStrategy( $jsonStrategy);

$response = $router->dispatch($request);

// send the response to the browser
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);

exit();
$ui = new Ui($title="Index");


if(!empty($_SESSION['banned'])){

    echo '<h1>Vous avez été banni.</h1>';

    exit($_SESSION['banned']);
}


if(!isset($_SESSION['playerId']) || isset($_GET['menu'])){

    include('scripts/index.php');
}

elseif(isset($_GET['logout'])){

    unset($_SESSION['mainPlayerId']);
    unset($_SESSION['playerId']);
    unset($_SESSION['nonewturn']);
    session_destroy();

    ob_clean();

    header('location:index.php');
    exit();
}


ob_start();
$player = new Player($_SESSION['playerId']);
$player->get_data(false);
?>
<div id="new-turn"><?php NewTurnView::renderNewTurn($player) ?></div>

<div id="infos"><?php InfosView::renderInfos($player);?></div>

<div id="menu"><?php MenuView::renderMenu(); ?></div>

<?php MainView::render($player) ?>


<?php

echo '<div style="color: red;">';

if(!CACHED_INVENT) echo 'CACHED_INVENT = false<br />';
if(!CACHED_KILLS) echo 'CACHED_KILLS = false<br />';
if(!CACHED_CLASSEMENTS) echo 'CACHED_CLASSEMENTS = false<br />';
if(!CACHED_QUESTS) echo 'CACHED_QUESTS = false<br />';
if(AUTO_GROW) echo 'AUTO_GROW = true<br />';
if(FISHING) echo 'AUTO_GROW = true<br />';
if(ITEM_DROP > 10) echo 'ITEM_DROP = '. ITEM_DROP .'<br />';
if(DMG_CRIT > 10) echo 'DMG_CRIT = '. DMG_CRIT .'<br />';
if(AUTO_BREAK) echo 'AUTO_BREAK = true<br />';
if(AUTO_FAIL) echo 'AUTO_FAIL = true<br />';

echo '</div>';

echo Str::minify(ob_get_clean());
