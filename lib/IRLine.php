<?php


class IRLine
{
    private $id;
    private $php_code;
    private $ln;
    private $op;
    private $val1;
    private $val2;
    private $result;

    public function __construct($id, $ln, $op, $val1, $val2, $result, $php_code)
    {
        $this->id = $id;
        $this->ln = $ln;
        $this->op = $op;
        $this->val1 = $val1;
        $this->val2 = $val2;
        $this->result = $result;
        $this->php_code = trim($php_code);
    }

    public function __toString()
    {
        $v1 = $this->val1 === null ? "" : " ".(string)$this->val1;
        $v2 = $this->val2 === null ? "" : " ".(string)$this->val2;
        $r = $this->result === null ? "" : " ".(string)$this->result;
        return $this->convert_esc("$this->op$v1$v2$r");
    }

    private function convert_esc($str)
    {
        $str = str_replace("\n", "\\n", $str);
        $str = str_replace("\t", "\\t", $str);
        $str = str_replace("\f", "\\f", $str);
        $str = str_replace("\r", "\\r", $str);
        $str = str_replace("\v", "\\v", $str);
        $str = str_replace("\0", "\\0", $str);
        return addslashes($str);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLn()
    {
        return $this->ln;
    }

    public function getOp()
    {
        return $this->op;
    }

    public function getVal1()
    {
        return $this->val1;
    }

    public function getVal2()
    {
        return $this->val2;
    }

    public function getResult()
    {
        return $this->result;
    }
    
    public function setResult($result)
    {
        $this->result = $result;
    }
    
    public function setVal1($val1)
    {
        $this->val1 = $val1;
    }
    
    public function setVal2($val2)
    {
        $this->val2 = $val2;
    }

    public function getPHPCode()
    {
        return $this->php_code;
    }
}