<?php

use App\View\Forum\ForumHomeView;
use App\View\Forum\ForumView;
use App\View\Forum\LastPostsView;
use App\View\Forum\NewTopicView;
use App\View\Forum\PostEditView;
use App\View\Forum\PostReplyView;
use App\View\Forum\TopicView;

require_once('config.php');

/*
 * Fragments forum pour les panneaux glissants du HUD (js/hud.js) :
 * liste des sujets (?forum=), fil de discussion (?topic=&page=),
 * répondre (?reply=) et éditer (?edit=), sans enveloppe Ui ni
 * Infos/Menu — reprend les branches de forum.php en mode fragment.
 * Créer un sujet et la recherche restent des pages complètes.
 */

if (!empty($_SESSION['banned'])) {
    header('Location: index.php');
    exit();
}

if (!empty($_GET['forum'])) {

    ForumView::$fragment = true;
    ForumView::renderForum();
    exit();
}

if (!empty($_GET['newTopic'])) {

    NewTopicView::$fragment = true;
    NewTopicView::renderNewTopic();
    exit();
}

if (!empty($_GET['reply'])) {

    PostReplyView::$fragment = true;
    PostReplyView::renderPostReply();
    exit();
}

if (!empty($_GET['edit'])) {

    PostEditView::$fragment = true;
    PostEditView::renderPostEdit();
    exit();
}

if (!empty($_GET['topic'])) {

    TopicView::$fragment = true;
    TopicView::renderTopic();
    exit();
}

if (isset($_GET['lastPosts'])) {

    LastPostsView::$fragment = true;
    LastPostsView::renderLastPosts();
    exit();
}

/* Sans paramètre : l'accueil du forum (catégories et forums). */
ForumHomeView::$fragment = true;
ForumHomeView::renderHomeView();
