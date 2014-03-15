<?php

abstract class MustacheNodeVisitor {
    final function map(HasMustacheNodes $nodes) {
        $results = array();

        foreach ($nodes->nodes() as $k => $node)
            $results[$k] = $node->acceptVisitor($this);

        return $results;
    }

    abstract function visitText(MustacheNodeText $text);

    abstract function visitComment(MustacheNodeComment $comment);

    abstract function visitSetDelimiters(MustacheNodeSetDelimiters $setDelimiter);

    abstract function visitPartial(MustacheNodePartial $partial);

    abstract function visitVariableEscaped(MustacheNodeVariableEscaped $variable);

    abstract function visitVariableUnEscaped(MustacheNodeVariableUnEscaped $variable);

    abstract function visitSectionNormal(MustacheNodeSectionNormal $section);

    abstract function visitSectionInverted(MustacheNodeSectionInverted $section);
}

abstract class MustacheNode {
    abstract function acceptVisitor(MustacheNodeVisitor $visitor);

    abstract function originalText();

    function __construct() {
    }
}

interface HasMustacheNodes {
    /** @return MustacheNode[] */
    function nodes();
}

abstract class MustacheNodeTag extends MustacheNode {
    private $tag;

    function __construct(MustacheParsedTag $tag) {
        $this->tag = $tag;
    }

    protected final function tagContent() {
        return $this->tag->content();
    }

    function originalText() {
        return $this->tag->originalText();
    }

    final function indent() {
        return $this->tag->indent();
    }
}

final class MustacheNodeComment extends MustacheNodeTag {
    function acceptVisitor(MustacheNodeVisitor $visitor) {
        return $visitor->visitComment($this);
    }

    final function text() {
        return $this->tagContent();
    }
}

final class MustacheNodeSetDelimiters extends MustacheNodeTag {
    private $openTag, $innerPadding, $closeTag;

    function __construct(MustacheParsedTag $tag, MustacheParser $parser) {
        parent::__construct($tag);

        $contentScanner = new StringScanner($this->tagContent());

        $this->openTag      = $contentScanner->scanText("^\S+");
        $this->innerPadding = $contentScanner->scanText("\s+");
        $this->closeTag     = $contentScanner->scanText("\S+$");

        $parser->setDelimiters($this->openTag, $this->closeTag);
    }

    function acceptVisitor(MustacheNodeVisitor $visitor) {
        return $visitor->visitSetDelimiters($this);
    }

    final function openTag() {
        return $this->openTag;
    }

    final function closeTag() {
        return $this->closeTag;
    }
}

abstract class MustacheNodeVariable extends MustacheNodeTag {
    final function name() {
        return $this->tagContent();
    }
}

class MustacheNodeVariableEscaped extends MustacheNodeVariable {
    function acceptVisitor(MustacheNodeVisitor $visitor) {
        return $visitor->visitVariableEscaped($this);
    }
}

class MustacheNodeVariableUnEscaped extends MustacheNodeVariable {
    function acceptVisitor(MustacheNodeVisitor $visitor) {
        return $visitor->visitVariableUnEscaped($this);
    }
}

final class MustacheNodePartial extends MustacheNodeTag {
    function acceptVisitor(MustacheNodeVisitor $visitor) {
        return $visitor->visitPartial($this);
    }

    final function name() {
        return $this->tagContent();
    }
}

final class MustacheNodeStream implements HasMustacheNodes {
    /** @var MustacheNode[] */
    private $nodes = array();
    /** @var MustacheParsedTag[] */
    private $closeSectionTag = array();

    function __construct(MustacheParser $parser) {
        while (!$this->finished($parser))
            $this->parseOneTag($parser);

        if (!$this->closeSectionTag)
            $this->addText($parser->scanText('.*$'));
    }

    private function finished(MustacheParser $parser) {
        return $this->closeSectionTag || !$parser->textMatches('.*' . $parser->openTagRegex());
    }

    private function parseOneTag(MustacheParser $parser) {
        $text = '';
        $tag  = new MustacheParsedTag($parser, $text);

        $this->addText($text);
        $this->addTag($parser, $tag);
    }

    private function addTag(MustacheParser $parser, MustacheParsedTag $tag) {
        if ($tag->isCloseSectionTag())
            $this->closeSectionTag[] = $tag;
        else
            $this->nodes[] = $tag->toNode($parser);
    }

    private function addText($text) {
        if ($text !== '')
            $this->nodes[] = new MustacheNodeText($text);
    }

    function originalText() {
        $result = '';

        foreach ($this->nodes() as $node)
            $result .= $node->originalText();

        foreach ($this->closeSectionTag as $tag)
            $result .= $tag->originalText();

        return $result;
    }

    function nodes() {
        return $this->nodes;
    }

    function closeSectionTag() {
        return $this->closeSectionTag;
    }
}

final class MustacheDocument implements HasMustacheNodes {
    private $nodes;

    function __construct(MustacheParser $parser) {
        $this->nodes = new MustacheNodeStream($parser);

        if ($this->nodes->closeSectionTag() !== array())
            throw new Exception("Close of unopened section");
    }

    function originalText() {
        return $this->nodes->originalText();
    }

    function nodes() {
        return $this->nodes->nodes();
    }
}

class MustacheNodeText extends MustacheNode {
    private $text;

    function __construct($text) {
        $this->text = $text;
    }

    function acceptVisitor(MustacheNodeVisitor $visitor) {
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

    function __construct(MustacheParser $parser, &$textBefore) {
        $lineBoundary = $parser->lineBoundaryRegex();

        $textBefore = $this->scanUntilNextTag($parser);

        $this->isStandalone  = $parser->textMatches("(?<=$lineBoundary)");
        $this->spaceBefore   = $parser->scanText("\s*");
        $this->openTag       = $parser->scanText($parser->openTagRegex());
        $this->sigil         = $parser->scanText($this->sigilRegex());
        $this->paddingBefore = $parser->scanText("\s*");
        $this->content       = $parser->scanText($this->contentRegex($parser));
        $this->paddingAfter  = $parser->scanText("\s*");
        $this->closeSigil    = $parser->scanText($this->closeSigilRegex($parser));
        $this->closeTag      = $parser->scanText($parser->closeTagRegex());

        $this->isStandalone = $this->isStandalone
                              && $this->typeAllowsStandalone()
                              && $parser->textMatches("\s*?($lineBoundary)");

        if ($this->isStandalone) {
            $this->spaceAfter = $parser->scanText("\s*?($lineBoundary)");
        } else {
            $textBefore .= $this->spaceBefore;
            $this->spaceBefore = '';
            $this->spaceAfter  = '';
        }
    }

    private function scanUntilNextTag(MustacheParser $parser) {
        $lineBoundary = $parser->lineBoundaryRegex();
        $openTag      = $parser->openTagRegex();

        return $parser->scanText(".*?(?=\s*$openTag)(\s*($lineBoundary))?");
    }

    final function toNode(MustacheParser $parser) {
        switch ($this->sigil) {
            case '#':
                return new MustacheNodeSectionNormal($this, $parser);
            case '^':
                return new MustacheNodeSectionInverted($this, $parser);
            case '<':
            case '>':
                return new MustacheNodePartial($this);
            case '!':
                return new MustacheNodeComment($this);
            case '=':
                return new MustacheNodeSetDelimiters($this, $parser);
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
        return "(#|\^|\/|\<|\>|\=|\!|&|\{)?";
    }

    private function contentRegex(MustacheParser $parser) {
        if ($this->sigil == '!' || $this->sigil == '=')
            return ".*?(?=\s*" . $this->closeSigilRegex($parser) . $parser->closeTagRegex() . ")";
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

abstract class MustacheNodeSection extends MustacheNodeTag implements HasMustacheNodes {
    private $nodes;

    function __construct(MustacheParsedTag $startTag, MustacheParser $parser) {
        parent::__construct($startTag);

        $this->nodes = new MustacheNodeStream($parser);

        foreach ($this->nodes->closeSectionTag() as $tag) {
            if ($tag->content() != $this->tagContent())
                throw new Exception("Open section/close section mismatch");

            return;
        }

        throw new Exception("Section left unclosed");
    }

    function originalText() {
        return parent::originalText() . $this->nodes->originalText();
    }

    function nodes() {
        return $this->nodes->nodes();
    }

    final function name() {
        return $this->tagContent();
    }
}

final class MustacheNodeSectionNormal extends MustacheNodeSection {
    function acceptVisitor(MustacheNodeVisitor $visitor) {
        return $visitor->visitSectionNormal($this);
    }
}

final class MustacheNodeSectionInverted extends MustacheNodeSection {
    function acceptVisitor(MustacheNodeVisitor $visitor) {
        return $visitor->visitSectionInverted($this);
    }
}

