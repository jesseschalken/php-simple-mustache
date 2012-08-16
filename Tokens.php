<?php

abstract class MustacheTokenVisitor
{
  public abstract function visitText( MustacheTokenText $token );
  public abstract function visitTag( MustacheTokenTag $token );
}

abstract class MustacheToken
{
  public abstract function visit( MustacheTokenVisitor $visitor );
  public abstract function originalText();
}

class MustacheTokenText extends MustacheToken
{
  private $text = '';

  public function addText( $text )
  {
    $this->text .= $text;
  }

  public function visit( MustacheTokenVisitor $visitor )
  {
    return $visitor->visitText( $this );
  }

  public function originalText()
  {
    return $this->text;
  }
}

class MustacheTokenTag extends MustacheToken
{
  public function visit( MustacheTokenVisitor $visitor )
  {
    return $visitor->visitTag( $this );
  }

  public function __construct( MustacheTokeniser $tokeniser, $isStartOfLine, $indent )
  {
    $this->openTag       = $tokeniser->scanText( $tokeniser->openTagRegex() );
    $this->type          = $tokeniser->scanText( $tokeniser->tagTypeRegex() );
    $this->paddingBefore = $tokeniser->scanText( ' *' );
    $this->content       = $tokeniser->scanText( $tokeniser->tagContentRegex( $this->type ) );
    $this->paddingAfter  = $tokeniser->scanText( ' *' );
    $this->closeType     = $tokeniser->scanText( $tokeniser->closeTypeRegex( $this->type ) );
    $this->closeTag      = $tokeniser->scanText( $tokeniser->closeTagRegex() );
    $this->isStandalone  = $tokeniser->isStandaloneTag( $isStartOfLine, $this->type );

    if ( $this->isStandalone )
      $this->handleStandaloneTag( $tokeniser, $indent );
    else
      $tokeniser->addText( $indent );
  }

  private function handleStandaloneTag( MustacheTokeniser $tokeniser, $indent )
  {
    $this->spaceBefore = $indent;
    $this->spaceAfter  = $tokeniser->scanText( $tokeniser->eolSpaceRegex() );
  }

  private $openTag;
  private $type;
  private $paddingBefore;
  private $content;
  private $paddingAfter;
  private $closeType;
  private $closeTag;

  private $isStandalone;

  private $spaceBefore = '';
  private $spaceAfter  = '';

  public function type()
  {
    return $this->type;
  }

  public function content()
  {
    return $this->content;
  }

  public function originalText()
  {
    return $this->spaceBefore . $this->openTag . $this->type . 
      $this->paddingBefore . $this->content . $this->paddingAfter . 
      $this->closeType . $this->closeTag . $this->spaceAfter;
  }
}

class MustacheTokenStandaloneTag extends MustacheTokenTag
{
  public function visit( MustacheTokenVisitor $visitor )
  {
    return $visitor->visitStandaloneTag( $this );
  }

  public function originalText()
  {
    return $this->spaceBefore . parent::originalText() . $this->spaceAfter;
  }
}

final class MustacheTokenStream implements IteratorAggregate
{
  private $tokens           = array();
  private $currentTextToken = null;

  public function tokens()  
  {
    return $this->tokens;
  }

  public function getIterator()
  {
    return new ArrayIterator( $this->tokens );
  }

  public function originalText()
  {
    $text = '';

    foreach ( $this as $token )
      $text .= $token->originalText();

    return $text;
  }

  public function addText( $text )
  {
    if ( $text === '' )
      return;

    if ( $this->currentTextToken === null )
      $this->currentTextToken = $this->tokens[] = new MustacheTokenText;

    $this->currentTextToken->addText( $text );
  }

  public function addTag( MustacheTokenTag $tag )
  {
    $this->currentTextToken = null;

    $this->tokens[] = $tag;
  }
}

