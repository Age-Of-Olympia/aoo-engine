<?php

namespace App\View\Forum;
use App\Service\ForumCookieService;


class CookieView
{
    public static function displayCookieView($postJson,$player): void
    {
        $forumCookieService = new ForumCookieService();
        if (!empty($forumCookieService->getForumCookie($player->id, $postJson->name))){
            echo ' <img data-post="' . $postJson->name . '"
                            class="give-cookie disable"
                            src="img/ui/forum/cookie.png"
                            title="Vous avez donné un cookie"
                        />';
        } else {            
            $postTimestamp = $postJson->name / 1000;
            $sevenDaysAgo = time() - (MAX_DAYS_COOKIE_FORUM * 24 * 60 * 60);

            if ($postTimestamp > $sevenDaysAgo) { // Si post trop vieux, pas de possibilité de donner un cookie
                echo ' <img data-post="' . $postJson->name . '"
                            class="give-cookie"
                            src="img/ui/forum/cookie.png"
                        />';
        }
        }
                
                   

        
    }
}
