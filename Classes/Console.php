<?php
class Console
{
   public $commandsResults;
   public $commandsList;
   private $factory;
   private $dbconn;
    public function InitAndExec($inputString)
    {
        $this->factory = initCommmandFactory();

        $GLOBALS['consoleENV'] = ['self' => $_SESSION['playerId']];
        $this->commandsResults = new CommandResult();
        $this->dbconn = db();
        $this->dbconn->beginTransaction();

        $this->commandsList = Command::getCommandsFromInputString($inputString);

        if (count($this->commandsList) == 0) {
            $this->commandsResults->Error("Failed to parse command line");
        }

        try {
            $this->executeCommandList($this->commandsList);
            $this->dbconn->commit();
        } catch (Throwable $e) {
            if (Command::getEnvVariable("debug", "off") == 'on') {
                $this->commandsResults->Error($e->__toString());
            }
            if ($e->getMessage() != '') {
                $this->commandsResults->Error($e->getMessage());

                $this->commandsResults->Error('L:' . $e->getLine() . ' File: ' . $e->getFile());
            }

            $this->commandsResults->Error('faillure revert all changes');
            $this->dbconn->rollBack();
        } finally {
            if (isset($globalTimer)) {
                $execTime = $globalTimer->stop();
                $this->commandsResults->Log('Total execution time : ' . round($execTime, 3) . ' seconds');
            }
        }
    }

    private function ExecuteCommand($command, $commandLineSplit)
    {
        if (isset($commandLineSplit[0]) && ($commandLineSplit[0] === 'help' || $commandLineSplit[0] === '--help')) {
            $result = '<a href="https://age-of-olympia.net/wiki/doku.php?id=v4:console#' . $command->getName() . '">' . $command->getName() . '</a> ' . $command->printArguments() . "<br/>"
                . $command->getDescription();

            $command->result->Log('Help for command ' . $command->getName() . ': ');
            $command->result->Log($result);
        } else {
            if (count($commandLineSplit) >= $command->getRequiredArgumentsCount()) {
                try {
                    $command->result->Log('command found ' . $command->getName() . '. Executing...');
                    $resultstr = $command->executeIfAuthorized($commandLineSplit);
                    if (!empty($resultstr)) {
                        if (startsWithIgnoreCase($resultstr, "error")) {
                            $command->result->Error($resultstr);
                        } else {
                            $command->result->Log($resultstr);
                        }
                    }
                } catch (Throwable $e) {
                    $command->result->Error("Unexpected technical error, check command syntax : " . $command->getName() . " " . $command->printArguments());
                    if ($e instanceof ErrorException || $e instanceof Error)
                        throw $e;
                    $command->result->Error($e->getMessage());
                }
            } else {
                $command->result->Error('missing mandatory arguments ' . $command->printArguments());
            }
        }
    }
    function executeCommandList(array $commandsList)
    {
        for ($i = 0; $i < count($commandsList); $i++) {
            $subCommands = Command::ReplaceEnvVariable($commandsList[$i]);

            if (!isset($globalTimer) && Command::getEnvVariable("reporttime", '0')=='1') {
                $globalTimer = new PerfTimer();
            }

            foreach ($subCommands as $commandLine) {
                if(isset($globalTimer)) {
                    $localTimer = new PerfTimer();
                }
                $commandLineSplit = Command::getCommandLineSplit($commandLine);
                $commandeName = $commandLineSplit[0];
                $command = $this->factory->getCommand($commandeName);
                array_shift($commandLineSplit); //remove first part

                if ($command) {
                    $command->console = $this;
                    $command->result = $this->commandsResults;//@todo create a new result for each command child base system that is compatible with exeptions 
                    $command->db = $this->dbconn;
                    $this->ExecuteCommand($command, $commandLineSplit);
                } else {
                    $arg1 = isset($commandLineSplit[0]) ? $commandLineSplit[0] : '';
                    $this->commandsResults->Error("Unknown command  {$commandeName} {$arg1}");
                }
                if(isset($localTimer)) {
                    $execTime=$localTimer->stop();
                    $arg1 = isset($commandLineSplit[0]) ? $commandLineSplit[0] : '';
                    $this->commandsResults->Log("Command {$commandeName} {$arg1} executed in " . round($execTime, 3) . " seconds");
                }
                if ($this->commandsResults->hasError()) {

                    if (Command::getEnvVariable("revertMode", "all") == 'all') {
                        throw new Exception('');
                    }
                    $this->commandsResults->Error('Command ' . $commandeName . ' failed, stopping execution, ' . strval(count($commandsList) - 1 - $i) . ' ommited');
                    break;
                }
            }
            if ($this->commandsResults->hasError()) {
                break;
            }
        }
    }
}