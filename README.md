## simple-mustache-parser

A simple [Mustache](http://mustache.github.com/) parser and processor for PHP.

### Usage

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

You can use `Value::reflect()` to create a `Value` from an arbitrary PHP value, or simply extend the `Value` class and override its methods:

```
Value[] Value::toList();
string  Value::toString();
bool    Value::hasProperty(string $name);
Value   Value::getProperty(string $name);
```

That is all.
