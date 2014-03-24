<?php

namespace SimpleMustache;

class Value {
    static function reflect($v) {
        if (is_null($v))
            return new self;

        if (is_bool($v))
            return $v ? new TrueValue : new self;

        if (is_string($v))
            return new StringValue($v);

        if (is_int($v))
            return new StringValue((string)$v);

        if (is_float($v))
            return new StringValue((string)$v);

        if (is_array($v))
            return self::isAssoc($v) ? new ObjectValue($v) : new ListValue($v);

        if (is_object($v))
            return new ObjectValue(get_object_vars($v));

        throw new Exception("Unhandled type: " . gettype($v));
    }

    private static function isAssoc(array $array) {
        $i = 0;
        foreach ($array as $k => $v)
            if ($k !== $i++)
                return true;
        return false;
    }

    final function extend(Value $value) {
        return new ExtendedValue($value, $this);
    }

    final function resolveName($name) {
        if ($name === '.')
            return $this;

        $parts = explode('.', $name);
        $value = $this->getProperty(array_shift($parts));

        foreach ($parts as $part)
            $value = $value->getProperty($part);

        return $value;
    }

    function hasProperty($name) {
        return false;
    }

    function getProperty($name) {
        return new Value;
    }

    function toString() {
        return '';
    }

    /**
     * @return Value[]
     */
    function toList() {
        return array();
    }
}

final class TrueValue extends Value {
    function toList() {
        return array($this);
    }
}

final class StringValue extends Value {
    private $text;

    function __construct($text) {
        $this->text = $text;
    }

    function toString() {
        return $this->text;
    }
}

final class ListValue extends Value {
    private $array;

    function __construct(array $array) {
        $this->array = $array;
    }

    function toList() {
        $result = array();
        foreach ($this->array as $x)
            $result[] = Value::reflect($x);
        return $result;
    }
}

final class ObjectValue extends Value {
    private $object;

    function __construct(array $object) {
        $this->object = $object;
    }

    function hasProperty($name) {
        return array_key_exists($name, $this->object);
    }

    function getProperty($name) {
        return Value::reflect($this->object[$name]);
    }

    function toList() {
        return array($this);
    }
}

final class ExtendedValue extends Value {
    private $value;
    private $base;

    function __construct(Value $value, Value $base) {
        $this->value = $value;
        $this->base  = $base;
    }

    function hasProperty($name) {
        return $this->value->hasProperty($name) ||
               $this->base->hasProperty($name);
    }

    function getProperty($name) {
        if ($this->value->hasProperty($name))
            return $this->value->getProperty($name);
        else
            return $this->base->getProperty($name);
    }

    function toString() {
        return $this->value->toString();
    }

    function toList() {
        return $this->value->toList();
    }
}

