<?php
  class Configuration{
      
      static $constants = array(
          'WORKING_DIRECTORY'=> 'c:/wamp64/www/example.com/',
          'WEB_BASE_URL' => 'http://www.example.com',
          'DATABASE_HOST'=>'127.0.0.1',
          'DATABASE_PORT'=>'3306',
          'DATABASE_USER'=>'root',
          'DATABASE_PASSWORD'=>'',
          'DATABASE_SCHEMA'=>'example.com',
          'NAMESPACE'=>'example.com',
		  'DOMAIN_NAME'=>'example.com',          
		  'PROJECT_NAME'=>'Example Website',
          'LOGS_FOLDER'=>'c:/wamp64/logs/'
      );

      public static function getConstant($constant){
          foreach(self::$constants as $key => $value){
              if ($key == $constant){
                  return $value;
              }
          }
          trigger_error('Undefined constant <b>'.$constant.'</b>', E_USER_NOTICE);
      }
      
  }
?>
