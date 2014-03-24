<?php

namespace SimpleMustache;

class Regex {
    private $pattern;
    private $options;

    /**
     * @param string $pattern
     * @param string $options
     */
    function __construct($pattern, $options = '') {
        $this->pattern = $pattern;
        $this->options = $options;
    }

    /**
     * @param string $subject
     * @param int $offset
     * @return Match|null
     */
    function match($subject, $offset = 0) {
        $count = preg_match($this->pregPattern(), $subject, $match, PREG_OFFSET_CAPTURE, $offset);

        self::checkLastError();

        return $count ? new Match($match) : null;
    }

    private function pregPattern() {
        $regex  = $this->pattern;
        $regex2 = '';
        $start  = 0;
        do {
            $end = strpos($regex, '\\', $start);
            $end = $end === false ? strlen($regex) : $end;
            $regex2 .= str_replace(':', "\\:", substr($regex, $start, $end - $start));
            $regex2 .= substr($regex, $end, 2);
            $start = $end + 2;
        } while ($end < strlen($regex));

        return ":$regex2:$this->options";
    }

    private static function checkLastError() {
        $code = preg_last_error();

        if ($code === PREG_NO_ERROR)
            return;

        $messages = array(
            PREG_NO_ERROR              => 'No errors',
            PREG_INTERNAL_ERROR        => 'Internal PCRE error',
            PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit was exhausted',
            PREG_RECURSION_LIMIT_ERROR => 'Recursion limit was exhausted',
            PREG_BAD_UTF8_ERROR        => 'Malformed UTF-8 data',
            PREG_BAD_UTF8_OFFSET_ERROR => 'The offset didn\'t correspond to the begin of a valid UTF-8 code point',
        );

        $message = isset($messages[$code]) ? $messages[$code] : 'Unknown error';

        throw new Exception($message, $code);
    }

    /**
     * @param string $subject
     * @param int $offset
     * @return Match[]
     */
    function matchAll($subject, $offset = 0) {
        preg_match_all($this->pregPattern(), $subject, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $offset);

        self::checkLastError();

        $result = array();
        foreach ($matches as $match)
            $result[] = new Match($match);
        return $result;
    }

    /**
     * @param string $replacement
     * @param string $subject
     * @param int $limit
     * @param int $count
     * @return string
     */
    function replace($replacement, $subject, $limit = -1, &$count = null) {
        $result = preg_replace($this->pregPattern(), $replacement, $subject, $limit, $count);
        self::checkLastError();
        return $result;
    }

    /**
     * @param string $subject
     * @param string $limit
     * @return string[]
     */
    function split($subject, $limit = -1) {
        $result = preg_split($this->pregPattern(), $subject, $limit);
        self::checkLastError();
        return $result;
    }
}

class Match {
    private $match;

    function __construct(array $match) {
        $this->match = $match;
    }

    function offset($subPattern = 0) {
        return $this->match[$subPattern][1];
    }

    function text($subPattern = 0) {
        return $this->match[$subPattern][0];
    }

    function has($subPattern = 0) {
        return isset($this->match[$subPattern]) && $this->offset($subPattern) != -1;
    }

    function length($subPattern = 0) {
        return strlen($this->text($subPattern));
    }
}

class Exception extends \Exception {
}

