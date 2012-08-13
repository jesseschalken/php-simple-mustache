<?php

abstract class MustacheTokenVisitor
{
  public abstract function visitText( MustacheTokenText $token );
  public abstract function visitTag( MustacheTokenTag $token );

  public function visitStandaloneTag( MustacheTokenStandaloneTag $token )
  {
    return $this->visitTag( $token );
  }
}

abstract class MustacheToken
{
  public abstract function visit( MustacheTokenVisitor $visitor );

  public abstract function originalText();
}

class MustacheTokenText extends MustacheToken
{
  public function visit( MustacheTokenVisitor $visitor )
  {
    return $visitor->visitText( $this );
  }

  public function originalText()
  {
    return $this->text;
  }

  public $text = '';
}

class MustacheTokenTag extends MustacheToken
{
  public function visit( MustacheTokenVisitor $visitor )
  {
    return $visitor->visitTag( $this );
  }

  public $openTag;
  public $type;
  public $paddingBefore;
  public $content;
  public $paddingAfter;
  public $closeType;
  public $closeTag;

  public function originalText()
  {
    return $this->openTag . $this->type . $this->paddingBefore . $this->content 
      . $this->paddingAfter . $this->closeType . $this->closeTag;
  }

  public function toStandalone()
  {
    $token = new MustacheTokenStandaloneTag;

    $token->openTag       = $this->openTag;
    $token->type          = $this->type;
    $token->paddingBefore = $this->paddingBefore;
    $token->content       = $this->content;
    $token->paddingAfter  = $this->paddingAfter;
    $token->closeType     = $this->closeType;
    $token->closeTag      = $this->closeTag;

    return $token;
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

  public $spaceBefore;
  public $spaceAfter;
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

    $this->currentTextToken->text .= $text;
  }

  public function addTag( MustacheTokenTag $tag )
  {
    $this->currentTextToken = null;

    $this->tokens[] = $tag;
  }
}

