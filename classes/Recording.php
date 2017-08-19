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

    private $_start_line_mnumber;

    public function __construct() {
    }

    public function __toString() : string {
        return sprintf("Recording {\n\t%s %s %s\n\tchannel: %s\n\tdate: %s %s\n\tduration: %s\n\tsave to: %s\n\t\tthen move to: %s\n}", $this->_serie, $this->_episode, $this->_episode_name, $this->_channel, $this->_date, $this->_time, $this->_getDurationAsString(), $this->getTempPath(), $this->getFullPath());
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

    private static $_patterns = [
        'serie' => '_serie',
        'episode' => '_episode',
        'named' => '_episode_name',
        'on channel' => '_channel',
        'on date' => '_date',
        'at' => '_time',
        'duration' => '_duration',
        'save to' => '_save_to_path',
    ];

    public function addLine(string $line, int $line_number) {
        if (empty($this->_start_line_mnumber)) {
            $this->_start_line_mnumber = $line_number - 1;
        }
        $matched = FALSE;
        foreach (static::$_patterns as $pattern => $var_name) {
            if (preg_match('/^\s*' . $pattern . '\s+(.*)$/', $line, $re)) {
                $matched = TRUE;
                $this->{$var_name} = $re[1];
            }
        }
        if (!$matched) {
            _log("Warning: couldn't parse line $line_number from schedules file: '$line'. Ignoring.");
        }
    }

    public function validate() {
        // Check that duration format is OK
        if (empty($this->_duration)) {
            throw new \Exception("Error: missing 'duration' row in Record block starting at line $this->_start_line_mnumber. Skipping this Record block.");
        }
        $this->_getDurationInSeconds();

        if (empty($this->_serie)) {
            throw new \Exception("Error: missing 'serie' row in Record block starting at line $this->_start_line_mnumber. Skipping this Record block.");
        }

        if (empty($this->_episode)) {
            $this->_episode = 'S' . date('Y') . 'E' . date('md');
        }

        if (empty($this->_channel)) {
            throw new \Exception("Error: missing 'on channel' row in Record block starting at line $this->_start_line_mnumber. Skipping this Record block.");
        }

        if (empty($this->_date)) {
            throw new \Exception("Error: missing 'on date' row in Record block starting at line $this->_start_line_mnumber. Skipping this Record block.");
        }

        if (empty($this->_time)) {
            throw new \Exception("Error: missing 'at' row in Record block starting at line $this->_start_line_mnumber. Skipping this Record block.");
        }

        if (empty($this->_save_to_path)) {
            if (!Config::get('DEFAULT_SAVE_TO_PATH')) {
                throw new \Exception("Error: missing 'save to' row in Record block starting at line $this->_start_line_mnumber. Skipping this Record block.");
            }
            $this->_save_to_path = Config::get('DEFAULT_SAVE_TO_PATH');
        }
    }

    private function _getStartTimestamp() : int {
        // @TODO Allow recurring recordings
        return strtotime($this->_date . ' ' . $this->_time);
    }

    public function isComplete() : bool {
        $end_ts = $this->_getStartTimestamp() + $this->_getDurationInSeconds();
        return ( $end_ts <= time() );
    }

    public function startsNow() : bool {
        $starts_ts = $this->_getStartTimestamp();

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
        $end_ts = $this->_getStartTimestamp() + $this->_getDurationInSeconds();
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

    private function _getDurationAsString() : string {
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
        return md5($this->_serie . $this->_episode . $this->_date . $this->_time . $this->_channel . $this->_save_to_path);
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

        $cmd = "curl -so " . escapeshellarg($temp_path) . " " . escapeshellarg($hdhomerun_url);

        _log("Starting Recording: " . $this);
        _log("Record command: $cmd");

        exec($cmd, $output, $return);

        if ($return === 0) {
            _log("Command completed successfully (return value = 0)");
        } else {
            _log("Error: command exited with value $return");
            _log("  Command output: " . implode("\n", $output));
        }

        if (file_exists($temp_path)) {
            $final_path = $this->getFullPath();
            _log("Moving file from $temp_path to $final_path");
            if (!is_dir(dirname($final_path))) {
                mkdir(dirname($final_path), 0755, TRUE);
            }
            if (!rename($temp_path, $final_path)) {
                _log("Error: couldn't move temporary file from $temp_path to $final_path");
            }
        } else {
            _log("Error: recording failed! No temporary file found at $temp_path");
        }
        _log("Done.");
    }
}
