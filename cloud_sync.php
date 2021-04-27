<?php

$remoteDir = "P:\\";
$localDir = "C:\\backups\\";

date_default_timezone_set("Europe/Prague");

function checkDir($baseDir, $dir, &$files, $level=0, $verbose=false){
    if(!is_dir($dir)) die("Error: $dir is not a directory!\n");
    $dh = opendir($dir);
    if(!$dh) die("Error: Could not open directory!\n");
    while(($file = readdir($dh)) !== false){
        if($file == "." || $file == "..") continue;
        $absolute = $dir.$file;
        $size = filesize($absolute);
        $type = filetype($absolute);
        if($verbose){
            for($i=0; $i<=$level; $i++) echo("-");
            echo(" $absolute | $type | $size B\n");
        }
        $files[] = array($type, $size, substr($absolute, strlen($baseDir)));
        if($type == "dir") checkDir($baseDir, $absolute . "\\", $files, $level+1, $verbose);
    }
    closedir($dh);
}

function filesDiff($sourceFiles, $mirrorFiles) : array {
    $diff = array();
    foreach($sourceFiles as $source){
        $sourceType = $source[0];
        $sourceSize = $source[1];
        $sourceName = $source[2];
        //search mirror
        $foundInMirror = false;
        foreach($mirrorFiles as $mirror){
            $mirrorType = $mirror[0];
            $mirrorSize = $mirror[1];
            $mirrorName = $mirror[2];
            if($sourceName == $mirrorName){
                $foundInMirror = true;
                $sameType = ($sourceType == $mirrorType);
                $sameSize = ($sourceSize == $mirrorSize);
                $diff[] = array($sourceName, $sourceType, "found", $sameType, $sameSize);
                break;
            }
        }
        if(!$foundInMirror){
            $diff[] = array($sourceName, $sourceType, "missing", false, false);
        }
    }
    return $diff;
}

function mirrorFiles($diff, $sourceDir, $destinationDir) : array {
    $log = array();
    $status = array("synced" => 0, "moved" => 0, "copied_changed" => 0, "copied_new" => 0, "made_dir" => 0, "errors" => 0);
    $fileCount = count($diff);
    $i = 0;
    foreach($diff as $file){
        $i++;
        $fileName = $file[0];
        $fileType = $file[1];
        $fileSyncState = $file[2];
        $fileSameType = $file[3];
        $fileSameSize = $file[4];
        echo("\r$i/$fileCount... $fileName");
        if($fileSyncState == "found"){
            if($fileSameType && $fileSameSize){
                $log[] = "#file '$fileName' is mirrored correctly (according to its size)";
                $status["synced"]++;
            }else{
                //create backup of the old file
                $i = 1;
                while(true){
                    $backupName = $destinationDir.$fileName . ".bak" . $i;
                    if(!file_exists($backupName)){
                        //create the backup - move the old file
                        $log[] = "firstly moving '".$destinationDir.$fileName."' '$backupName' before rewriting with a new copy";
                        $res = rename($destinationDir.$fileName, $backupName);
                        $status["moved"]++;
                        $log[] = ($res ? "OK moved" : "ERROR!");
                        if(!$res) $status["errors"]++;
                        break; //we are done
                    }
                    $i++; //else just increment backupName
                }
                $log[] = "copy '".$sourceDir.$fileName."' '".$destinationDir.$fileName."' as it has changed";
                $res = copy($sourceDir.$fileName, $destinationDir.$fileName);
                $status["copied_changed"]++;
                $log[] = ($res ? "OK copied" : "ERROR!");
                if(!$res) $status["errors"]++;
            }
        }else{
            if($fileType == "dir"){
                $log[] = "mkdir '".$destinationDir.$fileName."'";
                $res = mkdir($destinationDir.$fileName);
                $status["made_dir"]++;
                $log[] = ($res ? "OK made dir" : "ERROR!");
                if(!$res) $status["errors"]++;
            }else{
                $log[] = "copy '".$sourceDir.$fileName."' '".$destinationDir.$fileName."'";
                $res = copy($sourceDir.$fileName, $destinationDir.$fileName);
                $status["copied_new"]++;
                $log[] = ($res ? "OK copied" : "ERROR!");
                if(!$res) $status["errors"]++;
            }
        }
    }
    return array($log, $status);
}

$remoteFiles = array();
checkDir($remoteDir, $remoteDir, $remoteFiles, 0, true);

$localFiles = array();
checkDir($localDir, $localDir, $localFiles, 0, true);

$diff = filesDiff($remoteFiles, $localFiles);

$mirrorResult = mirrorFiles($diff, $remoteDir, $localDir);


print_r($remoteFiles);
print_r($localFiles);
print_r($diff);
print_r($mirrorResult);

//log results
$logLine = strftime("%F %X", time()) . " ";
$logLine .= preg_replace('/\s+/', '', var_export($mirrorResult[1], true));
$logLine .= "\n";

file_put_contents(__DIR__."\\cloud_sync.log", $logLine, FILE_APPEND | LOCK_EX);
