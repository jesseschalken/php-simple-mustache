<?php

final class MustacheParser
{
  public static function parse( $template )
  {
    $parser   = new self( $template );
    $document = new MustacheDocument( $parser );

    assert( $document->originalText() === $template );

    return $document;
  }

  private $scanner;
  private $openTag  = '{{';
  private $closeTag = '}}';

  private function __construct( $template )
  {
    $this->scanner = new StringScanner( $template );
  }

  public function lineBoundaryRegex()
  {
    return "\r\n|\n|^|$";
  }

  public function openTagRegex()
  {
    return $this->escape( $this->openTag );
  }

  public function closeTagRegex()
  {
    return $this->escape( $this->closeTag );
  }

  public function setDelimiters( $openTag, $closeTag )
  {
    $this->openTag  = $openTag;
    $this->closeTag = $closeTag;
  }

  public function escape( $text )
  {
    return $this->scanner->escape( $text );
  }

  public function scanText( $regex )
  {
    return $this->scanner->scanText( $regex );
  }

  public function textMatches( $regex )
  {
    return $this->scanner->textMatches( $regex );
  }
}

final class StringScannerMatchFailureException extends Exception
{
}

final class StringScanner
{
  private $position = 0;
  private $string;

  public function __construct( $string )
  {
    $this->string = $string;
  }

  public function escape( $text )
  {
    return preg_quote( $text, '/' );
  }

  public function scanText( $regex )
  {
    $match = $this->matchText( $regex );

    $this->position += strlen( $match );

    assert( $this->position <= strlen( $this->string ) );

    return $match;
  }

  public function textMatches( $regex )
  {
    try
    {
      $this->matchText( $regex );

      return true;
    }
    catch ( StringScannerMatchFailureException $e )
    {
      return false;
    }
  }

  private function matchText( $regex )
  {
    preg_match( "/$regex/su", $this->string, $matches, PREG_OFFSET_CAPTURE, $this->position );

    if ( isset( $matches[0][0] ) && isset( $matches[0][1] ) && $matches[0][1] === $this->position )
      return $matches[0][0];

    $message = "Regex $regex failed to match at offset $this->position in "
      . "string " . json_encode( $this->string );

    throw new StringScannerMatchFailureException( $message );
  }
}

