<?php

require "lib/static_single_assignment/PhiFunc.php";

class BasicBlock
{
    private $id;
    private $lines;
    private $predecessors;
    private $successors;
    private $phis;

    public function __construct($id, $lines)
    {
        $this->id = $id;
        $this->lines = $lines;
        $this->predecessors = [];
        $this->successors = [];
        $this->phis = [];
    }

    public function addPredecessor($predecessor)
    {
        array_push($this->predecessors, $predecessor);
    }

    public function addSuccessor($successor)
    {
        array_push($this->successors, $successor);
    }

    public function getLines()
    {
        return $this->lines;
    }

    public function getPredecessors()
    {
        return $this->predecessors;
    }

    public function getSuccessors()
    {
        return $this->successors;
    }

    public function getId()
    {
        return $this->id;
    }

    public function addPhi($v)
    {
        $this->phis[$v] = new PhiFunc($v, count($this->predecessors));
    }

    public function changePhiKey($new_v, $old_v)
    {
        $this->phis[$new_v] = $this->phis[$old_v];
        $this->phis[$new_v]->setVar($new_v);
        unset($this->phis[$old_v]);
    }

    public function changePhiVal($key, $val, $i)
    {
        $this->phis[$key]->changeParam($val, $i);
    }

    public function getPhis()
    {
        return $this->phis;
    }

    public function changeLine($i, $new_line)
    {
        $this->lines[$i] = $new_line;
    }
}
