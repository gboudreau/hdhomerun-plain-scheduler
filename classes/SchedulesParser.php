<?php
namespace GBoudreau\HDHomeRun\Scheduler;

class SchedulesParser
{
    private $_schedules_text;
    private $_recordings = [];

    private $_recording = NULL;

    public function __construct(string $schedules_file) {
        $this->_schedules_text = file_get_contents($schedules_file);
        $this->_parse();
    }

    /**
     * Parse 'schedules file' content (plain text) into a list of Recording objects
     *
     * @return void
     * @throws \Exception An exception will be thrown if the schedules file contains invalid blocks.
     */
    private function _parse() {
        $line_number = 0;
        foreach (explode("\n", $this->_schedules_text) as $line) {
            $line_number++;
            $line = trim($line);

            if (empty($line)) {
                // Empty line
                continue;
            }

            if ($line[0] == '#' || $line[0] == ';') {
                // Comments
                continue;
            }

            if (strtolower($line) == 'record') {
                // Previous record bock ended (if any)
                $this->_finishRecording();

                // Recording block starts
                $this->_recording = new Recording();
                continue;
            }

            if (empty($this->_recording)) {
                throw new \Exception("Fatal error: each recording schedule needs to start with the word 'Record' alone on a line. Found '$line' on line $line_number. Exiting.");
            }

            $this->_recording->addLine($line, $line_number);
        }

        $this->_finishRecording();
    }

    private function _finishRecording() {
        if (isset($this->_recording)) {
            try {
                $this->_recording->validate();
                $this->_recordings[] = $this->_recording;
            } catch (\Exception $ex) {
                // Ignore this recording, but log the error
                _log($ex->getMessage());
            }
            unset($this->_recording);
        }
    }

    /**
     * @return Recording[]
     */
    public function getRecordings() : array {
        return $this->_recordings;
    }
}
