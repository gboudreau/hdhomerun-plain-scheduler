<?php
namespace GBoudreau\HDHomeRun\Scheduler;

$CONFIG = new \stdClass;

// Text file that contains the recording schedules; if you specify a relative path, it should be relative to this config file
$CONFIG->SCHEDULES_FILE = './schedules.txt';

// IP address of your HDHomeRun device
// Find it on your router admin page. or using: `hdhomerun_config discover`
$CONFIG->HDHOMERUN_IP_ADDRESS = '192.168.0.100';

// Path that will be used to temporarily save recorded streams, until they end
// If not specified, then a .grab folder inside the save_to folder will be used
$CONFIG->TEMP_PATH = '/path/to/temp/folder';

// Path used when you don't specify a 'save to' row in a Record block
$CONFIG->DEFAULT_SAVE_TO_PATH = '/path/to/recordings/';

// Make sure the user running the script can create this file; if you specify a relative path, it should be relative to this config file
$CONFIG->LOG_FILE = './hdhomerun-plain-scheduler.log';

// If you have access to XMLTV data, we can use this to simplify scheduling new recordings on the web
// Look for zap2xml or mc2xml if you're unsure how to obtain XMLTV data for your region.
$CONFIG->XMLTV_FILE = '/path/to/epg/data/xmltv.xml';

// If defined, only keep a subset of the channels from the XMLTV data
#$CONFIG->XMLTV_CHANNELS = [2.1, 6.1, 10.1, 12.1, 15.1, 17.1, 29.1];
