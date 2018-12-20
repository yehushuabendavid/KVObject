<?php

/**
 *   _____ _____ _____ _____ _____ __    __
 *  |  |  |   __|   __|   __|  |  |  |  |  |
 *  |  |  |__   |   __|   __|  |  |  |__|  |__
 *  |_____|_____|_____|__|  |_____|_____|_____| By USM
 *
 * @version 1.00
 * @author Partialy Almost All by Arnaud Celermajer 
 */

/**
 * 
 * @param String $haystack
 * @param String $needle
 * @return boolean
 */
//getQuizzModule()->GenerateDummyData();
function hours2minutes($h) {
    $str = explode(":", $h);

    if (sizeof($str) == 2) {
        if (is_numeric($str[0]) & is_numeric($str[1])) {
            $str[0] = intval($str[0]);
            $str[1] = intval($str[1]);
            return $str[0] * 60 + $str[1];
        }
    }
    return false;
}

function minutes2hours($m) {
    if (is_numeric(trim($m))) {
        return sprintf("%02d", intval($m / 60)) . ":" . sprintf("%02d", $m % 60);
    }
}

function strStartsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function now() {
    return date("Y-m-d H:i:s");
}

/**
 * 
 * @param String $haystack
 * @param String $needle
 * @return boolean
 */
function strEndsWith_be($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function sendmail($to, $from, $fromName, $subject, $messageTXT, $messageHTML) {
    $boundary = uniqid('np');
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: $fromName \r\n";
    $headers .= "To: $to \r\n";
    $headers .= "Content-Type: multipart/alternative;boundary=$boundary\r\n";
    $message = "This is a MIME encoded message.";
    $message .= "\r\n\r\n--" . $boundary . "\r\n";
    $message .= "Content-type: text/plain;charset=utf-8\r\n\r\n";
    $message .= $messageTXT;
    $message .= "\r\n\r\n--$boundary\r\n";
    $message .= "Content-type: text/html;charset=utf-8\r\n\r\n";
    $message .= $messageHTML;
    $message .= "\r\n\r\n--$boundary--";
    mail($to, $subject, $message, $headers);
}

function prelog($str) {
    echo preprint_r($str);
}

function postdata($url, $data = []) {

    $postdata = http_build_query($data);

    $opts = array('http' =>
        array(
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => $postdata
        )
    );

    $context = stream_context_create($opts);

    $result = file_get_contents($url, false, $context);
    return $result;
}

function postdataAuth($url, $Auth = "user:password", $data = []) {

    $Auth = base64_encode($Auth);
    $postdata = http_build_query($data);

    $opts = array('http' =>
        array(
            'method' => 'POST',
            'header' => "Authorization: Basic $Auth",
            'content' => $postdata
        )
    );

    $context = stream_context_create($opts);

    $result = file_get_contents($url, false, $context);
    return $result;
}

function preprint_r($str) {
    return "<pre>" . print_r($str, true) . "</pre>";
}

function get_key_val($array, $key) {
    if ((is_array($array)) && (isset($array[$key])))
        return $array[$key];
    return null;
}

function jsonFlush($rst) {
    jsonHeader();
    echo json_encode($rst);
    exit();
}

function jsonHeader() {
    header('Content-Type: application/json; charset=UTF-8');
}

function array2Option($rr) {
    $rst = [];
    foreach ($rr as $k => $v) {
        $rst[] = ["value" => $k, "content" => $v];
    }
    return $rst;
}

function removeBalise($str) {
    $balise = str_between("<", ">", $str);
    if ($balise != []) {
        foreach ($balise as $k => $b)
            $balise[$k] = "<$b>";
        $str = str_replace($balise, "", $str);
    }
    return $str;
}

function removeEntity($str) {
    $balise = str_between("&", ";", $str);
    if ($balise != []) {
        foreach ($balise as $k => $b)
            $balise[$k] = "&$b;";
        $str = str_replace($balise, "", $str);
    }
    return $str;
}

/**
 * 
 * @param string $start for exemple "<"
 * @param string $end for exemple ">" pour
 * @param string $haystack le text dans le quel tu cherches
 * @return array liste des choses trouvees.
 */
function str_between($start, $end, $haystack) {
    $offset = 0;
    $rst = [];
    while (($f1 = strpos($haystack, $start, $offset)) !== false) {
        $offset = $f1 + strlen($start);
        $f2 = strpos($haystack, $end, $offset);
        if ($f2 === false)
            $f2 = strlen($haystack);
        $rst[] = substr($haystack, $offset, $f2 - $offset);
        $offset = $f2 + strlen($end);
    }
    return $rst;
}

function get_ip_address() {
    // check for shared internet/ISP IP
    if (!empty($_SERVER['HTTP_CLIENT_IP']) && validate_ip($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }

    // check for IPs passing through proxies
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // check if multiple ips exist in var
        if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false) {
            $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($iplist as $ip) {
                if (validate_ip($ip))
                    return $ip;
            }
        } else {
            if (validate_ip($_SERVER['HTTP_X_FORWARDED_FOR']))
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED']) && validate_ip($_SERVER['HTTP_X_FORWARDED']))
        return $_SERVER['HTTP_X_FORWARDED'];
    if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && validate_ip($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
        return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && validate_ip($_SERVER['HTTP_FORWARDED_FOR']))
        return $_SERVER['HTTP_FORWARDED_FOR'];
    if (!empty($_SERVER['HTTP_FORWARDED']) && validate_ip($_SERVER['HTTP_FORWARDED']))
        return $_SERVER['HTTP_FORWARDED'];


    return $_SERVER['REMOTE_ADDR'];
}

/**
 * Ensures an ip address is both a valid IP and does not fall within
 * a private network range.
 */
function validate_ip($ip) {
    if (strtolower($ip) === 'unknown')
        return false;

    // generate ipv4 network address
    $ip = ip2long($ip);

    // if the ip is set and not equivalent to 255.255.255.255
    if ($ip !== false && $ip !== -1) {
        // make sure to get unsigned long representation of ip
        // due to discrepancies between 32 and 64 bit OSes and
        // signed numbers (ints default to signed in PHP)
        $ip = sprintf('%u', $ip);
        // do private network range checking
        if ($ip >= 0 && $ip <= 50331647)
            return false;
        if ($ip >= 167772160 && $ip <= 184549375)
            return false;
        if ($ip >= 2130706432 && $ip <= 2147483647)
            return false;
        if ($ip >= 2851995648 && $ip <= 2852061183)
            return false;
        if ($ip >= 2886729728 && $ip <= 2887778303)
            return false;
        if ($ip >= 3221225984 && $ip <= 3221226239)
            return false;
        if ($ip >= 3232235520 && $ip <= 3232301055)
            return false;
        if ($ip >= 4294967040)
            return false;
    }
    return true;
}

class CCol {

    const reset = "\033[0m";
    const bold = "\033[1m";
    const dark = "\033[2m";
    const italic = "\033[3m";
    const underline = "\033[4m";
    const blink = "\033[5m";
    const reverse = "\033[7m";
    const concealed = "\033[8m";
    const _col_default_ = "\033[39m";
    const _col_black = "\033[30m";
    const _col_red = "\033[31m";
    const _col_green = "\033[32m";
    const _col_yellow = "\033[33m";
    const _col_blue = "\033[34m";
    const _col_magenta = "\033[35m";
    const _col_cyan = "\033[36m";
    const _col_light_gray = "\033[37m";
    const _col_dark_gray = "\033[90m";
    const _col_light_red = "\033[91m";
    const _col_light_green = "\033[92m";
    const _col_light_yellow = "\033[93m";
    const _col_light_blue = "\033[94m";
    const _col_light_magenta = "\033[95m";
    const _col_light_cyan = "\033[96m";
    const _col_white = "\033[97m";
    const bg_default = "\033[49m";
    const bg_black = "\033[40m";
    const bg_red = "\033[41m";
    const bg_green = "\033[42m";
    const bg_yellow = "\033[43m";
    const bg_blue = "\033[44m";
    const bg_magenta = "\033[45m";
    const bg_cyan = "\033[46m";
    const bg_light_gray = "\033[47m";
    const bg_dark_gray = "\033[100m";
    const bg_light_red = "\033[101m";
    const bg_light_green = "\033[102m";
    const bg_light_yellow = "\033[103m";
    const bg_light_blue = "\033[104m";
    const bg_light_magenta = "\033[105m";
    const bg_light_cyan = "\033[106m";
    const bg_white = "\033[107m";

}

function xls2csv($afile) {
    include_once(__DIR__ . "/xlslib/PHPExcel.php");
    try {
        $errorimport = "";
        $xls = PHPExcel_IOFactory::load($afile);
        $ws = $xls->setActiveSheetIndex();
        $mr = $ws->getHighestRow();
        $mc = $ws->getHighestColumn();
        $mc++;
        $mc++;
        $mr++;
        $mr++;
        $rst = "";
        for ($row = 1; $row <= $mr; $row++) {
            $rst .= ($row == 1) ? "" : "\n";
            for ($col = "A"; $col != $mc; $col++) {
                $s = (($col == "A") ? "" : "\t") . str_replace(["\t", "\r\n", "\n"], [" ", " ", " "], $ws->getCell($col . $row));
//   echo $s . "\n";
                $rst .= $s;
            }
        }
        return $rst;
    } catch (Exception $exc) {
        $errorimport = "Excel file error :" . $exc->getTraceAsString();
        return "";
    }
}

function xls2array($afile, $firstlineColums = true) {
    $tmp = trim(xls2csv($afile));
    $tmp = explode("\n", $tmp);
    $rst = [];
    if ($firstlineColums) {
        $flg = explode("\t", $tmp[0]);
        foreach ($tmp as $k => $lg) {
            if ($k == 0) {
                continue;
            }
            $lg = explode("\t", $lg);
            foreach ($lg as $klg => $ff) {
                $rst[$k][$flg[$klg]] = $ff;
            }
        }
    } else {
        foreach ($tmp as $lg)
            $rst[] = explode("\t", $lg);
    }
    return $rst;
}

function xlsxFromArray($data, $filename) {

    include_once(__DIR__ . "/../xlslib/xlswriter.class.php");
    $writer = new XLSXWriter();
    $writer->writeSheet($data);
    $writer->writeToFile($filename);
}

function printQuery($queryRst, $returnHTML = false, $fieldsClasses = []) {
    if ($queryRst == [])
        $queryRst = "";
    if (!is_array($queryRst)) {
        $rst = $queryRst;
    } else {
        if (!is_array(reset($queryRst)))
            $queryRst = [$queryRst];
        $line_number = 0;
        $rst = "<table class='table table-hover table-bordered table-responsive table-striped'>";
        foreach ($queryRst as $currentRow) {
            if ($line_number == 0) {
                $rst .= "<tr>";
                foreach ($currentRow as $field_name => $field_value) {
                    $rst .= "<th><strong>$field_name</strong</th>";
                    if (!isset($fieldsClasses[$field_name])) {
                        $fieldsClasses[$field_name] = "";
                    }
                }
                $rst .= "</tr>";
            }
            $rst .= "<tr>";
            foreach ($currentRow as $field_name => $field_value) {
                $fieldClass = $fieldsClasses[$field_name];
                $rst .= "<td class='$fieldClass' >" . ((is_array($field_value)) ? printQuery($field_value, true) : $field_value) . "</td>";
            }
            $rst .= "</tr>";
            $line_number++;
        }
        $rst .= "</table>";
    }
    if ($returnHTML)
        return $rst;
    else
        echo $rst;
}

class LapsLoger {

    public $data;
    public $createTime;

    public function __construct($str = null) {
        $this->createTime = microtime(true);
        $this->data = [];
        if ($str != null)
            $this->data[] = [$this->createTime, $str];
    }

    public function add($o) {
        $this->data[] = [microtime(true), $o];
    }

    public function printLaps($lastStr = null, $toString = false) {
        return printQuery($this->getLaps($lastStr), $toString);
    }
    
    public function getLaps($lastStr = null) {
        if ($lastStr != null)
            $this->add($lastStr);
        $rst = [];
        $last = $this->createTime;
        foreach ($this->data as $t => $d) {
            $rst[$t]["begining"] = round($d[0] - $this->createTime, 4);
            $rst[$t]["last"] = round($d[0] - $last, 4);
            $rst[$t]["text"] = $d[1];
            $last = $d[0];
        }
       return $rst; 
    }

}

function redirect($url) {
    header("Location: $url");
    exit;
}

function BaseConvert($numberInput, $fromBaseInput, $toBaseInput) {
    if ($fromBaseInput == $toBaseInput)
        return $numberInput;
    $fromBase = str_split($fromBaseInput, 1);
    $toBase = str_split($toBaseInput, 1);
    $number = str_split($numberInput, 1);
    $fromLen = strlen($fromBaseInput);
    $toLen = strlen($toBaseInput);
    $numberLen = strlen($numberInput);
    $retval = '';
    if ($toBaseInput == '0123456789') {
        $retval = 0;
        for ($i = 1; $i <= $numberLen; $i++)
            $retval = bcadd($retval, bcmul(array_search($number[$i - 1], $fromBase), bcpow($fromLen, $numberLen - $i)));
        return $retval;
    }
    if ($fromBaseInput != '0123456789')
        $base10 = BaseConvert($numberInput, $fromBaseInput, '0123456789');
    else
        $base10 = $numberInput;
    if ($base10 < strlen($toBaseInput))
        return $toBase[$base10];
    while ($base10 != '0') {
        $retval = $toBase[bcmod($base10, $toLen)] . $retval;
        $base10 = bcdiv($base10, $toLen, 0);
    }
    return $retval;
}

function call_function($functionName, $params, $byParamName = True) {
    if (!function_exists($functionName))
        return NULL;

    $m = new ReflectionFunction($functionName);
    $ps = [];
    if ($byParamName) {
        foreach ($m->getParameters() as $p) {

            /* @var $p ReflectionParameter */
            if (isset($params[$p->name])) {
                $ps[] = $params[$p->name];
            } else {
                $ps[] = null;
            }
        }
    } else {
        $ps=$params;
    }
    return $m->invokeArgs($ps);
}

function lineArray($arr, $key = []) {
    $lasts = [];
    if (is_array($arr)) {
        foreach ($arr as $k => $v) {
            $tmp = $key;
            $tmp[] = $k;
            if (is_array($v)) {
                lineArray($v, $tmp);
            } else {
                $same = true;
                foreach ($tmp as $kc => $vc) {
                    if (!isset($lasts[$kc])) {
                        $same = false;
                    } else {
                        if ($lasts[$kc] != $vc) {
                            $same = false;
                        }
                    }
                    echo ($same) ? ("  " . str_repeat(" ", strlen($vc))) : "  $vc";
                }
                echo ": $v\n";
                $lasts = $tmp;
            }
        }
    } else {
        echo $arr . "\n";
    }
}

function mastermind($proposal, $code) {
    if (sizeof($code) == sizeof($proposal)) {
        $goodPlace = 0;
        $goodColor = 0;
        // print_r([$proposal,$code]);
        foreach ($proposal as $i => $v) {
            $r = array_search($v, $code);
            if ($r === false) {
                continue;
            }
            if ($i == $r) {
                $goodPlace++;
            } else {
                $goodColor++;
            }
        }
        return [$goodPlace, $goodColor];
    }
}

function dayAfter($time, $offset = 1) {
    return mktime(date("H", $time), date("i", $time), date("s", $time), date("n", $time), date("j", $time) + $offset, date("Y", $time));
}

function dayBefore($time, $offset = 1) {
    return mktime(date("H", $time), date("i", $time), date("s", $time), date("n", $time), date("j", $time) - $offset, date("Y", $time));
}

function yesterday() {
    return dayBefore(time());
}

function tomorrow() {
    return dayAfter(time());
}

function frenchTinyDate($ts) {
    $month["01"] = " Jan";
    $month["02"] = " Fev";
    $month["03"] = " Mar";
    $month["04"] = " Avr";
    $month["05"] = " Mai";
    $month["06"] = " Juin";
    $month["07"] = " Juil";
    $month["08"] = " Aout";
    $month["09"] = " Sept";
    $month["10"] = " Oct";
    $month["11"] = " Nov";
    $month["12"] = " Dec";
    return date('d', $ts) . $month[date("m", $ts)] . date(" Y", $ts);
}

function frenchDate($ts) {
    $month["01"] = " Janvier";
    $month["02"] = " Février";
    $month["03"] = " Mars";
    $month["04"] = " Avril";
    $month["05"] = " Mai";
    $month["06"] = " Juin";
    $month["07"] = " Juillet";
    $month["08"] = " Août";
    $month["09"] = " Septembre";
    $month["10"] = " Octobre";
    $month["11"] = " Novembre";
    $month["12"] = " Décembre";
    return date('d', $ts) . $month[date("m", $ts)] . date(" Y", $ts);
}

function frenchMonth($t, $articel = false) {
    $month["01"] = "de Janvier";
    $month["02"] = "de Février";
    $month["03"] = "de Mars";
    $month["04"] = "d'Avril";
    $month["05"] = "de Mai";
    $month["06"] = "de Juin";
    $month["07"] = "de Juillet";
    $month["08"] = "d'Août";
    $month["09"] = "de Septembre";
    $month["10"] = "d'Octobre";
    $month["11"] = "de Novembre";
    $month["12"] = "de Décembre";
    $m = $month[date("m", $t)];
    $m = ($articel) ? $m : trim(substr($m, 2));
    return $m;
}

function frenchLongDate($ts) {
    $days[0] = "dimanche ";
    $days[1] = "lundi ";
    $days[2] = "mardi ";
    $days[3] = "mercredi ";
    $days[4] = "jeudi ";
    $days[5] = "vendredi ";
    $days[6] = "samedi ";
    $month["01"] = " Janvier";
    $month["02"] = " Février";
    $month["03"] = " Mars";
    $month["04"] = " Avril";
    $month["05"] = " Mai";
    $month["06"] = " Juin";
    $month["07"] = " Juillet";
    $month["08"] = " Août";
    $month["09"] = " Septembre";
    $month["10"] = " Octobre";
    $month["11"] = " Novembre";
    $month["12"] = " Décembre";
    return $days[date("w", $ts)] . date('d', $ts) . $month[date("m", $ts)] . date(" Y", $ts);
}

function ln($str) {
    print_r($str);
    echo "\n";
}

function is_aint($o) {
    if (is_numeric($o)) {
        if (strpos($o, ".") == -1) {
            return true;
        }
    }
    return FALSE;
}

function lnp($str) {

    echo '<pre>';
    echo "<hr>";
    print_r($str);

    echo '</pre>';
    echo "\n";
}

function randget($arr) {
    if ($arr == []) {
        return Null;
    }
    $k = array_keys($arr);
    $k = $k[rand(0, sizeof($k) - 1)];
    return [$k => $arr[$k]];
}
function doc2html($s){
    $st = explode("\n", $s);
    $rst="";
    foreach ($st as $k=>$v){
     $v= trim($v);
     if ($v=="/**") {
         continue;
     }
     if ($v=="*/") {
         continue;
     }
     $v= str_replace("/**", "", $v);
     if (strStartsWith($v,"*")){
         $rst.="<i>".substr($v, 1)."</i><br>";
     } else {
     $rst.="<big><b>".$v."</b></big><br>";
         
     }
    }
    return $rst;
    
}