<?php

namespace SimpleMustache;

abstract class Context {
    static function fromValue(Value $value) {
        return new ExtendedContext(new BaseContext, $value);
    }

    function extend(Value $v) {
        return new ExtendedContext($this, $v);
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
     * @return Value
     */
    abstract function resolveProperty($name);

    /**
     * @return Value
     */
    abstract function currentValue();
}

class BaseContext extends Context {
    function resolveProperty($name) {
        return new ValueFalse;
    }

    function currentValue() {
        return new ValueFalse;
    }
}

class ExtendedContext extends Context {
    private $context;
    private $value;

    function __construct(Context $context, Value $value) {
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


