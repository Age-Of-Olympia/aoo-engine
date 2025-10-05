<?php
namespace App\Entity;

use Doctrine\DBAL\Types\BigIntType;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ORM\Table(name: "forums_cookie")]
class ForumCookie
{
    
    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private BigIntType $post_name;

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

    public function setPostName(BigIntType $post_name): void
    {
        $this->post_name = $post_name;
    }

       public function getPostName(): BigIntType
    {
        return $this->post_name;
    }

   

}
