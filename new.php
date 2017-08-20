<?php
namespace GBoudreau\HDHomeRun\Scheduler;

require_once 'init.inc.php';

global $parser;

if (isset($_POST['createNew'])) {
    $recording = Recording::create(
        $_POST['serie'],
        $_POST['episode'],
        $_POST['episode_name'],
        $_POST['channel'],
        $_POST['datetime'],
        $_POST['duration'] . 'm',
        $_POST['save_to']
    );
    try {
        $recording->validate();
        $recording->addToSchedulesFile();
    } catch (\Exception $ex) {
        echo $ex->getMessage();
    }
}

$recordings = $parser->getRecordings();
usort($recordings, [__NAMESPACE__ . '\Recording', 'sortByDateTime']);
?>

<?php require 'head.inc.php' ?>

<div class="row">
    <div>
        <a href="./">&lt; Back</a>
    </div>
    <main class="p-3 col-sm-11">
        <h2>Schedule New Recording</h2>
        <form method="post" action="">
            <div class="form-group row">
                <label for="serieField" class="col-sm-2 col-form-label">Serie *</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" id="serieField" name="serie" placeholder="eg. The Big Bang Theory" required>
                </div>
            </div>
            <div class="form-group row">
                <label for="episodeField" class="col-sm-2 col-form-label">Episode ID</label>
                <div class="col-sm-6" style="max-width: 150px">
                    <input type="text" class="form-control" id="episodeField" name="episode" placeholder="eg. S01E10">
                </div>
            </div>
            <div class="form-group row">
                <label for="episodeNameField" class="col-sm-2 col-form-label">Episode Name</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" id="episodeNameField" name="episode_name" placeholder="eg. Bla Bla">
                </div>
            </div>
            <div class="form-group row">
                <label for="channelField" class="col-sm-2 col-form-label">Channel *</label>
                <div class="col-sm-2" style="max-width: 150px">
                    <input type="number" step="0.1" class="form-control" id="channelField" name="channel" placeholder="eg. 10.1" required>
                </div>
            </div>
            <div class="form-group row">
                <label for="dateField" class="col-sm-2 col-form-label">Date/Time *</label>
                <div class="col-sm-6" style="max-width: 270px">
                    <input type="datetime-local" class="form-control" id="dateField" name="datetime" placeholder="" required>
                </div>
            </div>
            <div class="form-group row">
                <label for="durationField" class="col-sm-2 col-form-label">Duration *</label>
                <div class="col-sm-2" style="max-width: 150px">
                    <input type="number" class="form-control" id="durationField" name="duration" placeholder="minutes" required>
                </div>
            </div>
            <div class="form-group row">
                <label for="saveToField" class="col-sm-2 col-form-label">Save To Folder</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" id="saveToField" name="save_to" placeholder="<?php phe(Config::get('DEFAULT_SAVE_TO_PATH')) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" name="createNew">Save Recording Schedule</button>
        </form>
    </main>
</div>

<?php require 'foot.inc.php' ?>
