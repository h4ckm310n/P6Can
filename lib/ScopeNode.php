<?php


class ScopeNode
{
    private $parent;
    private $children;
    private $lines;
    private $name;
    private $cfg;

    public function __construct($parent, $name)
    {
        $this->parent = $parent;
        $this->children = [];
        $this->lines = [];
        $this->name = $name;
        $this->cfg = [];
    }

    public function setChild($child)
    {
        $this->children[$child->name] = $child;
    }

    public function newLine($line)
    {
        array_push($this->lines, $line);
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLines()
    {
        return $this->lines;
    }

    public function setCFG($cfg)
    {
        $this->cfg = $cfg;
    }

    public function getCFG()
    {
        return $this->cfg;
    }
}