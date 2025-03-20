<?php

class CommandResult
{
    private $results = [];
    private $hasError = false;
    public $allReadyIngested = false;
    public function addLog($message,$type=1,$level = 1){
        $this->results[] = ['message' => $message, 'type' => $type, 'level' => $level];
        if($type>=3){
            $this->hasError = true;
        }
    }
    public function Error($message,$level = 1){
        $this->addLog($message,3,$level);
    }
    public function Waring($message,$level = 1){
        $this->addLog($message,2,$level);
    }
    public function Log($message,$level = 1){
        $this->addLog($message,1,$level);
    }
    public function getResults(){
        return $this->results;
    }
    public function hasError(){
        return $this->hasError;
    }

    public function Ingest(CommandResult $child)
    {
        if($child->allReadyIngested){
            return;
        }
        $this->hasError|= $child->hasError();
        $childResults = $child->getResults();
        for ($i=0; $i < sizeof($childResults) ; $i++) {
            $childResults[$i]['level']++;
            $this->results[] =$childResults[$i];
        }
        $child->allReadyIngested = true;
    }
}