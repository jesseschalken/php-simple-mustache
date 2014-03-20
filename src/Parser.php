<?php

namespace SimpleMustache;

use Closure;
use Exception;

final class Parser {
    private $openTag = '{{', $closeTag = '}}';

    function __construct($template) {
        $this->string = $template;
    }

    function parseNodes($openSection = null) {
        $lineBoundary = "\r\n|\n|^|$";

        $nodes = array();

        while ($this->textMatches('.*' . $this->escape($this->openTag))) {
            $nodes[] = new NodeText(
                $this->scanText(".*?(?=\\s*{$this->escape($this->openTag)})(\\s*($lineBoundary))?")
            );

            $isStandalone = $this->textMatches("(?<=$lineBoundary)");
            $spaceBefore  = $this->scanText("\\s*");
            $this->scanText($this->escape($this->openTag));
            $sigil = $this->scanText("(#|\\^|\\/|\\<|\\>|\\=|\\!|&|\\{)?");
            $this->scanText("\\s*");

            $closeSigil = $sigil === '{' ? '}' : $sigil;
            $endRegex   = "\\s*({$this->escape($closeSigil)})?{$this->escape($this->closeTag)}";
            if ($sigil == '!' || $sigil == '=')
                $content = $this->scanText(".*?(?=$endRegex)");
            else
                $content = $this->scanText('(\w|[?!\/.-])*');

            $this->scanText($endRegex);

            if ($isStandalone &&
                $sigil != '{' &&
                $sigil != '&' &&
                $sigil != '' &&
                $this->textMatches("\\s*?($lineBoundary)")
            ) {
                $this->scanText("\\s*?($lineBoundary)");
            } else {
                $nodes[]     = new NodeText($spaceBefore);
                $spaceBefore = '';
            }

            switch ($sigil) {
                case '=':
                    preg_match('/^(\\S+)\\s+(\\S+)$/su', $content, $match);
                    $this->openTag  = $match[1];
                    $this->closeTag = $match[2];
                    break;
                case '#':
                    $nodes[] = new NodeSection(self::parseNodes($content), $content, false);
                    break;
                case '^':
                    $nodes[] = new NodeSection(self::parseNodes($content), $content, true);
                    break;
                case '<':
                case '>':
                    $nodes[] = new NodePartial($content, $spaceBefore);
                    break;
                case '!':
                    break;
                case '&':
                case '{':
                    $nodes[] = new NodeVariable($content, false);
                    break;
                case '':
                    $nodes[] = new NodeVariable($content, true);
                    break;
                case '/':
                    if ($openSection === null)
                        throw new Exception("Close of unopened section");
                    else if ($content !== $openSection)
                        throw new Exception("Open tag/close tag mismatch");
                    else
                        return $nodes;
                default:
                    throw new Exception("Unhandled sigil: $sigil");
            }
        }

        if ($openSection !== null)
            throw new Exception("Unclosed section");

        $nodes[] = new NodeText($this->scanText('.*$'));

        return $nodes;
    }

    private $position = 0, $string;

    function escape($text) {
        return preg_quote($text, '/');
    }

    function scanText($regex) {
        $position = $this->position;
        $string   = $this->string;

        $match = $this->matchText(
            $regex,
            function ($x) {
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
        return $this->matchText(
            $regex,
            function () {
                return true;
            },
            function () {
                return false;
            }
        );
    }

    private function matchText($regex, Closure $success, Closure $fail) {
        preg_match("/$regex/su", $this->string, $matches, PREG_OFFSET_CAPTURE, $this->position);

        if (!isset($matches[0]))
            return $fail();

        list($text, $position) = $matches[0];

        return $position === $this->position ? $success($text) : $fail();
    }
}

final class StringScannerMatchFailureException extends Exception {
}

