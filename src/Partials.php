<?php

namespace SimpleMustache;

abstract class Partials {
    /**
     * @param string $name
     * @return string
     */
    function get($name) {
        return '';
    }
}

final class PartialsArray extends Partials {
    private $partials;

    /**
     * @param string[] $partials
     */
    function __construct(array $partials) {
        $this->partials = $partials;
    }

    function get($name) {
        if (isset($this->partials[$name]))
            return $this->partials[$name];

        return parent::get($name);
    }
}

