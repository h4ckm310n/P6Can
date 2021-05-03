<?php


function includeFile($filename)
{
    $filename2 = str_replace("php://", "", $filename);
    include $filename2;
}

$filename = $_GET["filename"];
includeFile($filename);
