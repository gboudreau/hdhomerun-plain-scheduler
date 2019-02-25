<?php
namespace GBoudreau\HDHomeRun\Scheduler;

class XMLTV
{
    public static function getEPGFromFile(array $recordings) {
        $channels = [];
        $programs  = [];
        $categories = [];

        if (Config::get('XMLTV_FILE')) {
            $tmp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hdhr-epg.cache';

            if (file_exists($tmp_file) && filesize($tmp_file) > 50000 && filemtime($tmp_file) >= time() - 2*60*60) {
                $epg = json_decode(file_get_contents($tmp_file));
                static::_filterChannels($epg, $recordings);
                return $epg;
            }

            $epg_xml = simplexml_load_file(Config::get('XMLTV_FILE'));

            foreach ($epg_xml->channel as $channel) {
                $id = sprintf("%.1f", (string) $channel->attributes()['id']);
                $channels[$id] = (string) $channel->{'display-name'};
            }

            foreach ($epg_xml->programme as $program) {
                $start = strtotime((string) $program->attributes()['start']);
                $stop = strtotime((string) $program->attributes()['stop']);
                if ($stop < time()) {
                    continue;
                }

                $channel = sprintf("%.1f", (string) $program->attributes()['channel']);
                $title = (string) $program->title;
                $episode_name = (string) $program->{'sub-title'};
                $category = (string) $program->{'category'};

                if (empty($title)) {
                    continue;
                }

                $programs[$channel][$start] = (object) [
                    'serie' => $title,
                    'episode_name' => $episode_name,
                    'start' => date('Y-m-d H:i:s', $start),
                    'start_t' => str_replace(' ', 'T', date('Y-m-d H:i:s.0', $start)),
                    'duration' => round(($stop - $start) / 60),
                    'stop' => date('Y-m-d H:i:s', $stop),
                    'channel' => (string) $channel,
                    'category' => $category,
                ];

                $categories[$category] = TRUE;
            }

            $categories = array_keys($categories);
            $categories = array_remove($categories, '');
            sort($categories);
            $categories[] = 'N/A';

            foreach ($programs as $channel => $ps) {
                ksort($ps);
                $programs[$channel] = $ps;
            }
        }

        $epg = (object) ['channels' => $channels, 'programs' => $programs, 'categories' => $categories];

        if (isset($tmp_file)) {
            file_put_contents($tmp_file, json_encode($epg));
        }

        static::_filterChannels($epg, $recordings);

        return $epg;
    }

    private static function _filterChannels(&$epg, array $recordings) {
        $programs = (array) $epg->programs;
        foreach ($programs as $channel => $ps) {
            $ps = (array) $ps;
            foreach ($ps as $k => $p) {
                if (strtotime($p->stop) < time()) {
                    unset($ps[$k]);
                    continue;
                }

                $p->recording = FALSE;
                foreach ($recordings as $recording) {
                    if ($recording->getChannel() == $channel && strtotime($p->start) >= $recording->getStartTimestamp() && strtotime($p->start) < $recording->getStartTimestamp() + $recording->getDurationInSeconds()) {
                        $p->recording[] = $recording->getName();
                    }
                }
                if (is_array($p->recording)) {
                    $p->recording = implode(', ', $p->recording);
                }
            }
        }
        $epg->programs = $programs;

        $keep_channels = Config::get('XMLTV_CHANNELS');
        if ($keep_channels) {
            $channels = (array) $epg->channels;
            foreach ($channels as $id => $name) {
                if (!array_contains($keep_channels, (float) $id)) {
                    unset($channels[(string) $id]);
                }
            }
            $epg->channels = $channels;

            $programs = (array) $epg->programs;
            foreach ($programs as $channel => $ps) {
                if (!array_contains($keep_channels, (float) $channel)) {
                    unset($programs[$channel]);
                }
            }
            $epg->programs = $programs;
        }
    }
}
