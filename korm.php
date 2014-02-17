<?php

  class kORM {

  	
  	static $config = array(); //конфиги подключения к базе
  	static $conn = array(); // все подключения
    static $memcache = '';

  	private $ORM = '';
  	private $conf = 'default';
    private $sql = '';
  	private $filters = array();
  	private $sort = array();
  	private $limit = null;
  	private $columns = '*';
    private $time = 0; // cache time
    private $wh_str = '';
    private $ord_str = '';




  	function __construct($ORM, $conf = ''){
  		$this->ORM = $ORM;
  		$this->config = $conf; //текущая конфигурация
  	}


    function __toString() {
      return $this->build();
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

    public function wh_str($sql) {
      $this->wh_str = $sql;
      return $this; 
    }

    public function ord_str($sql) {
      $this->ord_str = $sql;
      return $this;
    }

    /**
    * функция where _  in
    */

    function in($column, $values = array(), $type = 'AND') {
        
        if (is_array($values)){
            $values = implode(',', $values);
        }

        $this->filters[] = array('column'=>$column, 'value'=>$values, 'op'=>'IN', 'type'=>'AND');
        
        return $this;

    }


    /**
    * обработка массива с удалением пустых значений
    */
    function arr2value($arr, $prefix = ',') {

      $res = '';

      foreach ($arr as $item) {
        $item = trim($item);
        if ($item !== '') {
          if ($res !== '')
              $res .= ','; 
          $res .= $item; 
        }
      }

      return $res;
    
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
  		
      if ($this->sql !== '')
        return $this->sql;

      $sql = 'SELECT';

      if(is_array($this->columns)){

        $columns = '';
        
        foreach($this->columns as $column) {
          
          if ($columns !== '')
            $columns .= ',';
          
          $columns .= $this->separ($column);

        }

      } 
  		  		  		
  		$sql .= ' '.$columns.' FROM '.$this->separ($this->ORM);
  		
      if ($this->wh_str !== '')
        $sql .= $this->wh_str;
      elseif (count($this->filters) > 0)
        $sql .= $this->build_filters();
  		
      if ($this->ord_str !== '')
        $sql .= $this->ord_str;
      elseif (count($this->sort) > 0)
        $sql .= $this->build_sort();

  		if ($this->limit !== null)
  			$sql .= ' LIMIT '.$this->limit;

  		$sql .= ';';

		  return $sql;

  	}

    
    function count(){
      
      $sql = 'SELECT COUNT('.$this->columns.') FROM'.$this->separ($this->ORM);
      $sql .= $this->build_filters();

      $result = $this->query($sql);

      if ($result) {
        $count = $result->fetch_row();
        return $count[0];
       }  

      return null;
      

    }


  	function build_filters(){

  		$res = '';

  		foreach ($this->filters as $filter){
  			
  			if ($res !== '')
  				$res .= ' '.$filter['type'].' ';

  			$res .=	$this->separ($filter['column']);

        $op = trim($filter['op']);

        if ($op == '')
          $res .= ' '.$filter['value'];
        else
          $res .=$op.$this->quote($filter['value']);


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

      if ($result)
        return $result->fetch_assoc(); 

      return null;

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



  function sql($sql) {
    $this->sql = $sql;
    return $this;
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

  
    function set($column, $value = 0) {
      
      $this->set[$column] = $value;
      return $this;

    }


    function array2insert($arr = array()){
      
      $this->set = $arr;
      $this->save(); 

      return $this;

    }

    
    function save() {

      foreach($this->set as $key => $set){
        
        $set = trim($set);

        if ($set !== '') {
          
          if (isset($columns))
            $columns .= ',';

          if (isset($values))
            $values .= ',';

          $columns .= '`'.$key.'`';
          $values .= '"'.$set.'"';

        }  

      }

      
     $this->query('INSERT INTO `'.$this->ORM.'` ('.$columns.') VALUES('.$values.');');
 
    }



   


  }


// автозагрузка класса

  
  //функция быстрой загрузки
  if (!function_exists('table')) {
    function table($table, $conf = ''){
      return new kORM($table, $conf);
    }
  }  


  spl_autoload_register(function ($class) {
      
 
  $fclass = SITEPATH.'app/models/'.$class.'.php';
    
  if (file_exists($fclass))
     require $fclass;
    else
      return table($class);


      //error(500, 'not found class '.$class);

  });

