<?php

final class Mustache
{
  public static function run( $template, $data )
  {
    $tokens = MustacheTokeniser::tokenise( $template );

    // TODO
    return null;
  }
}

