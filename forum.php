<?php

use Classes\Forum;
use App\View\Forum\ForumHomeView;
use App\View\Forum\ForumView;
use App\View\Forum\LastPostsView;
use App\View\Forum\NewTopicView;
use App\View\Forum\PostEditView;
use App\View\Forum\PostReplyView;
use App\View\Forum\RewardView;
use App\View\Forum\SearchView;
use App\View\Forum\TopicView;

require_once('config.php');

if(!empty($_SESSION['banned'])){
    header('Location: index.php');
}

if(!empty($_GET['forum'])){


    ForumView::renderForum();
    exit();
}

elseif(!empty($_GET['topic'])){


    TopicView::renderTopic();
    exit();
}

elseif(!empty($_GET['reply'])){


    PostReplyView::renderPostReply();

    exit();
}

elseif(!empty($_GET['edit'])){


   PostEditView::renderPostEdit();

    exit();
}

elseif(!empty($_GET['newTopic'])){

    NewTopicView::renderNewTopic();
    exit();
}

elseif(isset($_GET['rewards'])){


    RewardView::renderRewards();
    exit();
}

elseif(isset($_GET['search'])){


    SearchView::renderSearch();

    exit();
}

elseif(isset($_GET['lastPosts'])){


    LastPostsView::renderLastPosts();

    exit();
}

elseif(isset($_GET['autosave']) && isset($_POST['text'])){

    if(trim($_POST['text']) != ''){
        if($_POST['currentSessionId'] == $_SESSION['playerId'])
        {
            Forum::put_autosave($_SESSION['playerId'], $_POST['text']);
        }
        else
        {
            exit('error session swich');
        }
    }

    exit();
}

ForumHomeView::renderHomeView();