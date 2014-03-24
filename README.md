## simple-mustache-parser

A simple [Mustache](http://mustache.github.com/) parser and processor for PHP.

### Usage

```php
use SimpleMustache\Document;
use SimpleMustache\Value;
use SimpleMustache\PartialsArray;

$document = Document::parse('hello {{user.name}}');
$value    = Value::reflect(array('user' => array('name' => 'Joe')));
$partials = new PartialsArray;

print $document->process($value, $partials);
// => "hello Joe"
```

Use `Document::process()` to parse a Mustache template. Then call `->process($value, $partials)` to process it, with an instance of `Value` and `Partials`.

You can use `Value::reflect()` to create a `Value` from an arbitrary PHP value, or simply extend the `Value` class and override its methods:

```
Value[] Value::toList();
string  Value::toString();
bool    Value::hasProperty(string $name);
Value   Value::getProperty(string $name);
```

You can use `new PartialsArray(...)` to create a `Partials` from an array, or simply extend the `Partials` class and override its method:

```
string  Partials::get(string $name);
```

That is all.
