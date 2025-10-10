<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ORM\Table(name: "forums_cookie")]
class ForumCookie
{
    
    #[ORM\Id]
    #[ORM\Column(type: "string")]
    private string $post_name;

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $player_id;

    public function getPlayerId(): int
    {
        return $this->player_id;
    }
    
    public function setPlayerId(int $player_id): void
    {
        $this->player_id = $player_id;
    }

    public function setPostName(string $post_name): void
    {
        $this->post_name = $post_name;
    }

       public function getPostName(): string
    {
        return $this->post_name;
    }

   

}
