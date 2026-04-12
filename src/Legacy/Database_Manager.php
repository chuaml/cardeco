<?php 
class Database_Manager{
	protected $con;
	protected $sql;
	protected $result;
	
	function __construct(mysqli $con){
		$this->con = $con;
		$this->sql = '';
		$this->result = [];
	}
	
	public function exec_query(string $sql){
		$stmt = mysqli_query($this->con, $sql);
		if($stmt === false){
			trigger_error(mysqli_error($this->con));
			return false;
		}
		
		return $stmt;
	}
	
	public function getSql(){
		return $this->sql;
	}
	
	public function getResult(){
		return $this->result;
	}
}

interface select{
	public function select(string $sql);
}

interface insert{
	public function insert(string $sql);
}

interface update{
	public function update(string $sql);
}

interface delete{
	public function delete(string $sql);
}
