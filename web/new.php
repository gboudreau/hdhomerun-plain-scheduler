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

$channels = [];
$programs  = [];
if (Config::get('XMLTV_FILE')) {
    $epg_xml = simplexml_load_file(Config::get('XMLTV_FILE'));

    foreach ($epg_xml->channel as $channel) {
        $channels[(string) $channel->attributes()['id']] = (string) $channel->{'display-name'};
    }

    foreach ($epg_xml->programme as $program) {
        $start = strtotime((string) $program->attributes()['start']);
        $stop = strtotime((string) $program->attributes()['stop']);
        if ($stop < time()) {
            continue;
        }
        $channel = (string) $program->attributes()['channel'];
        $title = (string) $program->title;
        $episode_name = (string) $program->{'sub-title'};

        if (empty($title)) {
            continue;
        }

        $recording_scheduled = FALSE;
        foreach ($recordings as $recording) {
            if (strtolower($recording->getSerie()) == strtolower($title)) {
                $recording_scheduled = TRUE;
            }
        }

        $programs[$channel][$start] = (object) [
            'serie' => $title,
            'episode_name' => $episode_name,
            'start' => date('Y-m-d H:i:s', $start),
            'start_t' => str_replace(' ', 'T', date('Y-m-d H:i:s.0', $start)),
            'duration' => round(($stop - $start) / 60),
            'channel' => (string) $channel,
            'recording' => $recording_scheduled,
        ];
    }

    foreach ($programs as $channel => $ps) {
        ksort($ps);
        $programs[$channel] = $ps;
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

            <?php if (!empty($channels)) : ?>
                <hr/>
                <div class="form-group row">
                    <label for="autoFillChannel" class="col-sm-2 col-form-label"><h4>Auto-fill from EPG</h4></label>
                    <div class="col-sm-6">
                        <select class="form-control" id="autoFillChannel" onchange="showEPGPrograms(this)">
                            <option value="">Select a channel</option>
                            <?php foreach ($channels as $id => $name) : ?>
                                <option value="<?php phe($id) ?>" <?php echo_if(isset($this_recording) && $this_recording->getChannel() == $id, 'selected="selected"') ?>><?php phe("$id - $name") ?></option>
                            <?php endforeach; ?>
                        </select><br/>
                        <select class="form-control invisible" id="autoFillProgram" onchange="autoFillFromEPG(this)">
                        </select>
                        <input type="text" class="form-control invisible" id="filterProgramsField" placeholder="Filter programs">
                    </div>
                </div>
                <hr/>
            <?php endif; ?>

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
                <div class="col-sm-2" style="max-width: 190px">
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

<script>
    var programs = <?php echo json_encode($programs) ?>;
    var selected_channel;
    function showEPGPrograms(select) {
        selected_channel = $(select).val();
        var ps = programs[selected_channel];
        var $select = $('#autoFillProgram');
        $select.empty();

        var option = $('<option/>').attr({ 'value': '' }).text("Select a program");
        $select.append(option);

        for (var start in ps) {
            if (ps.hasOwnProperty(start)) {
                var p = ps[start];
                option = $('<option/>').attr({'value': start, 'data-serie': p.serie.toLowerCase(), 'data-episode-name': p.episode_name.toLowerCase()}).text("[" + p.start + "] " + p.serie + (p.episode_name != '' ? " - " + p.episode_name : "") + (p.recording ? " - RECORDING" : ""));
                $select.append(option);
            }
        }

        $select.removeClass('invisible');

        $('#filterProgramsField').removeClass('invisible').off().on('change', filterEPGPrgrams);
    }

    function autoFillFromEPG(select) {
        var program_start = $(select).val();
        var ps = programs[selected_channel];
        for (var start in ps) {
            if (start === program_start) {
                var p = ps[start];
                $('#serieField').val(p.serie);
                $('#episodeNameField').val(p.episode_name);
                $('#channelField').val(p.channel);
                $('#dateField').val(p.start_t);
                $('#durationField').val(p.duration);
                break;
            }
        }
    }

    function filterEPGPrgrams() {
        var input = this;
        var filter = $(input).val().toLowerCase();
        if (filter === '') {
            $('#autoFillProgram option').show();
            return;
        }
        $('#autoFillProgram option').hide();
        var $opt = $("option[data-serie*='" + filter + "'], option[data-episode-name*='" + filter + "']");
        $opt.show();
    }
</script>

<?php require 'foot.inc.php' ?>
