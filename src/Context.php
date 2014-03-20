<?php

namespace SimpleMustache;

abstract class MustacheContext {
    static function fromValue(MustacheValue $value) {
        return new MustacheExtendedContext(new MustacheBaseContext, $value);
    }

    function extend(MustacheValue $v) {
        return new MustacheExtendedContext($this, $v);
    }

    function resolveName($name) {
        if ($name === '.')
            return $this->currentValue();

        $parts = explode('.', $name);
        $value = $this->resolveProperty(array_shift($parts));

        foreach ($parts as $part)
            $value = self::fromValue($value)->resolveProperty($part);

        return $value;
    }

    /**
     * @param string $name
     * @return MustacheValue
     */
    abstract function resolveProperty($name);

    /**
     * @return MustacheValue
     */
    abstract function currentValue();
}

class MustacheBaseContext extends MustacheContext {
    function resolveProperty($name) {
        return new MustacheValueFalsey;
    }

    function currentValue() {
        return new MustacheValueFalsey;
    }
}

class MustacheExtendedContext extends MustacheContext {
    private $context;
    private $value;

    function __construct(MustacheContext $context, MustacheValue $value) {
        $this->context = $context;
        $this->value   = $value;
    }

    function resolveProperty($name) {
        if ($this->value->hasProperty($name))
            return $this->value->getProperty($name);
        else
            return $this->context->resolveProperty($name);
    }

    function currentValue() {
        return $this->value;
    }
}


