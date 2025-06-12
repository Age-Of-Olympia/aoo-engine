<?php 
use Classes\Command;
use Classes\Argument;
use Classes\Json;

class SaveScriptCmd extends Command
{
    public function __construct() {
        parent::__construct("savescript",[new Argument('name',false),new Argument('script',false)]);
        parent::setDescription(<<<EOT
sauvegarde un script dans votre compte principal ( le compte avec lequel vous êtes connecté ).
Exemple:
> saveScript backhome "tp {self} birdland""
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $name = strtolower($argumentValues[0]);
        $newscript = $argumentValues[1];
        if(empty($name) || empty($newscript)){
            $this->result->Error('vous devez fournir un nom et un contenu pour sauvgarder un script.');
            return '';
        }
        $mainaccount = $_SESSION['mainPlayerId'];
        if(!isset($mainaccount) || $mainaccount == 0) {
            $this->result->Error('vous devez être connecté pour sauvgarder un script.');
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
        $folder = 'datas/private/console/scripts/';
        if(!file_exists($folder)){
            mkdir($folder, 0755,true);
        }
        Json::write_json($folder.$mainaccount.'_scripts.json', $data);
        $this->result->Log('Script saved successfully.');
        return '';
    }
}