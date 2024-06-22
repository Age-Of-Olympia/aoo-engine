<?php


class Str{

    public static function convert_time($seconds) {

        if($seconds < 0) $seconds = 0;

        $interval = new DateInterval("PT{$seconds}S");
        $now = new DateTimeImmutable('now', new DateTimeZone('utc'));

        $difference = $now->diff($now->add($interval))->format('%aj %hh %im');

        return $difference;
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
        else
            $rank = 6;

        return $rank;
    }


    public static function get_from_dir($dir){


        if($dir == 'e') return 'w';
        elseif($dir == 'w') return 'e';
        elseif($dir == 's') return 'n';
        elseif($dir == 'n') return 's';
        elseif($dir == 'nw') return 'se';
        elseif($dir == 'ne') return 'sw';
        elseif($dir == 'sw') return 'ne';
        elseif($dir == 'se') return 'nw';
    }


    public static function check_name( $str ){

        if(trim($str) == '') return false;

        if(strlen( $str ) > 30) return false;

        return preg_match("/^[a-z'àâçéèêëîïôûùü -]*$/i", $str);
    }


    public static function check_mail( $str ){

        if (!filter_var($str, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }
}

