<?php
namespace GBoudreau\HDHomeRun\Scheduler;

class SchedulesParser
{
    private $_schedules_text;
    private $_recordings = [];

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
                if (isset($recording)) {
                    // End of Recording block
                    $recording->validate();
                    $this->_recordings[] = $recording;
                    unset($recording);
                }
                continue;
            }
            if ($line[0] == '#' || $line[0] == ';') {
                // Comments
                continue;
            }

            if (strtolower(trim($line)) == 'record') {
                // Recording block starts
                $recording = new Recording();
                continue;
            }

            if (empty($recording)) {
                throw new \Exception("Fatal error: each recording schedule needs to start with the word 'Record' alone on a line. Found '$line' on line $line_number. Exiting.");
            }

            $recording->addLine($line, $line_number);
        }

        if (isset($recording)) {
            // End of Recording block at the end of the file
            try {
                $recording->validate();
                $this->_recordings[] = $recording;
            } catch (\Exception $ex) {
                // Ignore this recording, but log the error
                _log($ex->getMessage());
            }
            unset($recording);
        }
    }

    /**
     * @return Recording[]
     */
    public function getRecordings() : array {
        return $this->_recordings;
    }
}
