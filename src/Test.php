<?php

namespace SimpleMustache;

use DirectoryIterator;
use RegexIterator;
use SplFileInfo;

class MustacheTest extends \PHPUnit_Framework_TestCase {
    function testMustacheSpec() {
        /** @var SplFileInfo $file */
        $files = new RegexIterator(new DirectoryIterator(__DIR__ . "/../spec/specs"), '/^[^~].*\.json$/');
        foreach ($files as $file) {
            $name = $file->getFilename();
            $json1 = json_decode(file_get_contents($file->getPathname()), true);

            echo "Testing $name...\n";

            foreach ($json1['tests'] as $json) {
                $name     = $json['name'];
                $data     = $json['data'];
                $template = $json['template'];
                $expected = $json['expected'];
                $partials = isset($json['partials']) ? $json['partials'] : array();

                echo "  Testing $name...\n";

                $result = Mustache::run($template, $data, $partials);

                $this->assertEquals($expected, $result);
            }
        }
    }
}

