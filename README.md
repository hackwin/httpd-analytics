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
        'line',
        'ipAddress',
        'htaccessUserName',
        'date',
        'time',
        'timeZone',
        'requestMethod',
        'requestPath',
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
```

Database row values:
```
        $vals = array(
            'added' => date('Y-m-d H:i:s',strtotime($matches[$fields['date']] .':'.$matches[$fields['time']].' '.$matches[$fields['timeZone']])),
            'url' => $matches[$fields['requestPath']],
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
```

