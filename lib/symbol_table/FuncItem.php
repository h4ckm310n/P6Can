<?php


class FuncItem
{
    private $name;
    private $var_tab;
    private $parent;

    public function __construct($name, $var_tab)
    {
        $this->name = $name;
        $this->var_tab = $var_tab;
    }

    public function __toString()
    {
        return "Func $this->name()\n$this->var_tab";
    }
}
