# php-file-cloud-sync
PHP script to mirroring remote disk into local. Adjusted to Windows. Only **file sizes** are checked, not any checksums are made.

# Configuration
`$remoteDir` is an absolute path to remote disk you want to be cloning into your mirror
`$localDir` is an absolute path to local mirror

# Usage
Run the php script everytime you change the remote directory. You can use a Task Scheduler or some other cron solution. When a new file is found in the remote directory, it is copied into local mirror. When a file size changes in the remote directory, local file is renamed (.bak) and the remote file gets copied into local mirror. After every run, the script logs simple output into a log file inside the script's containing folder.
