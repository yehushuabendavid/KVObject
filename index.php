<?php

$t0 = microtime(false);

include_once 'engine.php';

$params = explode("/", urldecode($_SERVER["QUERY_STRING"]));
$apifnc = "api_" . array_shift($params);
$GLOBALS["MAIN_API_LOG"] = kv_getOrCreate("api_log", ["A_Fnc" => $apifnc]);
$GLOBALS["MAIN_API_LOG"]->CALL = microtime(true);
$GLOBALS["MAIN_API_LOG"]->CALL_nb  = $GLOBALS["MAIN_API_LOG"]->CALL_nb + 1 ;
$GLOBALS["MAIN_API_LOG"]->A_Param = $_SERVER["QUERY_STRING"];
$GLOBALS["MAIN_API_LOG"]->A_POST = $_POST;
$GLOBALS["MAIN_API_LOG"]->reponse = "";

if (!function_exists($apifnc))
    api_return(404, $_SERVER["QUERY_STRING"]);
call_function($apifnc, $params, FALSE);
$GLOBALS["MAIN_API_LOG"]->perf = microtime(true) - $GLOBALS["MAIN_API_LOG"]->CALL;


function jsonFlush_LOG($data) {
    jsonHeader();
    echo json_encode($data);
    flush();
    $GLOBALS["MAIN_API_LOG"]->reponse = $data;
    $GLOBALS["MAIN_API_LOG"]->perf = microtime(true) - $GLOBALS["MAIN_API_LOG"]->CALL;
    exit();
}

function api_version() {
    api_return(200, 1.0, "version");
}


function api_return($code, $msg, $container = "message") {
    $rr = [];
    $rr["code"] = $code;
    $rr[$container] = $msg;
    jsonFlush_LOG($rr);
}

Add your function api_foobar($p1,$p2) 
You will be able to call it http://server/?foobar/p1/p2
