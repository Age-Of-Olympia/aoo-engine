<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionTypeRegistry;
use App\View\Action\ActionTreeListView;

$registry = new ActionTypeRegistry();
$actions = (new ActionCatalogService())->listActions();

// Group each action under its concrete type key (the closest type in its
// ancestry), so the list nests beneath the inheritance tree.
$actionsByType = [];
foreach ($actions as $action) {
    $key = $registry->typeKeysForAction($action)[0] ?? 'action';
    $actionsByType[$key][] = $action;
}

$body = (new ActionTreeListView())->render($registry->tree(), $actionsByType);

echo admin_layout('Actions', renderFlashMessage() . $body, [
    'styles' => ['/admin/css/action-type-tree.css'],
    'scripts' => ['/admin/js/action-type-tree.js'],
]);
