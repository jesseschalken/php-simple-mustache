<?php

final class MustacheTokeniser
{
  public static function tokenise( $template )
  {
    $tokeniser = new self;
    $tokeniser->scanner = new StringScanner( $template );
    $tokeniser->tokens  = new MustacheTokenStream;
    $tokeniser->process();

    assert( $tokeniser->tokens->originalText() === $template );
    var_dump( $tokeniser );

    return $tokeniser->tokens;
  }

  private $scanner;
  private $tokens;

  private $openTag  = '{{';
  private $closeTag = '}}';

  private function __construct() {}

  public function process()
  {
    while ( $this->hasTextRemaining() )
      $this->processOne();
  }

  public function processOne()
  {
    $this->skipToNextTagOrEof();

    $isStartOfLine = $this->isStartOfLine();
    $indent        = $this->scanText( $this->indentRegex() );

    if ( $this->textMatches( $this->openTagRegex() ) )
      $this->handleTagFound( $isStartOfLine, $indent );
    else
      $this->addText( $indent . $this->scanText( '.*' ) );
  }

  public function skipToNextTagOrEof()
  {
    $newLine = $this->newLineRegex();
    $indent  = $this->indentRegex();
    $openTag = $this->openTagRegex();

    $this->addText( $this->scanText( ".*?(?<=$newLine|)(?=$indent$openTag|$)" ) );
  }

  public function handleTagFound( $isStartOfLine, $indent )
  {
    $token = new MustacheTokenTag( $this, $isStartOfLine, $indent );

    $this->handleChangeDelimiters( $token );
    $this->tokens->addTag( $token );
  }

  public function isStandaloneTag( $isStartOfLine, $type )
  {
    return $isStartOfLine
      && $this->typeAllowsStandalone( $type )
      && $this->textMatches( $this->eolSpaceRegex() );
  }

  public function convertToStandaloneTag( MustacheTokenTag $token, $indent )
  {
    $token = $token->toStandalone();
    $token->spaceBefore = $indent;
    $token->spaceAfter  = $this->scanText( $this->eolSpaceRegex() );

    return $token;
  }

  public function handleChangeDelimiters( MustacheTokenTag $token )
  {
    if ( $token->type() == '=' )
      list( $this->openTag, $this->closeTag ) = explode( ' ', $token->content() );
  }

  public function hasTextRemaining()
  {
    return $this->scanner->hasTextRemaining();
  }

  public function isStartOfLine()
  {
    return $this->textMatches( "(?<=" . $this->newLineRegex() . ")" );
  }

  public function indentRegex()
  {
    return "[\t ]*";
  }

  public function newLineRegex()
  {
    return "\r\n|\n|^";
  }

  public function openTagRegex()
  {
    return $this->escape( $this->openTag );
  }

  public function closeTagRegex()
  {
    return $this->escape( $this->closeTag );
  }

  public function eolSpaceRegex()
  {
    return $this->indentRegex() . "(" . $this->newLineRegex() . "|$)";
  }

  public function closeTypeRegex( $type )
  {
    if ( $type == '{' )
      $type = '}';

    return '(' . $this->escape( $type ) . ')?';
  }

  public function tagTypeRegex()
  {
    return "(#|\^|\/|\<|\>|\=|\!|&|\{)?";
  }

  public function tagContentRegex( $type )
  {
    if ( $this->typeHasAnyContent( $type ) )
      return ".*?(?=" . $this->closeTypeRegex( $type ) . $this->closeTagRegex() . ")";
    else
      return '(\w|[?!\/.-])*';
  }

  public function typeHasAnyContent( $type )
  {
    return $type == '!' || $type == '=';
  }

  public function typeAllowsStandalone( $type )
  {
    return $type != '&' && $type != '{' && $type != '';
  }

  public function addText( $text )
  {
    $this->tokens->addText( $text );
  }

  public function textMatches( $regex )
  {
    return $this->scanner->textMatches( $regex );
  }

  public function scanText( $regex )
  {
    return $this->scanner->scanText( $regex );
  }

  public function escape( $text )
  {
    return $this->scanner->escape( $text );
  }
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

    if ( $match === null )
      throw new Exception( "Regex " . json_encode( $regex ) . " failed at offset $this->position" );

    $this->position += strlen( $match );

    return $match;
  }

  public function textMatches( $regex )
  {
    return $this->matchText( $regex ) !== null;
  }

  private function matchText( $regex )
  {
    preg_match( "/$regex/su", $this->string, $matches, PREG_OFFSET_CAPTURE, $this->position );

    if ( isset( $matches[0] ) && $matches[0][1] === $this->position )
      return $matches[0][0];
    else
      return null;
  }

  public function hasTextRemaining()
  {
    return $this->position < strlen( $this->string );
  }
}

