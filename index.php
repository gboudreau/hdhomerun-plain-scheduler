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
                <th></th>
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

                // Remove duplicate recordings (ongoing recordings will be in both log and schedules file)
                $recordings = array_filter(
                    $recordings,
                    function ($v, $k) {
                        global $recordings;
                        for ($i=0; $i<$k; $i++) {
                            if ($recordings[$i]->getHash() == $v->getHash()) {
                                return FALSE;
                            }
                        }
                        return TRUE;
                    },
                    ARRAY_FILTER_USE_BOTH
                );
            }
            ?>
            <tr>
                <td colspan="6">
                    <button type="button" class="btn" data-toggle="collapse" data-target=".collapsible" onclick="$(this).find('span').toggleClass('show')">
                        <span class="collapse show">Show completed recordings</span>
                        <span class="collapse">Hide completed recordings</span>
                        <i class="fa fa-chevron-down" aria-hidden="false"></i>
                    </button>
                </td>
            </tr>
            <?php foreach ($recordings as $recording) : ?>
                <tr class="<?php echo_if(!$recording->isEditable() && $recording->isComplete(), 'collapsible collapse') ?> <?php phe($recording->getClass()) ?>">
                    <td><?php phe(date('Y-m-d H:i', $recording->getStartTimestamp())) ?></td>
                    <td><?php phe($recording->getDurationAsString()) ?></td>
                    <td><?php phe($recording->getChannel()) ?></td>
                    <td><?php phe($recording->getName()) ?></td>
                    <td><?php phe($recording->getStatus()) ?></td>
                    <td>
                        <?php if ($recording->isEditable()) : ?>
                            <a href="new.php?hash=<?php phe($recording->getHash()) ?>"><i class="fa fa-pencil-square-o" aria-hidden="false"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>

<?php require 'foot.inc.php' ?>
