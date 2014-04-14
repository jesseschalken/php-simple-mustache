<?php

namespace SimpleMustache;

abstract class Node {
    /**
     * @param Value $context
     * @param Partials $partials
     * @return string
     */
    abstract function process(Value $context, Partials $partials);
}

class NodeVariable extends Node {
    private $escaped;
    private $name;

    /**
     * @param string $name
     * @param bool $isEscaped
     */
    function __construct($name, $isEscaped) {
        $this->escaped = $isEscaped;
        $this->name    = $name;
    }

    function name() {
        return $this->name;
    }

    function process(Value $context, Partials $partials) {
        $result = $context->resolveName($this->name)->toString();

        return $this->escaped ? htmlspecialchars($result, ENT_COMPAT, 'UTF-8') : $result;
    }
}

final class NodePartial extends Node {
    private $name;
    private $indent;

    /**
     * @param string $name
     * @param string $indent
     */
    function __construct($name, $indent) {
        $this->name   = $name;
        $this->indent = $indent;
    }

    function process(Value $context, Partials $partials) {
        $partial = $partials->get($this->name);
        $partial = $this->indentText($partial);
        $partial = Document::parse($partial);
        return $partial->process($context, $partials);
    }

    private function indentText($text) {
        $lines = explode("\n", $text);
        foreach ($lines as $k => &$line)
            if (!($k == count($lines) - 1 && $line === ''))
                $line = "$this->indent$line";
        return join("\n", $lines);
    }
}

class Document extends Node {
    static function parse($template) {
        $parser = new Parser($template);
        return new self($parser->parse());
    }

    private $nodes;

    /**
     * @param Node[] $nodes
     */
    function __construct(array $nodes) {
        $this->nodes = $nodes;
    }

    function nodes() {
        return $this->nodes;
    }

    function process(Value $context, Partials $partials) {
        $result = '';
        foreach ($this->nodes as $node)
            $result .= $node->process($context, $partials);
        return $result;
    }
}

class NodeText extends Node {
    private $text;

    /**
     * @param string $text
     */
    function __construct($text) {
        $this->text = $text;
    }

    function process(Value $context, Partials $partials) {
        return $this->text;
    }
}

class NodeSection extends Document {
    private $name;
    private $inverted;

    /**
     * @param array $nodes
     * @param string $name
     * @param bool $inverted
     */
    function __construct(array $nodes, $name, $inverted) {
        parent::__construct($nodes);
        $this->name     = $name;
        $this->inverted = $inverted;
    }

    function process(Value $context, Partials $partials) {
        $values = $context->resolveName($this->name)->toList();

        if ($this->inverted) {
            return $values ? '' : parent::process($context, $partials);
        } else {
            $result = '';
            foreach ($values as $value)
                $result .= parent::process(new ExtendedValue($value, $context), $partials);
            return $result;
        }
    }
}

