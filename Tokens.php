<?php

abstract class MustacheTokenVisitor
{
  public abstract function text( MustacheTokenText $token );
  public abstract function tag( MustacheTokenTag $token );

  public function tagStandalone( MustacheTokenTagStandalone $token )
  {
    return $this->tag( $token );
  }
}

abstract class MustacheToken
{
  public abstract function visit( MustacheTokenVisitor $visitor );

  public abstract function getOriginalText();
}

class MustacheTokenText extends MustacheToken
{
  public function visit( MustacheTokenVisitor $visitor )
  {
    return $visitor->text( $this );
  }

  public function getOriginalText()
  {
    return $this->text;
  }

  public $text = '';
}

class MustacheTokenTag extends MustacheToken
{
  public function visit( MustacheTokenVisitor $visitor )
  {
    return $visitor->tag( $this );
  }

  public $openTag;
  public $type;
  public $paddingBefore;
  public $content;
  public $paddingAfter;
  public $closeType;
  public $closeTag;

  public function getOriginalText()
  {
    return $this->openTag . $this->type . $this->paddingBefore . $this->content 
      . $this->paddingAfter . $this->closeType . $this->closeTag;
  }

  public function toStandalone()
  {
    $token = new MustacheTokenTagStandalone;

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

class MustacheTokenTagStandalone extends MustacheTokenTag
{
  public function visit( MustacheTokenVisitor $visitor )
  {
    return $visitor->tagStandalone( $this );
  }

  public function getOriginalText()
  {
    return $this->spaceBefore . parent::getOriginalText() . $this->spaceAfter;
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

  public function getOriginalText()
  {
    $text = '';

    foreach ( $this as $token )
      $text .= $token->getOriginalText();

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

