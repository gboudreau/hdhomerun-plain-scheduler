<?php
namespace GBoudreau\HDHomeRun\Scheduler;

define('RECORDING_HASH', $argv[1]);

chdir(__DIR__ . '/..');
require_once 'init.inc.php';

// 12h should be more than enough!
set_time_limit(12*60*60);

$recording = unserialize(base64_decode($argv[2]));
/** @var Recording $recording */
$recording->startRecording();
