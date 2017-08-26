<?php
namespace GBoudreau\HDHomeRun\Scheduler;

class XMLTV
{
    public static function getEPGFromFile(array $recordings) {
        $channels = [];
        $programs  = [];

        if (Config::get('XMLTV_FILE')) {
            $tmp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hdhr-epg.cache';

            if (file_exists($tmp_file) && filemtime($tmp_file) >= time() - 2*60*60) {
                $epg = json_decode(file_get_contents($tmp_file));
                return $epg;
            }

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
                    if ($recording->getChannel() == $channel && $start >= $recording->getStartTimestamp() && $start < $recording->getStartTimestamp() + $recording->getDurationInSeconds()) {
                        $recording_scheduled = $recording->getName();
                        break;
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

        $epg = (object) ['channels' => $channels, 'programs' => $programs];

        if (isset($tmp_file)) {
            file_put_contents($tmp_file, json_encode($epg));
        }

        return $epg;
    }
}
