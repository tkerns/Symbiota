<?php
include_once($serverRoot.'/config/dbconnection.php');
 
class ExsiccatiManager {

	private $conn;

	function __construct() {
		$this->conn = MySQLiConnectionFactory::getCon("readonly");
	}

	function __destruct(){
 		if(!($this->conn === false)) $this->conn->close();
	}

	public function getTitleArr($ometid = 0, $mode = 0){
		$retArr = array();
		$sql = '';
		if($ometid){
			//Display full list
			$sql = 'SELECT et.ometid, et.title, et.abbreviation, et.editor, et.exsrange, et.source, et.notes '.
				'FROM omexsiccatititles et '.
				'WHERE ometid = '.$ometid;
		}
		elseif($mode){
			//Display full list
			$sql = 'SELECT et.ometid, et.title, et.abbreviation, et.editor, et.exsrange, et.source, et.notes '.
				'FROM omexsiccatititles et '.
				'ORDER BY et.title';
		}
		else{
			//Display only exsiccati that have linked specimens
			$sql = 'SELECT DISTINCT et.ometid, et.title, et.abbreviation, et.editor, et.exsrange, et.source, et.notes '.
				'FROM omexsiccatititles et INNER JOIN omexsiccatinumbers en ON et.ometid = en.ometid '.
				'INNER JOIN omexsiccatiocclink ol ON en.omenid = ol.omenid '.
				'INNER JOIN omoccurrences o ON ol.occid = o.occid '.
				'ORDER BY et.title';
		}
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr[$r->ometid]['title'] = $r->title;
				$retArr[$r->ometid]['abbreviation'] = $r->abbreviation;
				$retArr[$r->ometid]['editor'] = $r->editor;
				$retArr[$r->ometid]['exsrange'] = $r->exsrange;
				$retArr[$r->ometid]['source'] = $r->source;
				$retArr[$r->ometid]['notes'] = $r->notes;
			}
			$rs->close();
		}
		return $retArr;
	}

	public function getExsNumberArr($ometid){
		$retArr = array();
		if($ometid){
			//Grab all numbers for that exsiccati title; only show number that have occid links
			$sql = 'SELECT DISTINCT en.omenid, en.exsnumber, en.notes, '.
				'CONCAT_WS(" ",o.recordedby, CONCAT("(",IFNULL(o.recordnumber,"s.n."),")"),o.eventDate) as collector '.
				'FROM omexsiccatinumbers en INNER JOIN omexsiccatiocclink ol ON en.omenid = ol.omenid '.
				'INNER JOIN omoccurrences o ON ol.occid = o.occid '.
				'WHERE en.ometid = '.$ometid.' ORDER BY en.exsnumber+1,en.exsnumber';
			//echo $sql;
			if($rs = $this->conn->query($sql)){
				while($r = $rs->fetch_object()){
					if(!array_key_exists($r->omenid,$retArr)){
						$retArr[$r->omenid]['number'] = $r->exsnumber;
						$retArr[$r->omenid]['notes'] = $r->notes;
						$retArr[$r->omenid]['collector'] = $r->collector;
					}
				}
				$rs->close();
			}
		}
		return $retArr;
	}

	public function getExsNumberObj($omenid){
		$retArr = array();
		if($omenid){
			//Grab info for just that exsiccati number with the title info
			$sql = 'SELECT et.title, et.abbreviation, et.editor, et.exsrange, en.exsnumber, en.notes '.
				'FROM omexsiccatititles et INNER JOIN omexsiccatinumbers en ON et.ometid = en.ometid '. 
				'WHERE en.omenid = '.$omenid;
			//echo $sql;
			if($rs = $this->conn->query($sql)){
				if($r = $rs->fetch_object()){
					$retArr['title'] = $r->title;
					$retArr['abbreviation'] = $r->abbreviation;
					$retArr['editor'] = $r->editor;
					$retArr['exsrange'] = $r->exsrange;
					$retArr['exsnumber'] = $r->exsnumber;
					$retArr['notes'] = $r->notes;
				}
				$rs->close();
			}
		}
		return $retArr;
	}

	public function getExsOccArr($omenid){
		$retArr = array();
		$sql = 'SELECT ol.ranking, ol.notes, o.occid, o.occurrenceid, o.catalognumber, '.
			'o.sciname, o.scientificnameauthorship, o.recordedby, o.recordnumber, DATE_FORMAT(o.eventdate,"%d %M %Y") AS eventdate, '.
			'trim(o.country) AS country, trim(o.stateprovince) AS stateprovince, trim(o.county) AS county, '.
			'trim(o.municipality) AS municipality, o.locality, i.thumbnailurl, i.url '.
			'FROM omexsiccatiocclink ol INNER JOIN omoccurrences o ON ol.occid = o.occid '.
			'LEFT JOIN images i ON o.occid = i.occid '.
			'WHERE ol.omenid = '.$omenid.' ORDER BY ol.ranking, o.recordedby, o.recordnumber';
		if($rs = $this->conn->query($sql)){
			while($r = $rs->fetch_object()){
				$retArr[$r->occid]['ranking'] = $r->ranking;
				$retArr[$r->occid]['notes'] = $r->notes;
				$retArr[$r->occid]['occurrenceid'] = $r->occurrenceid;
				$retArr[$r->occid]['catalognumber'] = $r->catalognumber;
				$retArr[$r->occid]['sciname'] = $r->sciname;
				$retArr[$r->occid]['author'] = $r->scientificnameauthorship;
				$retArr[$r->occid]['recordedby'] = $r->recordedby;
				$retArr[$r->occid]['recordnumber'] = $r->recordnumber;
				$retArr[$r->occid]['eventdate'] = $r->eventdate;
				$retArr[$r->occid]['country'] = $r->country;
				$retArr[$r->occid]['stateprovince'] = $r->stateprovince;
				$retArr[$r->occid]['county'] = $r->county;
				$retArr[$r->occid]['municipality'] = $r->municipality;
				$retArr[$r->occid]['locality'] = $r->locality;
				if($r->url){ 
					$retArr[$r->occid]['url'] = $r->url;
					$retArr[$r->occid]['tnurl'] = ($r->thumbnailurl?$r->thumbnailurl:$r->url);
				}
			}
			$rs->close();
		}
		return $retArr;
	}
	
	public function addTitle($pArr){
		$sql = 'INSERT INTO omexsiccatititles(title, abbreviation, editor, exsrange, source, notes) '.
			'VALUES("'.$this->cleanStr($pArr['title']).'","'.$this->cleanStr($pArr['abbreviation']).'","'.
			$this->cleanStr($pArr['editor']).'","'.$this->cleanStr($pArr['exsrange']).
			'","'.$this->cleanStr($pArr['source']).'","'.$this->cleanStr($pArr['notes']).'")';
		//echo $sql;
		$this->conn->query($sql);
	}
	
	public function editTitle($pArr){
		$sql = 'UPDATE omexsiccatititles '.
			'SET title = "'.$this->cleanStr($pArr['title']).'", abbreviation = "'.$this->cleanStr($pArr['abbreviation']).
			'", editor = "'.$this->cleanStr($pArr['editor']).'", exsrange = "'.$this->cleanStr($pArr['exsrange']).
			'", source = "'.$this->cleanStr($pArr['source']).'", notes = "'.$this->cleanStr($pArr['notes']).'" '.
			'WHERE (ometid = '.$pArr['ometid'].')';
		$this->conn->query($sql);
	}

	public function deleteTitle($ometid){
		if($ometid && is_numeric($ometid)){
			$sql = 'DELETE FROM omexsiccatititles WHERE (ometid = '.$ometid.')';
			$this->conn->query($sql);
		}
	}

	public function addNumber($pArr){
		$sql = 'INSERT INTO omexsiccatinumbers(ometid,exsnumber,notes) '.
			'VALUES('.$pArr['ometid'].',"'.$this->cleanStr($pArr['exsnumber']).'","'.$this->cleanStr($pArr['notes']).'")';
		$this->conn->query($sql);
	}

	public function editNumber($pArr){
		if($pArr['omenid'] && is_numeric($pArr['omenid'])){
			$sql = 'UPDATE omexsiccatinumbers '.
				'SET exsnumber = "'.$this->cleanStr($pArr['exsnumber']).'",notes = "'.$this->cleanStr($pArr['notes']).'" '.
				'WHERE (omenid = '.$this->cleanStr($pArr['omenid']).')';
			$this->conn->query($sql);
		}
	}

	public function deleteNumber($omenid){
		if($omenid && is_numeric($omenid)){
			$sql = 'DELETE FROM omexsiccatinumbers WHERE (omenid = '.$omenid.')';
			$this->conn->query($sql);
		}
	}

	public function addOccLink($pArr){
		if($pArr['omenid'] && $pArr['occid'] && is_numeric($pArr['omenid']) && is_numeric($pArr['occid']) && is_numeric($pArr['ranking'])){
			$sql = 'INSERT INTO omexsiccatiocclink(omenid,occid,ranking,notes) '.
				'VALUES ('.$pArr['omenid'].','.$pArr['occid'].','.$pArr['ranking'].',"'.$this->cleanStr($pArr['notes']).'")';
			$this->conn->query($sql);
		}
	}

	public function editOccLink($pArr){
		if($pArr['omenid'] && $pArr['occid'] && is_numeric($pArr['omenid']) && is_numeric($pArr['occid']) && is_numeric($pArr['ranking'])){
			$sql = 'UPDATE omexsiccatiocclink '.
				'SET ranking = '.$pArr['ranking'].', notes = "'.$this->cleanStr($pArr['notes']).'" '.
				'WHERE (omenid = '.$pArr['omenid'].') AND (occid = '.$pArr['occid'].')';
			$this->conn->query($sql);
		}
	}

	public function deleteOccLink($emenid, $occid){
		if($emenid && $occid && is_numeric($emenid) && is_numeric($occid)){
			$sql = 'DELETE FROM omexsiccatioccid WHERE (omenid = '.$omenid.') AND (occid = '.$occid.')';
			$this->conn->query($sql);
		}
	}

	private function cleanStr($str){
 		$newStr = trim($str);
 		$newStr = preg_replace('/\s\s+/', ' ',$newStr);
 		$newStr = $this->conn->real_escape_string($newStr);
 		return $newStr;
 	}
}
?> 