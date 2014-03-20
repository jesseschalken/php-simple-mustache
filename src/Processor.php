<?php

namespace SimpleMustache;

final class MustacheContext {
    private $context;

    static function process(MustacheDocument $document, MustacheValue $value, MustachePartials $partials) {
        return $document->process(new self(array($value)), $partials);
    }

    private function __construct(array $context) {
        $this->context = $context;
    }

    function extend(MustacheValue $v) {
        $context = $this->context;
        array_unshift($context, $v);
        return new self($context);
    }

    function resolveName($name) {
        if ($name === '.')
            return isset($this->context[0]) ? $this->context[0] : new MustacheValueFalsey;

        $parts = explode('.', $name);
        $v     = self::resolveProperty($this->context, array_shift($parts));

        foreach ($parts as $part)
            $v = self::resolveProperty(array($v), $part);

        return $v;
    }

    /**
     * @param MustacheValue[] $context
     * @param string $name
     * @return MustacheValue
     */
    private static function resolveProperty(array $context, $name) {
        foreach ($context as $value)
            if ($value->hasProperty($name))
                return $value->getProperty($name);
        
        return new MustacheValueFalsey;
    }
}

