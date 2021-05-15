<?php

require "lib/static_single_assignment/DJNode.php";
require "lib/static_single_assignment/PiggyBankNode.php";

class SSAConstructor
{
    private $scope_node;
    private $cfg;
    private $basic_blocks;
    private $dj_graph;
    private $idfs;
    private $defs;

    public function __construct($scope_node)
    {
        $this->scope_node = $scope_node;
        $this->cfg = $scope_node->getCFG();
        $this->basic_blocks = $this->cfg["basic_blocks"];
        $this->convert();
        for ($i=0; $i<count($this->basic_blocks)-2; ++$i)
        {
            $block = $this->basic_blocks[$i];
            foreach ($block->getPhis() as $phi)
                echo "$phi\n";
            foreach ($block->getLines() as $line)
                echo "$line\n";
            echo "\n";
        }
        $children = $this->scope_node->getChildren();
        foreach ($children as $child)
        {
            $ssa_con = new SSAConstructor($child);
            $this->scope_node->setChild($ssa_con->getScopeNode());
        }
    }

    private function convert()
    {
        $this->defs = $this->findDefs();
        $this->dj_graph = $this->genDJ();
        $this->idfs = [];
        $this->placePhi();
        $this->renameVars();
        $this->cfg["basic_blocks"] = $this->basic_blocks;
        $this->scope_node->setCFG($this->cfg);
    }

    private function genDJ()
    {
        // DJ-Graph

        $joins = [];
        [$nodes, $doms] = $this->iterateDoms();

        $levels = [];
        $nodes["START"]->setLevel(0);
        $visited = [];
        $stack = new SplStack();
        $stack->push("START");
        while (!$stack->isEmpty())
        {
            $i = $stack->pop();
            if (in_array($i, $visited, true))
                continue;
            $node = $nodes[$i];

            // Join edges
            $joins[$i] = [];
            $dom = $doms[$i];
            $predecessors = $this->basic_blocks[$i]->getPredecessors();
            foreach ($predecessors as $predecessor)
            {
                if ($predecessor !== $dom)
                    array_push($joins[$i], $predecessor);
            }

            $dom_children = $node->getDomChildren();
            $level = $node->getLevel();
            if (!isset($levels[$level]))
                $levels[$level] = [];
            array_push($levels[$level], $i);
            foreach ($dom_children as $child)
            {
                $nodes[$child]->setLevel($level+1);
                $stack->push($child);
            }
        }

        foreach (array_keys($joins) as $key)
        {
            $join = $joins[$key];
            $nodes[$key]->setJoins($join);
            foreach ($join as $j)
                $nodes[$j]->addJoinChild($key);
        }

        // Subtree
        for ($i=count($levels)-1; $i>=0; --$i)
        {
            $level_nodes = $levels[$i];
            foreach ($level_nodes as $j)
            {
                $node = $nodes[$j];
                $subtree = [$j];
                $dom_children = $node->getDomChildren();
                foreach ($dom_children as $child)
                {
                    $child_subtree = $nodes[$child]->getSubtree();
                    foreach ($child_subtree as $t)
                        array_push($subtree, $t);
                }
                $nodes[$j]->setSubtree($subtree);
            }
        }

        return ["nodes" => $nodes, "doms" => $doms, "joins" => $joins, "levels" => $levels];
    }

    private function iterateDoms()
    {
        // A Simple, Fast Dominance Algorithm

        $nodes = [];
        $doms = [];
        $blocks_rpo = $this->reversePostorder();

        foreach (array_keys($this->basic_blocks) as $key)
        {
            $nodes[$key] = new DJNode($key);
            $doms[$key] = null;
        }

        $doms["START"] = "START";
        $changed = true;
        while ($changed)
        {
            $changed = false;
            // Reverse postorder
            for ($i=1; $i<count($blocks_rpo); ++$i)
            {
                $block = $blocks_rpo[$i];
                $predecessors = $this->basic_blocks[$block]->getPredecessors();
                if (count($predecessors) == 1)
                {
                    $doms[$block] = $predecessors[0];
                    continue;
                }
                $new_idom = $predecessors[0];
                for ($j=1; $j<count($predecessors); ++$j)
                {
                    $p = $predecessors[$j];
                    if ($doms[$p] !== null)
                    {
                        // Intersect
                        $f1 = $p;
                        $f2 = $new_idom;
                        while ($f1 !== $f2)
                        {
                            while ($f2 === "START" || $f1 === "END" || $f1 > $f2)
                                $f1 = $doms[$f1];
                            while ($f1 === "START" || $f2 === "END" || $f2 > $f1)
                                $f2 = $doms[$f2];
                        }
                        $new_idom = $f1;
                    }
                }
                if ($doms[$block] !== $new_idom)
                {
                    $doms[$block] = $new_idom;
                    $changed = true;
                }
            }
        }

        foreach (array_keys($doms) as $key)
        {
            $dom = $doms[$key];
            if ($dom === $key)
                continue;
            $nodes[$key]->setIdom($dom);
            $nodes[$dom]->addDomChild($key);
        }

        return [$nodes, $doms];
    }

    private function reversePostorder()
    {
        // Reverse post-order of CFG

        $visited = [];
        $order = [];
        $basic_blocks = $this->basic_blocks;

        $dfsWalk = function ($block) use ($basic_blocks, &$visited, &$order, &$dfsWalk)
        {
            array_push($visited, $block);
            $successors = $basic_blocks[$block]->getSuccessors();
            foreach ($successors as $successor)
            {
                if (in_array($successor, $visited, true))
                    continue;
                $dfsWalk($successor);
            }
            array_push($order, $block);
        };

        $dfsWalk("START");
        $reverse_order = array_reverse($order);
        return $reverse_order;
    }

    private function computeIDF($defs)
    {
        // A Linear Time Algorithm for Placing Phi-Nodes

        $nodes = $this->dj_graph["nodes"];
        $piggy_bank = [];
        $piggy_bank_nodes = [];
        foreach (array_keys($this->dj_graph["levels"]) as $key)
            $piggy_bank[$key] = null;
        foreach (array_keys($nodes) as $key)
            $piggy_bank_nodes[$key] = new PiggyBankNode($key, $nodes[$key]->getLevel());
        $idf = [];
        $curr_level = count($this->dj_graph["levels"]) - 1;

        $visit = function ($x) use ($nodes, $piggy_bank_nodes, &$curr_root, &$idf, &$piggy_bank, &$insertNode, &$visit)
        {
            $node = $nodes[$x];
            $j_nodes = $node->getJoinChildren();
            $d_nodes = $node->getDomChildren();
            foreach ($j_nodes as $y)
            {
                $y_node = $piggy_bank_nodes[$y];
                if ($y_node->getLevel() <= $piggy_bank_nodes[$curr_root]->getLevel())
                {
                    if (!$y_node->isInphi())
                    {
                        $piggy_bank_nodes[$y]->setInphi(true);
                        array_push($idf, $y);
                        if (!$y_node->isAlpha())
                            $insertNode($y);
                    }
                }
            }
            foreach ($d_nodes as $y)
            {
                $y_node = $piggy_bank_nodes[$y];
                if (!$y_node->isVisited())
                {
                    $piggy_bank_nodes[$y]->setVisited(true);
                    $visit($y);
                }
            }
        };

        $insertNode = function ($x) use (&$piggy_bank, $piggy_bank_nodes)
        {
            $level = $piggy_bank_nodes[$x]->getLevel();
            $piggy_bank_nodes[$x]->setNext($piggy_bank[$level]);
            $piggy_bank[$level] = $x;
        };

        $getNode = function () use (&$piggy_bank, $piggy_bank_nodes, &$curr_level)
        {
            if ($piggy_bank[$curr_level] !== null)
            {
                $x = $piggy_bank[$curr_level];
                $piggy_bank[$curr_level] = $piggy_bank_nodes[$x]->getNext();
                return $x;
            }

            for ($i=$curr_level; $i>=1; --$i)
            {
                if ($piggy_bank[$i] !== null)
                {
                    $curr_level = $i;
                    $x = $piggy_bank[$i];
                    $piggy_bank[$i] = $piggy_bank_nodes[$x]->getNext();
                    return $x;
                }
            }

            return null;
        };

        foreach ($defs as $x)
        {
            $piggy_bank_nodes[$x]->setAlpha(true);
            $insertNode($x);
        }

        while (($x = $getNode()) !== null)
        {
            $curr_root = $x;
            $piggy_bank_nodes[$x]->setVisited(true);
            $visit($x);
        }

        return $idf;
    }

    private function findDefs()
    {
        // Basic blocks containing definition of variables (ASSIGN)
        $defs = [];

        foreach (array_keys($this->basic_blocks) as $i)
        {
            $lines = $this->basic_blocks[$i]->getLines();
            foreach ($lines as $line)
            {
                if ($line->getOp() == "ASSIGN")
                {
                    $var = $line->getResult();
                    if (!isset($defs[$var]))
                        $defs[$var] = [];
                    array_push($defs[$var], $i);
                }
            }
        }

        return $defs;
    }

    private function placePhi()
    {
        foreach (array_keys($this->defs) as $key)
        {
            $idf = $this->computeIDF($this->defs[$key]);
            foreach ($idf as $n)
            {
                $this->basic_blocks[$n]->addPhi($key);
            }
        }
    }

    private function renameVars()
    {
        // Efficiently computing static single assignment form and the control dependence graph

        $count_var = [];
        $stack_var = [];
        $basic_blocks = $this->basic_blocks;
        $dj_nodes = $this->dj_graph["nodes"];
        $vars = array_keys($this->defs);

        $use_result_ops = ["ARG", "PARAM", "ENCAPEXPR", "ECHOEXPR", "INCLUDE", "EVAL", "RETURN"];
        $use_val_ops = ["ASSIGN", "ARRLD", "ADD", "SUB", "MUL", "DIV", "MOD", "CONCAT", "EQ", "NEQ", "GT", "GE", "LT", "LE", "IF", "ELSEIF"];

        // Init
        foreach ($vars as $v)
        {
            $count_var[$v] = 0;
            $stack_var[$v] = new SplStack();
        }

        $a = new SplStack();

        $whichPred = function ($succ, $pred) use ($basic_blocks)
        {
            $predecessors = $basic_blocks[$succ]->getPredecessors();
            for ($i=0; $i<count($predecessors); ++$i)
            {
                if ($predecessors[$i] === $pred)
                    break;
            }
            return $i;
        };

        // DFS
        $search = function ($b) use (&$count_var, &$stack_var, $basic_blocks, &$dj_nodes, $vars, $use_result_ops, $use_val_ops, &$whichPred, &$search)
        {
            $stack_count = [];
            $origins = [];
            foreach ($vars as $var)
                $stack_count[$var] = 0;

            $lines = $basic_blocks[$b]->getLines();
            $phis = $basic_blocks[$b]->getPhis();
            foreach (array_keys($phis) as $pv)
            {
                array_push($origins, $pv);
                $vi = $pv."\$".(string)$count_var[$pv];
                $basic_blocks[$b]->changePhiKey($vi, $pv);
                $stack_var[$pv]->push($count_var[$pv]);
                $count_var[$pv] += 1;
                $stack_count[$pv] += 1;
            }
            foreach (array_keys($lines) as $li)
            {
                $line = $lines[$li];
                $op = $line->getOp();
                $val1 = $line->getVal1();
                $val2 = $line->getVal2();
                $result = $line->getResult();

                if (in_array($op, $use_val_ops))
                {
                    if (in_array($val1, $vars, true))
                    {
                        $vi = $val1."\$".(string)$stack_var[$val1]->top();
                        $line->setVal1($vi);
                    }

                    if (in_array($val2, $vars, true))
                    {
                        $vi = $val2."\$".(string)$stack_var[$val2]->top();
                        $line->setVal2($vi);
                    }

                    if (in_array($result, $vars, true))
                    {
                        $vi = $result."\$".(string)$count_var[$result];
                        $line->setResult($vi);
                        $stack_var[$result]->push($count_var[$result]);
                        $count_var[$result] += 1;
                        $stack_count[$result] += 1;
                    }

                    $basic_blocks[$b]->changeLine($li, $line);
                }

                elseif (in_array($op, $use_result_ops))
                {
                    if (in_array($result, $vars, true))
                    {
                        $vi = $result."\$".(string)$stack_var[$result]->top();
                        $line->setResult($vi);
                        $basic_blocks[$b]->changeLine($li, $line);
                    }
                }
            }

            $successors = $basic_blocks[$b]->getSuccessors();
            foreach ($successors as $successor)
            {
                $j = $whichPred($successor, $b);
                $phis = $basic_blocks[$successor]->getPhis();
                foreach (array_keys($phis) as $key)
                {
                    $v = $phis[$key]->getOrigin();
                    if (!in_array($b, $this->defs[$v], true) && !in_array($v, $origins, true))
                        continue;
                    $vi = $v."\$".(string)$stack_var[$v]->top();
                    $basic_blocks[$successor]->changePhiVal($key, $vi, $j);
                }
            }

            $dom_children = $dj_nodes[$b]->getDomChildren();
            foreach ($dom_children as $dom_child)
                $search($dom_child);

            // Pop stack
            foreach (array_keys($stack_count) as $var)
            {
                for ($i=0; $i<$stack_count[$var]; ++$i)
                    $stack_var[$var]->pop();
            }
        };

        $search("START");
        $this->basic_blocks = $basic_blocks;
    }

    public function getCFG()
    {
        return $this->cfg;
    }

    public function getScopeNode()
    {
        return $this->scope_node;
    }
}
