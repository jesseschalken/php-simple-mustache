<?php

final class MustacheTokeniser
{
  public static function tokenise( $template )
  {
    $tokeniser = new self( $template );
    $tokeniser->process();

    assert( $tokeniser->tokens->getOriginalText() === $template );

    return $tokeniser->tokens;
  }

  private function __construct( $template )
  {
    $this->scanner = new StringScanner( $template );
    $this->tokens  = new MustacheTokenStream;
  }

  private $openTag  = '{{';
  private $closeTag = '}}';

  private $tokens;
  private $scanner;

  private function newLineRegex()
  {
    return "\r\n|\n|^";
  }

  private function closeTagRegex()
  {
    return $this->escape( $this->closeTag );
  }

  private function process()
  {
    while ( $this->doProcessStep() );
  }

  private function doProcessStep()
  {
    $this->skipToNextTagOrEof();

    $isStartOfLine = $this->isStartOfLine();
    $spaceBefore   = $this->scan( $this->indentRegex() );

    if ( !$this->matches( $this->openTagRegex() ) )
      return $this->handleNoTagsRemaining( $spaceBefore );

    $token = $this->scanSingleTag();
    $token = $this->handleStandaloneTag( $isStartOfLine, $spaceBefore, $token );

    $this->handleChangeDelimiters( $token );
    $this->tokens->addTag( $token );

    return true;
  }

  private function skipToNextTagOrEof()
  {
    $newLine = $this->newLineRegex();
    $indent  = $this->indentRegex();
    $openTag = $this->openTagRegex();

    $this->addText( $this->scan( ".*?((?<=$newLine|)(?=$indent$openTag)|$)" ) );
  }

  private function isStartOfLine()
  {
    return $this->matches( "(?<=" . $this->newLineRegex() . ")" );
  }

  private function indentRegex()
  {
    return "[\t ]*";
  }

  private function openTagRegex()
  {
    return $this->escape( $this->openTag );
  }

  private function handleNoTagsRemaining( $textToAdd = '' )
  {
    $this->addText( $textToAdd );
    $this->addText( $this->scan( '.*' ) );

    return false;
  }

  private function scanSingleTag()
  {
    $token = new MustacheTokenTag;
    $token->openTag       = $this->scan( $this->openTagRegex() );
    $token->type          = $this->scan( $this->tagTypeRegex() );
    $token->paddingBefore = $this->scan( ' *' );
    $token->content       = $this->scan( $this->tagContentRegex( $token->type ) );
    $token->paddingAfter  = $this->scan( ' *' );
    $token->closeType     = $this->scan( $this->closeTypeRegex( $token->type ) );
    $token->closeTag      = $this->scan( $this->closeTagRegex() );

    return $token;
  }

  private function handleStandaloneTag( $isStartOfLine, $spaceBefore, MustacheTokenTag $token )
  {
    if ( $this->isStandaloneTag( $isStartOfLine, $token->type ) )
      $token = $this->convertToStandalone( $token, $spaceBefore );
    else
      $this->addText( $spaceBefore );

    return $token;
  }

  private function isStandaloneTag( $isStartOfLine, $type )
  {
    return $isStartOfLine
      && $this->typeAllowsStandalone( $type )
      && $this->matches( $this->eolSpaceRegex() );
  }

  private function eolSpaceRegex()
  {
    return $this->indentRegex() . "(" . $this->newLineRegex() . "|$)";
  }

  private function convertToStandalone( MustacheTokenTag $token, $spaceBefore )
  {
    $token = $token->toStandalone();
    $token->spaceBefore = $spaceBefore;
    $token->spaceAfter  = $this->scan( $this->eolSpaceRegex() );

    return $token;
  }

  private function handleChangeDelimiters( MustacheTokenTag $token )
  {
    if ( $token->type == '=' )
      list( $this->openTag, $this->closeTag ) = explode( ' ', $token->content );
  }

  private function tagTypeRegex()
  {
    return "(#|\^|\/|\<|\>|\=|\!|&|\{)?";
  }

  private function tagContentRegex( $type )
  {
    if ( $this->typeHasAnyContent( $type ) )
      return ".*?(?=" . $this->closeTypeRegex( $type ) . $this->closeTagRegex() . ")";
    else
      return '(\w|[?!\/.-])*';
  }

  private function closeTypeRegex( $type )
  {
    if ( $type == '{' )
      $type = '}';

    return '(' . $this->escape( $type ) . ')?';
  }

  private function typeHasAnyContent( $type )
  {
    return $type == '!' || $type == '=';
  }

  private function typeAllowsStandalone( $type )
  {
    return $type != '&' && $type != '{' && $type != '';
  }

  private function addText( $text )
  {
    $this->tokens->addText( $text );
  }

  private function matches( $regex )
  {
    return $this->scanner->matches( $regex );
  }

  private function scan( $regex )
  {
    return $this->scanner->scan( $regex );
  }

  private function escape( $text )
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

  public function scan( $regex )
  {
    $match = $this->match( $regex );

    if ( $match === null )
      throw new Exception( "Regex " . json_encode( $regex ) . " failed at offset $this->position" );

    $this->position += strlen( $match );

    return $match;
  }

  public function matches( $regex )
  {
    return $this->match( $regex ) !== null;
  }

  private function match( $regex )
  {
    preg_match( "/$regex/su", $this->string, $matches, PREG_OFFSET_CAPTURE, $this->position );

    if ( isset( $matches[0] ) && $matches[0][1] === $this->position )
      return $matches[0][0];
    else
      return null;
  }
}

