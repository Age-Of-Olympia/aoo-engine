<?php 
use Classes\Command;
use Classes\Argument;
use Classes\Json;

class SavescriptCmd extends Command
{
    public function __construct() {
        parent::__construct("savescript",[new Argument('name',false),new Argument('script',false)]);
        parent::setDescription(<<<EOT
sauvegardeunscript dans votre compte principal ( le compte avec lequel vous êtes connecté ).
Exemple:
> saveScript backhome "tp {self} birdland""
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $name = $argumentValues[0];
        $newscript = $argumentValues[1];
        $mainaccount = $_SESSION['mainPlayerId'];
        if(!isset($mainaccount) || $mainaccount == 0) {
            $this->result->Error('You must be logged in to save a script.');
            return '';
        }
        $scripts = json()->decode('scripts', $mainaccount);
        if($scripts === false) {
            $scripts =array();
        }
        else{
            $scripts = (array)$scripts;
        }
        $scripts[$name]=$newscript;


        $data = Json::encode( (object)$scripts);
        Json::write_json('datas/private/scripts/'.$mainaccount.'.json', $data);
        $this->result->Log('Script saved successfully.');
        return '';
    }
}