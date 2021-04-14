<?php


class DJNode
{
    private $block;
    private $idom;
    private $joins;
    private $dom_children;
    private $join_children;
    private $level;
    private $subtree;
    private $df;

    public function __construct($block)
    {
        $this->block = $block;
        $this->idom = null;
        $this->joins = [];
        $this->dom_children = [];
        $this->join_children = [];
        $this->subtree = [];
        $this->df = null;
    }

    public function setIdom($idom)
    {
        $this->idom = $idom;
    }

    public function getIdom()
    {
        return $this->idom;
    }

    public function setJoins($joins)
    {
        $this->joins = $joins;
    }

    public function getJoins()
    {
        return $this->joins;
    }

    public function addDomChild($child)
    {
        array_push($this->dom_children, $child);
    }

    public function getDomChildren()
    {
        return $this->dom_children;
    }

    public function addJoinChild($child)
    {
        array_push($this->join_children, $child);
    }

    public function getJoinChildren()
    {
        return $this->join_children;
    }

    public function setLevel($level)
    {
        $this->level = $level;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function getBlock()
    {
        return $this->block;
    }

    public function setSubtree($subtree)
    {
        $this->subtree = $subtree;
    }

    public function getSubtree()
    {
        return $this->subtree;
    }

    public function setDF($df)
    {
        $this->df = $df;
    }

    public function getDF()
    {
        return $this->df;
    }
}