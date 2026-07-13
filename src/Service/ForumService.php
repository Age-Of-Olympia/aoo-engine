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

    /**
     * Nombre de sujets de forum non lus (catégories RP / Privés / HRP,
     * hors Missives). Le décompte lit tous les JSONs de sujets : cache
     * de session, invalidé par Forum::put_view dès qu'un sujet est lu
     * et au bout d'une minute (nouveaux posts d'autres joueurs).
     * Consommé par la pastille orange (TopBarView, check_forum.php).
     */
    public function GetUnreadCount(Player $player): int
    {
        $cache = $_SESSION['forumUnreadCache'] ?? null;

        if (is_array($cache) && $cache['playerId'] === $player->id && $cache['time'] > time() - 60) {

            return $cache['n'];
        }

        try {
            $n = count($this->GetAllUnreadTopics($player));
        } catch (\Throwable $e) {
            $n = 0;
        }

        $_SESSION['forumUnreadCache'] = ['playerId' => $player->id, 'time' => time(), 'n' => $n];

        return $n;
    }

    /**
     * Sujets non lus groupés par forum : nom du forum => nombre.
     * Alimente les pastilles par forum de l'accueil (ForumHomeView).
     *
     * @return array<string, int>
     */
    public function GetUnreadCountByForum(Player $player): array
    {
        $byForum = [];

        try {
            foreach ($this->GetAllUnreadTopics($player) as $entry) {

                $name = $entry['forumJson']->name;
                $byForum[$name] = ($byForum[$name] ?? 0) + 1;
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $byForum;
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

                    /* Un sujet jamais ouvert n'a pas de propriété views :
                     * il est non lu, pas une erreur. */
                    if (isset($topJson->views) && is_array($topJson->views))//old way
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