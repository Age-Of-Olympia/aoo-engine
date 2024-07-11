<?php

class ToolCmd extends Command
{
    public function __construct() {
        parent::__construct("tool",[new Argument('path',false)]);
    }

    public function execute(  array $argumentValues ) : string
    {

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
