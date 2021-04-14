<?php

function func1($a)
{
    function func2()
    {
        echo "111";
        return;
    }

    if ($a != 0)
        return 0;

    $b = $a;
    return $b;
}

$a = $_GET["a"];

while ($a < 3)
{
    echo "222\n";
    $a = $a + 1;
}

$b = func1($a);

