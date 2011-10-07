<?php
class SpecUploadDirect extends SpecUploadManager {

 	public function __construct(){
		parent::__construct();
 	}
 	
	public function __destruct(){
 		parent::__destruct();
	}
	
 	public function analyzeFile(){
	 	$this->readUploadParameters();
		$sourceConn = $this->getSourceConnection();
		if($sourceConn){
			$sql = trim($this->queryStr);
			if(substr($sql,-1) == ";") $sql = substr($sql,0,strlen($sql)-1); 
			if(strlen($sql) > 20 && stripos(substr($sql,-20)," limit ") === false){
				$sql .= " LIMIT 10";
			}
			$rs = $sourceConn->query($sql);
			if(!$rs){
				echo "<li style='font-weight:bold;'>ERROR: Possible syntax error in source SQL</li>";
				return;
			}
			$sourceArr = Array();
			if($row = $rs->fetch_assoc()){
				foreach($row as $k => $v){
					$sourceArr[] = strtolower($k);
				}
			}
			$rs->close();
			$this->sourceArr = $sourceArr;
			//$this->echoFieldMapTable($sourceArr);
		}
	}

 	public function uploadData($finalTransfer){
 		global $charset;
	 	$this->readUploadParameters();
 		$sourceDbpkFieldName = "";
		if(array_key_exists("dbpk",$this->fieldMap)){
			$sourceDbpkFieldName = $this->fieldMap["dbpk"]["field"];
			unset($this->fieldMap["dbpk"]);
		}
		$sqlInsertInto = "INSERT INTO uploadspectemp (collid,dbpk,";
		$sqlInsertInto .= implode(",",array_keys($this->fieldMap));
		$sqlInsertInto .= ") ";
		$sqlInsertValuesBase = "VALUES (".$this->collId;
		
		$sourceConn = $this->getSourceConnection();
		if($sourceConn){
			if($sourceDbpkFieldName){
				//Delete all records in uploadspectemp table
				$sqlDel = "DELETE FROM uploadspectemp WHERE (collid = ".$this->collId.')';
				$this->conn->query($sqlDel);
				
				echo "<li style='font-weight:bold;'>Connected to Source Database</li>";
				set_time_limit(800);
				$sourceConn->query("SET NAMES ".str_replace('-','',strtolower($charset)).";");
				//echo "<div>".$this->queryStr."</div><br/>";
				if($result = $sourceConn->query($this->queryStr)){
					echo "<li style='font-weight:bold;'>Results obtained from Source Connection, now reading Resultset... </li>";
					$this->transferCount = 0;
					while($row = $result->fetch_assoc()){
						$recMap = Array();
						$row = array_change_key_case($row);
						foreach($this->fieldMap as $symbField => $sMap){
							$valueStr = $row[$sMap['field']];
							$recMap[$symbField] = $valueStr;
						}
						$this->loadRecord($recMap);
						unset($recMap);
					}
					
					$this->finalUploadSteps($finalTransfer);
					$result->close();
				}
				else{
					echo "<hr /><div style='color:red;'>Unable to create a Resultset with the Source Connection. Check connection parameters, source sql statement, and firewall restriction</div>";
					echo "<div style='color:red;'>ERROR: ".$sourceConn->error."</div><hr />";
					//echo "<div>SQL: $this->sourceSql</div>";
				}
				$sourceConn->close();
			}
			else{
				echo "<div style='color:red;'>Source Primary Key has not yet been mapped to a system field</div>";
			}
		}
	}
	
	private function getSourceConnection() {
		if(!$this->server || !$this->username || !$this->password || !$this->schemaName){
			echo "<div style='color:red;'>One of the required connection variables are null. Please resolve.</div>";
			return null;
		}
		$connection = new mysqli($this->server, $this->username, $this->password, $this->schemaName);
		if(mysqli_connect_errno()){
			echo "<div style='color:red;'>Could not connect to Source database!</div>";
			echo "<div style='color:red;'>ERROR: ".mysqli_connect_error()."</div>";
			return null;
		}
		return $connection;
    }
}
?>
