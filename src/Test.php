<?php

namespace SimpleMustache;

use DirectoryIterator;
use RegexIterator;
use SplFileInfo;

class Test extends \PHPUnit_Framework_TestCase {
    /**
     * @param $data
     * @param $template
     * @param $expected
     * @param $partials
     * @dataProvider dataProvider
     */
    function testMustacheSpec($data, $template, $expected, $partials) {
        $result = Document::parse($template)->process(
            Context::fromValue(Value::reflect($data)),
            new PartialsArray($partials)
        );

        $this->assertEquals($expected, $result);
    }

    function dataProvider() {
        $result = array();

        /** @var SplFileInfo $file */
        $files = new RegexIterator(new DirectoryIterator(__DIR__ . "/../spec/specs"), '/^[^~].*\.json$/');
        foreach ($files as $file) {
            $fname = $file->getFilename();
            $json1 = json_decode(file_get_contents($file->getPathname()), true);

            foreach ($json1['tests'] as $json) {
                $name     = $json['name'];
                $data     = $json['data'];
                $template = $json['template'];
                $expected = $json['expected'];
                $partials = isset($json['partials']) ? $json['partials'] : array();

                $result["$fname - $name"] = array($data, $template, $expected, $partials);
            }
        }

        return $result;
    }
}

