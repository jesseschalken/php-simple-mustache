<?php

namespace SimpleMustache;

abstract class Node {
    abstract function process(Context $context, Partials $partials);
}

class NodeVariable extends Node {
    private $isEscaped;
    private $name;

    function __construct($name, $isEscaped) {
        $this->isEscaped = $isEscaped;
        $this->name      = $name;
    }

    function name() {
        return $this->name;
    }

    function process(Context $context, Partials $partials) {
        $result = $context->resolveName($this->name)->text();

        return $this->isEscaped ? htmlspecialchars($result, ENT_COMPAT, 'UTF-8') : $result;
    }
}

final class NodePartial extends Node {
    private $content;
    private $indent;

    function __construct($content, $indent) {
        $this->content = $content;
        $this->indent  = $indent;
    }

    function process(Context $context, Partials $partials) {
        $partial = $partials->get($this->content);
        $partial = $this->indentText($partial);
        $partial = Document::parse($partial);
        $result  = $partial->process($context, $partials);
        return $result;
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

    function process(Context $context, Partials $partials) {
        $result = '';

        foreach ($this->nodes as $node)
            $result .= $node->process($context, $partials);

        return $result;
    }
}

class NodeText extends Node {
    private $text;

    function __construct($text) {
        $this->text = $text;
    }

    function process(Context $context, Partials $partials) {
        return $this->text;
    }
}

class NodeSection extends Document {
    private $isInverted;

    function __construct(array $nodes, $name, $isInverted) {
        parent::__construct($nodes);
        $this->name       = $name;
        $this->isInverted = $isInverted;
    }

    function process(Context $context, Partials $partials) {
        $values = $context->resolveName($this->name)->toList();

        if ($this->isInverted) {
            if (!$values)
                return parent::process($context->extend(new ValueFalse), $partials);
            else
                return '';
        } else {
            $result = '';
            foreach ($values as $value)
                $result .= parent::process($context->extend($value), $partials);
            return $result;
        }
    }
}

