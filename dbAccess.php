<?php
include_once "dbinfo.php";
class DBAccess {

    /**
     *
     * @var PDO
     */
    public $db;

    public function __construct() {
        $this->db = new PDO("mysql:host=" . DB_SERVER . ";charset=utf8;dbname=" . DB_NAME, DB_USER, DB_PASS);
    }

    public function log($obj) {
        $err = print_r($obj, true);
        $GLOBALS["sqlerr"] = $err;
    }

    public function query($sql, $params = []) {
        $GLOBALS["sqlerr"]  = "";
        $GLOBALS["sql"]  = $sql;
        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $stmt = $this->db->prepare($sql);
        if ($stmt===FALSE) {

       $this->log(print_r($this->db->errorInfo(),true));
                
            return [];
        } else {
           if (($params == [] ) && ($params !== [])) $params=[];// error_log ("MAIS MAIS C est VIDE !!!!");
            foreach ($params as $param_Name => $param_Value) {
                $stmt->bindValue($param_Name, $param_Value);
            }
            $good = $stmt->execute();
            if ($good===false) {
                
            //var_dump($good);
                $this->log(print_r($this->db->errorInfo(),true));
                
             //   $this->log(["Sql" => $sql, "error" => $this->db->errorInfo(), "params" => $params]);
                return [];
            } else {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

    public function printQuery($queryRst, $returnHTML = false) {
     
        $rst = "<table class='table table-hover table-bordered table-responsive table-striped'>";
        $line_number=0;
        foreach ($queryRst as $currentRow) {
            if ($line_number == 0) {
                $line_number++;
                $rst.= "<tr>";
                foreach ($currentRow as $field_name => $field_value)
                    $rst.= "<th><strong>$field_name</strong</th>";
                $rst.= "</tr>";
            }
            $rst.= "<tr>";
            foreach ($currentRow as $field_name => $field_value)
                $rst.= "<td>$field_value</td>";
            $rst.= "</tr>";
        }
        $rst.= "</table>";
        if ($returnHTML)
            return $rst;
        else
            echo $rst;
    }

    public function printSQL($sql, $params = [], $returnHTML = false) {
       
       $rst =  $this->printQuery($this->query($sql, $params), $returnHTML);
       if ($returnHTML) return $rst;
    }
    public function quotestr($str,$withoutQuote=false){
        $str = $this->db->quote($str);
        if ($withoutQuote) $str = substr ($str, 1,-1);
        return $str ;
    } 

}

$mainDB = new DBAccess();

function getMainDB() {
    global $mainDB;
    return $mainDB;
}

function doQuery($sql,$param = null){
    
    return getMainDB()->query($sql,$param);
    
}