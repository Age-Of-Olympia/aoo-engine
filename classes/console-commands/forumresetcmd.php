<?php

class ForumResetCmd extends Command
{
    public function __construct() {
        parent::__construct("forum_reset",[]);
    }

    public function execute(  array $argumentValues ) : string
    {
        File::rrmdir(realpath('img/ui/forum/uploads/'));

        File::rrmdir(realpath('datas/private/forum/'));

        $resultLog = '';

        // assuming file.zip is in the same directory as the executing script.
        $file = 'datas/private/forum.zip';

        $realpath = realpath($file);

        // get the absolute path to $file
        $path = pathinfo($realpath, PATHINFO_DIRNAME);

        $zip = new ZipArchive;
        $res = $zip->open($file);
        if ($res === TRUE) {
            // extract it to the path we determined above
            $zip->extractTo($path);
            $zip->close();
            $resultLog.= "WOOT! $file extracted to $path <br/>";
        } else {
            $resultLog.= "Doh! I couldn't open $file <br/>";
        }


        $db = new Db();

        $sql = 'TRUNCATE TABLE `players_forum_missives`;';
        $db->exe($sql);

        $sql = 'TRUNCATE TABLE `players_forum_rewards`;';
        $db->exe($sql);

        $sql = 'TRUNCATE TABLE `forums_keywords`;';
        $db->exe($sql);

        $resultLog.= "End of forum reset <br/>";

        return $resultLog;
    }
}
