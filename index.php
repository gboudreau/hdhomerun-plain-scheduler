<?php
namespace GBoudreau\HDHomeRun\Scheduler;

require_once 'init.inc.php';

global $parser;

$recordings = $parser->getRecordings();
usort($recordings, [__NAMESPACE__ . '\Recording', 'sortByDateTime']);

?>

<?php require 'head.inc.php' ?>

<div class="row">
    <main class="p-3">
        <h2>
            Scheduled Recordings
            <button class="btn btn-default" onclick="window.location.href='new.php'">Create new</button>
        </h2>
        <table class="table table-striped table-responsive">
            <thead>
            <tr>
                <th>When</th>
                <th>Duration</th>
                <th>Channel</th>
                <th>What</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $log_file = Config::get('LOG_FILE');
            if (!empty($log_file)) {
                $parser = new LogParser($log_file);
                $past_recordings = $parser->getRecordings();
                usort($past_recordings, [__NAMESPACE__ . '\Recording', 'sortByDateTime']);
                $recordings = array_merge($past_recordings, $recordings);
            }
            ?>
            <?php foreach ($recordings as $recording) : ?>
                <tr>
                    <td><?php phe(date('Y-m-d H:i', $recording->getStartTimestamp())) ?></td>
                    <td><?php phe($recording->getDurationAsString()) ?></td>
                    <td><?php phe($recording->getChannel()) ?></td>
                    <td><?php phe($recording->getName()) ?></td>
                    <td><?php phe($recording->getStatus()) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>

<?php require 'foot.inc.php' ?>
