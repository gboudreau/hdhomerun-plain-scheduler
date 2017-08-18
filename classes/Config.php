<?php
namespace GBoudreau\HDHomeRun\Scheduler;

class Config
{

    public static function get($name, $default = FALSE) {
        $env_value = getenv($name);
        if ($env_value !== FALSE) {
            if (strtoupper($env_value) === 'TRUE') {
                return TRUE;
            }
            if (strtoupper($env_value) === 'FALSE') {
                return FALSE;
            }
            return $env_value;
        }
        global $CONFIG;
        if (empty($CONFIG)) {
            require_once __DIR__ . '/../config.php';
        }
        if (isset($CONFIG->{$name})) {
            return $CONFIG->{$name};
        }
        return $default;
    }

    public static function set($name, $value) {
        global $CONFIG;
        $CONFIG->{$name} = $value;
    }
}
