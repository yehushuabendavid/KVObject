<?php
session_start();
include_once __DIR__ . '/engine.php';

error .... to force you to change you password
define("KVA_PASS", "kvadminpassword");
define("pass", KVA_PASS);

if (isset($_GET["logout"])) {
    $_SESSION["KVadmin"] = "";
}
$tmsg = "";
if (isset($_POST["__KVadminpassKVadmin__"])) {

    $kvconf = new KVObject();
    $kvconf->createCollection("kvconf");
    $kvconf->loadFromArrayOrCreate(["mainconf" => "mainconf"]);
    if ($kvconf->lastlog == "") {
        $kvconf->lastlog = 0;
    }
    if ((time() - $kvconf->lastlog) > 2) {
        if ($_POST["__KVadminpassKVadmin__"] == pass) {
            $_SESSION["KVadmin"] = "admin";
        } else {
            $_SESSION["KVadmin"] = "";
        }
    } else {
        $tmsg = "Please Wait a bit";
    }
    $kvconf->lastlog = time();
}
if ((!isset($_SESSION["KVadmin"])) || ($_SESSION["KVadmin"] != "admin")) {
    ?>
    <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
            <script type="text/javascript" src="//netdna.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
            <link href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.3.0/css/font-awesome.min.css"
                  rel="stylesheet" type="text/css">
            <link href="//pingendo.github.io/pingendo-bootstrap/themes/default/bootstrap.css"
                  rel="stylesheet" type="text/css">
        </head>
        <body><center><br><br><br><br><br><br><hr><br><br><br>
            <form method="POST" action="?doadmin=1"><input type="password" name="__KVadminpassKVadmin__"> <button type="submit" value="OK">.OK.</button></form>
            <br><?= $tmsg ?><br><br><hr></center>
    </body></html><?php
    exit();
}

if (isset($_GET["fnc"])) {
    $fnc = $_GET["fnc"];

    if ($fnc == "kva_save_object") {
        call_function($fnc, ["data" => $_POST]);
    }

    if (strStartsWith($fnc, "kva_")) {
        call_function($fnc, $_POST);
    }
}

if (isset($_GET["collection"])) {
    $_SESSION["coll"] = $_GET["collection"];
}
function kva_simplesql() {
    getMainDB()->printSQL($_POST["sql"]);
    exit();
}
function kva_get_session() {
    jsonFlush($_SESSION);
}

function kva_save_object($data) {




    $rst["obj_id"] = $data["obj_id"];
    $obj_id = $data["obj_id"];
    unset($data["obj_id"]);
    $collection = $_SESSION["coll"];
    $o = new KVObject($collection);
    $o->loadFromID($obj_id);
    $o->update($data);
    jsonFlush($rst);
}

function kva_create_collection($collection) {
    $collection = trim($collection);
    $rst["collection"] = $_SESSION["coll"];
    if ($collection != "") {
        $o = new KVObject();
        $o->createCollection($collection);
    }
}

function kva_del_collection($collection) {

    $o = new KVObject();
    $o->deleteCollection($collection);
}

function kva_clear_collection($collection) {

    $o = new KVObject();
    $o->clearCollection($collection);
}

function kva_dosql() {

    $kv = kv_getOrCreate("kvconf", ["type" => "sql_log", "sql_uid" => md5($_POST["sql"])]);
    $rst = $kv->rst;
    if ($rst == "") {
        $rst = getMainDB()->printSQL($_POST["sql"], [], true);
        $kv->rst = $_POST["sql"] . "<hr>" . $GLOBALS["sqlerr"] . "<hr>" . $rst;
        $kv->asql = $_POST["sql"];
    }
    echo $kv->rst;
    exit();
}

function kva_add_properties($obj_id, $props) {
    error_log("Props :" . $props);
    if (trim($props) != "") {
        $collection = $_SESSION["coll"];
        $o = new KVObject($collection);
        $o->obj_id = $obj_id;
        $props = explode(" ", $props);
        foreach ($props as $prop) {
            $prop = trim($prop);
            if ($prop != "") {
                error_log("add :" . $props);
                $o->$prop = $o->$prop;
            }
        }
    }
    $rst["obj_id"] = $obj_id;
    jsonFlush($rst);
}

function kva_clone_object($obj_id) {
    $collection = $_SESSION["coll"];
    $o = new KVObject($collection);
    $o->loadFromID($obj_id);
    $o->insert($o->dataObject);
    $rst["obj_id"] = $o->obj_id;
    jsonFlush($rst);
}

function kva_new_object($collection) {

    $o = new KVObject($collection);
    $o->insert(["__create" => date("Y-m-d H-i-s")]);
    $rst["obj_id"] = $o->obj_id;

    jsonFlush($rst);
}

function kva_remove_property($obj_id, $prop) {
    $collection = $_SESSION["coll"];
    $o = new KVObject($collection);
    $o->loadFromID($obj_id);
    $o->removeProperty($prop);
    $rst["obj_id"] = $obj_id;
    jsonFlush($rst);
}

function kva_delete_object($obj_id) {
    $collection = $_SESSION["coll"];
    $o = new KVObject($collection);
    $o->delete($obj_id);
}

function kva_set_current_collection($collection) {
    $_SESSION["coll"] = $collection;
    exit();
}

function kva_get_objet($obj_id) {
    $collection = $_SESSION["coll"];
    $o = new KVObject($collection);
    $o->loadFromID($obj_id);
    $rst["obj_id"] = $obj_id;
    $rst["collection"] = $collection;
    $rst["data"] = $o->dataObject;
    jsonFlush($rst);
}

function kva_megasearch($search) {
    $sr = explode(" ", trim($search));
    foreach ($sr as $k => $s) {
        $para["*" . str_repeat("@", $k)] = "%@%$s%";
    }

    $o = new KVObject();
    $cs = $o->getCollections();
    $rst = [];
    foreach ($cs as $c) {
        $o->collection = $c;
        $cnt = $o->count($para);

        $rst["search$c"] = ($cnt == 0) ? "" : $cnt;
    }
    jsonFlush($rst);
}

function kva_search($search) {


    $collection = $_SESSION["coll"];
    $o = new KVObject($collection);
    $sr = explode(" ", trim($search));
    foreach ($sr as $k => $s) {
        if (strpos($s, "=>") !== FALSE) {
            $s = explode("=>", $s);
            $para[$s[0] . str_repeat("@", $k)] = $s[1];
        } else {
            $para["*" . str_repeat("@", $k)] = "%@%$s%";
        }
    }
//   jsonFlush(["table"=>print_r($para,true)]);
    $cnt = $o->count($para);
    $rsto = $o->findIds($para, ["limit" => [0, 100]]);


    $rst = ["table" => print_r($o->SQLHisto, true)];
    $rst["info"] = [$GLOBALS["sql"], $GLOBALS["sqlerr"]];
    if ($cnt != 0) {


        //$rsto = array_slice($rsto, 0, 100);
        $rsto = $o->loadFromIDs($rsto);
        //jsonFlush(["table"=>print_r($rsto,true)]);
        $lst = $o->objectListToFastArray($rsto, [], true);
        foreach ($lst as $k => $v) {
            $oid = $k; //$lst[$k]["obj_id"];
            $lst[$k][0] = "<span class='btn btn-xs'>$oid&nbsp;" .
                    "<span class='btn btn-xs btn-primary fa fa-pencil' onclick='objedit($oid)'></span>&nbsp;" .
                    "<span class='btn btn-xs btn-warning fa fa-copy' onclick='cloneobj($oid)'></span>&nbsp;" .
                    "<span class='btn btn-xs btn-danger fa fa-trash' onclick='delobj($oid)'></span></span>";
        }
        $rst = ["table" => $lst];
    }
    $rst["count"] = $cnt . " / " . $o->count();
    // $rst = ["table" =>"<pre>". print_r($lst,true)."</pre>"];
    // $rst = ["table" => "test"];
    jsonFlush($rst);
}

function kva_get_collection() {

    //  error_log("kv call for collection ");
    $o = new KVObject();
    $rst = [];
    foreach ($o->getCollections() as $col) {
        $o->collection = $col;
        $rst[] = ["name" => $col, "size" => $o->count()];
    }
    jsonFlush($rst);
}

function kva_get_stats($collection = "") {
    if ($collection == "") {
        if (isset($_SESSION["coll"]))
            $collection = $_SESSION["coll"];
    }
    $c = new KVObject();
    $rst["collection"] = "No found";
    $rst["count"] = "0";
    if (in_array($collection, $c->getCollections())) {
        $rst["collection"] = $collection;
        $c->collection = $collection;
        $rst["count"] = $c->count();
        //   $rst["table"] = $c->printObjectList($cs,true);
    }
    jsonFlush($rst);
}

function kva_get_current_collection() {
    if (isset($_SESSION["coll"]))
        echo $_SESSION["coll"];
    exit();
}

function ____START_PAGE() {
    
}
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
        <script type="text/javascript" src="//netdna.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
        <script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/bootbox.js/4.4.0/bootbox.min.js"></script>
        <link href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.3.0/css/font-awesome.min.css"
              rel="stylesheet" type="text/css">
        <link href="//pingendo.github.io/pingendo-bootstrap/themes/default/bootstrap.css"
              rel="stylesheet" type="text/css">
        <script>

            $(function () {
                jkva_get_collection();
                jkva_get_stats('');
                jkva_get_current_collection();
                $(document).on("click", ".collmenu", function (evt) {
                    jkva_set_current_collection(evt.target.getAttribute("coll"));
                });
            });
            setInterval(function () {
                jkva_get_collection();
            }, 5000);
            function jsontotxt(js) {
                //console.log(js)
                if (typeof (js) == "string") {
                    return js;
                }
                rst = "";
                for (var i in js) {
                    rst += "<pre>";
                    rst += JSON.stringify(js[i], undefined, 2);
                    rst += "</pre>";
                }
                return rst;
            }
            function jkva_get_session() {
                $.post("?fnc=kva_get_session", {}, function (data) {
                    console.log(data);
                });
            }
            function jkva_get_current_collection() {
                $.post("?fnc=kva_get_current_collection", {}, function (data) {
                    currentCollection = data;
                });
            }
            function jkva_clear_collection(collection) {
                bootbox.confirm("Do You want to clear all the objects of <strong>" + currentCollection + "</strong> ?",
                        function (rst) {
                            if (rst)
                                $.post("?fnc=kva_clear_collection", {collection: collection}, function (data) { });
                        });
            }
            function jkva_del_collection(collection) {
                bootbox.confirm("Do You want to clear all the objects and the collection <strong>" + currentCollection + "</strong> ?",
                        function (rst) {
                            if (rst)
                                $.post("?fnc=kva_del_collection", {collection: collection}, function (data) { });
                        });
            }
            function jkva_set_current_collection(collection) {

                $.post("?fnc=kva_set_current_collection", {collection: collection}, function (data) {
                    currentCollection = collection;
                    jkva_get_stats(currentCollection);
                    if ($("#search" + collection).text() == "") {
                        jkva_search("");
                        $("#txtsearch").val("");
                    } else {
                        jkva_search($("#megasearch").val());
                        $("#txtsearch").val($("#megasearch").val());
                    }
                });
            }
            function jkva_get_stats(collection) {
                $.post("?fnc=kva_get_stats", {collection: collection}, function (data) {

                    $(".currentCollection").text(data["collection"]);
                    $(".collcount").text(data["count"]);
//
                });
            }

            function jkva_search(search) {
                var str = '<hr><center><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i></center><hr>';
                $(".collview").html(str);
                $.post("?fnc=kva_search&collection=" + currentCollection, {search: search}, function (data) {
                    $(".collview").html(data["info"] + "<hr>" + jsontotxt(data["table"]));
                    $(".collcount").text(data["count"]);
                });
            }

            function jkva_megasearch(search) {
                $('txtsearch').val(search);
                jkva_get_collection();
                $.post("?fnc=kva_megasearch", {search: search}, function (data) {
                    for (var i in data)
                        $("#" + i).html(data[i]);
                });
            }

            function jkva_dosimplesql() {
                sql = $('#simplesql').val();
                $.post("?fnc=kva_simplesql", {sql: sql}, function (data) {
                    $("#rstsimplesql").html(data);
                });
            }

            function jkva_get_collection() {
                $.post("?fnc=kva_get_collection", {}, function (data) {
                    var d = new Date();
                    for (i in data) {
                        var col = data[i]["name"];
                        if ($("#collmenu" + col).length) {
                            //     console.log($.find("#collmenu" + col).size);
                            $("#collmenu" + col).attr("code", d.getTime());
                            $(".size" + col).html(data[i]["size"]);
                        } else {
                            $("#collectionsMenu").append(
                                    "<li class='collmenu list-group-item'  coll = '" + col + "' code='" + d.getTime() + "' id='collmenu" + col + "'>" +
                                    col + "<span class='badge megabadge progress-bar-info' id='search" + col + "'></span> <span class='badge size" + col + "'>" + data[i]["size"] + "</span></li>");
                        }
                    }
                    $(".collmenu").each(function (indx, el) {
                        if ($(el).attr("code") != d.getTime())
                            $(el).remove();
                    });
                });
            }

            function objedit(aid) {
                var str = '<i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i><span>Loading...</span>';
                $("#EditModalTitle").html(str);
                $("#editData").html(str);
                $("#EditModal").modal("show");
                $.post("?fnc=kva_get_objet&collection" + currentCollection, {obj_id: aid}, function (data) {
                    // console.log(data);
                    str = "<i class='fa fa-pencil'></i> obj_id #" + data["obj_id"] + " @ " + data["collection"];
                    //   console.log(str);
                    str += " <strong><span class='btn btn-xs btn-primary fa fa-plus-circle' onclick='addprop(" + aid + ")'> Property</span>";
                    str += " <span class='btn btn-xs btn-warning fa fa-copy'onclick='cloneobj(" + aid + ",true)'> Clone</span>";
                    str += " <span class='btn btn-xs btn-danger fa fa-trash' onclick='delobj(" + aid + ",true)'> DELETE !</span>";
                    str += "</strong>";
                    $("#EditModalTitle").html(str);
                    str = "<input name='obj_id' hidden value='" + data["obj_id"] + "'>";
                    for (var i in data["data"]) {
                        if ((typeof data["data"][i]) == "object")
                            continue;
                        str += "<br><span class='btn btn-xs btn-danger fa fa-trash' onclick='removeproperty(" + aid + ",\"" + i + "\")'></span> " + i + " <input class='form-control' name='" + i + "' type='text' value='" + data["data"][i] + "'> ";
                    }
                    $("#editData").html(str);
                });
            }
            function createCollection() {
                bootbox.prompt("<h4>Create a new collection</h4>",
                        function (str) {
                            $.post("?fnc=kva_create_collection",
                                    {collection: str},
                                    function (data) {
                                        jkva_get_collection();
                                        jkva_set_current_collection(data["coolection"]);
                                    });
                        });
            }
            function addprop(aid) {
                $("#EditModal").modal("hide");
                bootbox.prompt("<h4>Add property to object #" + aid + " @ " + currentCollection + "</h4>",
                        function (str) {
                            $.post("?fnc=kva_add_properties&collection=" + currentCollection,
                                    {obj_id: aid, props: str},
                                    function (data) {

                                        setTimeout(function () {
                                            objedit(data["obj_id"]);
                                            jkva_search($('#txtsearch').val());
                                        }, 500);
                                    });
                        });
            }
            function cloneobj(aid, openafter) {
                $("#EditModal").modal("hide");
                $.post("?fnc=kva_clone_object&collection=" + currentCollection,
                        {obj_id: aid},
                        function (data) {
                            jkva_search($('#txtsearch').val());
                            if (openafter) {
                                setTimeout(function () {
                                    objedit(data["obj_id"]);
                                }, 1000);
                            }
                        });
            }
            function delobj(aid, reopen) {
                $("#EditModal").modal("hide");
                bootbox.confirm("<h4>Delete object #" + aid + " @ " + currentCollection + " ? NO UNDO POSSIBLE</h4>", function (rst) {

                    if (rst) {

                        $.post("?fnc=kva_delete_object&collection=" + currentCollection,
                                {obj_id: aid},
                                function (data) {
                                    jkva_search($('#txtsearch').val());
                                });
                    } else {
                        if (reopen == true) {
                            setTimeout(function () {
                                objedit(aid);
                            }, 1000);
                        }
                    }


                });
            }

            function removeproperty(aid, prop) {
                $("#EditModal").modal("hide");
                bootbox.confirm("<h4>Remove property: (" + prop + ") of object #" + aid + " @ " + currentCollection + " ?</h4>", function (rst) {

                    if (rst) {

                        $.post("?fnc=kva_remove_property&collection=" + currentCollection,
                                {obj_id: aid, prop: prop},
                                function (data) {
                                    setTimeout(function () {
                                        objedit(data["obj_id"]);
                                    }, 1000);
                                });
                    } else {
                        setTimeout(function () {
                            objedit(aid);
                        }, 1000);
                    }


                });
            }
            function newobject() {

                $.post("?fnc=kva_new_object&collection=" + currentCollection, {collection: currentCollection},
                        function (data) {
                            addprop(data["obj_id"], true);
                            //   jkva_search($('#txtsearch').val());

                        });
            }
            function saveObj() {

                // bootbox.alert(currentCollection);
                var arr = $(document.getElementById("editForm")).serialize()
                $.post("?fnc=kva_save_object&collection=" + currentCollection, arr, function (data) {
                    objedit(data["obj_id"]);
                    jkva_search($('#txtsearch').val());
                });
            }
            function pursqlkp(event) {
                if (event.key == "Enter") {
                    $.post("?fnc=kva_dosql", {sql: $(pursql).val()}, function (data) {
                        $(".collview").html(data);
                    });
                }
            }
        </script>
    </head>
    <style>
        .collmenu{
            cursor: pointer;
        }
        .collmenu:hover{
            background: activecaption;
        }
    </style>
    <body><div class="col-md-12">
            <h1 class="text-center">
                <i class="fa fa-fw fa-key"></i>KVObject
                <small>&nbsp;Lite Admin by <a href="http://us-major.com/">us-major</a></small>
            </h1>
        </div>
        <div class="section">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-2"><a href="?logout=1">Logout</a><br>Collections :
                        <span class="btn btn-primary btn-xs fa fa-plus" onclick="createCollection()"> Create</span>   <br>
                        <br><input type="text" id="megasearch" onchange="jkva_megasearch($('#megasearch').val())" > 
                        <span class="btn btn-primary btn-xs fa fa-search" onclick="jkva_megasearch($('#megasearch').val())"></span>
                        <span class="btn btn-success btn-xs fa fa-eraser" onclick="$('#megasearch').val('');
                                $('.megabadge').text('')"></span>
                        <br>   <br>
                        <ol class="list-group" id="collectionsMenu"></ol></div>
                    <div class="col-md-10"><div id="mainpanel"><hr><input id="pursql" style="width:100%" onkeypress="pursqlkp(event)"><br>
                            <div class="panel panel-primary">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Collection : <span class="lead currentCollection">Not selected</span> : 
                                        <span class="collcount"></span> Elements 
                                        <span class="btn btn-primary btn-sm  fa fa-new" onclick="newobject()"> Create</span>
                                        <span class="fa fa-eraser btn btn-success" onclick="jkva_clear_collection(currentCollection)"></span> 
                                        <span class="fa fa-trash-o btn btn-danger" onclick="jkva_del_collection(currentCollection)"></span> </h3>
                                </div>
                                <div class="panel-body">                                    
                                    <span class="btn btn-sm btn-primary" onclick="jkva_search($('#txtsearch').val())">Search</span>
                                    <input class="input" style="width:75%" type="text" id="txtsearch">
                                    <div class="collview" style="overflow: auto; height: 75%"></div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <div id="EditModal" class="modal fade">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>

                            <h4 class="modal-title" id="EditModalTitle"></h4>
                        </div>
                        <div class="modal-body" id="EditModalBody">

                            <form name="editForm" id="editForm">
                                <div id="editData" class="form-group"></div>
                            </form>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary" onclick="saveObj()">Save changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <input id="simplesql" style="width: 80%"><button onclick="jkva_dosimplesql()">OK</button>
            <div id="rstsimplesql"></div>
    </body>

</html>