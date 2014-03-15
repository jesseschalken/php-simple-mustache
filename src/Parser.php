<?php

namespace SimpleMustache;

use Closure;
use Exception;

final class MustacheParser {
    static function parse($template) {
        $parser   = new self($template);
        $document = MustacheDocument::parse($parser);

        \PHPUnit_Framework_TestCase::assertEquals($template, $document->originalText());

        return $document;
    }

    private $scanner, $openTag = '{{', $closeTag = '}}';

    private function __construct($template) {
        $this->scanner = new StringScanner($template);
    }

    function lineBoundaryRegex() {
        return "\r\n|\n|^|$";
    }

    function openTagRegex() {
        return $this->escape($this->openTag);
    }

    function closeTagRegex() {
        return $this->escape($this->closeTag);
    }

    function setDelimiters($openTag, $closeTag) {
        $this->openTag  = $openTag;
        $this->closeTag = $closeTag;
    }

    function escape($text) {
        return $this->scanner->escape($text);
    }

    function scanText($regex) {
        return $this->scanner->scanText($regex);
    }

    function textMatches($regex) {
        return $this->scanner->textMatches($regex);
    }
}

final class StringScannerMatchFailureException extends Exception {
}

final class StringScanner {
    private $position = 0, $string;

    function __construct($string) {
        $this->string = $string;
    }

    function escape($text) {
        return preg_quote($text, '/');
    }

    function scanText($regex) {
        $position = $this->position;
        $string   = $this->string;

        $match = $this->matchText($regex, function ($x) {
                return $x;
            },
            function () use ($regex, $position, $string) {
                throw new StringScannerMatchFailureException(
                    "Regex $regex failed to match at offset $position in string " . json_encode($string));
            }
        );

        $this->position += strlen($match);

        assert($this->position <= strlen($this->string));

        return $match;
    }

    function textMatches($regex) {
        return $this->matchText($regex, function () {
            return true;
        }, function () {
            return false;
        });
    }

    private function matchText($regex, Closure $success, Closure $fail) {
        preg_match("/$regex/su", $this->string, $matches, PREG_OFFSET_CAPTURE, $this->position);

        if (!isset($matches[0]))
            return $fail();

        list($text, $position) = $matches[0];

        return $position === $this->position ? $success($text) : $fail();
    }
}

