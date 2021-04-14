<?php

function form_body($name)
{
    return $_POST[$name];
}

function sql_select($tid)
{
    $conn = mysqli_connect("127.0.0.1", "admin", "12345", "db");
    $result = mysqli_query($conn, "SELECT * FROM Table_t WHERE tid=`$tid`;");
    mysqli_close($conn);
    return $result;
}

$tid = form_body("tid");
$r = sql_select($tid);
