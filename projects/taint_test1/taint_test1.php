<?php

$ip = $_GET["ip"];
$version = $_GET["version"];
if ($version == 4)
    $command = "ping ".$ip;
else
    $command = "ping6 ".$ip;
shell_exec("$command -c 4");
