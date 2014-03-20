<?php

namespace SimpleMustache;

abstract class MustacheNode {
    abstract function process(MustacheContext $context, MustachePartials $partials);
}

class MustacheNodeVariable extends MustacheNode {
    private $isEscaped;
    private $name;

    function __construct($name, $isEscaped) {
        $this->isEscaped = $isEscaped;
        $this->name      = $name;
    }

    function name() {
        return $this->name;
    }

    function process(MustacheContext $context, MustachePartials $partials) {
        $result = $context->resolveName($this->name)->text();

        return $this->isEscaped ? htmlspecialchars($result, ENT_COMPAT) : $result;
    }
}

final class MustacheNodePartial extends MustacheNode {
    private $content;
    private $indent;

    function __construct($content, $indent) {
        $this->content = $content;
        $this->indent  = $indent;
    }

    function process(MustacheContext $context, MustachePartials $partials) {
        $partial = $partials->partial($this->content);
        $partial = $this->indentText($partial);
        $partial = MustacheParser::parse($partial);
        $result  = $partial->process($context, $partials);
        return $result;
    }

    private function indentText($text) {
        return preg_replace("/(?<=^|\r\n|\n)(?!$)/su", addcslashes($this->indent, '\\$'), $text);
    }
}

class MustacheDocument extends MustacheNode {
    private $nodes;

    /**
     * @param MustacheNode[] $nodes
     */
    function __construct(array $nodes) {
        $this->nodes = $nodes;
    }

    function nodes() {
        return $this->nodes;
    }

    function process(MustacheContext $context, MustachePartials $partials) {
        $result = '';

        foreach ($this->nodes as $node)
            $result .= $node->process($context, $partials);

        return $result;
    }
}

class MustacheNodeText extends MustacheNode {
    private $text;

    function __construct($text) {
        $this->text = $text;
    }

    function process(MustacheContext $context, MustachePartials $partials) {
        return $this->text;
    }
}

class MustacheNodeSection extends MustacheDocument {
    private $isInverted;

    function __construct(array $nodes, $name, $isInverted) {
        parent::__construct($nodes);
        $this->name       = $name;
        $this->isInverted = $isInverted;
    }

    function process(MustacheContext $context, MustachePartials $partials) {
        $values = $context->resolveName($this->name)->toList();

        if ($this->isInverted) {
            if (!$values)
                return parent::process($context->extend(new MustacheValueFalsey), $partials);
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

