<?php
use Classes\Command;
use Classes\Argument;
use Classes\Item;
use Classes\Json;
use Classes\Db;

class ItemCmd extends Command
{
    public function __construct() {
        parent::__construct("item", [new Argument('action',false),
            new Argument('item_name',false),new Argument('private',true)]);
        parent::setDescription(<<<EOT
Gestion des Objets
Creation d'un objet en donnant son nom, sera public si pas d'argument private. Création d'objet enchanté ou maudites
create: Objet normal
create-enchanted: objet enchanté
create-cursed: objet maudit
create-vorpal: objet vorpal
Exemple:
> item create nouvel_item 1
> item create-vorpal existing_item_name 
> item create-cursed existing_item_name_2 1
Modification d'une propriété d'un objet : 
item edit [id] [propriété] [valeur] 
Exemple:
> item edit 1 name "Nouveau nom"
> item edit 1 is_bankable 0 
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        $action = $argumentValues[0];
        if($action == 'create'){
            return create_item($argumentValues);
        }

        if(in_array($action, array('create-enchanted','create-vorpal','create-cursed'))){
            return create_special($argumentValues,$action);
        }

        if($action == 'edit'){
            return edit_item($argumentValues);
        }
        
        return '<font color="orange">Action : '.$action.' unknown</font>';
    }

}

function create_item($argumentValues){
    
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

function create_special ($argumentValues, $action){

    if(!json()->decode('items', $argumentValues[1])){

        return '<font color="orange">error item '. $argumentValues[1] .' does not exist</font>';
    }


    $private = (!empty($argumentValues[2])) ? 1 : 0;

    $itemType = explode('-', $action)[1];
    $options = array($itemType=>1);

    $lastId = Item::put_item($argumentValues[1], $private, $options);


    return 'Item '. $argumentValues[1] .' ('. $itemType .') créé (id.'. $lastId .')';
}

function edit_item($argumentValues){

    $item = new Item($argumentValues[1]);

    if(!isset($argumentValues[2])){

        return '<font color="red">invalid argument option1 ('. $argumentValues[2] .').<br />
                Usage: item edit [id] [field] [value] ie item edit 1 name "Or"</font>';
    }

    if(!isset($argumentValues[3])){

        return '<font color="red">invalid argument option2 ('. $argumentValues[3] .').<br />
                Usage: item edit [id] [field] [value] ie item edit 1 name "Or"</font>';
    }


    $field = $argumentValues[2];

    $value = $argumentValues[3];


    if(in_array($field, array('id'))){

        return '<font color="orange">field "'. $field .'" is protected</font>';
    }

    $sql = '
            UPDATE items
            SET
            `'. $field .'` = ?
            WHERE
            id = ?
            ';

    $db = new Db();

    $sql = $db->exe($sql, array($value, $item->id));

    return 'Item '. $argumentValues[1] .' modifié.';
}
