<?php

namespace SimpleMustache;

use Exception;

abstract class MustacheValue {
    static function reflect($v) {
        if (is_null($v))
            return new MustacheValueFalsey;

        if (is_bool($v))
            return $v ? new MustacheValueTruthy : new MustacheValueFalsey;

        if (is_string($v))
            return new MustacheValueText($v);

        if (is_int($v))
            return new MustacheValueText((string)$v);

        if (is_float($v))
            return new MustacheValueText((string)$v);

        if (is_array($v))
            return self::isAssoc($v) ? new MustacheValueObject($v) : new MustacheValueList($v);

        if (is_object($v))
            return new MustacheValueObject(get_object_vars($v));

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
     * @return MustacheValue
     * @throws \Exception
     */
    function getProperty($name) {
        throw new Exception("No such property: $name");
    }

    function text() {
        return '';
    }

    /**
     * @return MustacheValue[]
     */
    function toList() {
        return array();
    }
}

final class MustacheValueFalsey extends MustacheValue {
}

final class MustacheValueTruthy extends MustacheValue {
    function toList() {
        return array(new MustacheValueFalsey);
    }
}

final class MustacheValueText extends MustacheValue {
    private $text;

    function __construct($text) {
        $this->text = $text;
    }

    function text() {
        return $this->text;
    }
}

final class MustacheValueList extends MustacheValue {
    private $array;

    function __construct(array $array) {
        $this->array = $array;
    }

    function toList() {
        $result = array();
        foreach ($this->array as $x)
            $result[] = MustacheValue::reflect($x);
        return $result;
    }
}

final class MustacheValueObject extends MustacheValue {
    private $object;

    function __construct(array $object) {
        $this->object = $object;
    }

    function hasProperty($name) {
        return array_key_exists($name, $this->object);
    }

    function getProperty($name) {
        return MustacheValue::reflect($this->object[$name]);
    }

    function toList() {
        return array($this);
    }
}

