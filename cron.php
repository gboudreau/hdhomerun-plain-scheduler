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

// If any recording is complete, remove it from the schedules file
foreach ($parser->getRecordings() as $recording) {
    if ($recording->isComplete()) {
        $recording->removeFromSchedulesFile();
    }
}
