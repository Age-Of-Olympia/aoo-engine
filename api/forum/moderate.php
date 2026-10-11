<?php
use App\Service\AdminMenuAccessService;
use App\Service\PlayerService;
use Classes\Forum;
use Classes\Json;

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ExitError('Méthode invalide');
}

$player = (new PlayerService($_SESSION['playerId']))->GetPlayer($_SESSION['playerId']);

if (!(new AdminMenuAccessService())->allows('forum-moderation', $player)) {
    ExitError('Droit de modération requis');
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? '';

// File names are millisecond timestamps: digits only, never a path
$name = (string) ($data['name'] ?? '');
if (!ctype_digit($name)) {
    ExitError('Message ou sujet invalide');
}

if (in_array($action, ['delete', 'hide', 'restore'], true)) {
    $postJson = json()->decode('forum', 'posts/' . $name);
    if (!$postJson) {
        ExitError('Message introuvable');
    }
    Forum::set_post_deleted($postJson, $action === 'restore' ? null : $action === 'hide');
    ExitSuccess('ok');
}

if ($action === 'rename') {
    $title = trim((string) ($data['title'] ?? ''));
    $topJson = json()->decode('forum', 'topics/' . $name);
    if (!$topJson || $title === '') {
        ExitError('Sujet ou titre invalide');
    }
    $topJson->title = $title;
    Json::write_json('datas/private/forum/topics/' . $topJson->name . '.json', Json::encode($topJson));
    ExitSuccess('ok');
}

ExitError('Action inconnue');
