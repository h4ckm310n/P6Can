<?php

/*
 * Definition:
 * -----------------------
 * Source:
 * $_GET, $_POST, $_REQUEST, $_COOKIE --> SRC
 *
 * -----------------------
 * Process:
 * = --> assign
 * md5() --> repair
 *
 * -----------------------
 * Sink:
 * system(), shell_exec() --> cmd
 * eval() --> eval
 * query --> sql
 * include, require --> inc  Expr_Include
 * echo --> echo  Stmt_Echo
 */


use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
require "lib/IRLine.php";


class IRGenerator
{
    private $ast;
    private $php_lines;
    private $tacs;
    private $lab_tab;
    private $tmp_tab;
    private $var_stack;

    function __construct($ast)
    {
        $this->ast = $ast;
        $this->php_lines = $ast["php_lines"];
        $this->tacs = [];
        $this->tmp_tab = new SymbolTable($ast["filename"]);
        $this->lab_tab = new SymbolTable($ast["filename"]);
        $this->var_stack = new SplStack();
        $this->generate();
        /*foreach ($this->tacs as $tac)
            echo "$tac\n";*/
    }

    public function getTacs()
    {
        return $this->tacs;
    }

    private function newLab($lab)
    {
        $this->lab_tab->insert($lab, $lab);
    }
    
    private function newTmp($tmp)
    {
        $this->tmp_tab->insert($tmp, $tmp);
    }

    private function newLine($ln, $op, $val1, $val2, $result)
    {
        $id = count($this->tacs) + 1;
        $line = new IRLine($id, $ln, $op, $val1, $val2, $result, $this->php_lines[$ln-1]);
        array_push($this->tacs, $line);
    }

    private function generate()
    {
        $nodes = $this->ast["nodes"];
        foreach ($nodes as $node)
            $this->parse($node);
    }

    private function parse($node)
    {
        if ($node instanceof Scalar)
            $this->parseScalar($node);
        else if ($node instanceof Stmt)
            $this->parseStmt($node);
        else if ($node instanceof Expr)
            $this->parseExpr($node);
    }

    private function parseScalar($node)
    {
        if ($node instanceof Scalar\Encapsed)
            $this->parseEncapsed($node);
        else if ($node instanceof Scalar\LNumber || $node instanceof Scalar\DNumber
            || $node instanceof Scalar\String_ || $node instanceof Scalar\EncapsedStringPart)
        {
            $ln = $node->getLine();
            $result = "\$_t".(string)$this->tmp_tab->size();
            $this->newTmp($result);
            $this->newLine($ln, "SCALAR", $node->value, null, $result);
            $this->var_stack->push($result);
        }
        else if ($node instanceof Scalar\MagicConst)
        {
            $ln = $node->getLine();
            $result = "\$_t".(string)$this->tmp_tab->size();
            $val1 = str_replace("Scalar_MagicConst_", "", $node->getType());
            $this->newTmp($result);
            $this->newLine($ln, "MAGICCONST", $val1, null, $result);
            $this->var_stack->push($result);
        }
    }

    private function parseEncapsed($expr)
    {
        /*
         * "mkdir $a"
         * SCALAR mkdir  $_t1
         * ENCAPEXPR $_t1
         * ENCAPEXPR a
         * ENCAPSED 2 $_t2
         */

        $ln = $expr->getLine();
        $part_ids = [];
        for ($i=0; $i<count($expr->parts); ++$i)
        {
            $part = $expr->parts[$i];
            $this->parse($part);
            array_push($part_ids, $this->var_stack->pop());
        }
        foreach ($part_ids as $part)
            $this->newLine($ln, "ENCAPEXPR", null, null, $part);

        $result = "\$_t".(string)$this->tmp_tab->size();
        $this->newTmp($result);
        $this->newLine($ln, "ENCAPSED", count($expr->parts), null, $result);
        $this->var_stack->push($result);
    }

    private function parseStmt($node)
    {
        if ($node instanceof Stmt\Expression)
            $this->parseExpr($node->expr);
        else if ($node instanceof Stmt\Function_)
            $this->parseFuncDef($node);
        else if ($node instanceof Stmt\Return_)
            $this->parseFuncReturn($node);
        else if ($node instanceof Stmt\If_)
            $this->parseIf($node);
        else if ($node instanceof Stmt\While_ || $node instanceof Stmt\For_ || $node instanceof Stmt\Foreach_)
            $this->parseLoop($node);
        else if ($node instanceof Stmt\Echo_)
            $this->parseEcho($node);
    }

    private function parseExpr($expr)
    {
        if ($expr instanceof Expr\Assign)
            $this->parseAssign($expr);
        else if ($expr instanceof Expr\Variable)
            $this->var_stack->push($expr->name);
        else if ($expr instanceof Expr\Array_)
            $this->var_stack->push("[]");
        else if ($expr instanceof Expr\ArrayDimFetch)
            $this->parseArrLD($expr);
        else if ($expr instanceof Expr\BinaryOp)
            $this->parseBinaryOp($expr);
        else if ($expr instanceof Expr\FuncCall)
            $this->parseFuncCall($expr);
        else if ($expr instanceof Expr\Include_)
            $this->parseInclude($expr);
        else if ($expr instanceof Expr\Eval_)
            $this->parseEval($expr);
    }

    private function parseEval($node)
    {
        /*
         * eval($a);
         * EVAL a
         */

        $ln = $node->getLine();
        $this->parse($node->expr);
        $result = $this->var_stack->pop();
        $this->newLine($ln, "EVAL", null, null, $result);
    }

    private function parseInclude($node)
    {
        $ln = $node->getLine();
        $this->parse($node->expr);
        $result = $this->var_stack->pop();
        $this->newLine($ln, "INCLUDE", null, null, $result);
    }

    private function parseFuncDef($node)
    {
        /*
         * function a($b) { ... }
         * FUNC a
         * PARAM 0 b
         * ...
         * ENDFUNC a
         */

        $ln = $node->getLine();
        $endln = $node->getEndLine();
        $result = $node->name->name;
        $this->newLine($ln, "FUNC", null, null, $result);

        for ($i=0; $i<count($node->params); ++$i)
        {
            $param = $node->params[$i];
            $this->parse($param->var);
            $p = $this->var_stack->pop();
            $this->newLine($ln, "PARAM", $i, null, $p);
        }

        foreach ($node->stmts as $stmt)
            $this->parse($stmt);
        $this->newLine($endln, "ENDFUNC", null, null, $result);
    }

    private function parseFuncReturn($node)
    {
        /*
         * return x
         * RETURN x
         */

        $ln = $node->getLine();
        if ($node->expr !== null)
        {
            $this->parse($node->expr);
            $result = $this->var_stack->pop();
        }
        else
            $result = null;
        $this->newLine($ln, "RETURN", null, null, $result);
    }

    private function parseClass($node)
    {

    }

    private function parseIf($node)
    {
        /*
         * if (...) {...}
         * else if (...)
         * else {...}
         * IF $_t1 L1
         * ELSEIF $_t2 L2
         * ELSEIF $_t3 L3
         * ELSE L4
         * GOTO L5
         * LAB L1
         * ...
         * GOTO L5
         * LAB L2
         * ...
         * GOTO L5
         * LAB L3
         * ...
         * GOTO L5
         * LAB L4
         * ...
         * GOTO L5
         * LAB L5
         * ...
         */

        $queue = new SplQueue();

        //if
        $ln = $node->getLine();
        $this->parse($node->cond);
        $val1 = $this->var_stack->pop();
        $stmts = $node->stmts;
        $label = "\$_L".(string)$this->lab_tab->size();
        $this->newLab($label);
        $this->newLine($ln, "IF", $val1, null, $label);
        $queue->enqueue([$label, $stmts]);

        //else if
        $elseifs = $node->elseifs;
        foreach ($elseifs as $elseif)
        {
            $elseif_ln = $elseif->getLine();
            $this->parse($elseif->cond);
            $val1 = $this->var_stack->pop();
            $label = "\$_L".(string)$this->lab_tab->size();
            $this->newLab($label);
            $this->newLine($elseif_ln, "ELSEIF", $val1, null, $label);
            $queue->enqueue([$label, $elseif->stmts]);
        }

        //else
        $else = $node->else;
        if (count($else->stmts) > 0)
        {
            $else_ln = $else->getLine();
            $label = "\$_L".(string)$this->lab_tab->size();
            $this->newLab($label);
            $this->newLine($else_ln, "ELSE", null, null, $label);
            $queue->enqueue([$label, $else->stmts]);
        }

        //goto
        $goto_label = "\$_L" . (string)$this->lab_tab->size();
        $this->newLab($goto_label);
        if (count($else->stmts) == 0)
            $this->newLine($ln, "GOTO", null, null, $goto_label);
        $queue->enqueue([$goto_label, []]);

        //labels
        while (!$queue->isEmpty())
        {
            $item = $queue->dequeue();
            $label = $item[0];
            $stmts = $item[1];
            $this->newLine($ln, "LAB", null, null, $label);
            foreach ($stmts as $stmt)
                $this->parse($stmt);
            if ($label != $goto_label)
                $this->newLine($ln, "GOTO", null, null, $goto_label);
        }
    }

    private function parseLoop($node)
    {
        if ($node instanceof Stmt\While_)
        {
            $ln = $node->getLine();
            $label1 = "\$_L".(string)$this->lab_tab->size();
            $this->newLab($label1);
            $this->newLine($ln, "LAB", null, null, $label1);
            $this->parse($node->cond);
            $val1 = $this->var_stack->pop();
            $stmts = $node->stmts;
            $label2 = "\$_L".(string)$this->lab_tab->size();
            $this->newLab($label2);
            $this->newLine($ln, "IF", $val1, null, $label2);
            $label3 = "\$_L".(string)$this->lab_tab->size();
            $this->newLab($label3);
            $this->newLine($ln, "GOTO", null, null, $label3);
            $this->newLine($ln, "LAB", null, null, $label2);
            foreach ($stmts as $stmt)
                $this->parse($stmt);
            $this->newLine($ln, "GOTO", null, null, $label1);
            $this->newLine($ln, "LAB", null, null, $label3);
        }

    }

    private function parseEcho($node)
    {
        $exprs = $node->exprs;
        $ln = $node->getLine();
        foreach ($exprs as $expr)
        {
            $this->parse($expr);
            $result = $this->var_stack->pop();
            $this->newLine($ln, "ECHOEXPR", null, null, $result);
        }
        $this->newLine($ln, "ECHO", null, null, count($exprs));
    }

    private function parseFuncCall($expr)
    {
        /*
         * a = b(c, d);
         * ARG c
         * ARG d
         * CALL b 2 $_t1
         * ASSIGN $_t1 a
         */

        // args
        foreach ($expr->args as $arg)
        {
            $argln = $arg->getLine();
            $this->parse($arg->value);
            $result = $this->var_stack->pop();
            $this->newLine($argln, "ARG", null, null, $result);
        }

        $ln = $expr->getLine();
        $name = $expr->name->parts[0];
        $result = "\$_t".(string)$this->tmp_tab->size();
        $this->newTmp($result);
        $this->newLine($ln, "CALL", $name, count($expr->args), $result);
        $this->var_stack->push($result);
    }

    private function parseAssign($expr)
    {
        /*
         * a = b;
         * ASSIGN b a
         */

        $ln = $expr->getLine();
        //result
        if ($expr->var instanceof Expr\Variable)
            $result = $expr->var->name;

        //expr
        $this->parse($expr->expr);
        $val1 = $this->var_stack->pop();
        $val2 = null;

        if ($expr->var instanceof Expr\ArrayDimFetch) 
        {
            $result = "\$_t".(string)$this->tmp_tab->size();
            $this->newTmp($result);
            $this->var_stack->push($result);
        }
        $this->newLine($ln, "ASSIGN", $val1, $val2, $result);
        if ($expr->var instanceof Expr\ArrayDimFetch)
            $this->parseArrST($expr->var);
    }

    private function parseArrLD($expr)
    {
        /*
         * a = b[c];
         * ARRLD b c $_t1
         * ASSIGN $_t1 a
         */

        $ln = $expr->getLine();

        //var
        $this->parse($expr->var);
        $val1 = $this->var_stack->pop();

        //dim
        $this->parse($expr->dim);
        $val2 = $this->var_stack->pop();

        //
        $result = "\$_t".(string)$this->tmp_tab->size();
        $this->newTmp($result);
        $this->newLine($ln, "ARRLD", $val1, $val2, $result);
        $this->var_stack->push($result);
    }

    private function parseArrST($expr)
    {
        /*
         * a[b] = c;
         * ASSIGN c $_t1
         * ARRST $_t1 a b
         */

        $ln = $expr->getLine();
        $val1 = $this->var_stack->pop();

        //var
        $this->parse($expr->var);
        $val2 = $this->var_stack->pop();

        //dim
        $this->parse($expr->dim);
        $result = $this->var_stack->pop();

        //
        $this->newLine($ln, "ARRST", $val1, $val2, $result);
    }

    private function parseBinaryOp($expr)
    {
        /*
         * a = b + c * d
         * MUL c d $_t1
         * ADD b $_t1 $_t2
         * ASSIGN $_t2 a
         */

        $ln = $expr->getLine();

        //op
        $ops = [
            "Plus" => "ADD",
            "Minus" => "SUB",
            "Mul" => "MUL",
            "Div" => "DIV",
            "Mod" => "MOD",
            "Concat" => "CONCAT",
            "Equal" => "EQ",
            "NotEqual" => "NEQ",
            "Greater" => "GT",
            "GreaterOrEqual" => "GE",
            "Smaller" => "LT",
            "SmallerOrEqual" => "LE"
        ];

        //left
        $this->parse($expr->left);
        $val1 = $this->var_stack->pop();

        //right
        $this->parse($expr->right);
        $val2 = $this->var_stack->pop();

        $result = "\$_t".(string)$this->tmp_tab->size();
        $this->newTmp($result);
        $op = $ops[str_replace("Expr_BinaryOp_", "", $expr->getType())];
        $this->newLine($ln, $op, $val1, $val2, $result);
        $this->var_stack->push($result);
    }
}
