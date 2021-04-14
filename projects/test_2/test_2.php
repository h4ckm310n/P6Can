<?php

$b = $_GET["bbb"];
$conn = mysqli_connect("", "", "", "");
$result = mysqli_query($conn, $b);
