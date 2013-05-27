<?php

  class kORM {

  	
  	static $config = array(); //конфиги подключения к базе
  	static $conn = array(); // все подключения

  	private $ORM = '';
  	private $conf = 'default';
  	private $filters = array();
  	private $sort = array();
  	private $limit = null;
  	private $columns = '*';

  	static function table($ORM, $conf = 'default') {
      return new kORM($ORM, $conf);
    }
  	
    
    function __construct($ORM, $conf = 'default'){
  		$this->ORM = $ORM;
  		$this->config = $conf; //текущая конфигурация
  	}


  	/*
    * добавляем конфигурацию подключения к базе
    */
    static function config($name, $host, $user, $pswd, $db){
  		ORM::$config[$name] = array('host'=>$host, 'user'=>$user, 'pswd'=>$pswd, 'db'=>$db);
      return True;
  	}

  	

  	/**
    ** сonnected DB
    */

    private function conn($conf) {
  		 		
      /*if (isset(ORM::$conn[$conf]))
          return;*/

      if (!is_array(ORM::$config[$conf]))
        die('no config DB found');

     
      $config = ORM::$config[$conf]; 


      $mysqli = new mysqli($config['host'], $config['user'], $config['pswd'], $config['db']);
      if ($mysqli->connect_error) {
          die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }

            
      ORM::$conn[$conf] = $mysqli;
     
      return True;
  	
  	}
  
  	
    /**
    * функции добавления
    */

    function separ($value){
  		return '`'.$value.'`';
  	} 

    function quote($value){
      return chr(39).$value.chr(39);
    }	

  	function add($column){
  		$this->columns[] = $column;
  		return $this;	
  	}

  	function columns($columns = array()){
  		$this->columns = $columns;
  		return $this;
  	}

  	function filter($column, $value, $op ='=', $type = 'AND') {
  		$this->filters[] = array('column'=>$column, 'value'=>$value, 'op'=>$op, 'type'=>$type);
  		return $this;
  	} 

  	function sort($column, $type = 'ASC') {
		  $this->sort[$column] = $type;
		  return $this;  		
  	}

  	function build(){

  		$sql = 'SELECT';
  		  		  		
  		$sql .= ' '.$this->columns.' FROM '.$this->separ($this->ORM);
  		
      if (count($this->filters) > 0)
        $sql .= $this->build_filters();
  		
      if (count($this->sort) > 0)
        $sql .= $this->build_sort();

  		if ($this->limit !== null)
  			$sql .= 'LIMIT '.$this->limit;

  		$sql .= ';';

		  return $sql;

  	}


  	function build_filters(){

  		$res = '';

  		foreach ($this->filters as $filter){
  			
  			if ($res !== '')
  				$res .= ' '.$filter['type'].' ';

  			$res .=	$this->separ($filter['column']).$filter['op'].$this->quote($filter['value']);

  		} 

  		return ' WHERE '.$res;

  	} 


  	function build_sort(){

  		$res = '';

  		foreach ($this->sort as $key => $sort){
  			
  			if ($res !== '')
  				$res = ',';
  			
  			$res .= $this->separ($key).' '.$sort;
  		}

  		return ' ORDER BY '.$res;
  	
  	}


  	function all() {

  		$sql = $this->build();
      $result = $this->query($sql);
      
        

      while ($row = $result->fetch_assoc()) {
          $result_array[] = $row;
      }

      return $result_array;   
        		
  	}


   function query($sql, $conf='default'){
      
     $this->conn($conf);
     $curr = ORM::$conn[$conf];

     $result =  $curr->query($sql);
      
      if ($curr->errno) {
        die('Select Error (' . $mysqli->errno . ') ' . $mysqli->error);
      }

      return $result;
    
    }


}