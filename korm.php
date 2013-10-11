<?php

  
  class kORM {

  	
  	static $config = array(); //конфиги подключения к базе
  	static $conn = array(); // все подключения
    static $memcache = '';

  	private $ORM = '';
  	private $conf = 'default';
  	private $filters = array();
  	private $sort = array();
  	private $limit = null;
  	private $columns = '*';
    private $time = 0; // cache time


  	function __construct($ORM, $conf = ''){
  		$this->ORM = $ORM;
  		$this->config = $conf; //текущая конфигурация
  	}


    static function table($ORM, $conf = '') {
      return new kORM($ORM, $conf);
    }


    //активируем мемкеш
    static function memcache($host = '127.0.0.1', $port = 11211) {
        
        if (class_exists('Memcache')) {
          kORM::$memcache = new Memcache;
          kORM::$memcache->connect($host, $port);
        }  
      
        return;
    }

  	/*
    * добавляем конфигурацию подключения к базе
    */
    static function config($name, $user = 'root', $pswd = '', $host = 'localhost', $db = ''){
      
      if ($db == '')
        $db = $name;

  		kORM::$config[$name] = array('host'=>$host, 'user'=>$user, 'pswd'=>$pswd, 'db'=>$db);
      return True;

  	}

  	
  	/**
    ** сonnected DB
    */

    private function conn($conf) {
  		 		
      if ($conf == '')
        $config = current(kORM::$config); //first config
      else  
        $config = kORM::$config[$conf]; 

      if (!is_array($config))
          error_log('no config DB `'.$conf.'` found'); 

      $mysqli = new mysqli($config['host'], $config['user'], $config['pswd'], $config['db']);
      if ($mysqli->connect_error) {
          error_log('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }

      $mysqli->query('SET NAMES UTF8');      
      kORM::$conn[$conf] = $mysqli;
     
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

  	function where($column, $value = 1, $op ='=', $type = 'AND') {
  		$this->filters[] = array('column'=>$column, 'value'=>$value, 'op'=>$op, 'type'=>$type);
  		return $this;
  	} 

  	function whor($column, $value = 1, $op ='=') {
      $this->filters[] = array('column'=>$column, 'value'=>$value, 'op'=>$op, 'type'=>'OR');
      return $this; 
    }

    function not($column, $value = 1){
      $this->filters[] = array('column'=>$column, 'value'=>$value, 'op'=>'<>', 'type'=>'AND');
      return $this;
    }

    function sort($column, $type = 'ASC') {
		  $this->sort[$column] = $type;
		  return $this;  		
  	}

  	function limit($limit){
      $this->limit = $limit;
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
  			$sql .= ' LIMIT '.$this->limit;

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


    function one() {

      $sql = $this->build();
      $result = $this->query($sql);
        
      return $result->fetch_assoc(); 

    }


  function query($sql, $conf=''){
      
    if ($this->time > 0)
        $result = $this->cache($sql);

    $this->conn($conf);
    $curr = kORM::$conn[$conf];

    $result = $curr->query($sql);

    if ($this->time > 0)
      $this->cache($sql, $result);
      
    if ($curr->errno) 
      error_log('Select Error (' . $mysqli->errno . ') ' . $mysqli->error);
    

    return $result;
    
  }

   
    function cache($sql, $value = null) {

      $key = md5($sql);

      if (is_null($value)) 
         return korm::$memcache->set($key, $value, False, $this->time);   

      if ($result = korm::$memcache->get($key))
          return $result;
      else
          return False;
    
    }


    function time($time = 3600){
      $this->time = $time;
      return $this;
    }

  
  }


//функция быстрой загрузки
if (!function_exists('table')) {
  function table($table, $conf = ''){
    return new kORM($table, $conf);
  }
}  
