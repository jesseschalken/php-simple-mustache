<?php

class MustacheNodeVisitorDumpAst extends MustacheNodeVisitor {
    private $indentLevel = 0;

    static function dumpDocument(MustacheDocument $document) {
        $self = new self;

        return join('', $self->map($document));
    }

    private function dump($string) {
        return json_encode((string)$string);
    }

    private function indentLine($text) {
        return str_repeat('  ', $this->indentLevel) . $text . "\n";
    }

    function visitText(MustacheNodeText $text) {
        return $this->indentLine('text: ' . $this->dump($text->text()));
    }

    function visitComment(MustacheNodeComment $comment) {
        return $this->indentLine('comment: ' . $this->dump($comment->text()));
    }

    function visitSetDelimiters(MustacheNodeSetDelimiters $setDelimiter) {
        return $this->indentLine('set delimiters: ' . $this->dump($setDelimiter->openTag()) . ', '
                                 . $this->dump($setDelimiter->closeTag()));
    }

    function visitPartial(MustacheNodePartial $partial) {
        return $this->indentLine('partial: ' . $this->dump($partial->name()));
    }

    function visitVariableEscaped(MustacheNodeVariableEscaped $variable) {
        return $this->indentLine('variable: ' . $this->dump($variable->name()));
    }

    function visitVariableUnEscaped(MustacheNodeVariableUnEscaped $variable) {
        return $this->indentLine('unescaped variable: ' . $this->dump($variable->name()));
    }

    function visitSectionNormal(MustacheNodeSectionNormal $section) {
        return $this->indentLine('section: ' . $this->dump($section->name())) . $this->dumpSection($section);
    }

    function visitSectionInverted(MustacheNodeSectionInverted $section) {
        return $this->indentLine('inverted section: ' . $this->dump($section->name()))
               . $this->dumpSection($section);
    }

    private function dumpSection(MustacheNodeSection $section) {
        $this->indentLevel++;

        $result = join('', $this->map($section));

        $this->indentLevel--;

        return $result;
    }
}

