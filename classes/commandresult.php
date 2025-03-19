<?php

class CommandResult
{
    private $results = [];
    private $hasError = false;

    public function addLog($message,$isError = false,$level = 1){
        $this->results[] = ['message' => $message, 'isError' => $isError, 'level' => $level];
        if($isError){
            $this->hasError = true;
        }
    }
    public function Error($message,$level = 1){
        $this->addLog($message,true,$level);
    }
    public function Log($message,$level = 1){
        $this->addLog($message,false,$level);
    }
    public function getResults(){
        return $this->results;
    }
    public function hasError(){
        return $this->hasError;
    }
}