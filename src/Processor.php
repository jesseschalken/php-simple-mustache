<?php

namespace SimpleMustache;

final class MustacheProcessor {
    private $context, $partials;

    static function process(MustacheDocument $document, MustacheValue $value, MustachePartialProvider $partials) {
        $self = new self($document, $value, $partials);

        $result = '';
        foreach ($document->nodes() as $node)
            $result .= $node->process($self);

        return $result;
    }

    private function __construct(MustacheDocument $document, MustacheValue $value, MustachePartialProvider $partials) {
        $this->partials = $partials;
        $this->context  = array($value);
    }

    function visitText(MustacheNodeText $text) {
        return $text->text();
    }

    function visitPartial(MustacheNodePartial $partial) {
        $result = '';
        $text   = $this->partials->partial($partial->name());
        $text   = self::indentText($partial->indent(), $text);

        foreach (MustacheParser::parse($text)->nodes() as $node)
            $result .= $node->process($this);

        return $result;
    }

    function visitVariableEscaped(MustacheNodeVariable $var) {
        return htmlspecialchars($this->variableText($var), ENT_COMPAT);
    }

    function visitVariableUnEscaped(MustacheNodeVariable $var) {
        return $this->variableText($var);
    }

    function visitSectionNormal(MustacheNodeSection $section) {
        $result = '';
        foreach ($this->sectionValues($section) as $value)
            $result .= $this->renderSectionValue($value, $section);
        return $result;
    }

    function visitSectionInverted(MustacheNodeSection $section) {
        if (!$this->sectionValues($section))
            return $this->renderSectionValue(new MustacheValueFalsey, $section);
        else
            return '';
    }

    private function sectionValues(MustacheNodeSection $section) {
        return $this->resolveName($section->name())->toList();
    }

    private function renderSectionValue(MustacheValue $value, MustacheNodeSection $section) {
        $this->pushContext($value);

        $result = '';
        foreach ($section->nodes() as $node)
            $result .= $node->process($this);

        $this->popContext();

        return $result;
    }

    private function variableText(MustacheNodeVariable $var) {
        return $this->resolveName($var->name())->text();
    }

    private function pushContext(MustacheValue $v) {
        array_unshift($this->context, $v);
    }

    private function popContext() {
        array_shift($this->context);
    }

    private function resolveName($name) {
        if ($name === '.')
            return $this->currentContext();

        $parts = explode('.', $name);
        $v     = self::resolveProperty($this->context, array_shift($parts));

        foreach ($parts as $part)
            $v = self::resolveProperty(array($v), $part);

        return $v;
    }

    private function currentContext() {
        foreach ($this->context as $v)
            return $v;

        return new MustacheValueFalsey;
    }

    private static function indentText($indent, $text) {
        return preg_replace("/(?<=^|\r\n|\n)(?!$)/su", addcslashes($indent, '\\$'), $text);
    }

    /**
     * @param MustacheValue[] $context
     * @param string $name
     *
     * @return MustacheValue
     */
    private static function resolveProperty(array $context, $name) {
        $i = 0;

        $getter = function () use ($context, &$getter, $name, &$i) {
            return isset($context[$i]) ? $context[$i++]->property($name, $getter) : new MustacheValueFalsey;
        };

        return $getter();
    }
}

