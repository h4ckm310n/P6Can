<?php


class TaintNode
{
    private $predecessors;
    private $successors;
    private $name;
    private $name_with_id;
    private $line;
    private $node_type;
    private $caller_arg;
    private $sink_types;
    private $vul_level;

    public function __construct($name, $line, $node_type, $vul_level, $caller_arg=null)
    {
        $this->name = $name;
        $this->line = $line;
        $this->node_type = $node_type;
        $this->caller_arg = $caller_arg;
        $this->name_with_id = null;
        $this->vul_level = $vul_level;
        $this->sink_types = [
            "EVAL" => "Code execution",
            "INCLUDE" => "File inclusion",
            "ECHO" => "Cross site scripting",
            "shell_exec" => "Command execution",
            "mysqli_query" => "SQL injection",
        ];
        $this->predecessors = [];
        $this->successors = [];
    }

    public function addPredecessor($predecessor)
    {
        if (in_array($predecessor, $this->predecessors, true))
            return;
        array_push($this->predecessors, $predecessor);
    }

    public function addSuccessor($successor)
    {
        if (in_array($successor, $this->successors, true))
            return;
        array_push($this->successors, $successor);
    }

    public function getSuccessors()
    {
        return $this->successors;
    }

    public function getPredecessors()
    {
        return $this->predecessors;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function isSource()
    {
        //return $this->is_source;
        return $this->node_type === 1;
    }

    public function isSecuring()
    {
        //return $this->is_securing;
        return $this->node_type === 2;
    }

    public function isSink()
    {
        //return $this->is_sink;
        return $this->node_type === 3;
    }

    public function getVulLevel()
    {
        return $this->vul_level;
    }

    public function setNameWithID($name_with_id)
    {
        $this->name_with_id = $name_with_id;
    }

    public function getNameWithID()
    {
        return $this->name_with_id;
    }

    public function getSinkType()
    {
        return $this->sink_types[$this->name];
    }

    public function getCallerArg()
    {
        return $this->caller_arg;
    }
}