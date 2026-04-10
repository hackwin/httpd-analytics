<?php
  
  class Database{
      
        private static $connection;
        private static $instance;
        private static $lastInsertId;

        private function __construct(){
            try{
                $host = Configuration::getConstant('DATABASE_HOST');
                $dbsc = Configuration::getConstant('DATABASE_SCHEMA');
                $user = Configuration::getConstant('DATABASE_USER');
                $pass = Configuration::getConstant('DATABASE_PASSWORD');

                self::$connection = new PDO("mysql:host=$host;dbname=$dbsc;",$user,$pass);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                //self::$connection->setAttribute(PDO::MYSQL_ATTR_FOUND_ROWS, TRUE);
            }
            catch(PDOException $ex){
                echo '<pre>'.print_r($ex,true).'</pre>';
            }
        }

        public static function getInstance(){
            if (is_null(self::$instance))
                self::$instance = new self();
            return self::$instance;
        }

        public function query($statement,$variables=array()){
            
            if(is_array($variables) == false){
                $variables = array($variables);
            }
            
            if('select' == strtolower(substr(trim($statement),0,strlen('select')))){
                $stmt = self::$connection->prepare($statement);
                $stmt->execute($variables);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            elseif('insert' == strtolower(substr(trim($statement),0,strlen('insert')))){
                $stmt = self::$connection->prepare($statement);
                $stmt->execute($variables);
                return self::$connection->lastInsertId(); 
            }
            elseif('update' == strtolower(substr(trim($statement),0,strlen('update')))){
                $stmt = self::$connection->prepare($statement);
                $stmt->execute($variables);
                return ($this->query('select found_rows()',[]))[0]['found_rows()'];
            }
            else if('delete' == strtolower(substr(trim($statement),0,strlen('delete')))){
                $this->softDelete($statement, $variables);
                $stmt = self::$connection->prepare($statement);
                $stmt->execute($variables);
                return ($this->query('select found_rows()',[]))[0]['found_rows()'];
            }
            else{
                $stmt = self::$connection->query($statement);
            }
            
        }
        
        public function softDelete($query, $variables=array()){
            $copy = $query;            
            $copy = str_replace('select * ', 'delete', $copy);
            $from = stripos($copy, 'from ');
            $where = stripos($copy, ' where');
            $table = substr($copy, $from+strlen('from '), $where-$from-strlen('from '));
            $ending = substr($copy, $where);

            $select = "select * from $table".$ending;
            //var_dump($select);
            $rows = $this->query($select, $variables);
            //var_dump($rows);
            
            foreach($rows as $row){
                $sql = 'insert into deleted_data (`query`,`query_vars`, `table`,`record`,`user_id`) values(?, ?, ?, ?, ?)';
                $this->query($sql, array($query,json_encode($variables),$table,json_encode($row),$_SESSION['user_id']));
            }            
        }

        public function insert($table,$vars,$onDuplicateKeyUpdate=false){
            $columns = array_keys($vars);
            $values = array_values($vars);

            $prepVars = array();

            for($i=0; $i<count($columns); $i++)
                $prepVars[':'.$columns[$i]] = $values[$i];

            foreach($columns as &$column)
                $column = '`'.$column.'`';

            $query = 'INSERT INTO '.$table.' ('.implode(',',$columns).') VALUES ('.implode(',',array_keys($prepVars)).')';
            //$query .= $onDuplicateKeyUpdate ? ' ON DUPLICATE KEY UPDATE updated = NOW()' : '';
            
            //die($query);
            
            $stmt = self::$connection->prepare($query);
            $stmt->execute($prepVars);

            return self::$connection->lastInsertId();
            //return $stmt->rowCount();
        }

        public function select($table,$columns='*',$where='TRUE',$order='id',$limit=1000,$variables=array()){
            $query = 'SELECT '.$columns.' FROM '.$table.' WHERE '.$where.' ORDER BY '.$order.' LIMIT '.$limit;
            //echo $query;
	    //exit;
            
            if(!empty($variables)){
                $stmt = self::$connection->prepare($query);
                $stmt->execute($variables);
            }
            else{
                $stmt = self::$connection->query($query);
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function count($table,$where='TRUE'){
            $query = 'SELECT count(*) FROM '.$table.' WHERE '.$where;

            $stmt = self::$connection->prepare($query);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['count(*)'];
        }

        public function update($table,$update,$where='TRUE'){

            $stmt = self::$connection->prepare('UPDATE '.$table.' SET '.$update.' WHERE '.$where);
 	    //print_r($stmt);
            $stmt->execute();

            return $stmt->rowCount();
        }

        public function delete($table,$where){
            $stmt = self::$connection->prepare('DELETE FROM '.$table.' WHERE '.$where);
            $stmt->execute();

            return $stmt->rowCount();
        }

        public function increment($table,$field,$id){
	        return $this->update($table,$field.'='.$field.'+1',$where='id='.$id); //change to safe from sql injection
        }
        
        public function lastInsertId(){
            return self::$connection->lastInsertId();
        }
        
        public function beginTransaction(){
            return self::$connection->beginTransaction();
        }
        
        public function inTransaction(){
            return self::$connection->inTransaction();
        }
        
        public function rollBack(){
            return self::$connection->rollBack();
        }
        
        public function commit(){
            return self::$connection->commit();
        }
      
  }
  
?>
