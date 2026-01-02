# httpd-analytics
Custom log format and insertion into database tables.  Support for apache.access.log and php_error.log

Apache settings:
```
LogFormat "%a %u %t %m \"%U\" \"%q\" %>s \"%h\" %I %O %D \"%f\" \"%V\" %p \"%{Referer}i\" \"%{User-agent}i\" \"%{Cookie}i\"" custom_log_format
```
Enable module: `mod_logio`

PHP Regex: 
```
$apacheAccessRegex = '/^'; // begin regex
$apacheAccessRegex .= '(\S+)'; // %a (Client IP address)
$apacheAccessRegex .= ' (\S+)'; // %u (Remote user if authenticated)
$apacheAccessRegex .= ' \[([^:]+):(\d+:\d+:\d+) ([^\]]+)\]'; // %t time
$apacheAccessRegex .= ' (\S+)'; // %m (Request method)
$apacheAccessRegex .= ' "((?:[^"\\\\]|\\\\.)*)"'; // \"%U\" (URL path requested without query string)
$apacheAccessRegex .= ' "((?:[^"\\\\]|\\\\.)*)"'; // \"%q\" (Query String)
$apacheAccessRegex .= ' (\S+)'; // %>s (Status of the original request -- http response code)
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
```

Fields:
```
    $fields = array(
        'line' => $i++, //0
        'ipAddress' => $i++, //1
        'htaccessUserName' => $i++, //2
        'date' => $i++, //3
        'time' => $i++, //4
        'timeZone' => $i++, //5
        'requestMethod' => $i++, //6
        'requestPath' => $i++, //7
        'queryString' => $i++, //8             
        'httpResponseCode' => $i++, //9
        'hostName' => $i++, //10
        'requestSizeBytes' => $i++, //11
        'responseSizeBytes' => $i++, //12
        'loadTimeMicroSeconds' => $i++, //13
        'fileName' => $i++, //14
        'serverName' => $i++, //15
        'serverPort' => $i++, //16
        'referer' => $i++, //17
        'userAgent' => $i++, //18
        'cookie' => $i++ //19
    );
```

Database row values:
```
        $vals = array(
            /* 0 */ 'added' => date('Y-m-d H:i:s',strtotime($matches[$fields['date']] .':'.$matches[$fields['time']].' '.$matches[$fields['timeZone']])),
            /* 1 */ 'url' => $matches[$fields['requestPath']],
            /* 2 */ 'query_string' => $matches[$fields['queryString']],
            /* 3 */ 'file_name' => $matches[$fields['fileName']],
            /* 4 */ 'server_name' => $matches[$fields['serverName']],
            /* 5 */ 'server_port' => $matches[$fields['serverPort']],
            /* 6 */ 'method' => $matches[$fields['requestMethod']],
            /* 8 */ 'http_response_code' => $matches[$fields['httpResponseCode']],
            /* 9 */ 'ip_address' => $matches[$fields['ipAddress']],
            /* 10 */ 'host_name' => '',//$matches[$fields['hostName']],
            /* 11 */ 'bytes_request' => $matches[$fields['requestSizeBytes']],
            /* 12 */ 'bytes_response' => $matches[$fields['responseSizeBytes']],
            /* 13 */ 'load_time' => $matches[$fields['loadTimeMicroSeconds']]/1000000,
            /* 14 */ 'user_agent' => str_replace('\"', '"', $matches[$fields['userAgent']]),
            /* 15 */ 'referer' => $matches[$fields['referer']],
            /* 16 */ 'cookie' => $matches[$fields['cookie']],
            /* 17 */ 'htaccess_user' => $matches[$fields['htaccessUserName']],
            /* 18 */ 'source_file' => $apacheAccessLogFile,
            /* 19 */ 'line_number' => $lineNumber,
            /* 20 */ 'line' => $matches[$fields['line']]
        );
```

