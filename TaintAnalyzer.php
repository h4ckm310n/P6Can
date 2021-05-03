<?php

require "lib/TaintNode.php";

class TaintAnalyzer
{
    private $caller;
    private $callee_name;
    private $scope_node;
    private $basic_blocks;
    private $args;
    private $tainted;
    private $tainted_entries;
    private $sinks;
    private $callee_analyzers;
    private $count_vals;
    private $return_val;

    public function __construct($args, $scope_node, &$caller=null)
    {
        $this->caller = $caller;
        $this->callee_name = "";
        $this->scope_node = $scope_node;
        $this->basic_blocks = $scope_node->getCFG()["basic_blocks"];
        $this->args = $args;
        $this->tainted = [];
        $this->tainted_entries = [];
        $this->sinks = [];
        $this->callee_analyzers = [];
        $this->count_vals = [];
        $this->return_val = ["var" => null, "tainted" => false, "vul_level" => 0];

        $this->analyzeBlock($this->basic_blocks["START"]);
    }

    public function outputResult($level)
    {
        //echo "In ".$this->callee_name.":\n";

        $visitNode = function ($node, $path) use (&$visitNode, $level)
        {
            $name = $node->getName();
            $name_with_id = $node->getNameWithID();
            $successors = $node->getSuccessors();
            array_push($path, $name_with_id === null ? $name : $name_with_id);
            //echo "$name_with_id === null ? $name : $name_with_id => ";
            if (count($successors) == 0 && !$node->isSink() && $level>0)
            {
                echo str_repeat("  ", $level)."In ".$this->callee_name.":\n";
                echo str_repeat("  ", $level)."Taint path: ".json_encode($path)."\n";
                echo str_repeat("  ", $level)."Source: ".$path[0].", in line ".$this->tainted[$path[0]]->getLine()->getLn().": ";
                echo $this->tainted[$path[0]]->getLine()->getPHPCode()."\n";
            }
            if ($node->isSink())
            {
                echo str_repeat("  ", $level)."In ".$this->callee_name.":\n";
                echo str_repeat("  ", $level)."Taint path: ".json_encode($path)."\n";
                echo str_repeat("  ", $level)."Source: ".$path[0].", in line ".$this->tainted[$path[0]]->getLine()->getLn().": ";
                echo $this->tainted[$path[0]]->getLine()->getPHPCode()."\n";
                if (isset($this->callee_analyzers[$path[0]]))
                    $this->callee_analyzers[$path[0]]->outputResult($level+1);
                echo str_repeat("  ", $level)."Sink: ".$path[count($path)-1].", in line ".$node->getLine()->getLn().": ";
                echo $node->getLine()->getPHPCode()."\n";
                if (($sink_type=$node->getSinkType()) !== null)
                {
                    echo str_repeat("  ", $level)."Vulnerability type: ".$sink_type."\n";
                    echo str_repeat("  ", $level)."Vulnerability level: ".$node->getVulLevel()."\n";
                }
                if (isset($this->callee_analyzers[$path[count($path)-1]]))
                    $this->callee_analyzers[$path[count($path)-1]]->outputResult($level+1);
            }

            foreach ($successors as $successor)
            {
                $visitNode($this->tainted[$successor], $path);
            }
        };

        foreach ($this->tainted_entries as $entry)
        {
            $visitNode($this->tainted[$entry], []);
        }
    }

    public function setCalleeName($callee_name)
    {
        $this->callee_name = $callee_name;
    }

    private function analyzeBlock($basic_block)
    {
        $phis = $basic_block->getPhis();
        foreach ($phis as $phi)
        {
            $var = $phi->getVar();
            foreach ($phi->getParams() as $param)
            {
                if ($this->isTainted($param))
                {
                    if (!isset($this->tainted[$var]))
                        $this->tainted[$var] = new TaintNode($var, $phi, 0, $this->tainted[$param]->getVulLevel());
                    $this->connectNodes($param, $var);
                }
            }
        }

        $lines = $basic_block->getLines();
        $func_args = [];
        $tainted_args = [];
        $encap_exprs = [];
        $echo_exprs = [];
        foreach ($lines as $line)
        {
            $ln = $line->getLn();
            $op = $line->getOp();
            $val1 = $line->getVal1();
            $val2 = $line->getVal2();
            $result = $line->getResult();

            if ($op === "PARAM")
            {
                if ($val1 < count($this->args))
                {
                    if ($this->args[$val1]["tainted"]) 
                    {
                        $this->tainted[$result] = new TaintNode($result, $line, 0, $this->args[$val1]["vul_level"], $this->args[$val1]["name"]);
                        array_push($this->tainted_entries, $result);
                    }
                }
            }

            elseif ($op === "ARRLD" && $this->findSource($val1))
            {
                $this->tainted[$val1."#".(string)$this->count_vals[$val1]] = new TaintNode($val1, $line, 1, 1);
                $this->tainted[$val1."#".(string)$this->count_vals[$val1]]->setNameWithID($val1."#".(string)$this->count_vals[$val1]);
                $this->tainted[$result] = new TaintNode($result, $line, 0, 1);
                array_push($this->tainted_entries, $val1."#".(string)$this->count_vals[$val1]);
                $this->connectNodes($val1."#".(string)$this->count_vals[$val1], $result);
                $this->count_vals[$val1] += 1;
            }

            elseif (($level = $this->findSecOP($op, $val1)) > 0)
            {
                if (count($tainted_args) > 0 && $level != 3)
                {
                    $this->tainted[$val1."#".(string)$this->count_vals[$val1]] = new TaintNode($val1, $line, 2, $level);
                    $this->tainted[$val1."#".(string)$this->count_vals[$val1]]->setNameWithID($val1."#".(string)$this->count_vals[$val1]);
                    foreach ($tainted_args as $arg)
                        $this->connectNodes($arg["name"], $val1."#" .(string)$this->count_vals[$val1]);
                    $this->tainted[$result] = new TaintNode($result, $line, 0, $level);
                    $this->connectNodes($val1."#".(string)$this->count_vals[$val1], $result);
                }

                $tainted_args = [];
                $func_args = [];
            }

            elseif ($this->findSink($op, $val1, $val2, $result))
            {
                if ($op === "CALL")
                {
                    $level = 3;
                    // Sink level
                    foreach ($tainted_args as $arg)
                        $level = ($arg["vul_level"] < $level) ? $arg["vul_level"] : $level;

                    if (count($tainted_args) > 0)
                    {
                        $this->tainted[$val1."#".(string)$this->count_vals[$val1]] = new TaintNode($val1, $line, 3, $level);
                        $this->tainted[$val1."#".(string)$this->count_vals[$val1]]->setNameWithID($val1."#".(string)$this->count_vals[$val1]);
                        array_push($this->sinks, $val1."#".(string)$this->count_vals[$val1]);
                        //$this->tainted[$result] = new TaintNode($result, $line, false, false);
                        foreach ($tainted_args as $arg)
                        {
                            $this->connectNodes($arg["name"], $val1."#".(string)$this->count_vals[$val1]);
                        }
                        //$this->connectNodes($val1."#".(string)$this->count_vals[$val1], $result);
                        $this->count_vals[$val1] += 1;
                    }
                    $tainted_args = [];
                    $func_args = [];
                }
                elseif ($op === "ECHO")
                {
                    $tainted_exprs = [];
                    $level = 3;
                    foreach ($echo_exprs as $expr)
                    {
                        if ($this->isTainted($expr))
                        {
                            $expr_level = $this->tainted[$expr]->getVulLevel();
                            $level = ($expr_level < $level) ? $expr_level : $level;
                            array_push($tainted_exprs, $expr);
                        }
                    }

                    if ($tainted_exprs > 0)
                    {
                        $this->tainted[$op."#".(string)$this->count_vals[$op]] = new TaintNode($op, $line, 3, $level);
                        $this->tainted[$op."#".(string)$this->count_vals[$op]]->setNameWithID($op."#".(string)$this->count_vals[$op]);
                        foreach ($tainted_exprs as $expr)
                            $this->connectNodes($expr, $op."#".(string)$this->count_vals[$op]);
                        $this->count_vals[$op] += 1;
                    }

                    $echo_exprs = [];
                }
                else
                {
                    $this->tainted[$op."#".(string)$this->count_vals[$op]] = new TaintNode($op, $line, 3, $this->tainted[$result]->getVulLevel());
                    $this->tainted[$op."#".(string)$this->count_vals[$op]]->setNameWithID($op."#".(string)$this->count_vals[$op]);
                    array_push($this->sinks, $op."#".(string)$this->count_vals[$op]);
                    $this->connectNodes($result, $op."#".(string)$this->count_vals[$op]);
                    $this->count_vals[$op] += 1;
                }
            }

            // Converted to SSA, no need
            /*elseif ($op === "SCALAR" && isset($this->tainted[$result]))
                unset($this->tainted[$result]);*/
            
            elseif ($op === "ASSIGN" && $this->isTainted($val1))
            {
                $this->tainted[$result] = new TaintNode($result, $line, 0, $this->tainted[$val1]->getVulLevel());
                $this->connectNodes($val1, $result);
            }
            elseif ($this->findBinOP($op) && ($this->isTainted($val1) || $this->isTainted($val2)))
            {
                if (isset($this->tainted[$val1]))
                {
                    $this->tainted[$result] = new TaintNode($result, $line, 0, $this->tainted[$val1]->getVulLevel());
                    $this->connectNodes($val1, $result);
                }
                if (isset($this->tainted[$val2]))
                {
                    $this->tainted[$result] = new TaintNode($result, $line, 0, $this->tainted[$val2]->getVulLevel());
                    $this->connectNodes($val2, $result);
                }
            }

            elseif ($op === "ENCAPEXPR")
                array_push($encap_exprs, $result);

            elseif ($op === "ENCAPSED")
            {
                $tainted_exprs = [];
                $level = 3;
                foreach ($encap_exprs as $expr)
                {
                    if ($this->isTainted($expr))
                    {
                        $expr_level = $this->tainted[$expr]->getVulLevel();
                        $level = ($expr_level < $level) ? $expr_level : $level;
                        array_push($tainted_exprs, $expr);
                    }
                }
                if (count($tainted_exprs) > 0)
                {
                    $this->tainted[$result] = new TaintNode($result, $line, 0, $level);
                    foreach ($tainted_exprs as $expr)
                        $this->connectNodes($expr, $result);
                }
                $encap_exprs = [];
            }
            
            elseif ($op === "ECHOEXPR")
                array_push($echo_exprs, $result);

            elseif ($op === "ARG")
            {
                $is_tainted = $this->isTainted($result);
                $level = $is_tainted ? $this->tainted[$result]->getVulLevel() : 0;
                $arg = ["name" =>$result, "tainted" => $is_tainted, "vul_level" => $level];
                array_push($func_args, $arg);
                if ($is_tainted)
                    array_push($tainted_args, $arg);
            }

            elseif ($op === "CALL")
            {
                // Find callee
                if (in_array($val1, array_keys($this->scope_node->getChildren()), true))
                    $callee = $this->scope_node->getChildren()[$val1];

                elseif ((($parent = $this->scope_node->getParent()) !== null) && in_array($val1, array_keys($parent->getChildren()), true))
                    $callee = $parent->getChildren()[$val1];
                else
                    continue;

                $level = 3;
                // Sink level
                foreach ($tainted_args as $arg)
                    $level = ($arg["vul_level"] < $level) ? $arg["vul_level"] : $level;

                if (count($tainted_args) > 0)
                {
                    if (!isset($this->count_vals[$val1]))
                        $this->count_vals[$val1] = 0;

                    $this->tainted[$val1."#".(string)$this->count_vals[$val1]] = new TaintNode($val1, $line, 3, $level);
                    $this->tainted[$val1."#".(string)$this->count_vals[$val1]]->setNameWithID($val1."#".(string)$this->count_vals[$val1]);
                    array_push($this->sinks, $val1."#".(string)$this->count_vals[$val1]);
                    foreach ($tainted_args as $arg)
                    {
                        $this->connectNodes($arg["name"], $val1."#".(string)$this->count_vals[$val1]);
                    }
                    $this->count_vals[$val1] += 1;
                }

                $taint_analyzer = new TaintAnalyzer($func_args, $callee, $this);
                $return_val = $taint_analyzer->getReturnVal();

                if ($return_val["tainted"])
                {
                    // No tainted args, source
                    if (count($tainted_args) == 0)
                    {
                        if (!isset($this->count_vals[$val1]))
                            $this->count_vals[$val1] = 0;
                        $this->tainted[$val1."#".(string)$this->count_vals[$val1]] = new TaintNode($val1, $line, 1, $return_val["vul_level"]);
                        $this->tainted[$val1."#".(string)$this->count_vals[$val1]]->setNameWithID($val1."#".(string)$this->count_vals[$val1]);
                        array_push($this->tainted_entries, $val1."#".(string)$this->count_vals[$val1]);
                        $this->count_vals[$val1] += 1;
                    }

                    $this->tainted[$result] = new TaintNode($result, $line, 0, $return_val["vul_level"]);
                    $this->connectNodes($val1."#".(string)($this->count_vals[$val1]-1), $result);
                }

                $taint_analyzer->setCalleeName($val1."#".(string)($this->count_vals[$val1]-1));
                $this->callee_analyzers[$val1."#".(string)($this->count_vals[$val1]-1)] = $taint_analyzer;
                $func_args = [];
                $tainted_args = [];
            }
            elseif ($op === "RETURN")
            {
                $is_tainted = $this->isTainted($result);
                $level = $is_tainted ? $this->tainted[$result]->getVulLevel() : 0;
                $this->return_val = ["var" => $result, "tainted" => $is_tainted, "vul_level" => $level];
            }
        }

        $successors = $basic_block->getSuccessors();
        foreach ($successors as $successor)
            $this->analyzeBlock($this->basic_blocks[$successor]);
    }

    private function findSource($val)
    {
        $sources = ["_GET", "_POST", "_REQUEST"];
        if (in_array($val, $sources, true))
        {
            if (!isset($this->count_vals[$val]))
                $this->count_vals[$val] = 0;
            return true;
        }
        return false;
    }

    private function findSink($op, $val1, $val2, $result)
    {
        $sinks = ["EVAL", "INCLUDE", "ECHO"];
        $sink_funcs = ["shell_exec", "mysqli_query"];
        if ($op === "CALL" && in_array($val1, $sink_funcs, true))
        {
            if (!isset($this->count_vals[$val1]))
                $this->count_vals[$val1] = 0;
            return true;
        }
        elseif ($op === "ECHO")
            return true;
        elseif (in_array($op, $sinks, true) && $this->isTainted($result))
        {
            if (!isset($this->count_vals[$op]))
                $this->count_vals[$op] = 0;
            return true;
        }
        return false;
    }

    private function findSecOP($op, $val)
    {
        $sec_funcs = ["md5" => 3, "sha1" => 3, "crypt" => 3,
                      "preg_match" => 2, "preg_replace" => 2, "str_replace" => 2, "htmlspecialchars" => 2, "strip_tags" => 2, "escapeshellcmd" => 2];
        if ($op === "CALL" && in_array($val, array_keys($sec_funcs), true))
        {
            if (!isset($this->count_vals[$val]))
                $this->count_vals[$val] = 0;
            return $sec_funcs[$val];
        }
        return 0;
    }

    private function findBinOP($op)
    {
        $binops = ["ADD", "SUB", "MUL", "DIV", "MOD", "CONCAT"];
        if (in_array($op, $binops))
            return true;
        return false;
    }

    private function isTainted($var)
    {
        return (isset($this->tainted[$var]) && $this->tainted[$var]->getVulLevel() != 3);
    }

    private function connectNodes($pred, $succ)
    {
        $this->tainted[$pred]->addSuccessor($succ);
        $this->tainted[$succ]->addPredecessor($pred);
    }

    public function getReturnVal()
    {
        return $this->return_val;
    }
}
