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

abstract class MustacheNodeVariable extends MustacheNode {
    /** @var MustacheParsedTag */
    private $tag;

    function __construct(MustacheParsedTag $tag) {
        $this->tag = $tag;
    }

    function name() {
        return $this->tag->content();
    }

    function originalText() {
        return $this->tag->originalText();
    }
}

class MustacheNodeVariableEscaped extends MustacheNodeVariable {
    function process(MustacheProcessor $visitor) {
        return $visitor->visitVariableEscaped($this);
    }
}

class MustacheNodeVariableUnEscaped extends MustacheNodeVariable {
    function process(MustacheProcessor $visitor) {
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

class MustacheNodeStream {
    /**
     * @param MustacheParser $parser
     * @param MustacheParsedTag|null $closeTag
     * @return MustacheNode[]
     */
    static function parse(MustacheParser $parser, &$closeTag = null) {
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
}

final class MustacheDocument {
    private $nodes;

    function __construct(MustacheParser $parser) {
        $this->nodes = MustacheNodeStream::parse($parser, $closeTag);

        if ($closeTag)
            throw new Exception("Close of unopened section");
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
                return new MustacheNodeSection($this, $parser, false);
            case '^':
                return new MustacheNodeSection($this, $parser, true);
            case '<':
            case '>':
                return new MustacheNodePartial($this);
            case '!':
                return new MustacheNodeComment($this);
            case '=':
                return MustacheNodeSetDelimiters::parse($this, $parser);
            case '&':
            case '{':
                return new MustacheNodeVariableUnEscaped($this);
            case '':
                return new MustacheNodeVariableEscaped($this);
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

class MustacheNodeSection extends MustacheNode {
    private $nodes;
    private $isInverted;
    /** @var MustacheParsedTag */
    private $startTag;
    /** @var MustacheParsedTag */
    private $endTag;

    function __construct(MustacheParsedTag $startTag, MustacheParser $parser, $isInverted) {
        $this->startTag   = $startTag;
        $this->isInverted = $isInverted;
        $this->nodes      = MustacheNodeStream::parse($parser, $this->endTag);

        if (!$this->endTag)
            throw new Exception("Section left unclosed");
        if ($this->endTag->content() !== $startTag->content())
            throw new Exception("Open section/close section mismatch");
    }

    function originalText() {
        $result = '';

        foreach ($this->nodes as $node)
            $result .= $node->originalText();

        $startTag = $this->startTag->originalText();
        $endTag   = $this->endTag->originalText();
        return "$startTag$result$endTag";
    }

    function nodes() {
        return $this->nodes;
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

