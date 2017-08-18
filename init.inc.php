<?php
namespace GBoudreau\HDHomeRun\Scheduler;

require 'vendor/autoload.php';

require_once 'functions.inc.php';

chdir(__DIR__);

$schedules_file = Config::get('SCHEDULES_FILE');

try {
    global $parser;
    $parser = new SchedulesParser($schedules_file);
} catch (\Exception $ex) {
    _log($ex->getMessage());
    exit(1);
}
