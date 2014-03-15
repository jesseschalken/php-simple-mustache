<?php

namespace SimpleMustache;

final class Mustache {
    static function run($template, $data, array $partials) {
        $document = MustacheParser::parse($template);
        $value    = MustacheValue::reflect($data);
        $partials = new MustachePartialProviderArray($partials);

        return MustacheProcessor::process($document, $value, $partials);
    }
}

