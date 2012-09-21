<?php

final class Mustache
{
  public static function run( $template, $data, MustachePartialProviderArray $partials )
  {
    $document = MustacheParser::parse( $template );

    $value = MustacheValue::reflect( $data );

    return MustacheProcessor::process( $document, $value, $partials );
  }
}

