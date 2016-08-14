<?php 
/**
* mypdo 
*@author:michaelgbw(18829212319@163.com)
*@version :1.0(2016-06-29)
*/
//-----------------------------------------------------
/*
使用方法：
require_once('mypdo.class.php');
	$db = mypdo::getInstance('host', 'user', 'password', 'db', 'xxx');
    ....do something...	
    $db->close();
*/
//------------------------------------------------------
header("Content-type:text/html;charset=utf-8");
class mypdo{
	protected static $_instance =null;
	protected $dbname= '';
	protected $db;
	public function __construct($dbHost, $dbUser, $dbPasswd, $dbName, $dbCharset='utf8'){
		try{
			$dsn= 'mysql:host='.$dbHost.';dbname='.$dbName;
			$opts = array(
			 	PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,//异常（推荐使用） 用try catch捕获，也可以手动抛出异常
			  	PDO::ATTR_AUTOCOMMIT=>0, //关闭自动提交, 
			  	PDO::ATTR_TIMEOUT => 5 ); //设置超时时间			 
			$this->db = new PDO($dsn,$dbUser,$dbPasswd,$opts);
			$this->db->exec('SET character_set_connection='.$dbCharset.', character_set_results='.$dbCharset);
		}
		catch (PDOException $e){
			self::outputError($e->getmessage());
		}
	}
	public function __clone(){}

	/**
	* 单例模式
	* 
	* @return Object
	*/
	public static function getInstance($dbHost, $dbUser, $dbPasswd, $dbName, $dbCharset='utf8'){
        		if (self::$_instance === null) {
            		self::$_instance = new self($dbHost, $dbUser, $dbPasswd, $dbName, $dbCharset);
        		}
        		return self::$_instance;
    	}
    	/**
    	* Query 查询
     	*
     	* @param String $sql SQL语句
     	* @param String $mode 查询方式(All or row)
     	* @param Boolean $debug
     	* @param Boolean $type (false是数组，ture是对象)
     	* @return Array
     	*/
    	public function query($sql, $mode='All', $debug=false,$type=false){
    		if($debug == true){
    			$this->debug($sql);
    		}
    		try{
    			$result=$this->db->query($sql);

    		}
    		catch (PDOException $e){
    			self::outputError($e->getmessage());
    			$result=null;
    		}
    		
    		if($result){
    			if($type)
	    			$result->setFetchMode(PDO::FETCH_OBJ);
	    		else
	    			$result->setFetchMode(PDO::FETCH_ASSOC);
    			if($mode =="All"){
    				$re=$result->fetchAll();
    			}
    			elseif($mode =="row"){
    				$re=$result->fetch();
    			}
    		}
    		else{
    			$re= null;
    		}
    		return $re;
    	}
    	/**
    	* bind 查询（支持占位符 即?）
     	*
     	* @param String $sql SQL语句
     	* @param Array $param 绑定的参数
     	* @param String $mode 查询方式(All or row)
     	* @param Boolean $debug
     	* @return int 1/0
     	*/
    	public function bind($sql,$param,$mode='All', $debug=false){
    		if(is_array($param)){
    			if($debug == true){
    				$this->debug($sql);
    			}
    			$sth=$this->db->prepare($sql);
    			try{
    				$result=$sth->execute($param);
	    		}
	    		catch (PDOException $e){
	    			self::outputError($e->getmessage());
	    			$result=0;
	    		}
    			return $result;
    		}
    		else{
    			self::outputError("必须为数组");
    		}
    	}
    	/**
     	* Update 更新
     	*
     	* @param String $table 表名
     	* @param Array $array 字段与值(字段 => 值)
     	* @param String $where 条件
     	* @param Boolean $debug
     	* @return Int
     	*/
    	public function update($table, $array,$where,$debug = false){
    		self::checkFields($table,$array);
    		$arrcode='';
    		if(is_array($array)){
    			foreach ($array as $key => $value) {
    				if(is_numeric($value))
    					$arrcode.= "`$key`=$value,";
    				else
    					$arrcode.= "`$key`='$value',";
    				$arrcode=substr($arrcode, 0, -1);
    			}
    		}
    		if($where){
    			$sql="UPDATE `$table` SET $arrcode WHERE $where";
    		}
    		else{
    			self::outputError("缺少where条件");
    		}
    		if ($debug == true){
    			self::debug($sql);
    		}
    		try{
    			$result = $this->db->exec($sql);
	    	}
	    	catch (PDOException $e){
			self::outputError($e->getmessage());
   			$result=0;
       		}
    		return $result;
    	}
    	/**
     	* Insert 插入
     	*
     	* @param String $table 表名
     	* @param Array $array 字段与值
     	* @param Boolean $debug
     	* @return Int
     	*/
    	public function insert($table,$array,$debug= false){
    		self::checkFields($table,$array);
    		$sql = "INSERT INTO `$table` (`".implode('`,`', array_keys($array))."`) VALUES ('".implode("','", $array)."')";
        		if ($debug === true) 
        			self::debug($sql);
        		try{
    			$result = $this->db->exec($sql);
	    	}
	    	catch (PDOException $e){
			self::outputError($e->getmessage());
   			$result=0;
       		}
        		return $result;
    	}
    	/**
     	* Delete 删除
     	*
     	* @param String $table 表名
     	* @param String $where 条件
     	* @param Boolean $debug
     	* @return Int
     	*/
    	public function delete($table, $where = '', $debug = false){
        		if ($where == '') {
            		self::outputError("where 条件为空");
        		}
        		else {
            		$sql = "DELETE FROM `$table` WHERE $where";
            		if ($debug === true) 
            			self::debug($sql);
            		try{
    				$result = $this->db->exec($sql);
	    		}
	    		catch (PDOException $e){
				self::outputError($e->getmessage());
   				$result=0;
       			}
            		return $result;
        		}
    	}
    	/**
	* execSql 执行SQL语句
	*
	* @param String $sql
	* @param Boolean $debug
	* @return Int
	*/
	public function execSql($sql, $debug = false){
		if ($debug == true)
	 		self::debug($sql);
		try{
    			$result = $this->db->exec($sql);
	    	}
	    	catch (PDOException $e){
			self::outputError($e->getmessage());
   			//$result=0;
       		}
		return $result;
	}
	/**
     	* 获取指定列的数量
     	* 
     	* @param string $table
     	* @param string $field_name (默认为 * )
     	* @param string $where
     	* @param bool $debug
     	* @return int
     	*/
    	public function getCount($table, $field_name= '*', $where = '', $debug = false){
        		$strSql = "SELECT COUNT($field_name) AS num FROM $table";
        		if ($where != ''){
        			$strSql .= " WHERE $where";
        		} 
        		if ($debug === true) 
        			self::debug($strSql);
        		$arrTemp = $this->query($strSql, 'row');
        		return $arrTemp['num'];
    	}
    	/**
     	* beginTransaction 事务开始
     	*/
    	public function beginTransaction(){
        		$this->db->beginTransaction();
    	}
    	/**
     	* commit 事务提交
     	*/
    	public function commit(){
        		$this->db->commit();
    	}
    	/**
     	* rollback 事务回滚
     	*/
    	public function rollback(){
        		$this->db->rollback();
    	}
    	/**
     	* transaction 通过事务处理多条SQL语句
     	* 只有innodb支持事务哦
     	*
     	* @param array $arraySql
     	* @return Boolean
     	*/
    	public function execTransaction($arraySql){
        		$retval = 1;
        		$this->beginTransaction();
        		foreach ($arraySql as $strSql) {
            		if ($this->execSql($strSql) == 0) 
            			$retval = 0;
        		}
        		if ($retval == 0) {
            		$this->rollback();
            		return false;
        		}
        		else {
            		$this->commit();
            		return true;
        		}
    	}
    	/**
    	* close 关闭数据库
    	*
    	*/
    	public function close(){
        		$this->db = null;
    	}

    	//private start 

    	/**
     	* checkFields 检查指定字段是否在指定数据表中存在
    	*
     	* @param String $table
     	* @param array $arrayField
     	*/
    	private function checkFields($table,$arrayFields){
    		$field = self::getFields($table);
    		foreach ($field as $key => $value) {
    			if(! in_array($key, $field)){
    				self::outputError("未知的 $key 在当前的表结构中");
    			}
    		}
    	}
    	/**
     	* getFields 获取指定数据表中的全部字段名
     	*
     	* @param String $table 表名
     	* @return array
     	*/
     	private function getFields($table){
     		$fields=array();
     		try{
    			$record=$this->db->query("show columns from $table");
	    	}
	    	catch (PDOException $e){
			self::outputError($e->getmessage());
   			return array();
       		}
     		$record->setFetchMode(PDO::FETCH_ASSOC);
     		$result = $record->fetchAll();
		foreach ($result as $rows) {
            		$fields[] = $rows['Field'];
        		}
        		return $fields;
     	}
    	/**
     	* getPDOError 捕获PDO错误信息
     	*/
    	private function getPDOError(){
        		if ($this->db->errorCode() != '00000') {
           		$arrayError = $this->db->errorInfo();
            		return  '错误码:  '.$this->db->errorCode().'  错误信息:  '.$arrayError[2];
        		}
    	}
    	/**
     	* debug
     	* 
     	* @param mixed $debuginfo
     	*/
    	private function debug($debuginfo){
        		var_dump($debuginfo);
        		die();
    	}
    	/**
     	* outputError
     	* 
     	* @param String $strErrMsg
     	*/
    	private function outputError($strErrMsg){
    		try{
    			throw new Exception($strErrMsg);
    		}
        		catch (Exception $e){
        			//echo $e->getMessage();
        			echo self::getPDOError();
        		}
    	}
}
?>