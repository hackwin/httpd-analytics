<?php

/*
%a: Client IP address.
%A: Local IP address.
%B or %b: Response size in bytes (excluding headers), %b uses '-' for zero bytes.
%s: Status of the original request.
%D: Time taken to serve the request in microseconds.
%T: Time taken to serve the request in seconds.
%r: First line of the request.
%H: Request protocol.
%m: Request method.
%u: Remote user if authenticated.
%U: URL path requested without query string.
%v: Canonical ServerName.
%%: The percent sign.
%{c}a: Underlying peer IP address.
%{VARNAME}C: Content of the cookie VARNAME.
%{VARNAME}e: Content of the environment variable VARNAME.
%f: Filename.
%h: Remote hostname, logs IP if HostnameLookups is Off.
%{c}h: Like %h, but reports the underlying TCP connection hostname.
%{VARNAME}i: Content of the request header line VARNAME.
%k: Number of keepalive requests on the connection.
%l: Remote logname from identd.
%L: Request log ID from error log.
%{VARNAME}n: Content of the note VARNAME.
%{VARNAME}o: Content of the reply header line VARNAME.
%p: Canonical port of the server.
%{format}p: Canonical, local, or remote port of the server or client.
%P: Process ID of the child that serviced the request.
%{format}P: Process or thread ID, pid, tid, or hextid.
%q: Query string.
%R: Handler generating the response.
%t: Time the request was received in a specific format.
%{format}t: Time using strftime, begin or end formats with optional epoch related tokens.
%{UNIT}T: Time taken to serve the request in ms, us or s.
%V: Server name according to UseCanonicalName setting.
%X: Connection status after response.
%I: Bytes received, including headers (requires mod_logio).
%O: Bytes sent, including headers (requires mod_logio).
%S: Bytes transferred, both received and sent (requires mod_logio).
%{VARNAME}^ti: Content of request trailer line VARNAME.
%{VARNAME}^to: Content of response trailer line VARNAME.
*/

$startTime = microtime(true);
set_time_limit(4*60);
require_once('Configuration.php');
$logDirectory = Configuration::getConstant('LOGS_FOLDER'); // your logs folder here
require_once('Database.php');
$db = Database::getInstance();

/* VirtualHost --> LogFormat "%a %u %t %m \"%{REQUEST_URI}e\" \"%U\" \"%q\" %>s \"%h\" %I %O %D \"%f\" \"%V\" %p \"%{Referer}i\" \"%{User-agent}i\" \"%{Cookie}i\"" custom_log_format */

$apacheAccessRegex = '/^'; // begin regex
$apacheAccessRegex .= '(\S+)'; // %a (Client IP address)
$apacheAccessRegex .= ' (\S+)'; // %u (Remote user if authenticated)
$apacheAccessRegex .= ' \[([^:]+):(\d+:\d+:\d+) ([^\]]+)\]'; // %t time
$apacheAccessRegex .= ' (\S+)'; // %m (Request method)
$apacheAccessRegex .= ' "((?:[^"\\\\]|\\\\.)*)"'; // \"%{REQUEST_URI}e\" (pre URL path requested)
$apacheAccessRegex .= ' "((?:[^"\\\\]|\\\\.)*)"'; // \"%U\" (post URL path requested)
$apacheAccessRegex .= ' "((?:[^"\\\\]|\\\\.)*)"'; // \"%q\" (Query String)
$apacheAccessRegex .= ' (\S+)'; /* %>s (Status of the original request -- http response code)*/
$apacheAccessRegex .= ' "([^"]*)"'; // \"h\" (remote hostname, logs IP if HostnameLookups is Off)
$apacheAccessRegex .= ' (\S+)'; // %I (bytes received, including headers (requires mod_logio))
$apacheAccessRegex .= ' (\S+)'; // %O (bytes sent, including headers (requires mod_logio))
$apacheAccessRegex .= ' (\S+)'; // %D (Time taken to serve the request in microseconds)
$apacheAccessRegex .= ' "([^"]*)"'; // \"%f\" (filename)
$apacheAccessRegex .= ' "([^"]*)"'; // \"%V\" (Server name according to UseCanonicalName settings -- vhost)
$apacheAccessRegex .= ' (\S+)'; // %p (Canonical port of the server)
$apacheAccessRegex .= ' "((?:[^"\\\\]|\\\\.)*)"'; // \%{Referer}i\"
$apacheAccessRegex .= ' "((?:[^"\\\\]|\\\\.)*)"'; // \"%{User-agent}i\"
$apacheAccessRegex .= ' "((?:[^"\\\\]|\\\\.)*)"'; // \"%{Cookie}i\""
$apacheAccessRegex .= '$/'; //end regex

$dbQueryData = array();
$batchInsertCount = 100;

if(php_sapi_name() == 'cli' && count($argv) == 2){
    loadSubdirectoryLogs($argv[1]);
}
else{
    $logDirectories = array('example.com');
    foreach($logDirectories as $subDirectory){
        loadSubdirectoryLogs($subDirectory);
    }
}

echo '<hr>Took: '.number_format(microtime(true)-$startTime, 3).' seconds';
exit;

function loadSubdirectoryLogs($subDirectory){
    global $db, $logDirectory;
    echo 'Running on subdirectory: '.$subDirectory.'<br>';    
    if(file_exists($logDirectory.'/'.$subDirectory) == false){
        echo 'error subdirectory '.$subDirectory.' does not exist!<br>';
        exit;
    }
    $files = array_diff(scandir($logDirectory.'/'.$subDirectory),array('.','..'));

    //var_dump($files);
    $apacheLogFiles = array();
    foreach($files as $file){
        if(strstr($file, 'apache.access')){
            $apacheLogFiles[] = $subDirectory.'/'.$file;
        }
    }

    rsort($apacheLogFiles);
        
    foreach($apacheLogFiles as $apacheLogFile){
        $rows = $db->query('select max(line_number) as max_line_number from analytics.page_statistics where source_file = ?', array($apacheLogFile));
        if(empty($rows)){            
            insertLogFile($apacheLogFile);
        }
        else{        
            // log file grows larger than db table as page hits occur
            $lineCount = countRegexMatchedLines($logDirectory.$apacheLogFile); // in log file
            if($rows[0]['max_line_number'] < $lineCount){ // in mysql table
                insertLogFile($apacheLogFile, $startingFrom=$rows[0]['max_line_number']); // add lines from log file into mysql table
            }
        }
    }
}

function insertLogFile($apacheLogFile, $startingFrom=0){
    global $db, $logDirectory, $apacheAccessRegex;
    $fileHandle = fopen($logDirectory.$apacheLogFile, 'r');
    if ($fileHandle) {
        $db->beginTransaction();
        $lineNumber = 1;
        while (($line = fgets($fileHandle)) !== false) {
            $vals = matchesApacheRegex($apacheLogFile, $line, $lineNumber);
            if($vals !== false){                                                    
                if($lineNumber >= $startingFrom+1){
                    collectData($vals);
                }
                else{
                    //echo 'skipping previously inserted line #'.$lineNumber.' from log file '.$apacheLogFile.'!<br>';
                    if($lineNumber > $startingFrom-1){
                        echo 'skipped all previously inserted lines up to line #'.$lineNumber.' from log file '.$apacheLogFile.'!<br>';
                    }
                }
            }            
            else{
                file_put_contents(Configuration::getConstant('LOGS_FOLDER').'errors-insert-line-db.html', $apacheLogFile.'(Line: '.$lineNumber.'): '.$line.'<br>', FILE_APPEND);
                echo 'failed regex on #'.$lineNumber.' from '.$apacheLogFile.'!<br>';
                echo 'line: '.$line.'!<br>';
                //exit;
            }
            $lineNumber++;
        }
        multiInsertDatabase();
        fclose($fileHandle);
        $db->commit();
    }
}

function matchesApacheRegex($apacheAccessLogFile, $line, $lineNumber){
    global $apacheAccessRegex;
    $line = trim($line);
    
    $fields = array(
        'line',
        'ipAddress',
        'htaccessUserName',
        'date',
        'time',
        'timeZone',
        'requestMethod',
        'preRequestPath',
		'postRequestPath',
        'queryString',           
        'httpResponseCode', 
        'hostName', 
        'requestSizeBytes', 
        'responseSizeBytes', 
        'loadTimeMicroSeconds', 
        'fileName', 
        'serverName',
        'serverPort', 
        'referer', 
        'userAgent', 
        'cookie'
    );
    $fields = array_flip($fields);
    
    $matches = array();
    $matched = preg_match($apacheAccessRegex, $line, $matches);
    
    if($matched == 0){
        echo 'failed to match line #'.$lineNumber.' in '.$apacheAccessLogFile.'<br>';
        echo $line.'<br>';
        //exit;
    }

    //$matches = fixMatches($matches);
    
    //var_dump($matches);
    //var_dump($fields);
    //exit;

    if($matches != null && $fields != null && count($matches) == count($fields)){
        $vals = array(
            'added' => date('Y-m-d H:i:s',strtotime($matches[$fields['date']] .':'.$matches[$fields['time']].' '.$matches[$fields['timeZone']])),
            'pre_url' => strtok($matches[$fields['preRequestPath']], '?'),
			'post_url' => $matches[$fields['postRequestPath']],
            'query_string' => $matches[$fields['queryString']],
            'file_name' => $matches[$fields['fileName']],
            'server_name' => $matches[$fields['serverName']],
            'server_port' => $matches[$fields['serverPort']],
            'method' => $matches[$fields['requestMethod']],
            'http_response_code' => $matches[$fields['httpResponseCode']],
            'ip_address' => $matches[$fields['ipAddress']],
            'host_name' => '',//$matches[$fields['hostName']],
            'bytes_request' => $matches[$fields['requestSizeBytes']],
            'bytes_response' => $matches[$fields['responseSizeBytes']],
            'load_time' => $matches[$fields['loadTimeMicroSeconds']]/1000000,
            'user_agent' => str_replace('\"', '"', $matches[$fields['userAgent']]),
            'referer' => $matches[$fields['referer']],
            'cookie' => $matches[$fields['cookie']],
            'htaccess_user' => $matches[$fields['htaccessUserName']],
            'source_file' => $apacheAccessLogFile,
            'line_number' => $lineNumber,
            'line' => $matches[$fields['line']]
        );

        return $vals;
    }
    else{
        if($matches == null){
            $matches = array();
        }
        if($fields == null){
            $fields = array();
        };
        echo 'matches: '.count($matches).', fields: '.count($fields).'<br>';
        var_dump($matches);
        return false;
    }
}

function containsCSlashes($text){
    $pattern = '/\\\\([nrtvf]|x[0-9a-fA-F]{2}|0[0-7]{1,3}|\\\\)/';
    return preg_match($pattern, $text);
}

function fixMatches($matches){  // fixes for when the request isn't in this format: "GET /robots.txt HTTP/1.1"
    if(empty($matches)){
        return;
    }

    /*for($i=1; $i<count($matches); $i++){ //decode c-slash escaped chars, such as \n, in all fields
        if(containsCSlashes($matches[$i])){
            $matches[$i] = stripcslashes($matches[$i]);
        }
    }*/
    return $matches;
}

function insertDatabase($vals){
    global $db;
    foreach($vals as $key => $val){
        if($val == '-'){
            $vals[$key] = '';
        }
    }
			
    $keys = array_keys($vals);
    $vals = array_values($vals);
                
    $query = 'insert into `analytics`.`page_statistics` 
        ('.rtrim(implode(',',$keys),',').') values ('.rtrim(str_repeat('?,', count($vals)),',').')';
    
    $inserted = 0;
    //echo $query.'<br>';
    try{
        $inserted = $db->query($query, $vals);
    }
    catch(Exception $e){			
        var_dump($e);
    }
    
    if($inserted > 0){
        //echo '<br>Inserted row id: '.$inserted.'!<br>';	
    }
    else{
        echo '<br>Failed to insert record!<br>';
    }

    return $inserted;
}

function collectData($vals){
    global $queryDataVals, $batchInsertCount;

    $queryDataVals[] = $vals;

    if(count($queryDataVals) > $batchInsertCount){
        multiInsertDatabase();
    }
}

function multiInsertDatabase(){
    global $db, $queryDataVals;
    if(empty($queryDataVals)){
        return;
    }

    foreach($queryDataVals as $key => $val){
        if($val == '-'){
            $queryDataVals[$key] = '';
        }
    }

    $query = 'insert into `analytics`.`page_statistics` ';
    
    $keys = array_keys($queryDataVals[0]);
    $query .= '('.rtrim(implode(',',$keys),',').') values ';

    $mergedVals = array();
    foreach($queryDataVals as $queryDataVal){
        $vals = array_values($queryDataVal);
        $query .= '('.rtrim(str_repeat('?,', count($vals)),',').'), ';
        foreach($vals as $val){
            $mergedVals[] = $val;
        }
    }

    $query = rtrim($query, ', ');

    try{
        $inserted = $db->query($query, $mergedVals);
    }
    catch(Exception $e){	
        var_dump($e);
    }

    $queryDataVals = array();
}

function countRegexMatchedLines($file){
    global $apacheAccessRegex;
    $fileHandle = fopen($file, 'r');
    $lineNumber = 0;
    while (($line = fgets($fileHandle)) !== false) {
        $matches = array();
        $return = preg_match($apacheAccessRegex, trim($line), $matches);
        
        //$matches = fixMatches($matches);

        if($return == 1){
            ++$lineNumber;
        }
        else{
            echo 'error: ('.$lineNumber.') '.$line.'<br>';
        }
    }
    fclose($fileHandle);
    return $lineNumber;    
}