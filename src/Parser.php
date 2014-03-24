<?php

namespace SimpleMustache;

final class Parser {
    private $openTag = '{{', $closeTag = '}}';
    private $string;
    private $lineStart = true;

    /**
     * @param string $template
     */
    function __construct($template) {
        $this->string = $template;
    }

    /**
     * @param string|null $openSection
     * @return Node[]
     * @throws Exception
     */
    function parse($openSection = null) {
        /** @var Node[] $nodes */
        $nodes = array();

        while (true) {
            $openTagRE  = Regex::quote($this->openTag);
            $closeTagRE = Regex::quote($this->closeTag);

            $match = $this->regex("((?<=\n|^)[ \t]*)?$openTagRE(#|\\^|\\/|\\<|\\>|\\=|\\!|&|\\{|)\\s*");
            if (!$match)
                break;

            $nodes[] = new NodeText($this->remove($match->offset()));
            $this->skip($match->length());

            $isStandalone = $match->has(1) && ($this->lineStart || $match->offset(1) > 0);
            $spaceBefore  = $match->has(1) ? $match->text(1) : '';
            $sigil        = $match->text(2);
            $closeSigilRE = Regex::quote($sigil === '{' ? '}' : $sigil);
            $endRegex     = "\\s*($closeSigilRE|)$closeTagRE";

            if ($sigil == '!' || $sigil == '=') {
                $match   = $this->regex($endRegex);
                $content = $this->remove($match->offset());
                $this->skip($match->length());
            } else {
                $match   = $this->regex("^([\\w?!\\/.-]*)$endRegex", 'su');
                $content = $match->text(1);
                $this->skip($match->offset());
                $this->skip($match->length());
            }

            $isStandalone = $isStandalone &&
                            $sigil != '{' &&
                            $sigil != '&' &&
                            $sigil != '';

            if ($isStandalone) {
                $match = $this->regex("^[ \t]*(\r?\n|$)");
                if (!$match) {
                    $isStandalone = false;
                } else {
                    $this->skip($match->offset());
                    $this->skip($match->length());
                    $this->lineStart = true;
                }
            }

            if (!$isStandalone) {
                $this->lineStart = false;
                $nodes[]         = new NodeText($spaceBefore);
                $spaceBefore     = '';
            }

            switch ($sigil) {
                case '=':
                    $regex = new Regex('^(\\S+)\\s+(\\S+)$', 'su');
                    $match = $regex->match($content);

                    $this->openTag  = $match->text(1);
                    $this->closeTag = $match->text(2);
                    break;
                case '#':
                    $nodes[] = new NodeSection($this->parse($content), $content, false);
                    break;
                case '^':
                    $nodes[] = new NodeSection($this->parse($content), $content, true);
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

        $nodes[] = new NodeText($this->removeAll());

        return $nodes;
    }

    private function regex($pattern) {
        $regex = new Regex($pattern, 'su');
        return $regex->match($this->string);
    }

    private function remove($len) {
        $result = $this->read($len);
        $this->skip($len);
        return $result;
    }

    private function skip($len) {
        $this->string = substr($this->string, $len);
    }

    private function read($len) {
        return substr($this->string, 0, $len);
    }

    private function removeAll() {
        $result       = $this->string;
        $this->string = '';
        return $result;
    }
}

