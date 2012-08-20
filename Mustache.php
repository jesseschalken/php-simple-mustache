<?php

final class Mustache
{
  public static function run( $template, $data )
  {
    $document = MustacheParser::parse( $template );

    // TODO
    return null;
  }
}

