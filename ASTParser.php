<?php
use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class ASTParser
{
    private $path;
    private $files;
    private $codes;
    private $asts;
    private $parser;

    function __construct($path)
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->path = $path;
        $this->files = [];
        $this->codes = [];
        $this->asts = [];
        $this->scan_files($this->path);
        foreach ($this->files as $file)
            $this->get_content($file);
        $this->parse();
    }

    private function scan_files($path)
    {
        $files = scandir($path);
        foreach ($files as $fn)
        {
            if ($fn == ".." || $fn == ".")
                continue;
            else if (is_dir($path.'/'.$fn)) {
                $this->scan_files($path.'/'.$fn);
            }
            else if (preg_match("/.*\.php$/i", $fn))
                array_push($this->files, $path.'/'.$fn);
        }
    }

    private function get_content($file)
    {
        $content = file_get_contents($file);
        array_push($this->codes, ["filename" => $file, "content" => $content]);
    }

    private function parse()
    {
        foreach ($this->codes as $code)
        {
            echo "Parsing ".$code["filename"]." to AST\n";
            $content = $code["content"];
            $nodes = $this->parser->parse($content);
            $php_lines = explode("\n", $content);
            array_push($this->asts, ["filename" => $code["filename"], "nodes" => $nodes, "php_lines" => $php_lines]);
            //$this->asts[$code["filename"]] = ["filename" => $code["filename"], "nodes" => $nodes];
        }
    }

    public function getASTs()
    {
        return $this->asts;
    }
}

