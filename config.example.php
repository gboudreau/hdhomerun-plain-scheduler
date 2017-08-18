<?php
namespace GBoudreau\HDHomeRun\Scheduler;

$CONFIG = new \stdClass;

// Text file that contains the recording schedules; if you specify a relative path, it should be relative to this config file
$CONFIG->SCHEDULES_FILE = './schedules.txt';

// IP address of your HDHomeRun device
// Find it on your router admin page. or using: `hdhomerun_config discover`
$CONFIG->HDHOMERUN_IP_ADDRESS = '192.168.0.100';

// Path used when you don't specify a 'save to' row in a Record block
$CONFIG->DEFAULT_SAVE_TO_PATH = '/path/to/recordings/';

// Make sure the user running the script can create this file; if you specify a relative path, it should be relative to this config file
$CONFIG->LOG_FILE = './hdhomerun-plain-scheduler.log';
