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
    private $_save_to_path;

    private $_hash;
    protected $_status = 'Scheduled';

    private $_start_line_number;
    private $_end_line_number;

    public function __construct($hash = NULL) {
        $this->_hash = $hash;
    }

    public static function create($serie, $episode, $episode_name, $channel, $date_time, $duration, $save_to) : self {
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
        if (!empty($episode_name)) {
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
            $record_block .= sprintf("\t\tnamed %s\n", $this->_episode_name);
        }
        $record_block .= sprintf("\ton channel  %s\n", $this->_channel);
        $record_block .= sprintf("\ton date  %s\n", $this->_date);
        $record_block .= sprintf("\tat  %s\n", $this->_time);
        $record_block .= sprintf("\tduration  %s\n", $this->getDurationAsString());
        if (!empty($this->_save_to_path)) {
            $record_block .= sprintf("\tsave to  %s\n", $this->_save_to_path);
        }
        return $record_block;
    }

    public function getName() : string {
        return trim(sprintf("%s %s %s", $this->_serie, $this->_episode, $this->_episode_name));
    }

    public function getChannel() : string {
        return $this->_channel . '';
    }

    public function getStatus() {
        return $this->_status;
    }

    public function getTempPath() : string {
        $folder = Config::get('TEMP_PATH');
        if (!$folder) {
            $folder = $this->_save_to_path . DIRECTORY_SEPARATOR . ".grab";
        }
        $filename = basename($this->getFullPath());
        return clean_dir_name($folder . DIRECTORY_SEPARATOR . $filename);
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
            _log("Warning: couldn't parse line $line_number from " . ($this instanceof LoggedRecording ? "log" : "schedules" ) ." file: '$line'. Ignoring.");
            return FALSE;
        }

        return TRUE;
    }

    public function validate() {
        // Check that duration format is OK
        if (empty($this->_duration)) {
            throw new \Exception("Error: missing 'duration' row in Record block starting at line $this->_start_line_number. Skipping this Record block.");
        }
        $this->_getDurationInSeconds();

        if (empty($this->_serie)) {
            throw new \Exception("Error: missing 'serie' row in Record block starting at line $this->_start_line_number. Skipping this Record block.");
        }

        if (empty($this->_episode)) {
            $this->_episode = 'S' . date('Y') . 'E' . date('md');
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
        $end_ts = $this->getStartTimestamp() + $this->_getDurationInSeconds();
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
        $end_ts = $this->getStartTimestamp() + $this->_getDurationInSeconds();
        $duration_in_secs = $end_ts - time();
        $this->_duration = $duration_in_secs . 's';
    }

    private function _getDurationInSeconds() : int {
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
        $duration_in_secs = $this->_getDurationInSeconds();
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
            // Recording is already ongoing
            return;
        }

        $cmd = 'php ./record.php ' . escapeshellarg($this->getHash());
        exec("$cmd >/dev/null 2>/dev/null &");
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


        _log("================================================================================", TRUE);

        // Adjust _duration as needed
        $this->_adjustDurationToStartRecordingNow();

        $hdhomerun_url = 'http://' . Config::get('HDHOMERUN_IP_ADDRESS') . ':5004/auto/v' . $this->_channel . '?duration=' . $this->_getDurationInSeconds();
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
            $final_path = $this->getFullPath();
            _log("Moving file from $temp_path to $final_path");
            if (!is_dir(dirname($final_path))) {
                mkdir(dirname($final_path), 0755, TRUE);
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

    public function removeFromSchedulesFile() {
        _log("Removing Record Block (" . $this->getHash() . ") from schedules file: lines $this->_start_line_number to $this->_end_line_number");
        $schedules = explode("\n", file_get_contents(Config::get('SCHEDULES_FILE')));
        $new_schedules = [];
        for ($l = 0; $l<count($schedules); $l++) {
            if ($l < $this->_start_line_number-1 || $l > $this->_end_line_number-1) {
                $new_schedules[] = $schedules[$l];
            }
        }
        file_put_contents(Config::get('SCHEDULES_FILE'), implode("\n", $new_schedules));
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
        if (string_contains($this->_status, 'Error: ')) {
            return 'bg-danger';
        }
        switch ($this->_status) {
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
