<?php


class ItemCmd extends Command
{
    public function __construct() {
        parent::__construct("item", [new Argument('action',false),
            new Argument('item_name',false),new Argument('private',true)]);
        parent::setDescription(<<<EOT
Création d'un objet en donnant son nom, sera public si pas d'argument private. Création d'objet enchanté ou maudites
create: Objet normal
create-enchanted: objet enchanté
create-cursed: objet maudit
create-vorpal: objet vorpal
Exemple:
> item create nouvel_item 1
> item create-vorpal existing_item_name 
> item create-cursed existing_item_name_2 1
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        $action = $argumentValues[0];
        if($action == 'create'){
            $private = (!empty($argumentValues[2])) ? 1 : 0;


            $lastId = Item::put_item($argumentValues[1], $private);


            $dir = ($private) ? 'private' : 'public';


            $data = (object) array(
                'id'=>$lastId,
                'name'=>$argumentValues[1],
                "private"=>$private,
                'price'=>1,
                'text'=>"Description de l'objet."
            );


            Json::write_json('datas/'. $dir .'/items/'. $argumentValues[1] . '.json', Json::encode($data));


            return 'Item '. $argumentValues[1] .' créé (id.'. $lastId .')';
        }

        if(in_array($action, array('create-enchanted','create-vorpal','create-cursed'))){
            if(!json()->decode('items', $argumentValues[1])){

                return '<font color="orange">error item '. $argumentValues[1] .' does not exist</font>';
            }


            $private = (!empty($argumentValues[2])) ? 1 : 0;

            $itemType = explode('-', $action)[1];
            $options = array($itemType=>1);

            $lastId = Item::put_item($argumentValues[1], $private, $options);


            return 'Item '. $argumentValues[1] .' ('. $itemType .') créé (id.'. $lastId .')';
        }

        return '<font color="orange">Action : '.$action.' unknown</font>';
    }
}
