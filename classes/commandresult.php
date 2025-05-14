<?php
use App\Enum\LogType;
class CommandResult
{
    private $results = [];
    private $hasError = false;
    public $allreadyIngested = false;
    public function addLog($message,LogType $type=LogType::Log,$level = 1){
        $this->results[] = ['message' => $message, 'type' => $type, 'level' => $level];
        if($type>=LogType::Error){
            $this->hasError = true;
        }
    }
    public function Error($message,$level = 1){
        $this->addLog($message,LogType::Error,$level);
    }
    public function Warning($message,$level = 1){
        $this->addLog($message,LogType::Warning,$level);
    }
    public function Log($message,$level = 1){
        $this->addLog($message,LogType::Log,$level);
    }
    public function getResults(){
        return $this->results;
    }
    public function hasError(){
        return $this->hasError;
    }

    public function Ingest(CommandResult $child)
    {
        if($child->allreadyIngested){
            return;
        }
        $this->hasError|= $child->hasError();
        $childResults = $child->getResults();
        for ($i=0; $i < sizeof($childResults) ; $i++) {
            $childResults[$i]['level']++;
            $this->results[] =$childResults[$i];
        }
        $child->allreadyIngested = true;
    }
}