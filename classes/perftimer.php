<?php
class PerfTimer {
    private $start_time=0;
    private $end_time=0;

    public function __construct(bool $start = true) {
        if ($start) {
            $this->start();
        }
    }

    public function start() {
        if ($this->start_time != 0) {
            throw new Exception("Timer allready been started");
        }
        $this->start_time = microtime(true);
    }

    public function stop() {
        if ($this->start_time == 0 || $this->end_time != 0) {
            throw new Exception("Timer has not been started or already stopped.");
        }
        $this->end_time = microtime(true);
        return $this->end_time - $this->start_time;
    }

    public function getElapsedTime() {
        if( $this->end_time == 0) {
            return microtime(true) - $this->start_time;
        }
        return $this->end_time - $this->start_time;
    }
}