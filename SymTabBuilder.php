<?php


use PhpParser\NodeTraverser;
use PhpParser\Node\Stmt;
require "lib/symbol_table/VarVisitor.php";
require "lib/symbol_table/SymbolTable.php";
require "lib/symbol_table/FuncItem.php";
require "lib/symbol_table/VarItem.php";

class SymTabBuilder
{
    private $ast;
    private $func_tab;
    private $class_tab;
    private $symtabs;

    public function __construct($ast)
    {
        $this->ast = $ast;
        $this->func_tab = new SymbolTable($this->ast["filename"]);
        $this->class_tab = new SymbolTable($this->ast["filename"]);
        $this->build();
    }

    private function build()
    {
        echo "Building symbol tables for ".$this->ast["filename"]."\n";
        $nodes = $this->ast["nodes"];
        $var_tab = $this->genVarTab($nodes, $this->ast["filename"]);
        $this->genFuncTab($nodes);
        //echo $var_tab;
        //echo $this->func_tab;
        $this->symtabs = ["var_tab" => $var_tab, "func_tab" => $this->func_tab];
    }

    private function genVarTab($nodes, $scope)
    {
        $var_tab = new SymbolTable($scope);

        // $nodes out of function/class
        $local_scope_nodes = [];
        foreach ($nodes as $node)
        {
            if (($node instanceof Stmt\Function_) || ($node instanceof Stmt\Class_) || ($node instanceof Stmt\Global_))
                continue;
            array_push($local_scope_nodes, $node);
        }
        $traverser = new NodeTraverser();
        $visitor = new VarVisitor;
        $traverser->addVisitor($visitor);
        $traverser->traverse($local_scope_nodes);
        $vars = $visitor->getVars();

        foreach ($vars as $var)
        {
            $var_tab->insert($var->name, new VarItem($var->name, $scope));
        }

        return $var_tab;
    }

    private function genFuncTab($nodes)
    {
        foreach ($nodes as $node)
        {
            if ($node instanceof Stmt\Function_)
            {
                $var_tab = $this->genVarTab($node->stmts, $node->name->name);
                $this->func_tab->insert($node->name->name, new FuncItem($node->name->name, $var_tab));
            }
        }
    }

    private function genClassTab($nodes)
    {

    }
    public function getSymTabs()
    {
        return $this->symtabs;
    }
}