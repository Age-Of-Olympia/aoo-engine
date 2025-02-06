<?php


if(!empty($success) && $success == true){


    if($target->emplacements->main1->row->name != 'poing'){


        $target->equip($target->emplacements->main1);


        echo '<div><font color="#66ccff">Vous désarmez '. $target->data->name .'!</font></div>';


        if(rand(1,100) <= ITEM_DROP){


            $target->drop($target->emplacements->main1, 1);

            echo '<div><font color="#66ccff">Son arme tombe sur le sol!</font></div>';
        }
    }

    else{

        echo '<div>'. $target->data->name .' se bat à main nue.</div>';
    }
}
