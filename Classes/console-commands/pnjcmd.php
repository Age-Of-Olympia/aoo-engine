<?php
use App\Service\PlayerPnjService;
use Classes\Command;
use Classes\Argument;
use App\Service\AdminAuthorizationService;
class PnjCmd extends Command
{
    public function __construct() {
        parent::__construct("pnj",[new Argument('action',false), new Argument('mat joueur',false), new Argument('mat pnj',false)]);
        parent::setDescription(<<<EOT
Manipule l'affectation d'un pnj a un joueur 
Exemple:
> pnj add Orcrist Shaolan
> pnj add 12 -23
> pnj rem 12 -13 (marche aussi avec del, delete ou remove)
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $action = strtolower($argumentValues[0]);

        $player=parent::getPlayer($argumentValues[1]);
        $player->get_data();
        $target=parent::getPlayer($argumentValues[2]);
        $target->get_data();

        //Si le pnj ajouté ou retiré a l'option isSuperAdmin, seul un superAdmin lui même peut le retirer ou l'ajouté
        if($target->have('options','isSuperAdmin') ){ 
            AdminAuthorizationService::DoSuperAdminCheck();
        }

        if($action == 'add'){
            return add_pnj($player, $target);
        }
        if($action == 'rem' || $action == 'remove' || $action == 'del' || $action == 'delete'  ){
            return remove_pnj($player, $target);
        }

        return '<font color="orange">Action : '.$action.' unknown</font>';
    }
}

function add_pnj($player, $target)
{
    $playerPnjService = new PlayerPnjService();

    $playerPnj = $playerPnjService->getByPlayerIdAndPnjId($player->id, $target->id);

    if (!$playerPnj) {
        $playerPnjService->create($player->id,$target->id,true);    
    }

    return 'PNJ '. $target->data->name .' ajouté au joueur '.$player->data->name ;
}


function remove_pnj($player, $target)
{
    $playerPnjService = new PlayerPnjService();

    $playerPnjService->deleteByPlayerIdAndPnjId($player->id, $target->id);

    return 'PNJ '. $target->data->name .' retiré au joueur '.$player->data->name ;
}