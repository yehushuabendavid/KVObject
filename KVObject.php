<?php

/**
 *   
 *  _  ____   _____  _     _        _
 * | |/ /\ \ / / _ \| |__ (_)___ __| |_
 * | ' <  \ V / (_) | '_ \| / -_) _|  _|
 * |_|\_\  \_/ \___/|_.__// \___\__|\__|
 *                       |__/
 * By USM
 * @author Arnaud Celermajer arnaud@us-major.com
 */
/**
 * Description of KVObject
 * @version 2.00
 * @author Arnaud Celermajer
 */
include_once __DIR__ . '/dbAccess.php';
include_once __DIR__ . '/usefull.php';

define("DECIMAL_VAL", " DECIMAL(14,4) ");

define("VARCHAR_VAL", " VARCHAR(255) ");

define("INT_VAL", " INT(11) ");

function KV_internalUnserialize($str) {
    if (is_array($str))
        return $str;
    if (substr($str, 0, 2) == "a:")
        return unserialize($str);
    return $str;
}

function KV_internalSerialize($v) {
    if (is_array($v))
        $v = serialize($v);
    return $v;
}

class KVObject {

    protected $fields = [];
    protected $ykv = null;

    /**
     *
     * @var DBAccess 
     */
    protected $mainDB;
    public $obj_id;
    public $dataObject;
    public $collection;
    public $SQLHisto = [];
    public $__internal_autosave;
    public $__internal_toBeUpdated;
    public $__internal_index;
    public $__internal_index_checked;

    static function getCollections() {
        $rst = [];
        $c = getMainDB()->query("show tables");
        foreach ($c as $t) {
            $tname = reset($t);
            if (strEndsWith_be($tname, "_kv"))
                $rst[] = substr($tname, 0, -3);
        }
        return $rst;
    }

    static function printObjectList($objs, $returnHTML = false) {
        return printQuery(static::objectListToArray($objs), $returnHTML);
    }

    public function selectAll($fields = null) {
        $rst = [];
        $params = null;
        if ($fields == null) {
            $sql = "select * from " . $this->getCollectionName() . "_kv order by id";
        } else {
            $sql = "select * from " . $this->getCollectionName() . "_kv ";
            foreach ($fields as $k => $f) {
                if ($k == 0) {
                    $sql .= " where ";
                } else {
                    $sql .= " or ";
                }
                $sql .= " (_key = :k$k) ";
                $params["k$k"] = $f;
            }
            $sql .= "order by id";
        }
        $ids = getMainDB()->query($sql, $params);
        $c = $this->getClassName();
        $lastid = -1;
        foreach ($ids as $id) {
            if ($lastid != $id["id"]) {
                $lastid = $id["id"];
                $cc = new $c();
                /* @var $cc KVObject */

                $cc->collection = $this->collection;
                $cc->obj_id = $id["id"];
                $rst[$lastid] = $cc;
            }
            $rst[$lastid]->dataObject[$id["_key"]] = KV_internalUnserialize($id["_val"]);
        }
        return $rst;
    }

    static function objectListToFastArray($objs) {
        $dt = [];
        foreach ($objs as $oo) {
            $dt[$oo->obj_id] = $oo->dataObject;
        }
        return $dt;
    }

    static function objectListToArray($objs, $fields = [], $with_obj_id = false) {
        if ($objs == [])
            return [];

        if ($fields == []) {
            foreach ($objs as $oo) {
                $k = array_keys($oo->dataObject);
                foreach ($k as $kk)
                    $fields[$kk] = 1;
            }
            $fields = array_keys($fields);
        }

        $rst = [];
        foreach ($objs as $indx => $oo) {
            if ($with_obj_id)
                $rst[$indx]["obj_id"] = $oo->obj_id;
            foreach ($fields as $f)
                $rst[$indx][$f] = $oo->$f;
        }

        return $rst;
    }

    public function __construct($obj_id = null) {
        $this->__internal_index_checked = FALSE;
        $this->__internal_autosave = TRUE;
        $this->__internal_toBeUpdated = [];
        $this->collection = "";
        $this->__internal_index = NULL;
        if (is_string($obj_id)) {
            $this->collection = $obj_id;
            $obj_id = null;
            $this->loadIndexes();
        }
        $this->mainDB = getMainDB();
        $this->obj_id = $obj_id;
    }

    public function deleteCollection($collectionName) {
        $sql = "drop table $collectionName" . "_kv";
        $this->mainDB->query($sql);
    }

    public function clearCollection($collectionName = null) {
        if ($collectionName == null)
            $collectionName = $this->getCollectionName();
        $sql = "truncate table $collectionName" . "_kv";
        $this->mainDB->query($sql);
    }

    public function createCollection($collectionName = null, $memory = False) {
        if ($collectionName == null)
            $collectionName = $this->getCollectionName();

        $engine = $memory ? "MEMORY" : 'InnoDB';
        $text = $memory ? "varchar(2048)" : 'longtext';




        if (!in_array($collectionName, $this->getCollections())) {
            $sql2 = "CREATE TABLE `$collectionName" . "_kv` (
 `id` int(11) NOT NULL,
 `_key` varchar(127) COLLATE utf8_unicode_ci NOT NULL,
 `_val` $text CHARACTER SET utf32 COLLATE utf32_unicode_ci NOT NULL,
 PRIMARY KEY (`id`,`_key`),
 KEY `_key` (`_key`),
 KEY `_val` (`_val`(191))
) ENGINE=$engine DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

            $this->mainDB->query($sql2);
        }
        $this->collection = $collectionName;
    }

    public function insert($p, $HerreANewID = null) {
        if ($HerreANewID == null)
            $newid = $this->mainDB->query("select ifnull(1+max(id),1) as nid from " . $this->getCollectionName() . "_kv")[0]["nid"];
        else
            $newid = $HerreANewID;
        $this->obj_id = $newid;
        $this->dataObject = [];
        $sql = "insert into  " . $this->getCollectionName() . "_kv (id , _key , _val ) VALUES ";
        $num = 0;
        $params = [];
        foreach ($p as $k => $v) {
            $v = KV_internalSerialize($v);
            $num++;
            if ($num != 1)
                $sql .= ", ";
            $sql .= " (:id$num,:key$num,:val$num)";
            $params["id$num"] = $newid;
            $params["key$num"] = $k;
            $params["val$num"] = $v;
            $this->dataObject[$k] = $v;
        }
        $this->mainDB->query($sql, $params);
        $this->updateIndex();
    }

    public function delete($obj_id = null) {
        if ($obj_id === null) {
            $obj_id = $this->obj_id;
        }
        $sql = "delete from " . $this->getCollectionName() . "_kv where id=:ii";
        $this->mainDB->query($sql, ["ii" => $obj_id]);
        
       $this->loadIndexes();
       if ($this->__internal_index != NULL) {
           getMainDB()->query("delete from kvidx_".$this->collection . " where id = ".$obj_id);
       }
    }

    public function getClassName() {
        return get_class($this);
    }

    public function getCollectionName() {
        if ($this->collection != "")
            return $this->collection;
        return substr($this->getClassName(), 2);
    }

    public function beginUpdate() {
        $this->__internal_autosave = FALSE;
    }

    public function endUpdate() {
        $this->__internal_autosave = TRUE;
        $k = 0;
        $sql = "replace into " .
                $this->getCollectionName() .
                "_kv (id, _key, _val) values ";
        $param = [];
        $doit="no";
        foreach ($this->__internal_toBeUpdated as $field => $useless) {
            $k++;
            if ($k != 1)
                $sql .= ", ";
            $sql .= "(:objid$k, :key$k, :val$k)";
            $param["objid$k"] = $this->obj_id;
            $param["key$k"] = $field;
            $param["val$k"] = KV_internalSerialize($this->dataObject[$field]);
            if ($this->inIndexes($field)) {
                $doit="yes";
            }
        }
        $this->mainDB->query($sql, $param);
        if ($doit=="yes") {
            $this->updateIndex();
        }
    }

    public function update($data) {
        foreach ($data as $k => $v) {
            if ($v == NULL)
                $v = "";
            $this->$k = $v;
        }
    }

    public function getProperties($key) {
        $rst = [];
        $this->load();
        foreach ($this->dataObject as $k => $v) {
            if (strpos($k, $key) === 0) {
                $rst[$k] = $v;
            }
        }
        return $rst;
    }

    public function getAllProperties() {
        if ($this->obj_id == NULL) {
            return [];
        }
        $sql = "select _key  from " . $this->getCollectionName() . "_kv" . " where  id=" . $this->obj_id;
        return getMainDB()->query($sql);
    }

    public function loadFromArrayOrCreate($arr) {
        $this->loadFromArray($arr);
        if ($this->obj_id == null)
            $this->insert($arr);
    }

    public function loadFromArray($arr) {
        $oo = $this->selectFromArray($arr);
        $this->obj_id = null;
        $this->dataObject = [];
        if ($oo != []) {
            $oo = reset($oo);
            $this->obj_id = $oo->obj_id;
            $this->dataObject = $oo->dataObject;
        }
    }

    public function getDynamic($key) {
        if (isset($this->dataObject[$key]))
            return KV_internalUnserialize($this->dataObject[$key]);
        $rst = $this->mainDB->query("select _val as rst from " . $this->getCollectionName() . "_kv" . " where (_key=:kdata) and (id=:id)", ["kdata" => $key, "id" => $this->obj_id]);

        if (isset($rst[0]["rst"]))
            $this->dataObject[$key] = KV_internalUnserialize($rst[0]["rst"]);
        else
            $this->dataObject[$key] = "";
        return KV_internalUnserialize($this->dataObject[$key]);
    }

    public function selectFromArray($arr, $fields = null) {
        if ($arr == [])
            return $this->selectAll($fields);


        $sql = "select id  from " . $this->getCollectionName() . "_kv" . " where  ";
        $i = 0;
        $params = [];
        foreach ($arr as $k => $v) {
            if ($i != 0)
                $sql .= " or ";
            $i++;
            $sql .= "(( _key =:k$i ) and (_val = :v$i)) ";
            $params["k$i"] = $k;
            $params["v$i"] = KV_internalSerialize($v);
        }
        $sql .= " group by id having count(*) = " . sizeof($arr);
        $sql = "select  * from " . $this->getCollectionName() . "_kv" . " where ( id in ($sql) ) ";
        if ($fields != null) {
            $sql .= " and (";
            foreach ($fields as $if => $f) {
                if ($if != 0)
                    $sql .= " or ";
                $sql .= " (_key = :ak$if)";
                $params["ak$if"] = $f;
            }
            $sql .= ")";
        }
        $sql .= " order by id";
        //  print_r($sql);
        $ids = getMainDB()->query($sql, $params);

        $c = $this->getClassName();
        $lastid = -1;
        $rst = [];
        foreach ($ids as $id) {
            if ($lastid != $id["id"]) {
                $lastid = $id["id"];
                $cc = new $c();
                /* @var $cc KVObject */

                $cc->collection = $this->collection;
                $cc->obj_id = $id["id"];
                $rst[$lastid] = $cc;
            }
            $rst[$lastid]->dataObject[$id["_key"]] = KV_internalUnserialize($id["_val"]);
        }
        return $rst;

        /*
          $sql = "select id , count(*) from " . $this->getTableName() . "_kv" . " where  ";
          $i = 0;
          $params = [];
          foreach ($arr as $k => $v) {
          if ($i != 0)
          $sql .= " or ";
          $i++;
          $sql .= "(( _key =:k$i ) and (_val = :v$i)) ";
          $params["k$i"] = $k;
          $params["v$i"] = KV_internalSerialize($v);
          }
          $sql .= " group by id having count(*) = " . sizeof($arr);
          $qr = $this->mainDB->query($sql, $params);

          $objs = [];
          $cc = $this->getClassName();



          foreach ($qr as $qrr) {
          $aid = $qrr["id"];
          $c = new $cc();
          $c->collection = $this->collection;
          $c->setId($aid);
          if (!$dyna)
          $c->load();
          $objs[] = $c;
          }
          return $objs;
         * 
         */
    }

    public function loadFromKeyValue($key, $value) {
        $rst = $this->mainDB->query("select id from " . $this->getCollectionName() . "_kv" . " where (_key=:kdata) and (_val=:vdata)", ["kdata" => $key, "vdata" => $value]);
        $result = [];
        $this->obj_id = null;
        foreach ($rst as $rr) {
            $this->loadFromID($rr["id"]);
            break;
        }
    }

    public function SelectFromKeyValue($key, $value) {
        return $this->selectFromArray([$key => $value]);
    }

    public function SelectWithProperty($property, $dyna = false) {
        if ($dyna) {
            $rst = $this->mainDB->query("select id from " . $this->getCollectionName() . "_kv" . " where (_key=:kdata)", ["kdata" => $property]);
            $result = [];
            $cc = $this->getClassName();

            foreach ($rst as $rr) {
                $c = new $cc();
                $c->collection = $this->collection;
                /* @var $c DBdata */
                $c->obj_id = $rr["id"];
                //$c->loadFromID($rr["id"]);
                $result[] = $c;
            }
            return $result;
        } else {
            $ids = $this->mainDB->query(" select * from " . $this->getCollectionName() . "_kv where id in (select distinct id from " . $this->getCollectionName() . "_kv" . " where (_key=:kdata)) order by id", ["kdata" => $property]);
            $rst = [];
            $c = $this->getClassName();
            $lastid = -1;
            foreach ($ids as $id) {
                if ($lastid != $id["id"]) {
                    $lastid = $id["id"];
                    $cc = new $c();
                    /* @var $cc KVObject */
                    $cc->collection = $this->collection;
                    $cc->obj_id = $id["id"];
                    $rst[$lastid] = $cc;
                }
                $rst[$lastid]->dataObject[$id["_key"]] = KV_internalUnserialize($id["_val"]);
            }
            return $rst;
        }
    }

    public function setId($aid) {
        $this->obj_id = $aid;
        $this->dataObject = [];
    }

    public function load() {

        $this->loadFromID($this->obj_id);
    }

    public function loadFromID($aid) {
        $this->obj_id = $aid;
        $this->dataObject = [];
        $qr = $this->mainDB->query("select * from " . $this->getCollectionName() . "_kv" . " where id = :id", ["id" => $aid]);
        foreach ($qr as $row)
            $this->dataObject[$row["_key"]] = KV_internalUnserialize($row["_val"]);
    }

    public function loadFromIDs($ids, $fields = []) {
        $rst = [];
        $lid = -999;
        $w = '';
        if ($fields != []) {
            $w = " and _key in ('" . implode("','", $fields) . "') ";
        }
        $qr = $this->mainDB->query("select * from " . $this->getCollectionName() . "_kv" . " where id in (" . implode(",", $ids) . ") $w order by id");

        foreach ($qr as $row) {
            if ($lid != $row["id"]) {
                $lid = $row["id"];
                $rst[$lid] = new KVObject($this->getCollectionName());
                $rst[$lid]->obj_id = $lid;
            }
            $rst[$lid]->dataObject[$row["_key"]] = KV_internalUnserialize($row["_val"]);
        }
        return $rst;
    }

    public function __get($name) {
        if (!isset($this->dataObject[$name])) {
            return "";
            // $rst = $this->getDynamic($name);
        }
        return $this->dataObject[$name];
    }

    public function __set($name, $value) {

        if ($this->obj_id != null) {
            if ($name != "id") {
                $this->dataObject[$name] = $value;
                $this->__internal_toBeUpdated[$name] = 1;
                if ($this->__internal_autosave) {
                    $this->endUpdate();
                }
            }
        }
    }

    function removeProperty($prop) {

        unset($this->dataObject[$prop]);
        $sql = "delete from  " . $this->getCollectionName() . "_kv" . " where (id =:objid) and (_key =:key)";
        $param = ["objid" => $this->obj_id, "key" => $prop];
        $this->SQLHisto[] = ["SQL" => $sql, "param" => $param];
        $this->mainDB->query($sql, $param);
    }

    static function printQuery($queryRst, $returnHTML = false) {
        if ($returnHTML)
            return printQuery($queryRst, true);
        else
            echo printQuery($queryRst, true);
    }

    public function sqlFromCriteriaOLD($arr, $mod = []) {
        $doCount = true;
        $limit = "";

        if ((isset($mod["limit"])) and ( $mod["limit"] != [])) {
            $limit = " limit " . implode(", ", $mod["limit"]);
        }

        $sort = "";
        if ((isset($mod["sort"]))) {
            $sort = $mod["sort"];
            $dir = "ASC";
            if ($sort[0] == "-") {
                $sort = substr($sort, 1);
                $dir = "DESC";
            }
            $sort = "order by if (_key='$sort',0,1), cast(_val as " . DECIMAL_VAL . " ) $dir "; // , _val $dir ";
        }


        if ($arr == []) {
            return "select id from (select distinct id from " . $this->getCollectionName() . "_kv $sort $limit) as tkv ";
        }
        $Where = [];
        foreach ($arr as $w => $ww) {
            $w = str_replace("@", "", $w);
            if ($w == "*") {
                $doCount = false;
            }
            $Where[$w][] = $ww;
        }
        $STRWhere = "";
        foreach ($Where as $kw => $vwc) {

            if ($kw == "*") {


                $str = "( ";
            } else {

                $kw = getMainDB()->quotestr($kw);

                $str = "( _key = $kw and ";
            }foreach ($vwc as $vw) {
                //"_val "
                if (is_array($vw)) {
                    $vw = "|@" . implode(",", $vw);
                }
                $_val = "_val ";
                $op = "=";
                if (in_array(substr($vw, 0, 2), ["<@", ">@", "%@", "|@"])) {
                    $op = substr($vw, 0, 1);
                    $vw = substr($vw, 2);
                    if ($op == "%") {
                        $op = "like";
                        $vw = getMainDB()->quotestr($vw);
                    }



                    if ($op == "|") {
                        $op = "in";
                        $vw = explode(",", $vw);
                        foreach ($vw as $kvw => $vvw) {
                            $vw[$kvw] = getMainDB()->quotestr($vvw);
                        }
                        $vw = "(" . implode(",", $vw) . ")";
                    }
                } else
                if (in_array(substr($vw, 0, 3), ["<=@", ">=@", "!=@", "!%@"])) {
                    $op = substr($vw, 0, 2);
                    $vw = substr($vw, 3);

                    if ($op == "!%") {
                        $op = "not like";
                        $vw = getMainDB()->quotestr($vw);
                    }
                    if ($op == "!=") {
                        $vw = getMainDB()->quotestr($vw);
                    }
                }
                if ($op == "=") {

                    $vw = getMainDB()->quotestr($vw);
                }


                if (in_array($op, ["<=", ">=", ">", "<"])) {
                    $_val = "CAST( REPLACE(_val, ',' , '.' )  as " . DECIMAL_VAL . " )";
                }

                $str .= $_val;
                $str .= "$op $vw and ";
            }
            $STRWhere .= ($STRWhere == "" ? "" : " or \n " ) . $str . " 1=1) ";
        }
        $STRWhere .= " group by 1 ";
        if ($doCount) {
            $STRWhere .= "having count(*) =" . sizeof($Where);
        }
        return "select id from (select id from " . $this->getCollectionName() . "_kv where \n" . $STRWhere . " $sort $limit ) as ttkv  ";
    }

    public function deleteIds($ids) {

        getMainDB()->query("delete from " . $this->getCollectionName() . "_kv where id in (" .
                implode(",", $ids) . ")");
    }

    public function findIds($arr = [], $mod = []) {
        if (!isset($mod["limit"])) {
            $mod["limit"] = [];
        }
        $sql = $this->sqlFromCriteria($arr, $mod);

        $rst = [];
        foreach (getMainDB()->query($sql) as $r)
            $rst[] = $r["id"];
        return $rst;
    }

    /**
     * 
     * @param type $arr
     * @param type $fields
     * @param type $get_count_only
     * @return KVObject[]
     */
    public function find($arr, $fields = [], $get_count_only = false, $mod = []) {
        if (!isset($mod["limit"])) {
            $mod["limit"] = [];
        }
        $sql = $this->sqlFromCriteria($arr, $mod);
        $sort = "";
        if ((isset($mod["sort"]))) {
            $sort = $mod["sort"];
            $dir = "ASC";
            if ($sort[0] == "-") {
                $sort = substr($sort, 1);
                $dir = "DESC";
            }
            $sort = "order by if (_key='$sort',0,1), cast(_val as " . DECIMAL_VAL . ") $dir "; // , _val $dir ";
        }
        if ($get_count_only) {

            $sql = "Select count(*) from ($sql ) as ttt";
            $rst = getMainDB()->query($sql);
            $rr = reset($rst);
            return reset($rr);
        }
        if ($fields == []) {

            $sql = "Select * from " . $this->getCollectionName() . "_kv where id in ( $sql) $sort";
//            if (isset($mod["sort"])) {
//                $sort=$mod["sort"];
//             $sql .= "order by if (_key='$sort',0,1), cast(_val as DECIMAL(14,4) ) , _val ";   
//            }
            $this->SQLHisto[] = $sql;
            $rst = getMainDB()->query($sql);
        } else {

            foreach ($fields as $kfield => $vfield) {
                $fields[$kfield] = getMainDB()->quotestr($vfield);
            }
            $sql = " select id, _key ,  _val from  " . $this->getCollectionName() . "_kv where _key in (" . implode(",", $fields) . ") and id in ( $sql) $sort ";



//            if (isset($mod["sort"])) {
//                $sort=$mod["sort"];
//             $sql .= "order by if (_key='$sort',0,1), cast(_val as DECIMAL(14,4) ) , _val ";   
//            }

            $this->SQLHisto[] = $sql;
            $rst = getMainDB()->query($sql);
        }



        $c = $this->getClassName();
        $result = [];
        foreach ($rst as $idkv) {
            if (!isset($result[$idkv["id"]])) {
                $cc = new $c();
                $cc->collection = $this->collection;
                $cc->obj_id = $idkv["id"];
                $result[$idkv["id"]] = $cc;
            }
            $result[$idkv["id"]]->dataObject[$idkv["_key"]] = KV_internalUnserialize($idkv["_val"]);
        }
        return $result;
    }

    public function count($find = null) {
        if ($find == null) {
            $sql = "SELECT count(DISTINCT id) FROM " . $this->getCollectionName() . "_kv";
            $rst = getMainDB()->query($sql);
            $rr = reset($rst);
            return reset($rr);
        }
        return $this->find($find, [], true);
    }

    public function sqlFromCriteria($filter, $mod = []) {
        return $this->sqlFromCriteriaOLD($filter, $mod);
    }

    public function sqlFromCriteriaNEW($filter, $mod = []) {

        $fs = ["*" => 0];
        if (isset($mod["sort"])) {
            $sort = $mod["sort"];
            $dir = "ASC";
            if ($sort[0] == "-") {
                $sort = substr($sort, 1);
            }
            $fs[$sort] = 1;
        }
        $i = 2;
        $comm = "";
        $where = [];
        foreach ($filter as $k => $v) {
            $k = str_replace("@", "", $k);
            if (!isset($fs[$k])) {
                $fs[$k] = $i;
            }
            $i++;
            $Where[$k][] = $v;
        }
        $STRWhere = "";
        foreach ($Where as $kw => $vwc) {
            $kw = "t" . $fs[$kw] . "._val ";
            $str = "(";
            foreach ($vwc as $vw) {
                if (is_array($vw)) {
                    $vw = "|@" . implode(",", $vw);
                }
                $op = "=";
                if (in_array(substr($vw, 0, 2), ["<@", ">@", "%@", "|@"])) {
                    $op = substr($vw, 0, 1);
                    $vw = substr($vw, 2);
                    if ($op == "%") {
                        $op = "like";
                        $vw = getMainDB()->quotestr($vw);
                    }



                    if ($op == "|") {
                        $op = "in";
                        $vw = explode(",", $vw);
                        foreach ($vw as $kvw => $vvw) {
                            $vw[$kvw] = getMainDB()->quotestr($vvw);
                        }
                        $vw = "(" . implode(",", $vw) . ")";
                    }
                } else
                if (in_array(substr($vw, 0, 3), ["<=@", ">=@", "!|@", "!=@", "!%@"])) {
                    $op = substr($vw, 0, 2);
                    $vw = substr($vw, 3);

                    if ($op == "!|") {
                        $op = "not in";
                        $vw = explode(",", $vw);
                        foreach ($vw as $kvw => $vvw) {
                            $vw[$kvw] = getMainDB()->quotestr($vvw);
                        }
                        $vw = "(" . implode(",", $vw) . ")";
                    }

                    if ($op == "!%") {
                        $op = "not like";
                        $vw = getMainDB()->quotestr($vw);
                    }
                    if ($op == "!=") {
                        $vw = getMainDB()->quotestr($vw);
                    }
                }
                if ($op == "=") {

                    $vw = getMainDB()->quotestr($vw);
                }


                if (in_array($op, ["<=", ">=", ">", "<"])) {
                    $kw = "CAST($kw as " . DECIMAL_VAL . ")";
                }



                $str .= $kw;
                $str .= "$op $vw and ";
            }
            $STRWhere .= ($STRWhere == "" ? "where " : " and \n " ) . $str . " 1=1) ";
        }
        /*  foreach ($fields as $k){
          $fs[str_replace("@", "",$k)]=$i;
          $i++;
          }
         */



        $tb = " " . $this->getCollectionName() . "_kv ";
        //$sql = "select distinct  t0.id , t1._val as `CashPrice` "
        $sql = " from $tb as t0 ";
        $select = "select distinct  t0.id ";
        foreach ($fs as $k => $v) {
            if ($k == "*")
                continue;
            $k = getMainDB()->quotestr($k, true);
            $sql .= " inner join "
                    . "(select * from $tb where _key='$k') as t$v "
                    . "on "
                    . "t0.id = t$v.id and t$v._key='$k' ";
            $select .= ", t$v._val as `$k` ";
        }
        $sort = "order by 1 ";
        if ((isset($mod["sort"]))) {
            $sort = $mod["sort"];
            $dir = "ASC";
            if ($sort[0] == "-") {
                $sort = substr($sort, 1);
                $dir = "DESC";
            }
            if ($dir == "ASC") {
                $sort = "order by cast($sort as " . DECIMAL_VAL . ") , $sort ";
            } else {


                $sort = "order by cast($sort as " . DECIMAL_VAL . ") DESC , $sort DESC ";
            }
            // , _val $dir ";
        }


        $sql .= "  $STRWhere  $sort ";
        if ((isset($mod["limit"])) and ( $mod["limit"] != []))
            $sql .= " limit " . implode(",", $mod["limit"]);

        return "select id from (" . $select . $sql . ") as tzero ";
    }

    public function findByIndex($criteria = [], $fields = [], $sort = [], $limit = []) {

        $Where = [];

        foreach ($criteria as $w => $ww) {
            $w = str_replace("@", "", $w);
            if ($w == "*") {
                $doCount = false;
            }
            $Where[$w][] = $ww;
        }
        $STRWhere = "";
        foreach ($Where as $kw => $vwc) {
            foreach ($vwc as $vw) {
                if ($STRWhere != "")
                    $STRWhere .= " and ";
                if (is_array($vw)) {
                    $vw = "|@" . implode(",", $vw);
                }
                $op = "=";
                if (in_array(substr($vw, 0, 2), ["<@", ">@", "%@", "|@"])) {
                    $op = substr($vw, 0, 1);
                    $vw = substr($vw, 2);
                    if ($op == "%") {
                        $op = "like";
                        $vw = getMainDB()->quotestr($vw);
                    }



                    if ($op == "|") {
                        $op = "in";
                        $vw = explode(",", $vw);
                        foreach ($vw as $kvw => $vvw) {
                            $vw[$kvw] = getMainDB()->quotestr($vvw);
                        }
                        $vw = "(" . implode(",", $vw) . ")";
                    }
                } else
                if (in_array(substr($vw, 0, 3), ["<=@", ">=@", "!=@", "!%@"])) {
                    $op = substr($vw, 0, 2);
                    $vw = substr($vw, 3);

                    if ($op == "!%") {
                        $op = "not like";
                        $vw = getMainDB()->quotestr($vw);
                    }
                    if ($op == "!=") {
                        $vw = getMainDB()->quotestr($vw);
                    }
                }
                if ($op == "=") {

                    $vw = getMainDB()->quotestr($vw);
                }

                $STRWhere .= "( $kw $op $vw ) ";
            }
        }
        if ($sort != []) {
            foreach ($sort as $ksort => $vsort) {
                if ($vsort[0] == "-") {
                    $sort[$ksort] = substr($vsort, 1) . " DESC";
                }
            }
            $STRWhere .= " order by " . implode(", ", $sort);
        }
        if ($limit != []) {
            $STRWhere .= " limit " . implode(", ", $limit);
        }

        return [$STRWhere];
    }

    public function addToIndex($field, $type = "varchar(255)") {
        $indx = new KVObject("kvindexes");
        $indx->createCollection();
        $indx->loadFromArrayOrCreate(["IDX_collection" => $this->collection]);
        $indx->$field = $type;
        $this->__internal_index = $indx;
    }

    public function CreateIndexes() {
        $this->__internal_index = NULL;
        $sql = "DROP TABLE IF EXISTS kvidx_" . $this->collection;
        echo $sql."<br>";
        getMainDB()->query($sql);
        $sql = "create table kvidx_" . $this->collection . " (  `id` int(11) NOT NULL ";

        $indx = new KVObject("kvindexes");
        $indx->createCollection();
        $indx->loadFromArrayOrCreate(["IDX_collection" => $this->collection]);
        foreach ($indx->dataObject as $kf => $kt) {
            if (substr($kf, 0, 4) == "IDX_") {
                continue;
            }

            $sql .= ", `$kf` $kt NULL";
        }
        $sql .= ", PRIMARY KEY (`id`)";
        foreach ($indx->dataObject as $kf => $kt) {
            if (substr($kf, 0, 4) == "IDX_") {
                continue;
            }

            $sql .= " , INDEX `$kf` (`$kf`) ";
        }
        $sql .= ") DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        echo $sql."<br>";
        getMainDB()->query($sql);
        
        $this->updateIndex(true);
    }

    public function updateIndex($allCollection = false) {
        $this->loadIndexes();
        if ($this->__internal_index==NULL) {
            return 0 ;
        }
        $param = [];
        $sql = ["select distinct tb.id as id ", "from " . $this->collection . "_kv as tb "];
        $nb = 0;
        foreach ($this->__internal_index->dataObject as $kf => $kt) {
            $nb++;
            if (substr($kf, 0, 4) == "IDX_") {
                continue;
            }
            $sql[0] .= ", tb$nb._val as `$kf` ";
            $sql[1] .= " left join " . $this->collection . "_kv as tb$nb on (tb.id=tb$nb.id and tb$nb._key = :p$nb) ";
            $param["p$nb"] = $kf;
        }
        if ($allCollection) {
            $sql = " insert into kvidx_" . $this->collection . "(" . implode(" ", $sql) . ")";
            echo $sql."<br>"; 
            getMainDB()->query($sql, $param);
        } else {
            getMainDB()->query("delete from kvidx_" . $this->collection, " where id = " . $this->obj_id);
            $sql = " insert into kvidx_" . $this->collection . "(" . implode(" ", $sql) . " where tb.id = " . $this->obj_id . ")";
            getMainDB()->query($sql, $param);
        }
    }
    public function inIndexes($field) {
        $this->loadIndexes();
        if ($this->__internal_index == NULL) return FALSE;
        return $this->__internal_index->$field!="";
    } 
    public function loadIndexes() {
        if ($this->__internal_index == NULL) {
            if ($this->__internal_index_checked ) {
                return 0;
            }
            if ($this->collection != "kvindexes") {
                $this->__internal_index_checked = TRUE;
                $kv = new KVObject("kvindexes");
                $kv->loadFromArray(["IDX_collection" => $this->collection]);
                if ($kv->obj_id != NULL) {
                    $this->__internal_index = $kv;
                } else {
                    $this->__internal_index = NULL;
                }
            }
        }
    }

}

function kv_printObjectList($objs, $returnHTML = false) {
    return printQuery(KVObject::objectListToArray($objs), $returnHTML);
}

function kv_objectListToArray($kvObject_list) {
    return KVObject::objectListToArray($kvObject_list);
}

function kv_objectListToFastArray($kvObject_list) {
    return KVObject::objectListToFastArray($kvObject_list);
}

/**
 * 
 * @param type $collection
 * @param type $selectors
 * @param type $onlythisFields
 * @return KVObject[]
 */
function kv_find($collection, $selectors = [], $onlythisFields = [], $mod = []) {
    $kv = new KVObject($collection);
    $rst = $kv->find($selectors, $onlythisFields, false, $mod);
    $GLOBALS["SQL"] = $kv->SQLHisto;

    return $rst;
}

/**
 * 
 * @param type $collection
 * @param type $data
 * @return array
 */
function kv_find_to_array($collection, $selectors, $onlythisFields = [], $mod = []) {
    $kv = new KVObject($collection);
    return $kv->objectListToFastArray($kv->find($selectors, $onlythisFields, false, $mod));
}

/**
 * 
 * @param type $collection
 * @param type $data
 * @return \KVObject
 */
function kv_insert($collection, $data) {
    $kv = new KVObject($collection);
    $kv->insert($data);
    return $kv;
}

/**
 * 
 * @param type $collection
 * @param type $data
 * @return \KVObject
 */
function kv_getFirst($collection, $data) {
    $kv = new KVObject($collection);
    $kv->loadFromArray($data);
    return $kv;
}

/**
 * 
 * @param type $collection
 * @param type $data
 * @return \KVObject
 */
function kv_getOrCreate($collection, $data) {
    $kv = new KVObject($collection);
    $kv->createCollection();
    $kv->loadFromArrayOrCreate($data);
    return $kv;
}

/**
 * 
 * @param type $collection
 * @param type $listIds
 * @return KVObject[]
 */
function kv_loadFromIds($collection, $listIds) {
    $kv = new KVObject($collection);
    return $kv->loadFromIDs($listIds);
}

function kv_count($collection, $arr = []) {
    $kv = new KVObject($collection);
    return $kv->find($arr, [], TRUE);
}
