<?php
namespace GBoudreau\HDHomeRun\Scheduler;

require_once 'init.inc.php';

global $parser;
foreach ($parser->getRecordings() as $recording) {
    if ($recording->startsNow(TRUE)) {
        $recording->startRecordingThread();
    }
}
