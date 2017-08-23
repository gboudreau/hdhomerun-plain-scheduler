# HDHomeRun Plain-Text Scheduler
Recordings scheduling system using plain-text files for HDHomeRun tuners

## Requirements:

- Linux ([PR for Windows compatibility are welcome](https://github.com/gboudreau/hdhomerun-plain-scheduler/wiki/Windows-compatibility))
- PHP 7.x
- cURL module for PHP
- `exec()` available (it is used to spawn new PHP processes to handle individual recordings)
- Optional: web server (Apache, nginx, ...)

## Install
- Copy `config.example.php` to `config.php`, and edit as required;
- Copy `schedules.example.txt` to `schedules.txt`, and edit to list all the recordings you'd like to schedule;
- Add a cron job that will run every minute, to start recordings as needed:

```
crontab -l > mycron
echo >> mycron
echo '# HDHomeRun Plain-Text Scheduler' >> mycron
echo '* * * * *   php /path/to/hdhomerun-plain-scheduler/cron.php' >> mycron
crontab mycron
rm mycron
```

## Web UI

There is a very simple web UI to monitor your scheduled recording, and add new ones.  
To use it, simply point your favorite HTTP server to this folder. It contains an `index.php` file that will be used to serve web content.  
Of note: make sure the user running the HTTP server can read your schedules & log files, and optionally write to your schedules file (if you want to be able to create new schedules from the web).
