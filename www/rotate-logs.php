<?php

$logFiles = array();
$logFiles[] = Configuration::getConstant('LOGS_FOLDER').'mysql_query.log';
$logFiles[] = Configuration::getConstant('LOGS_FOLDER').'mysql.log';
$logFiles[] = Configuration::getConstant('LOGS_FOLDER').'example.com/php.error.log';

//ob_start();
foreach($logFiles as $logFile){
    if(file_exists($logFile)){
        $pathInfo = pathinfo($logFile);

        $destination = $pathInfo['dirname'].'/'.$pathInfo['filename'].'.'.date('Y.m.d').'.'.$pathInfo['extension'];
        //$result = copy($logFile, $destination);

        $fpIn = fopen($logFile, 'r+');
        $fpOut = fopen($destination, 'a+');
        if(flock($fpIn, LOCK_EX)){
            while(($line = fgets($fpIn)) !== false){
                fwrite($fpOut, $line);
            }
            ftruncate($fpIn, 0);
            flock($fpIn, LOCK_UN);    
        }
        fclose($fpIn);
        fclose($fpOut);
    }
}
/*$output = ob_get_contents();
file_put_contents(Configuration::getConstant('LOGS_FOLDER').'/log-rotate.html', '<b>'.date('Y-m-d h:i:s A' ).'</b><br><br>'.$output.'<hr>', FILE_APPEND);
ob_end_clean();
echo $output;*/