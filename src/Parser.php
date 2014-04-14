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
            $match = $this->match("((?<=\n|^)[ \t]*)?{$this->quote($this->openTag)}(#|\\^|\\/|\\<|\\>|\\=|\\!|&|\\{|)\\s*");
            if (!$match)
                break;

            $nodes[] = new NodeText($this->remove($match->offset()));
            $this->skip($match->length());

            $isStandalone = $match->has(1) && $match->offset(1) >= 0 && ($this->lineStart || $match->offset(1) > 0);
            $spaceBefore  = $match->has(1) && $match->offset(1) >= 0 ? $match->text(1) : '';
            $sigil        = $match->text(2);
            $endRegex     = "\\s*({$this->quote($sigil === '{' ? '}' : $sigil)}|){$this->quote($this->closeTag)}";

            if ($sigil == '!' || $sigil == '=') {
                $match   = $this->match($endRegex);
                $content = $this->remove($match->offset());
                $this->skip($match->length());
            } else {
                $match   = $this->match("^([\\w?!\\/.-]*)$endRegex", 'su');
                $content = $match->text(1);
                $this->skip($match->offset());
                $this->skip($match->length());
            }

            $isStandalone = $isStandalone &&
                            $sigil != '{' &&
                            $sigil != '&' &&
                            $sigil != '';

            if ($isStandalone) {
                $match = $this->match("^[ \t]*(\r?\n|$)");
                if (!$match) {
                    $isStandalone = false;
                } else {
                    $this->skip($match->offset());
                    $this->skip($match->length());
                }
            }

            if (!$isStandalone) {
                $nodes[]     = new NodeText($spaceBefore);
                $spaceBefore = '';
            }

            $this->lineStart = $isStandalone;

            switch ($sigil) {
                case '=':
                    $match = Regex::create('^(\\S+)\\s+(\\S+)$', 'su')->match($content);

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
                        throw new Exception("Close tag '$content' found but no section is open");
                    else if ($content !== $openSection)
                        throw new Exception("Close tag '$content' does not match current open section '$openSection'");
                    else
                        return $nodes;
                default:
                    throw new Exception("Unhandled sigil: $sigil");
            }
        }

        if ($openSection !== null)
            throw new Exception("Unclosed section '$openSection'");

        $nodes[] = new NodeText($this->removeAll());

        return $nodes;
    }

    private function match($pattern) {
        return Regex::create($pattern, 'su')->match($this->string);
    }

    private function remove($len) {
        $result = $this->read($len);
        $this->skip($len);
        return $result;
    }

    private function skip($len) {
        $this->string = (string)substr($this->string, $len);
    }

    private function read($len) {
        return (string)substr($this->string, 0, $len);
    }

    private function removeAll() {
        $result       = $this->string;
        $this->string = '';
        return $result;
    }

    private function quote($text) {
        return Regex::quote($text);
    }
}

