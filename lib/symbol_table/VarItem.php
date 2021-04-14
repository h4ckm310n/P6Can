<?php


class VarItem
{
    var $name;
    var $scope;

    public function __construct($name, $scope)
    {
        $this->name = $name;
        $this->scope = $scope;
    }

    public function __toString()
    {
        return "Var $this->name $this->scope";
    }
}