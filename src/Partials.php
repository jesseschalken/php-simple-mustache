<?php

namespace SimpleMustache;

abstract class MustachePartials {
    /**
     * @param string $name
     * @return string
     */
    function partial($name) {
        return '';
    }
}

final class MustachePartialsArray extends MustachePartials {
    private $partials;

    /**
     * @param string[] $partials
     */
    function __construct(array $partials) {
        $this->partials = $partials;
    }

    function partial($name) {
        if (isset($this->partials[$name]))
            return $this->partials[$name];

        return parent::partial($name);
    }
}

