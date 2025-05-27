<?php

class ToolCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("tool",[new Argument('path',true)]);
        parent::setDescription(<<<EOT
permet d'afficher les outils
Exemple:
> tool (affiche la liste des outils disponibles)
> tool add_100_item (lance directement le script add_100_items)
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        if(!isset($argumentValues[0]) || $argumentValues[0] == ''){


            $path = 'scripts/tools/';

            $filesTbl = File::scan_dir($path, without:'.php');

            foreach($filesTbl as $k=>$e){


                if($e == 'tiled_'){

                    unset($filesTbl[$k]);
                    continue;
                }


                $filesTbl[$k] = '<a href="tools.php?'. $e .'">'. $e .'</a>';
            }

            return implode(', ', $filesTbl);
        }

        // clean function outputs
        ob_start();

        $argumentValues[0] = str_replace('.', '', $argumentValues[0]);
        $argumentValues[0] = str_replace('/', '', $argumentValues[0]);

        $path = 'scripts/tools/'. $argumentValues[0] .'.php';

        if(!file_exists($path)){

            return '<font color="red">'. $path .' error file not found</font>';
        }

        include($path);

        ob_clean();


        return $path .' executed' ;
    }
}
