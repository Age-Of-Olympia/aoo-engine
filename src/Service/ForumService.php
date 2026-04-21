<?php

namespace App\Service;
use App\Entity\EntityManagerFactory;
use Classes\Player;
use Exception;

class ForumService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function GetAllUnreadTopics(Player $player): array
    {
        if (!empty($player->data->registerTime)) {

            $registerTime = $player->data->registerTime;
        } else {

            $registerTime = 0;
        }

        $result = [];
        foreach (array('RP', 'Privés', 'HRP') as $cat) {


            $catJson = json()->decode('forum', 'categories/' . $cat);
            if (!$catJson || empty($catJson->forums)) {
                continue;
            }


            foreach ($catJson->forums as $forum) {


                $forJson = json()->decode('forum', 'forums/' . $forum->name);
                if (!$forJson) {
                    continue;
                }


                if ($catJson->name == 'Privés') {


                    if (!empty($forJson->factions)) {


                        if (!in_array($player->data->faction, $forJson->factions) && !in_array($player->data->secretFaction, $forJson->factions)) {

                            continue;
                        }
                    }
                }


                if (empty($forJson->topics)) {
                    continue;
                }
                foreach ($forJson->topics as $topics) {


                    $topJson = json()->decode('forum/topics', $topics->name);
                    if (!$topJson || !isset($topJson->last)) {
                        continue;
                    }

                    // hide topics created previously to the register
                    if (timestampNormalization($topJson->last->time) < $registerTime) {

                        continue;
                    }

                    if(is_array($topJson->views))//old way
                    {
                        if (in_array($player->id, $topJson->views)) {
                            continue;
                        }
                    }
                    else if (isset($topJson->views->{$player->id}) && $topJson->views->{$player->id} >= $topJson->last->time) {
                        continue;
                    }
                    $result[] = ["topicJson"=>$topJson, "forumJson" =>$forJson];
                }

            }
        }

        return $result;
    }

}