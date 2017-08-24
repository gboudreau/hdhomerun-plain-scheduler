<?php
namespace GBoudreau\HDHomeRun\Scheduler;

class LoggedRecording extends Recording
{
    public function __construct($hash = NULL) {
        parent::__construct();
        $this->editable = FALSE;
    }

    public function setStatus(string $status) {
        $this->_status = $status;
    }
}
