<?php
use Classes\AdminCommand;
use Classes\Argument;
use Classes\Json;

class AnnonceCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("annonce",[new Argument('text',false)]);
        parent::setDescription(<<<EOT
Modifie l'annonce en page d'accueil
Exemple:
> annonce "Nouvelle mise à jour de Leyrion <3"
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $text = $argumentValues[0];


        $data = (object) array('text'=>$text, 'time'=>time());


        $data = Json::encode($data);


        Json::write_json('datas/public/annonce.json', $data);


        return 'annonce changed for: "'. htmlentities($text) .'"';
    }
}
