<?php 
use Classes\Command;
use Classes\Argument;

class ExeCmd extends Command
{
    public function __construct() {
        parent::__construct("exe",[new Argument('name',false)]);
        parent::setDescription(<<<EOT
execute un script de votre compte principal ( le compte avec lequel vous êtes connecté ).
Exemple:
> exe backhome"
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $name = $argumentValues[0];
        //$script = $argumentValues[1];
        $mainaccount = $_SESSION['mainPlayerId'];
        if(!isset($mainaccount) || $mainaccount == 0) {
            $this->result->Error('You must be logged in to execute a script.');
            return '';
        }
        $script = json()->decode('console/scripts', $mainaccount.'_scripts');
        if($script === false ) {
            $this->result->Error("Script '$name' not found.");
            return '';
        }
        $script = (array)$script;

        if(!isset($script[$name])) {
            $this->result->Error("Script '$name' not found.");
            return '';
        }

         $commandsList = Command::getCommandsFromInputString($script[$name]);

        if (count($commandsList) == 0) {
            $this->result->Error("Failed to parse command line for script '$name'");
            return '';
        }
        for($index = 0; $index < count($commandsList); $index++) {
            $commandsList[$index]= $this->replaceArguments($commandsList[$index], $argumentValues);
        }
        
        $this->console->executeCommandList($commandsList);
        $this->result->Log("Script '$name' executed successfully.");
        return '';
    }
    function replaceArguments(string $commande, array $argumentValues) : string {

     for($index = 1; $index < count($argumentValues); $index++) {
            $value = $argumentValues[$index];
            $commande = str_replace('{{arg' . $index . '}}', $value, $commande);
        }
        return $commande;
    }
}