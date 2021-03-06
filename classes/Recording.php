<?php
namespace GBoudreau\HDHomeRun\Scheduler;

class Recording
{
    private $_serie;
    private $_episode;
    private $_episode_name;
    private $_channel;
    private $_date;
    private $_time;
    private $_duration;
    private $_repeats = '';
    private $_save_to_path;

    private $_hash;
    protected $status = 'Scheduled';

    private $_start_line_number;
    private $_end_line_number;

    protected $editable = TRUE;

    public function __construct($hash = NULL) {
        $this->_hash = $hash;
    }

    public static function create($serie, $episode, $episode_name, $channel, $date_time, $duration, $save_to, $repeats) : self {
        $r = new Recording();
        $r->_serie = $serie;
        if (!empty($episode)) {
            $r->_episode = $episode;
        }
        if (!empty($episode_name)) {
            $r->_episode_name = $episode_name;
        }
        $r->_channel = $channel;
        $r->_date = substr($date_time, 0, 10);
        $r->_time = substr($date_time, 11);
        $r->_duration = $duration;
        $r->_repeats = $repeats;
        if (!empty($save_to)) {
            $r->_save_to_path = $save_to;
        }
        return $r;
    }

    public function __toString() : string {
        $record_block = "Record\n";
        $record_block .= sprintf("\tserie  %s\n", $this->_serie);
        if (!empty($this->_episode)) {
            $record_block .= sprintf("\tepisode  %s\n", $this->_episode);
        }
        if (!empty($this->_episode_name)) {
            $record_block .= sprintf("\t\tnamed  %s\n", $this->_episode_name);
        }
        $record_block .= sprintf("\ton channel  %s\n", $this->_channel);
        $record_block .= sprintf("\ton date  %s\n", $this->_date);
        $record_block .= sprintf("\tat  %s\n", $this->_time);
        $record_block .= sprintf("\tduration  %s\n", $this->getDurationAsString());
        if (!empty($this->_repeats)) {
            $record_block .= sprintf("\trepeats  %s\n", $this->_repeats);
        }
        if (!empty($this->_save_to_path)) {
            $record_block .= sprintf("\tsave to  %s\n", $this->_save_to_path);
        }
        return $record_block;
    }

    public function getName() : string {
        return trim(sprintf("%s %s %s", $this->getSerie(), $this->getEpisode(), $this->getEpisodeName()));
    }

    public function getSerie() : string {
        return $this->_serie;
    }

    public function getEpisode() {
        return $this->_episode;
    }

    public function getEpisodeName() {
        return $this->_episode_name;
    }

    public function getChannel() : string {
        return $this->_channel . '';
    }

    public function getStatus() {
        return $this->status;
    }

    public function isEditable() : bool {
        return $this->editable;
    }

    public function getTempPath() : string {
        $folder = Config::get('TEMP_PATH');
        if (!$folder) {
            $folder = $this->_save_to_path . DIRECTORY_SEPARATOR . ".grab";
        }
        $filename = basename($this->getFullPath());
        return clean_dir_name($folder . DIRECTORY_SEPARATOR . $filename);
    }

    public function getSaveTo() {
        return $this->_save_to_path;
    }

    public function getFullPath(bool $include_extension = TRUE) : string {
        if (preg_match('/S([0-9]+)E/', $this->_episode, $re)) {
            $season_folder = sprintf("Season %02d", $re[1]) . DIRECTORY_SEPARATOR;
        } else {
            $season_folder = '';
        }
        return clean_dir_name(
            $this->_save_to_path .
            DIRECTORY_SEPARATOR . $this->_serie .
            DIRECTORY_SEPARATOR . $season_folder .
            $this->_serie .
            " " . $this->_episode .
            (!empty($this->_episode_name) ? " " . $this->_episode_name : "") .
            ($include_extension ? ".ts" : "")
        );
    }

    protected static $_patterns = [
        'serie' => '_serie',
        'episode' => '_episode',
        'named' => '_episode_name',
        'on channel' => '_channel',
        'on date' => '_date',
        'at' => '_time',
        'duration' => '_duration',
        'repeats' => '_repeats',
        'save to' => '_save_to_path',
    ];

    public function addLine(string $line, int $line_number, bool $quiet = FALSE) : bool {
        if (empty($this->_start_line_number)) {
            $this->_start_line_number = $line_number - 1;
        }
        $this->_end_line_number = $line_number;

        $matched = FALSE;
        foreach (static::$_patterns as $pattern => $var_name) {
            if (preg_match('/^\s*' . $pattern . '\s+(.*)$/', $line, $re)) {
                $matched = TRUE;
                $this->{$var_name} = $re[1];
            }
        }
        if (!$matched) {
            //_log("Warning: couldn't parse line $line_number from " . ($this instanceof LoggedRecording ? "log" : "schedules" ) ." file: '$line'. Ignoring.");
            return FALSE;
        }

        return TRUE;
    }

    public function validate() {
        // Check that duration format is OK
        if (empty($this->_duration)) {
            throw new \Exception("Error: missing 'duration' row in Record block starting at line $this->_start_line_number. Skipping this Record block.");
        }
        $this->getDurationInSeconds();

        if (empty($this->_serie)) {
            throw new \Exception("Error: missing 'serie' row in Record block starting at line $this->_start_line_number. Skipping this Record block.");
        }

        if (empty($this->_channel)) {
            throw new \Exception("Error: missing 'on channel' row in Record block starting at line $this->_start_line_number. Skipping this Record block.");
        }

        if (empty($this->_date)) {
            throw new \Exception("Error: missing 'on date' row in Record block starting at line $this->_start_line_number. Skipping this Record block.");
        }

        if (empty($this->_time)) {
            throw new \Exception("Error: missing 'at' row in Record block starting at line $this->_start_line_number. Skipping this Record block.");
        }

        if (empty($this->_episode)) {
            $this->_episode = 'S' . date('Y', strtotime($this->_date)) . 'E' . date('md', strtotime($this->_date));
        }

        if (empty($this->_save_to_path)) {
            if (!Config::get('DEFAULT_SAVE_TO_PATH')) {
                throw new \Exception("Error: missing 'save to' row in Record block starting at line $this->_start_line_number. Skipping this Record block.");
            }
            $this->_save_to_path = Config::get('DEFAULT_SAVE_TO_PATH');
        }
    }

    public function getStartTimestamp() : int {
        // @TODO Allow recurring recordings
        return strtotime($this->_date . ' ' . $this->_time);
    }

    public function isComplete() : bool {
        $end_ts = $this->getStartTimestamp() + $this->getDurationInSeconds();
        return ( $end_ts <= time() );
    }

    public function startsNow() : bool {
        $starts_ts = $this->getStartTimestamp();

        // Recordings starts 1m early
        $starts_soon = $starts_ts >= time() && $starts_ts <= time() + 60;
        if ($starts_soon) {
            return TRUE;
        }

        // Start the recording midway, if we somehow missed the above start
        $starts_in_the_past = $starts_ts < time();
        if ($starts_in_the_past && !$this->isComplete()) {
            return TRUE;
        }

        return FALSE;
    }

    private function _adjustDurationToStartRecordingNow(bool $quiet = FALSE) {
        $end_ts = $this->getStartTimestamp() + $this->getDurationInSeconds();
        $duration_in_secs = $end_ts - time();
        $this->_duration = $duration_in_secs . 's';
    }

    public function getRepeats() : string {
        return $this->_repeats;
    }

    public function getDurationInSeconds() : int {
        $duration = 0;
        if (preg_match('/([0-9]+)h/', $this->_duration, $re)) {
            $duration += $re[1] * 60 * 60;
        }
        if (preg_match('/([0-9]+)m/', $this->_duration, $re)) {
            $duration += $re[1] * 60;
        }
        if (preg_match('/([0-9]+)s/', $this->_duration, $re)) {
            $duration += $re[1];
        }
        if (empty($duration)) {
            throw new \Exception("Error: couldn't parse duration value '$this->_duration'; expected format: XhXmXs");
        }
        return $duration;
    }

    public function getDurationAsString() : string {
        $duration_in_secs = $this->getDurationInSeconds();
        $duration = '';
        if ($duration_in_secs >= 60*60) {
            $hours = floor($duration_in_secs / (60*60));
            $duration .= $hours . 'h';
            $duration_in_secs -= $hours * 60*60;
        }
        if ($duration_in_secs >= 60) {
            $minutes = floor($duration_in_secs / 60);
            $duration .= $minutes . 'm';
            $duration_in_secs -= $minutes * 60;
        }
        if ($duration_in_secs > 0) {
            $duration .= $duration_in_secs . 's';
        }
        return $duration;
    }

    public function startRecordingThread() {
        $temp_path = $this->getTempPath();
        if (file_exists($temp_path)) {
            // Recording is already ongoing?
            $running_recordings = exec("ps ax | grep -v grep | grep 'bin/record.php' | grep " . escapeshellarg($this->getHash()) . " | wc -l");
            if ($running_recordings > 0) {
                return;
            }
            _log("Temp file exists, but no recording process found. Will rename temp file and re-start recording.");
            rename($temp_path, "$temp_path.1");
        }

        _log("Starting recording thread for $this->_serie (" . $this->getHash() . ") ...");
        $cmd = [
            Config::get('PHP_BIN', 'php'),
            Config::get('CWD', '.') . '/bin/record.php',
            escapeshellarg($this->getHash()),
            escapeshellarg(base64_encode(serialize($this))),
            '>>' . escapeshellarg(Config::get('LOG_FILE')),
            '2>>' . escapeshellarg(Config::get('LOG_FILE')),
            '&'
        ];
        exec(implode(' ', $cmd));
    }

    public function getHash() : string {
        if (empty($this->_hash)) {
            $this->_hash = md5($this->_serie . $this->_episode . $this->_date . $this->_time . $this->_channel . $this->_save_to_path);
        }
        return $this->_hash;
    }

    public function startRecording() {
        $temp_path = $this->getTempPath();
        if (file_exists($temp_path)) {
            // Recording is already ongoing
            return;
        }

        $folder = dirname($temp_path);
        if (!is_dir($folder)) {
            mkdir($folder, 0755, TRUE);
        }

        $final_path = $this->getFullPath();
        if (!is_dir(dirname($final_path))) {
            mkdir(dirname($final_path), 0755, TRUE);
        }

        // If the $final_path file already exists, append a (#) suffix to the filename; eg. "Some File (1).mpg"
        $i = 1;
        $ext = last(explode('.', $final_path));
        $final_path_no_ext = substr($final_path, 0, -(strlen($ext)+1));
        while (file_exists($final_path)) {
            $final_path = sprintf("%s (%d).%s", $final_path_no_ext, $i++, $ext);
        }
        if (is_link($final_path)) {
            unlink($final_path);
        }

        // Create a symlink at $final_path, to allow watching the recording while it is not yet complete
        symlink($temp_path, $final_path);


        _log("================================================================================", TRUE);

        // Adjust _duration as needed
        $this->_adjustDurationToStartRecordingNow();

        $hdhomerun_url = 'http://' . Config::get('HDHOMERUN_IP_ADDRESS') . ':5004/auto/v' . $this->_channel . '?duration=' . $this->getDurationInSeconds();
        // @TODO Add optional transcoding parameter

        _log("Starting Recording:\n" . trim($this));

        $fp = fopen($temp_path, 'w');

        $ch = curl_init($hdhomerun_url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        $success = curl_exec($ch);

        if ($success) {
            _log("Curl completed successfully");
        } else {
            _log("Error: curl command error: [" . curl_errno($ch) . "] " . curl_error($ch));
        }

        fclose($fp);
        curl_close($ch);

        if (file_exists($temp_path) && filesize($temp_path) > 0) {
            _log("Moving file from $temp_path to $final_path");

            // Delete temporary symlink
            if (is_link($final_path)) {
                unlink($final_path);
            }

            if (!rename($temp_path, $final_path)) {
                _log("Error: couldn't move temporary file from $temp_path to $final_path");
            }
        } else {
            if (file_exists($temp_path)) {
                _log("Error: recording failed! Empty file found at $temp_path (deleting)");
                unlink($temp_path);
            } else {
                _log("Error: recording failed! No temporary file found at $temp_path");
            }
        }
        _log("Done.");
    }

    public function addToSchedulesFile() {
        $schedules = file_get_contents(Config::get('SCHEDULES_FILE'));
        $schedules .= "\n" . $this;
        $success = file_put_contents(Config::get('SCHEDULES_FILE'), $schedules);
        if (!$success) {
            throw new \Exception("Failed to write to schedules file at " . Config::get('SCHEDULES_FILE'));
        }
    }

    public function removeFromSchedulesFile(bool $quiet = FALSE) {
        if (!$quiet) {
            _log("Removing Record Block (" . $this->getHash() . ") from schedules file: lines $this->_start_line_number to $this->_end_line_number");
        }
        $schedules = explode("\n", file_get_contents(Config::get('SCHEDULES_FILE')));
        $new_schedules = [];
        for ($l = 0; $l<count($schedules); $l++) {
            if ($l < $this->_start_line_number-1 || $l > $this->_end_line_number-1) {
                $new_schedules[] = $schedules[$l];
            }
        }
        $new_schedules = implode("\n", $new_schedules);

        // If this is a repeating schedule, schedule the next occurrence
        if (!empty($this->_repeats) && !$quiet) {
            $repeats = str_replace(' ', '', strtolower($this->_repeats));
            if ($repeats == 'weekly') {
                $repeats = strtolower(date('D', strtotime($this->_date)));
            } elseif ($repeats == 'daily') {
                $repeats = "mon-sun";
            } elseif ($repeats == 'weekdays') {
                $repeats = "mon-fri";
            }
            if (preg_match('/([[:alpha:]]{3})-([[:alpha:]]{3})/', $repeats, $re)) {
                $dows = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                $start = array_search($re[1], $dows);
                $end = array_search($re[2], $dows);
                $dows = array_slice($dows, $start, $end-$start+1);
            } else {
                $dows = explode(',', $repeats);
            }

            if (!empty($dows)) {
                $next_date = strtotime($this->_date);
                $i = 0;
                do {
                    $next_date = strtotime('next day', $next_date);
                    $next_dow = strtolower(date('D', $next_date));
                    if ($i++ >= 7) {
                        $next_date = NULL;
                        break;
                    }
                } while (!array_contains($dows, $next_dow));

                if ($next_date != NULL) {
                    $this->_date = date('Y-m-d', $next_date);
                    if (preg_match('/S(\d\d?)E(\d\d\d?)/', $this->_episode, $re)) {
                        $this->_episode = sprintf("S%02dE%02d", $re[1], $re[2]+1);
                        $this->_episode_name = date('Y-m-d', strtotime($this->_date));
                    } else {
                        $this->_episode = NULL;
                        $this->_episode_name = NULL;
                    }
                    $this->validate();
                    $new_schedules .= "\n" . $this;
                    _log("Scheduling new occurrence of " . $this->getName() . " ($this->_episode) on $this->_date.");
                }
            }
        }

        file_put_contents(Config::get('SCHEDULES_FILE'), $new_schedules);
    }

    public static function sortByDateTime(self $r1, self $r2) : int {
        $starts_ts1 = $r1->getStartTimestamp();
        $starts_ts2 = $r2->getStartTimestamp();
        if ($starts_ts1 != $starts_ts2) {
            return ( $starts_ts1 < $starts_ts2 ? -1 : 1 );
        }
        return 0;
    }

    public function getClass() : string {
        if (string_contains($this->status, 'Error: ')) {
            return 'bg-danger';
        }
        switch ($this->status) {
        case 'Recording...':
        case 'Recording Completed - Moving':
            return 'table-danger';
        case 'Recording Completed':
            return 'table-success';
        case 'Scheduled':
            return 'table-warning';
        }
        return '';
    }
}
