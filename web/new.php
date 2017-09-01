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
        $_POST['save_to'],
        $_POST['repeats']
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

        $recordings[] = $recording;
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

$epg = XMLTV::getEPGFromFile($recordings);
$channels = $epg->channels;
$programs = $epg->programs;
$categories = $epg->categories;
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

            <?php if (!empty($channels) && empty($_GET['hash'])) : ?>
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
                        <?php foreach ($categories as $k => $category) : ?>
                            <span class="invisible">
                                <label for="<?php phe("filterCat" . $k) ?>"><?php phe($category) ?></label>
                                <input type="checkbox" checked="checked" id="<?php phe("filterCat" . $k) ?>" value="<?php phe($category) ?>" />
                                &nbsp; &nbsp;
                            </span>
                        <?php endforeach; ?>
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
                <label for="repeatsField" class="col-sm-2 col-form-label">Repeats</label>
                <div class="col-sm-2" style="max-width: 150px">
                    <select class="form-control" id="repeatsField" name="repeats">
                        <option value="">No</option>
                        <option value="weekly" <?php echo_if(isset($this_recording) && $this_recording->getRepeats() == 'weekly', 'selected="selected"') ?>>Weekly</option>
                        <option value="daily" <?php echo_if(isset($this_recording) && $this_recording->getRepeats() == 'daily', 'selected="selected"') ?>>Daily</option>
                        <option value="weekdays" <?php echo_if(isset($this_recording) && $this_recording->getRepeats() == 'weekdays', 'selected="selected"') ?>>Weekdays</option>
                        <option value="mon-thu" <?php echo_if(isset($this_recording) && $this_recording->getRepeats() == 'mon-thu', 'selected="selected"') ?>>Monday to Thursday</option>
                        <option value="tue-fri" <?php echo_if(isset($this_recording) && $this_recording->getRepeats() == 'tue-fri', 'selected="selected"') ?>>Tuesday to Friday</option>
                    </select>
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
                option = $('<option/>').attr({'value': start, 'data-serie': p.serie.toLowerCase(), 'data-episode-name': p.episode_name.toLowerCase(), 'data-category': p.category.toLowerCase()}).text("[" + p.start + "] " + p.serie + (p.episode_name != '' ? " - " + p.episode_name : "") + (p.recording !== false ? " - RECORDING: " + p.recording : ""));
                $select.append(option);
            }
        }

        $('#filterProgramsField').off().on('change', filterEPGPrograms);

        var $categories_checkboxes = $select.closest('.form-group').find('input[type="checkbox"]');
        $categories_checkboxes.off().on('change', filterEPGPrograms);

        $select.closest('.form-group').find('.invisible').removeClass('invisible');

        filterEPGPrograms();
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

    function filterEPGPrograms() {
        // Hide all programs; will show those matching the filter(s)
        $('#autoFillProgram option').hide();

        var $opt = $('#autoFillProgram').find("option");

        $opt.first().show();

        var filter = $('#filterProgramsField').val().toLowerCase();
        if (filter !== '') {
            $opt = $opt.filter("option[data-serie*='" + filter + "'], option[data-episode-name*='" + filter + "']");
        }

        var $categories_checkboxes = $('#autoFillProgram').closest('.form-group').find('input[type="checkbox"]');
        $categories_checkboxes.each(function () {
            var $checkbox = $(this);
            if (!$checkbox.is(':checked')) {
                var filter = $checkbox.val() === 'N/A' ? '' : $checkbox.val();
                $opt = $opt.filter("option[data-category!='" + filter.toLowerCase() + "']");
            }
        });

        $opt.show();
    }
</script>

<?php require 'foot.inc.php' ?>
