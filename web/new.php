<?php
namespace GBoudreau\HDHomeRun\Scheduler;

chdir(__DIR__ . '/..');
require_once 'init.inc.php';

global $parser;

$recordings = $parser->getRecordings();

if (isset($_POST['createNew'])) {
    $recording = Recording::create(
        $_POST['serie'],
        $_POST['episode'],
        $_POST['episode_name'],
        $_POST['channel'],
        substr($_POST['datetime'], 0, 16),
        $_POST['duration'] . 'm',
        $_POST['save_to']
    );
    try {
        $recording->validate();

        // New recording is OK; delete old entry from schedules file
        foreach ($recordings as $r) {
            if ($r->getHash() == $_POST['hash']) {
                $r->removeFromSchedulesFile(TRUE);
                break;
            }
        }

        $recording->addToSchedulesFile();

        $_GET['hash'] = $recording->getHash();

        $_REQUEST['result']['success'] = 'Successfully saved recording.';
    } catch (\Exception $ex) {
        $_REQUEST['result']['error'] = $ex->getMessage();
    }
}

if (!empty($_GET['hash'])) {
    foreach ($recordings as $recording) {
        if ($recording->getHash() == $_GET['hash']) {
            $this_recording = $recording;
            break;
        }
    }
}
?>

<?php require 'head.inc.php' ?>

<div class="row">
    <div>
        <a href="./"><i class="fa fa-chevron-left" aria-hidden="true"></i> Back</a>
    </div>
    <main class="p-3 col-sm-11">
        <h2>Schedule New Recording</h2>

        <?php print_result() ?>
        <form method="post" action="">
            <input type="hidden" name="hash" value="<?php if (isset($this_recording)) phe($this_recording->getHash()) ?>" />
            <div class="form-group row">
                <label for="serieField" class="col-sm-2 col-form-label">Serie *</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" id="serieField" name="serie" placeholder="eg. The Big Bang Theory" required value="<?php if (isset($this_recording)) phe($this_recording->getSerie()) ?>">
                </div>
            </div>
            <div class="form-group row">
                <label for="episodeField" class="col-sm-2 col-form-label">Episode ID</label>
                <div class="col-sm-6" style="max-width: 150px">
                    <input type="text" class="form-control" id="episodeField" name="episode" placeholder="eg. S01E10" value="<?php if (isset($this_recording)) phe($this_recording->getEpisode()) ?>">
                </div>
            </div>
            <div class="form-group row">
                <label for="episodeNameField" class="col-sm-2 col-form-label">Episode Name</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" id="episodeNameField" name="episode_name" placeholder="eg. Bla Bla" value="<?php if (isset($this_recording)) phe($this_recording->getEpisodeName()) ?>">
                </div>
            </div>
            <div class="form-group row">
                <label for="channelField" class="col-sm-2 col-form-label">Channel *</label>
                <div class="col-sm-2" style="max-width: 150px">
                    <input type="number" step="0.1" class="form-control" id="channelField" name="channel" placeholder="eg. 10.1" required value="<?php if (isset($this_recording)) phe($this_recording->getChannel()) ?>">
                </div>
            </div>
            <div class="form-group row">
                <label for="dateField" class="col-sm-2 col-form-label">Date/Time *</label>
                <div class="col-sm-6" style="max-width: 270px">
                    <input type="datetime-local" class="form-control" id="dateField" name="datetime" placeholder="" required value="<?php if (isset($this_recording)) phe(str_replace(' ', 'T', date('Y-m-d H:i:s.0', $this_recording->getStartTimestamp()))) ?>">
                </div>
            </div>
            <div class="form-group row">
                <label for="durationField" class="col-sm-2 col-form-label">Duration *</label>
                <div class="col-sm-2" style="max-width: 150px">
                    <input type="number" class="form-control" id="durationField" name="duration" placeholder="minutes" required value="<?php if (isset($this_recording)) phe($this_recording->getDurationInSeconds() / 60) ?>">
                </div>
            </div>
            <div class="form-group row">
                <label for="saveToField" class="col-sm-2 col-form-label">Save To Folder</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" id="saveToField" name="save_to" placeholder="<?php phe(Config::get('DEFAULT_SAVE_TO_PATH')) ?>" value="<?php if (isset($this_recording)) phe($this_recording->getSaveTo()) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" name="createNew">Save Recording Schedule</button>
        </form>
    </main>
</div>

<?php require 'foot.inc.php' ?>
