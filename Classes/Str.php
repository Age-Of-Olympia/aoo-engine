<?php
namespace Classes;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

class Str{

    public static function convert_time($seconds) {

        if($seconds < 0) $seconds = 0;

        $interval = new DateInterval("PT{$seconds}S");
        $now = new DateTimeImmutable('now', new DateTimeZone('utc'));

        $difference = $now->diff($now->add($interval))->format('%aj %hh %im');

        return $difference;
    }

    public static function displaySeconds($seconds) {
        if ($seconds < 60) {
            return $seconds . ' secondes';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . ' minutes';
        } elseif ($seconds < 86400) {
            $heures = floor($seconds / 3600);
            return $heures . ' heures';
        } else {
            $jours = floor($seconds / 86400);
            return $jours . ' jours';
        }
    }


    public static function get_k($int) : string {


        // replace 1000 by 1k and 1555 by 1.5k


        if($int < 1000){

            return $int;
        }


        $int /= 1000;

        return round($int, 1) .'k';
    }


    public static function get_status($degats, $pvMax){


        $pct = ($pvMax-$degats)/$pvMax * 100;

        if($pct == 100) return "en parfait état";
        elseif($pct >= 75) return "en bon état";
        elseif($pct >= 50) return "en mauvais état";
        elseif($pct >= 25) return "en très mauvais état";
        elseif($pct >= 1) return "sur le point de s'effondrer";
        else return "détruit";
    }


    public static function get_rank($xp){

        if( $xp < 500 )
            $rank = 1;
        elseif( $xp < 1500 )
            $rank = 2;
        elseif( $xp < 3000 )
            $rank = 3;
        elseif( $xp < 5000 )
            $rank = 4;
        elseif( $xp < 7500 )
            $rank = 5;
        elseif( $xp < 10500 )
            $rank = 6;
        elseif( $xp < 14000 )
            $rank = 7;
        elseif( $xp < 18000 )
            $rank = 8;
        else
            $rank = 9;

        return $rank;
    }


    public static function get_previous_xp($rank) {

        switch($rank) {
            case 1:
                return 0;
            case 2:
                return 500;
            case 3:
                return 1500;
            case 4:
                return 3000;
            case 5:
                return 5000;
            case 6:
                return 7500;
            default:
                return null;
        }
    }


    public static function get_next_xp($rank){

        switch($rank) {
            case 1:
                $next_xp = 500;
                break;
            case 2:
                $next_xp = 1500;
                break;
            case 3:
                $next_xp = 3000;
                break;
            case 4:
                $next_xp = 5000;
                break;
            case 5:
                $next_xp = 7500;
                break;
            default:
                // For rank 6 and above, we can assume there is no next rank, so no next XP.
                $next_xp = null;
                break;
        }

        return $next_xp;
    }


    public static function calculate_xp_percentage($xp, $rank) {

        $previous_xp = self::get_previous_xp($rank);
        $next_xp = self::get_next_xp($rank);

        if ($previous_xp === null || $next_xp === null) {
            return null; // Invalid rank
        }

        $percentage = (($xp - $previous_xp) / ($next_xp - $previous_xp)) * 100;

        return round($percentage);
    }


    public static function get_reput($pr){

        if( $pr < 25 )
            $rank = "Inconnu";
        elseif( $pr < 100 )
            $rank = "Connu";
        elseif( $pr < 250 )
            $rank = "Populaire";
        elseif( $pr < 500 )
            $rank = "Héroïque";
        elseif( $pr < 1250 )
            $rank = "Légendaire";
        elseif( $pr < 2500 )
            $rank = "Mythologique";
        else
            $rank = "Divin";

        return $rank;
    }


    public static function check_name( $str ){

        if(trim($str) == '') return false;

        if(strlen( $str ) > 30) return false;

        return preg_match("/^[a-z'àâçéèêëîïôöûùü -]*$/i", $str);
    }


    public static function check_mail( $str ){

        if (!filter_var($str, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }


    public static function numberToRoman($num) : string{

        // Be sure to convert the given parameter into an integer
        $n = intval($num);
        $result = '';

        // Declare a lookup array that we will use to traverse the number:
        $lookup = array(
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
        );

        foreach ($lookup as $roman => $value)
        {
            // Look for number of matches
            $matches = intval($n / $value);

            // Concatenate characters
            $result .= str_repeat($roman, $matches);

            // Substract that from the number
            $n = $n % $value;
        }

        return $result;
    }

    public static function minify($b){

        return preg_replace(['/\>[^\S ]+/s','/[^\S ]+\</s','/(\s)+/s'],['>','<','\\1'],$b);
    }

    /**
     * Balises de mise en forme tolérées dans un texte libre saisi par un
     * joueur. Aucune ne porte d'attribut : c'est ce qui rend la liste
     * blanche sûre — pas de href, pas de style, pas de gestionnaire
     * d'évènement, donc rien à assainir à l'intérieur d'une balise.
     */
    private const RICH_TEXT_TAGS = ['b', 'strong', 'i', 'em', 'u', 's', 'br'];

    /** Celles qui ne se ferment pas. */
    private const RICH_TEXT_VOID_TAGS = ['br'];

    /**
     * Rendu d'un texte libre de joueur (message du jour, histoire) ou
     * d'admin (texte d'un type de bâtiment) : la mise en forme simple
     * passe, tout le reste devient du texte visible.
     *
     * Le sens de l'opération est ce qui compte. On échappe TOUT d'abord,
     * puis on ré-autorise exactement les motifs de la liste blanche —
     * jamais l'inverse. Une balise ne peut donc pas se faufiler avec un
     * attribut : « &lt;b onclick=… &gt; » ne correspond à aucun motif et
     * reste affiché tel quel, en clair, au lieu de s'exécuter.
     *
     * Les balises non refermées sont refermées, et les fermetures
     * orphelines supprimées : sans ça, un « <b> » oublié dans un message
     * du jour mettrait en gras tout le reste de la page.
     */
    public static function richText(?string $raw): string
    {
        /* double_encode: false — les textes déjà en base portent leurs
         * accents en ENTITÉS HTML (« Had&egrave;s », « &hellip; ») et
         * étaient rendus bruts jusqu'ici. Les ré-encoder transformait
         * « &egrave; » en « &amp;egrave; », affiché tel quel : le jeu
         * est en français, ça se voyait partout.
         *
         * Ça ne rouvre rien : une entité est décodée par le navigateur
         * APRÈS l'analyse du document, dans un nœud de TEXTE. Un
         * « &lt;script&gt; » redevient les caractères « <script> » à
         * l'écran, jamais une balise — il n'existe aucun chemin par
         * lequel une entité reconstruise du balisage. Le « & » isolé,
         * lui, reste bien échappé. */
        $escaped = htmlspecialchars((string) $raw, ENT_QUOTES, 'UTF-8', false);

        /* \s* et /? tolèrent « <br/> » et « <br /> » ; rien d'autre ne
         * peut tenir entre le nom de balise et le chevron fermant. */
        $allowed = implode('|', self::RICH_TEXT_TAGS);
        $restored = preg_replace_callback(
            '#&lt;(/?)(' . $allowed . ')\s*/?&gt;#i',
            static fn(array $m): string => '<' . $m[1] . strtolower($m[2]) . '>',
            $escaped
        );

        return nl2br(self::balanceTags((string) $restored));
    }

    /**
     * Referme ce que l'auteur a laissé ouvert, dans l'ordre, et jette
     * les fermetures qui ne correspondent à rien. Le texte d'un joueur
     * ne doit pas pouvoir déborder sur l'interface.
     */
    private static function balanceTags(string $html): string
    {
        $open = [];

        $out = preg_replace_callback(
            '#<(/?)([a-z]+)>#',
            static function (array $m) use (&$open): string {
                $closing = $m[1] === '/';
                $tag = $m[2];

                if (in_array($tag, self::RICH_TEXT_VOID_TAGS, true)) {
                    return $closing ? '' : $m[0];
                }

                if (!$closing) {
                    $open[] = $tag;

                    return $m[0];
                }

                /* Fermeture orpheline : rien à refermer, on l'efface. */
                $at = array_search($tag, $open, true);
                if ($at === false) {
                    return '';
                }

                /* Referme aussi ce qui a été ouvert par-dessus, sinon on
                 * produirait du HTML mal imbriqué. */
                $inner = array_splice($open, $at);
                array_shift($inner);

                return implode('', array_map(static fn(string $t): string => '</' . $t . '>', array_reverse($inner)))
                    . $m[0];
            },
            $html
        );

        return (string) $out
            . implode('', array_map(static fn(string $t): string => '</' . $t . '>', array_reverse($open)));
    }
}

