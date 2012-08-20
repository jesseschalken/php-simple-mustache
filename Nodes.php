<?php

abstract class MustacheNodeVisitor
{
  public abstract function visitText( MustacheNodeText $node );
  public abstract function visitTag( MustacheNodeTag $node );
  public abstract function visitSection( MustacheNodeSection $node );
}

abstract class MustacheNode
{
  public abstract function accept( MustacheNodeVisitor $visitor );
  public abstract function originalText();

  protected function __construct() {}
}

abstract class MustacheNodeStream extends MustacheNode
{
  private $nodes = array();

  protected function __construct( MustacheParser $parser )
  {
    for (;;) {
      $this->addText( $this->parseTilNextTagOrEof( $parser ) );

      $isStartOfLine = $parser->textMatches( "(?<=" . $parser->newLineRegex() . ")" );
      $indent        = $parser->scanText( $parser->indentRegex() );

      if ( $parser->textMatches( $parser->openTagRegex() ) ) {
        $tag = MustacheNodeTag::parse( $parser, $this, $isStartOfLine, $indent );

        if ( $tag->type() == '=' )
          $parser->handleSetDelimiters( $tag->content() );

        if ( $tag->type() == '/' )
          return $this->handleCloseSectionTag( $tag );
        else if ( $tag->type() == '#' || $tag->type() == '^' )
          $this->addNode( MustacheNodeSection::parse( $parser, $tag ) );
        else
          $this->addNode( $tag );
      } else
        return $this->addText( $indent . $parser->scanText( '.*' ) );
    }
  }

  public function parseTilNextTagOrEof( MustacheParser $parser )
  {
    $newLine = $parser->newLineRegex();
    $indent  = $parser->indentRegex();
    $openTag = $parser->openTagRegex();

    return $parser->scanText( ".*?(?<=$newLine|)(?=$indent$openTag|$)" );
  }

  protected abstract function handleCloseSectionTag( MustacheNodeTag $tag );

  public function originalText()
  {
    $text = '';

    foreach ( $this->nodes as $node )
      $text .= $node->originalText();

    return $text;
  }

  public function accept( MustacheNodeVisitor $visitor )
  {
    $results = array();

    foreach ( $this->nodes as $node )
      $results[] = $node->accept( $visitor );

    return $results;
  }

  public function addText( $text )
  {
    if ( $text !== '' )
      $this->addNode( MustacheNodeText::create( $text ) );
  }

  public function addNode( MustacheNode $node )
  {
    $this->nodes[] = $node;
  }
}

final class MustacheNodeDocument extends MustacheNodeStream
{
  public static function parse( MustacheParser $parser )
  {
    return new self( $parser );
  }

  protected function handleCloseSectionTag( MustacheNodeTag $tag )
  {
    throw new Exception( "Unopened section was closed" );
  }
}

class MustacheNodeText extends MustacheNode
{
  private $text = '';

  public static function create( $text )
  {
    return new self( $text );
  }

  protected function __construct( $text )
  {
    $this->text = $text;
  }

  public function accept( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitText( $this );
  }

  public function originalText()
  {
    return $this->text;
  }
}

class MustacheNodeTag extends MustacheNode
{
  public function accept( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitTag( $this );
  }

  public static function parse( MustacheParser $parser, MustacheNodeStream $container, $isStartOfLine, $indent )
  {
    $tag = new self;
    $tag->openTag       = $parser->scanText( $parser->openTagRegex() );
    $tag->type          = $parser->scanText( $parser->tagTypeRegex() );
    $tag->paddingBefore = $parser->scanText( ' *' );
    $tag->content       = $parser->scanText( $parser->tagContentRegex( $tag->type ) );
    $tag->paddingAfter  = $parser->scanText( ' *' );
    $tag->closeType     = $parser->scanText( $parser->closeTypeRegex( $tag->type ) );
    $tag->closeTag      = $parser->scanText( $parser->closeTagRegex() );

    $tag->isStandalone = $isStartOfLine
      && $tag->type != '&'
      && $tag->type != '{'
      && $tag->type != ''
      && $parser->textMatches( $parser->eolSpaceRegex() );

    if ( $tag->isStandalone ) {
      $tag->spaceBefore = $indent;
      $tag->spaceAfter  = $parser->scanText( $parser->eolSpaceRegex() );
    } else {
      $container->addText( $indent );
    }

    return $tag;
  }

  private $openTag;
  private $type;
  private $paddingBefore;
  private $content;
  private $paddingAfter;
  private $closeType;
  private $closeTag;

  private $isStandalone = false;

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

final class MustacheNodeSection extends MustacheNodeStream
{
  private $startTag;
  private $endTag;

  public static function parse( MustacheParser $parser, MustacheNodeTag $startTag )
  {
    return new self( $parser, $startTag );
  }

  protected function __construct( MustacheParser $parser, MustacheNodeTag $startTag )
  {
    $this->startTag  = $startTag;

    parent::__construct( $parser );

    if ( $this->endTag === null )
      throw new Exception( "Section left unclosed" );

    if ( $this->endTag->content() !== $this->startTag->content() )
      throw new Exception( "Open section/close section mismatch" );
  }

  protected function handleCloseSectionTag( MustacheNodeTag $tag )
  {
    $this->endTag = $tag;
  }

  public function originalText()
  {
    return $this->startTag->originalText() . parent::originalText() . $this->endTag->originalText();
  }
}

