<?php

namespace SimpleMustache;

use DirectoryIterator;
use RegexIterator;
use SplFileInfo;

class Test extends \PHPUnit_Framework_TestCase {
    /**
     * @param array $json
     * @throws \Exception
     * @dataProvider dataProvider
     */
    function testMustacheSpec($json) {
        $template = $json['template'];
        $data     = $json['data'];
        $partials = isset($json['partials']) ? $json['partials'] : array();
        $expected = $json['expected'];

        $partials = new PartialsArray($partials);
        $value    = Value::reflect($data);
        $document = Document::parse($template);
        $result   = $document->process($value, $partials);

        try {
            $this->assertEquals($expected, $result);
        } catch (\Exception $e) {
            print json_encode($json, JSON_PRETTY_PRINT);
            throw $e;
        }
    }

    function dataProvider() {
        $result = array();

        /** @var SplFileInfo $file */
        $files = new RegexIterator(new DirectoryIterator(__DIR__ . "/../spec/specs"), '/^[^~].*\.json$/');
        foreach ($files as $file) {
            $json1 = json_decode(file_get_contents($file->getPathname()), true);

            foreach ($json1['tests'] as $json)
                $result["{$file->getFilename()} - {$json['name']}"] = array($json);
        }

        return $result;
    }
}

