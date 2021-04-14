<?php
//shell
$a = $_GET['aaa'];
shell_exec("ping $a");

$a = md5($a);
system($a);

//eval
$b = $_POST["bbb"];
eval($b);

//database
$conn = new PDO("host=127.0.0.1;dbname=test1", "root", "admin");
$c = $_COOKIE["ccc"];
$conn->query("SELECT * FROM TTT WHERE tid=`$c`;");

//xss
$d = $_GET["ddd"];
echo $d;

//include
$e = $_POST["eee"];
include $e;

