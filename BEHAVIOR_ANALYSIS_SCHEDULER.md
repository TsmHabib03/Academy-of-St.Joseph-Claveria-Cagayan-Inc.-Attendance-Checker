# Behavior Analysis Scheduler

Use the CLI runner so behavior alerts are generated automatically for both students and teachers.

## Script

`scripts/run_behavior_analysis_cli.php`

## Manual run

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\ACSCCI-Attendance-Checker\scripts\run_behavior_analysis_cli.php
```

## Windows Task Scheduler (recommended)

1. Open Task Scheduler.
2. Create Task.
3. Set a trigger (for example: every 15 minutes between 6:00 AM and 6:00 PM).
4. Action:
   - Program/script: `C:\xampp\php\php.exe`
   - Add arguments: `C:\xampp\htdocs\ACSCCI-Attendance-Checker\scripts\run_behavior_analysis_cli.php`
   - Start in: `C:\xampp\htdocs\ACSCCI-Attendance-Checker`
5. Enable "Run whether user is logged on or not" if needed.

## Linux cron example

```bash
*/15 6-18 * * 1-5 /usr/bin/php /var/www/html/ACSCCI-Attendance-Checker/scripts/run_behavior_analysis_cli.php >> /var/log/attendease_behavior.log 2>&1
```

