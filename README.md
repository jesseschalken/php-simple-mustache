# simple-mustache-parser

A simple [Mustache](http://mustache.github.com/) parser and processor for PHP.

## Usage

```php
use SimpleMustache\Document;
use SimpleMustache\Value;
use SimpleMustache\PartialsArray;

$document = Document::parse('hello {{where}}');
$value    = Value::reflect(array('where' => 'there'));
$partials = new SimpleMustache\PartialsArray;

print $document->process($value, $partials);
// => "hello there"
```
