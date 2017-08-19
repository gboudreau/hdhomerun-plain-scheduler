<?php
namespace GBoudreau\HDHomeRun\Scheduler;

require_once 'init.inc.php';

global $parser;

// Start recordings (using separate threads)
foreach ($parser->getRecordings() as $recording) {
    if ($recording->startsNow()) {
        $recording->startRecordingThread();
    }
}
