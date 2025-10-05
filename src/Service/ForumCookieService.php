<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\ForumCookie;

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


}