<?php
namespace GBoudreau\HDHomeRun\Scheduler;

class LoggedRecording extends Recording
{
    public function setStatus(string $status) {
        $this->_status = $status;
    }
}
