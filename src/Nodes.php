<?php

namespace SimpleMustache;

use Exception;

abstract class MustacheNode {
    abstract function process(MustacheProcessor $visitor);

    abstract function originalText();
}

final class MustacheNodeComment extends MustacheNode {
    /** @var MustacheParsedTag */
    private $tag;

    function __construct(MustacheParsedTag $tag) {
        $this->tag = $tag;
    }

    function process(MustacheProcessor $visitor) {
        return $visitor->visitComment();
    }

    function originalText() {
        return $this->tag->originalText();
    }
}

final class MustacheNodeSetDelimiters extends MustacheNode {
    private $openTag, $innerPadding, $closeTag;
    /** @var MustacheParsedTag */
    private $tag;

    function __construct(MustacheParsedTag $tag) {
        $this->tag = $tag;
    }

    static function parse(MustacheParsedTag $tag, MustacheParser $parser) {
        $self           = new self($tag);
        $contentScanner = new StringScanner($tag->content());

        $self->openTag      = $contentScanner->scanText("^\\S+");
        $self->innerPadding = $contentScanner->scanText("\\s+");
        $self->closeTag     = $contentScanner->scanText("\\S+$");

        $parser->setDelimiters($self->openTag, $self->closeTag);

        return $self;
    }

    function process(MustacheProcessor $visitor) {
        return $visitor->visitSetDelimiters();
    }

    function originalText() {
        return $this->tag->originalText();
    }
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

    function originalText() {
        return $this->tag->originalText();
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

    function originalText() {
        return $this->tag->originalText();
    }

    function indent() {
        return $this->tag->indent();
    }
}

class MustacheDocument extends MustacheNode {
    /**
     * @param MustacheParser $parser
     * @param MustacheParsedTag|null $closeTag
     * @return MustacheNode[]
     */
    static function parseNodes(MustacheParser $parser, &$closeTag = null) {
        $nodes = array();

        while (!$closeTag && $parser->textMatches('.*' . $parser->openTagRegex())) {
            $text = '';
            $tag  = MustacheParsedTag::parse($parser, $text);

            $nodes[] = new MustacheNodeText($text);
            if ($tag->isCloseSectionTag())
                $closeTag = $tag;
            else
                $nodes[] = $tag->toNode($parser);
        }

        if (!$closeTag)
            $nodes[] = new MustacheNodeText($parser->scanText('.*$'));

        return $nodes;
    }

    static function parse(MustacheParser $parser) {
        $nodes = self::parseNodes($parser, $closeTag);

        if ($closeTag)
            throw new Exception("Close of unopened section");

        return new self($nodes);
    }

    private $nodes;

    /**
     * @param MustacheNode[] $nodes
     */
    function __construct(array $nodes) {
        $this->nodes = $nodes;
    }

    function originalText() {
        $result = '';

        foreach ($this->nodes as $node)
            $result .= $node->originalText();

        return $result;
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

    function originalText() {
        return $this->text;
    }
}

final class MustacheParsedTag {
    private $spaceBefore, $spaceAfter;
    private $openTag, $closeTag;
    private $sigil, $closeSigil;
    private $paddingBefore, $paddingAfter;
    private $content;
    private $isStandalone;

    static function parse(MustacheParser $parser, &$textBefore) {
        $lineBoundary = $parser->lineBoundaryRegex();

        $self       = new self;
        $textBefore = $self->scanUntilNextTag($parser);

        $self->isStandalone  = $parser->textMatches("(?<=$lineBoundary)");
        $self->spaceBefore   = $parser->scanText("\\s*");
        $self->openTag       = $parser->scanText($parser->openTagRegex());
        $self->sigil         = $parser->scanText($self->sigilRegex());
        $self->paddingBefore = $parser->scanText("\\s*");
        $self->content       = $parser->scanText($self->contentRegex($parser));
        $self->paddingAfter  = $parser->scanText("\\s*");
        $self->closeSigil    = $parser->scanText($self->closeSigilRegex($parser));
        $self->closeTag      = $parser->scanText($parser->closeTagRegex());

        $self->isStandalone = $self->isStandalone &&
                              $self->typeAllowsStandalone() &&
                              $parser->textMatches("\\s*?($lineBoundary)");

        if ($self->isStandalone) {
            $self->spaceAfter = $parser->scanText("\\s*?($lineBoundary)");
        } else {
            $textBefore .= $self->spaceBefore;
            $self->spaceBefore = '';
            $self->spaceAfter  = '';
        }

        return $self;
    }

    private function __construct() {
    }

    private function scanUntilNextTag(MustacheParser $parser) {
        $lineBoundary = $parser->lineBoundaryRegex();
        $openTag      = $parser->openTagRegex();

        return $parser->scanText(".*?(?=\\s*$openTag)(\\s*($lineBoundary))?");
    }

    final function toNode(MustacheParser $parser) {
        switch ($this->sigil) {
            case '#':
                return MustacheNodeSection::parse2($this, $parser, false);
            case '^':
                return MustacheNodeSection::parse2($this, $parser, true);
            case '<':
            case '>':
                return new MustacheNodePartial($this);
            case '!':
                return new MustacheNodeComment($this);
            case '=':
                return MustacheNodeSetDelimiters::parse($this, $parser);
            case '&':
            case '{':
                return new MustacheNodeVariable($this, false);
            case '':
                return new MustacheNodeVariable($this, true);
            default:
                throw new Exception("Unhandled sigil: $this->sigil");
        }
    }

    function isCloseSectionTag() {
        return $this->sigil == '/';
    }

    private function typeAllowsStandalone() {
        return $this->sigil != '{' && $this->sigil != '&' && $this->sigil != '';
    }

    private function sigilRegex() {
        return "(#|\\^|\\/|\\<|\\>|\\=|\\!|&|\\{)?";
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
    static function parse2(MustacheParsedTag $startTag, MustacheParser $parser, $isInverted) {
        /** @var MustacheParsedTag $endTag */
        $nodes = self::parseNodes($parser, $endTag);

        if (!$endTag)
            throw new Exception("Section left unclosed");
        if ($endTag->content() !== $startTag->content())
            throw new Exception("Open section/close section mismatch");

        return new self($nodes, $startTag, $endTag, $isInverted);
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

    function originalText() {
        $startTag = $this->startTag->originalText();
        $inner    = parent::originalText();
        $endTag   = $this->endTag->originalText();
        return "$startTag$inner$endTag";
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

