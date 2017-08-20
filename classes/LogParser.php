<?php
namespace GBoudreau\HDHomeRun\Scheduler;

class LogParser
{
    private $_log_text;
    private $_recordings = [];

    /** @var LoggedRecording */
    private $_recording = NULL;

    public function __construct(string $log_file) {
        $this->_log_text = file_get_contents($log_file);
        $this->_parse();
    }

    /**
     * Parse 'log file' content (plain text) into a list of Recording objects
     *
     * @return void
     */
    private function _parse() {
        $line_number = 0;
        foreach (explode("\n", $this->_log_text) as $line) {
            $line_number++;
            $line = trim($line);

            if (empty($line)) {
                // Empty line
                continue;
            }

            if ($line[0] == '=') {
                // Separator
                continue;
            }

            if (preg_match('/\[record\-([a-f0-9]+)\]/', $line, $re)) {
                $hash = $re[1];
            }

            if (string_ends_with($line, 'Starting Recording:')) {
                if (!empty($hash)) {
                    $this->_recordings[$hash] = new LoggedRecording($hash);
                }
                continue;
            } elseif ($line == 'Record') {
                continue;
            } elseif (string_contains($line, 'Removing Record Block')) {
                continue;
            } elseif (string_contains($line, 'Record command')) {
                $this->_recordings[$hash]->setStatus('Recording...');
                continue;
            } elseif (string_contains($line, 'Command completed successfully') || string_contains($line, 'Moving file from')) {
                $this->_recordings[$hash]->setStatus('Recording Completed - Moving');
                continue;
            } elseif (string_contains($line, 'Done.')) {
                $this->_recordings[$hash]->setStatus('Recording Completed');
                continue;
            }

            if (empty($this->_recordings[$hash])) {
                throw new \Exception("Fatal error: each recording schedule needs to start with the word 'Record' alone on a line. Found '$line' on line $line_number. Exiting.");
            }

            $this->_recordings[$hash]->addLine($line, $line_number, TRUE);
        }
    }

    /**
     * @return Recording[]
     */
    public function getRecordings() : array {
        return $this->_recordings;
    }
}
