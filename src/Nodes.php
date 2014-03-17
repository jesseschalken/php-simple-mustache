<?php

namespace SimpleMustache;

use Exception;

abstract class MustacheNode {
    abstract function process(MustacheProcessor $visitor);
}

class MustacheNodeVariable extends MustacheNode {
    /** @var MustacheParsedTag */
    private $tag;
    private $isEscaped;

    function __construct(MustacheParsedTag $tag, $isEscaped) {
        $this->tag       = $tag;
        $this->isEscaped = $isEscaped;
    }

    function name() {
        return $this->tag->content();
    }

    function process(MustacheProcessor $visitor) {
        if ($this->isEscaped)
            return $visitor->visitVariableEscaped($this);
        else
            return $visitor->visitVariableUnEscaped($this);
    }
}

final class MustacheNodePartial extends MustacheNode {
    /** @var MustacheParsedTag */
    private $tag;

    function __construct(MustacheParsedTag $tag) {
        $this->tag = $tag;
    }

    function process(MustacheProcessor $visitor) {
        return $visitor->visitPartial($this);
    }

    function name() {
        return $this->tag->content();
    }

    function indent() {
        return $this->tag->indent();
    }
}

class MustacheDocument extends MustacheNode {
    static function parse(MustacheTokenStream $parser) {
        $nodes = array();

        while ($parser->hasMore())
            foreach ($parser->readOne()->toNodes($parser) as $node)
                $nodes[] = $node;

        return new self($nodes);
    }

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

    function process(MustacheProcessor $visitor) {
        $result = '';

        foreach ($this->nodes as $node)
            $result .= $node->process($visitor);

        return $result;
    }
}

class MustacheNodeText extends MustacheNode {
    private $text;

    function __construct($text) {
        $this->text = $text;
    }

    function process(MustacheProcessor $visitor) {
        return $visitor->visitText($this);
    }

    function text() {
        return $this->text;
    }
}

class MustacheTokenStream {
    private $tokens;
    private $index = 0;

    static function parse(MustacheParser $parser) {
        $tokens = array();

        while ($parser->textMatches('.*' . $parser->openTagRegex()))
            foreach (MustacheParsedTag::parse($parser) as $token)
                $tokens[] = $token;

        $tokens[] = new MustacheTokenText($parser->scanText('.*$'));

        return new self($tokens);
    }

    /**
     * @param MustacheToken[] $tokens
     */
    function __construct(array $tokens) {
        $this->tokens = $tokens;
    }

    function readOne() {
        if (isset($this->tokens[$this->index]))
            return $this->tokens[$this->index++];
        else
            throw new Exception("No more tokens");
    }

    function hasMore() {
        return isset($this->tokens[$this->index]);
    }
}

abstract class MustacheToken {
    /**
     * @param MustacheTokenStream $tokens
     * @return MustacheNode[]
     */
    abstract function toNodes(MustacheTokenStream $tokens);
}

class MustacheTokenText extends MustacheToken {
    private $text;

    /**
     * @param string $text
     */
    function __construct($text) {
        $this->text = $text;
    }

    function toNodes(MustacheTokenStream $tokens) {
        return array(new MustacheNodeText($this->text));
    }
}

final class MustacheParsedTag extends MustacheToken {
    private $spaceBefore, $spaceAfter;
    private $openTag, $closeTag;
    private $sigil, $closeSigil;
    private $paddingBefore, $paddingAfter;
    private $content;
    private $isStandalone;

    /**
     * @param MustacheParser $parser
     * @return MustacheToken[]
     */
    static function parse(MustacheParser $parser) {
        $self = new self;

        $lineBoundary = $parser->lineBoundaryRegex();
        $openTag      = $parser->openTagRegex();
        $textBefore   = $parser->scanText(".*?(?=\\s*$openTag)(\\s*($lineBoundary))?");

        $self->isStandalone  = $parser->textMatches("(?<=$lineBoundary)");
        $self->spaceBefore   = $parser->scanText("\\s*");
        $self->openTag       = $parser->scanText($parser->openTagRegex());
        $self->sigil         = $parser->scanText("(#|\\^|\\/|\\<|\\>|\\=|\\!|&|\\{)?");
        $self->paddingBefore = $parser->scanText("\\s*");
        $self->content       = $parser->scanText($self->contentRegex($parser));
        $self->paddingAfter  = $parser->scanText("\\s*");
        $self->closeSigil    = $parser->scanText($self->closeSigilRegex($parser));
        $self->closeTag      = $parser->scanText($parser->closeTagRegex());

        $self->isStandalone = $self->isStandalone &&
                              $self->sigil != '{' &&
                              $self->sigil != '&' &&
                              $self->sigil != '' &&
                              $parser->textMatches("\\s*?($lineBoundary)");

        if ($self->isStandalone) {
            $self->spaceAfter = $parser->scanText("\\s*?($lineBoundary)");
        } else {
            $textBefore .= $self->spaceBefore;
            $self->spaceBefore = '';
            $self->spaceAfter  = '';
        }

        if ($self->sigil == '=') {
            $content = new StringScanner($self->content);

            $openTag = $content->scanText("^\\S+");
            $content->scanText("\\s+");
            $closeTag = $content->scanText("\\S+$");

            $parser->setDelimiters($openTag, $closeTag);
        }

        return array(new MustacheTokenText($textBefore), $self);
    }

    private function __construct() {
    }

    final function toNodes(MustacheTokenStream $parser) {
        switch ($this->sigil) {
            case '#':
                return array(MustacheNodeSection::parse2($this, $parser, false));
            case '^':
                return array(MustacheNodeSection::parse2($this, $parser, true));
            case '<':
            case '>':
                return array(new MustacheNodePartial($this));
            case '!':
            case '=':
                return array();
            case '&':
            case '{':
                return array(new MustacheNodeVariable($this, false));
            case '':
                return array(new MustacheNodeVariable($this, true));
            case '/':
                throw new Exception("Close of unopened section");
            default:
                throw new Exception("Unhandled sigil: $this->sigil");
        }
    }

    function isCloseSectionTag() {
        return $this->sigil == '/';
    }

    private function contentRegex(MustacheParser $parser) {
        if ($this->sigil == '!' || $this->sigil == '=')
            return ".*?(?=\\s*" . $this->closeSigilRegex($parser) . $parser->closeTagRegex() . ")";
        else
            return '(\w|[?!\/.-])*';
    }

    private function closeSigilRegex(MustacheParser $parser) {
        return '(' . $parser->escape($this->sigil === '{' ? '}' : $this->sigil) . ')?';
    }

    function content() {
        return $this->content;
    }

    function originalText() {
        return $this->spaceBefore .
               $this->openTag .
               $this->sigil .
               $this->paddingBefore .
               $this->content .
               $this->paddingAfter .
               $this->closeSigil .
               $this->closeTag .
               $this->spaceAfter;
    }

    function indent() {
        return $this->spaceBefore;
    }
}

class MustacheNodeSection extends MustacheDocument {
    static function parse2(MustacheParsedTag $startTag, MustacheTokenStream $parser, $isInverted) {
        $nodes = array();

        while ($parser->hasMore()) {
            $token = $parser->readOne();
            if ($token instanceof MustacheParsedTag &&
                $token->isCloseSectionTag()
            ) {
                if ($token->content() !== $startTag->content())
                    throw new Exception("Start tag end tag mismatch");
                else
                    return new self($nodes, $startTag, $token, $isInverted);
            } else {
                foreach ($token->toNodes($parser) as $node)
                    $nodes[] = $node;
            }
        }

        throw new Exception("Section left unclosed");
    }

    private $isInverted;
    private $startTag;
    private $endTag;

    function __construct(array $nodes, MustacheParsedTag $startTag, MustacheParsedTag $endTag, $isInverted) {
        parent::__construct($nodes);
        $this->startTag   = $startTag;
        $this->endTag     = $endTag;
        $this->isInverted = $isInverted;
    }

    final function name() {
        return $this->startTag->content();
    }

    function process(MustacheProcessor $visitor) {
        if ($this->isInverted)
            return $visitor->visitSectionInverted($this);
        else
            return $visitor->visitSectionNormal($this);
    }
}

