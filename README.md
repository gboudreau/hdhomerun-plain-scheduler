# HDHomeRun Plain-Text Scheduler
Recordings scheduling system using plain-text files for HDHomeRun tuners

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
