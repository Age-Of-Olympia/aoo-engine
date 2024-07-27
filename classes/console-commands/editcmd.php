<?php

class EditCmd extends Command
{
    public function __construct() {
        parent::__construct("edit",[new Argument('json',false), new Argument('private',true)]);
        parent::setDescription(<<<EOT
edite le json [json] +/- [private]
Exemple:
> edit items (liste les .json du repertoire "datas/public/items/")
> edit items private (liste les .json du repertoire "datas/private/items/")
> edit items/adonis (édite le .json "datas/public/items/adonis.json")
> edit plans/faille_naine private (édite le .json "datas/private/plans/faille_naine.json")
> edit plan (édite le .json du plan actuel)
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {


        ob_start();

        $dir = (!empty($argumentValues[1])) ? 'private' : 'public';

        $path = 'datas/'. $dir .'/'. $argumentValues[0] .'.json';

        $dirPath = 'datas/'. $dir .'/'. $argumentValues[0];


        if($argumentValues[0] == 'plan'){


            $admin = new Player($_SESSION['playerId']);
            $admin->get_coords();

            $plan = $admin->coords->plan;

            echo '
            <script>document.location = "tools.php?edit&dir=private&subDir=plans&finalDir='. $plan .'";</script>
            ';
        }

        elseif(file_exists($path)){


            list($subDir, $finalDir) = explode('/', $argumentValues[0]);

            $dataJson = json()->decode($subDir, $finalDir);

            if($dataJson){

                echo '
                <script>document.location = "tools.php?edit&dir='. $dir .'&subDir='. $subDir .'&finalDir='. $finalDir .'";</script>
                ';
            }
        }

        elseif(is_dir($dirPath)){


            echo $dirPath .' is a dir: listing .json<br />';

            $files = File::scan_dir($dirPath);

            foreach($files as $e){

                if(substr($e, -5) != '.json'){

                    continue;
                }

                list($dataDir, $dir, $subDir) = explode('/', $dirPath);

                $finalDir = str_replace('.json', '', $e);

                echo '<a href="tools.php?edit&dir='. $dir .'&subDir='. $subDir .'&finalDir='. $finalDir .'">'. $e .'</a><br />';
            }
        }

        else{

            echo '<font color="orange">file '. $path .' not found</font>';
        }


        return ob_get_clean();
    }
}
