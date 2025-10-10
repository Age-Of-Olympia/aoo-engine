<?php

namespace App\View\Forum;
use App\Service\ForumCookieService;
use Classes\Player;


class CookieView
{
    public static function displayCookieView($postJson,$player): void
    {
        echo '<div class="cookie-container">';
        $forumCookieService = new ForumCookieService();
        $tooltip  = " ";
        $nbCookie = 0;
        $allPostCookies = $forumCookieService->getAllCookiesForPost($postJson->name);
        if (!empty($allPostCookies)){
            $tooltip  = "Cookies donnés par ";
            foreach($allPostCookies as $postCookie){
                $cookieGiver = new Player($postCookie->getPlayerId());
                $cookieGiver->get_data();
                $tooltip.=' '. $cookieGiver->data->name. ' - ';
                $nbCookie ++;
            }
            $tooltip = substr($tooltip, 0, -2); //remove unwanted trailing - 
        }else{
            $tooltip  = "Pas de cookie.";
        }
        if (!empty($forumCookieService->getForumCookie($player->id, $postJson->name)) || $player->id == $postJson->author){
            echo ' <img class="give-cookie disable"
                            src="img/ui/forum/cookie.png"
                            title="Vous avez donné un cookie"
                        />';
        } else {            
            $postTimestamp = $postJson->name / 1000;
            $sevenDaysAgo = time() - (MAX_DAYS_COOKIE_FORUM * 24 * 60 * 60);

            if ($postTimestamp < $sevenDaysAgo) { // Si post trop vieux, pas de possibilité de donner un cookie
                echo ' <img class="give-cookie disable"
                            src="img/ui/forum/cookie.png"
                            title="Trop tard pour donner un cookie"
                        />';
            }else{
                    echo '<img data-post-name="' . $postJson->name . '" data-player-id="' . $player->id . '"
                            class="give-cookie"
                            src="img/ui/forum/cookie.png"
                        />';
            }
        
        }
        echo '<span class="cookie-giver" tooltip="'.$tooltip.'" flow="left">'.$nbCookie.'</span>';
        echo '</div>';
                
                   

        
    }
}
