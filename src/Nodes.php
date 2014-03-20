<?php

namespace SimpleMustache;

abstract class MustacheNode {
    abstract function process(MustacheProcessor $visitor, MustachePartialProvider $partials);
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

    function process(MustacheProcessor $visitor, MustachePartialProvider $partials) {
        $result = $visitor->resolveName($this->name)->text();

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

    function process(MustacheProcessor $visitor, MustachePartialProvider $partials) {
        $partial = $partials->partial($this->content);
        $partial = $this->indentText($partial);
        $partial = MustacheParser::parse($partial);
        $result  = $partial->process($visitor, $partials);
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

    function process(MustacheProcessor $visitor, MustachePartialProvider $partials) {
        $result = '';

        foreach ($this->nodes as $node)
            $result .= $node->process($visitor, $partials);

        return $result;
    }
}

class MustacheNodeText extends MustacheNode {
    private $text;

    function __construct($text) {
        $this->text = $text;
    }

    function process(MustacheProcessor $visitor, MustachePartialProvider $partials) {
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

    function process(MustacheProcessor $visitor, MustachePartialProvider $partials) {
        $values = $visitor->resolveName($this->name)->toList();

        if ($this->isInverted) {
            if (!$values)
                return parent::process($visitor->extend(new MustacheValueFalsey), $partials);
            else
                return '';
        } else {
            $result = '';
            foreach ($values as $value)
                $result .= parent::process($visitor->extend($value), $partials);
            return $result;
        }
    }
}

