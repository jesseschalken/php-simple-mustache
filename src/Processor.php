<?php

namespace SimpleMustache;

final class MustacheContext {
    /** @var MustacheValue[] */
    private $context;

    static function process(MustacheDocument $document, MustacheValue $value, MustachePartials $partials) {
        return $document->process(new self(array($value)), $partials);
    }

    /**
     * @param MustacheValue[] $context
     */
    private function __construct(array $context) {
        $this->context = $context;
    }

    function extend(MustacheValue $v) {
        $context = $this->context;
        array_unshift($context, $v);
        return new self($context);
    }

    function resolveName($name) {
        if ($name === '.') {
            if (isset($this->context[0]))
                return $this->context[0];
            else
                return new MustacheValueFalsey;
        }

        $parts = explode('.', $name);
        $value = $this->resolveProperty(array_shift($parts));

        foreach ($parts as $part) {
            $context = new self(array($value));
            $value   = $context->resolveProperty($part);
        }

        return $value;
    }

    /**
     * @param string $name
     * @return MustacheValue
     */
    private function resolveProperty($name) {
        foreach ($this->context as $value)
            if ($value->hasProperty($name))
                return $value->getProperty($name);

        return new MustacheValueFalsey;
    }
}



