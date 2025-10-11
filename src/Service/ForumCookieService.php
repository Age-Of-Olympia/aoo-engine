<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\ForumCookie;
use Classes\Player;
use Exception;

class ForumCookieService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getForumCookie(int $playerId, String $postName): array
    {
        $repo = $this->entityManager->getRepository(ForumCookie::class);

        return $repo->findBy(['player_id' => $playerId, 'post_name'=>$postName]);
    }

    public function getAllCookiesForPost(String $postName): array
    {
        $repo = $this->entityManager->getRepository(ForumCookie::class);

        return $repo->findBy(['post_name'=>$postName]);
    }

    public function create(int $playerId, String $postName ) : void
    {
        $forumCookie = new ForumCookie();
        $forumCookie->setPostName($postName);
        $forumCookie->setPlayerId($playerId);

        $this->entityManager->persist($forumCookie);
        $this->entityManager->flush();
    }

    public function giveCookie(int $playerId, String $postName ) : void
    {
        $postJson = json()->decode('forum', 'posts/'. $postName);
        $topJson = json()->decode('forum', 'topics/' .$postJson->top_id);

        if ($topJson->forum_id == 'Missives') {
            throw new Exception("Impossible de donner un cookie sur une missive");
        }  
        $author = new Player($postJson->author);
        $player = new Player($playerId);
        if($author->check_share_factions(($player))){
            $author->put_pr(PR_PER_COOKIE_SAME_FACTION);
        }else{
            $author->put_pr(PR_PER_COOKIE);
        }
        
        $this->create($playerId,$postName);        
    }

}