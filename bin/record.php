<?php
namespace GBoudreau\HDHomeRun\Scheduler;

define('RECORDING_HASH', $argv[1]);

chdir(__DIR__ . '/..');
require_once 'init.inc.php';

// 12h should be more than enough!
set_time_limit(12*60*60);

global $parser;
foreach ($parser->getRecordings() as $recording) {
    if ($recording->getHash() == RECORDING_HASH) {
        $recording->startRecording();
        break;
    }
}
