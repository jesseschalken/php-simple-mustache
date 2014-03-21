<?php

namespace SimpleMustache;

abstract class Value {
    static function reflect($v) {
        if (is_null($v))
            return new ValueFalse;

        if (is_bool($v))
            return $v ? new ValueTrue : new ValueFalse;

        if (is_string($v))
            return new ValueText($v);

        if (is_int($v))
            return new ValueText((string)$v);

        if (is_float($v))
            return new ValueText((string)$v);

        if (is_array($v))
            return self::isAssoc($v) ? new ValueObject($v) : new ValueList($v);

        if (is_object($v))
            return new ValueObject(get_object_vars($v));

        throw new Exception("Unhandled type: " . gettype($v));
    }

    private static function isAssoc(array $array) {
        $i = 0;
        foreach ($array as $k => $v)
            if ($k !== $i++)
                return true;
        return false;
    }

    function hasProperty($name) {
        return false;
    }

    /**
     * @param $name
     * @return Value
     * @throws \Exception
     */
    function getProperty($name) {
        throw new Exception("No such property: $name");
    }

    function text() {
        return '';
    }

    /**
     * @return Value[]
     */
    function toList() {
        return array();
    }
}

final class ValueFalse extends Value {
}

final class ValueTrue extends Value {
    function toList() {
        return array(new ValueFalse);
    }
}

final class ValueText extends Value {
    private $text;

    function __construct($text) {
        $this->text = $text;
    }

    function text() {
        return $this->text;
    }
}

final class ValueList extends Value {
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

final class ValueObject extends Value {
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

