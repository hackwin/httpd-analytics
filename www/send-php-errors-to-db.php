<?php

$startTime = microtime(true);
require_once('Configuration.php');
$logDirectory = Configuration::getConstant('LOGS_FOLDER');
$sites = array('example.com');

$queryData = array();
$queryDataVals = array();

require_once('Database.php');
$db = Database::getInstance();

foreach($sites as $site){
    $logs = getLogs($logDirectory, $site);
    foreach($logs as $log){
        $logLinesInDatabase = getLastLineLogFileDB($log);
        $logLinesInFile = getLastLineLogFile($log);
        if(filesize($log) > 0 && $logLinesInFile > $logLinesInDatabase){
            $db->beginTransaction();
            echo 'Reading: '.$log.' into database!<br>';
            readLines($log, $site, $start=$logLinesInDatabase);
            $db->commit();
        }
    }
}

echo '<hr>Took: '.number_format(microtime(true)-$startTime, 3).' seconds';

function getLogs($logDirectory, $site){
    $logs = array();
    $files = array_diff(scandir($logDirectory.$site), array('.','..'));
    //var_dump($files);
    foreach($files as $file){
        $log = $logDirectory.$site.'/'.$file;
        if(is_file($log) && strstr($file, 'php.error') && $file != 'php.error.log'){
            $logs[] = $log;            
        }
    }
    return $logs;
}

function readLines($file, $site, $start=0){
    global $queryData;
    $queryData = array();
    $fileHandle = fopen($file, 'r');
    $lineNumber=0;
    while(($line = fgets($fileHandle)) !== false){
        ++$lineNumber;
        if($start > $lineNumber-1){
            continue;
        }
        if($line != "\n" && $line != "\r" && $line != "\r\n"){
            $line = trim($line);
        }
        $typedLine = findLineType($line);
        if($typedLine['type'] == 'php_error'){
            if(count($queryData) > 100){
                multiInsert($queryData);
            }
            $queryData[] = array('matches' => $typedLine['matches'], 'stack_trace' => '', 'log_line_number' => $lineNumber, 'source_log_file' => $site.'/'.pathinfo($file, PATHINFO_BASENAME), 'site'=> $site);
        }
        else if($typedLine['type'] == 'stack_trace'){
            if(empty($queryData)){
                file_put_contents(Configuration::getConstant('LOGS_FOLDER').'errors-stack-trace-without-php-error.html', $site.'/'.$file.'(Line: '.$lineNumber.'): '.$line.'<br>', FILE_APPEND);
                echo 'missing an error to match to stack trace.'; exit;
            }
            $queryData[count($queryData)-1]['stack_trace'] .= $typedLine['line']."\n";
        }
        else{
            file_put_contents(Configuration::getConstant('LOGS_FOLDER').'errors-stack-trace-without-php-error.html', 'Unknown line type<br>', FILE_APPEND);
        }
    }
    fclose($fileHandle);
    multiInsert($queryData);
}

function findLineType($line){
    $regex = array();

    $regex['main'] = '/';
    $regex['main'] .= '\[(\d{2})-([A-Za-z]{3})-(\d{4})\s(\d{2}:\d{2}:\d{2})\s([A-Za-z_\/]+)\]';
    $regex['main'] .= ' PHP (Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards): ';
    $regex['main'] .= ' ?(.*?)';
    $regex['main'] .= ' in (.*?)';
    $regex['main'] .= ' on line (\d+)';
    $regex['main'] .= '$/';

    $regex['alt'] = '/';
    $regex['alt'] .= '\[(\d{2})-([A-Za-z]{3})-(\d{4})\s(\d{2}:\d{2}:\d{2})\s([A-Za-z_\/]+)\]';
    $regex['alt'] .= ' PHP (Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards): ';
    $regex['alt'] .= ' ?(.*?)';
    $regex['alt'] .= ' in (.*):(\d+)';
    $regex['alt'] .= '$/';

    $matches = array();
    
    if(preg_match($regex['main'], $line, $matches) || preg_match($regex['alt'], $line, $matches)){
        return array('type' => 'php_error', 'matches' => $matches);
    }
    else if(trim($line) == ''){
        return array('type' => 'stack_trace', 'line' => '[blank line]');
    }
    else{
        return array('type' => 'stack_trace', 'line' => $line);
    }
}

function multiInsert($queryData){
    global $db;
    
    foreach($queryData as $queryDataElement){                
        $fields = array('line', 'day', 'month', 'year', 'time', 'timeZone', 'errorType', 'message', 'file', 'lineNumber');
        $fields = array_flip($fields);

        $matches = $queryDataElement['matches'];        

        $queryDataVals[] = array(
            'time_occurred' => date('Y-m-d H:i:s',strtotime($matches[$fields['year']].'-'.$matches[$fields['month']].'-'.$matches[$fields['day']].' '.$matches[$fields['time']].' '.$matches[$fields['timeZone']])),
            'error_type' => $matches[$fields['errorType']],
            'message' => $matches[$fields['message']],
            'php_file' => $matches[$fields['file']],
            'line_number' => $matches[$fields['lineNumber']],
            'source_log_file' => $queryDataElement['source_log_file'],
            'log_file_line_number' => $queryDataElement['log_line_number'],
            'site' => $queryDataElement['site'],
            'line' => $matches[$fields['line']],
            'stack_trace' => $queryDataElement['stack_trace']
        );
    }

    $keys = array_keys($queryDataVals[0]);
    $query = 'insert into `analytics`.`php_errors` ('.rtrim(implode(',',$keys),',').') values ';
    $mergedVals = array();
    foreach($queryDataVals as $queryDataVal){
        $vals = array_values($queryDataVal);
        $query .= '('.rtrim(str_repeat('?,', count($vals)),',').'), ';
        foreach($vals as $val){
            $mergedVals[] = $val;
        }
    }
    $query = rtrim($query, ', ');
    $db->query($query, $mergedVals);
    $queryData = array();
}

function getLastLineLogFile($file){
    $fileHandle = fopen($file, 'r');
    $lineNumber = 0;
    while(($line = fgets($fileHandle)) !== false){        
        ++$lineNumber;
    }
    fclose($fileHandle);
    return $lineNumber;
}

function getLastLineLogFileDB($file=null){
    global $db;

    if($file != null){
        global $logDirectory;
        $file = str_replace($logDirectory,'',$file);
        $rows = $db->query('select coalesce(max(php_errors.log_file_line_number+LENGTH((stack_trace))-LENGTH(REPLACE(stack_trace, \'\n\', \'\'))),0) as maxLinesTotal from analytics.php_errors where source_log_file = ?', array($file));
        return $rows[0]['maxLinesTotal'];
    }
    else{
        $rows = $db->query('select t1.source_log_file, php_errors.log_file_line_number+LENGTH((stack_trace))-LENGTH(REPLACE(stack_trace, \'\n\', \'\')) as linesTotal from analytics.php_errors join (select max(log_file_line_number) as max_log_file_line_number, source_log_file from analytics.php_errors group by source_log_file) as t1 on php_errors.log_file_line_number = t1.max_log_file_line_number and php_errors.source_log_file = t1.source_log_file;');
        
        $logLinesMap = array();
        foreach($rows as $row){
            $logLinesMap[$row['source_log_file']] = $row['linesTotal'];
        }

        foreach($logLinesMap as $key => $val){
            if($key == $file){
                //echo 'found!';
                return $val;
            }
        }
        return $logLinesMap;
    }
    return false;
}