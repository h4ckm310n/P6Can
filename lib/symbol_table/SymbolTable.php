<?php


class SymbolTable
{
    private $table;
    private $scope;

    public function __construct($scope)
    {
        $this->scope = $scope;
        $this->table = [];
    }

    public function insert($key, $val)
    {
        $this->table[$key] = $val;
    }

    public function lookup($key)
    {
        return $this->table[$key];
    }

    public function size()
    {
        return count($this->table);
    }

    public function __toString()
    {
        $table_str = "";
        $keys = array_keys($this->table);
        for ($i=0; $i<count($this->table); ++$i)
            $table_str .= $keys[$i]." ".$this->table[$keys[$i]]."\n";
        return $table_str;
    }
}
