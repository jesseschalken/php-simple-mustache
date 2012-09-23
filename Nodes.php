<?php

abstract class MustacheNodeVisitor
{
  public abstract function visitText( MustacheNodeText $text );
  public abstract function visitComment( MustacheNodeComment $comment );
  public abstract function visitSetDelimiters( MustacheNodeSetDelimiters $setDelimiter );

  public abstract function visitPartial( MustacheNodePartial $partial );

  public abstract function visitVariableEscaped( MustacheNodeVariableEscaped $variable );
  public abstract function visitVariableUnescaped( MustacheNodeVariableUnescaped $variable );

  public abstract function visitSectionNormal( MustacheNodeSectionNormal $section );
  public abstract function visitSectionInverted( MustacheNodeSectionInverted $section );
}

abstract class MustacheNode
{
  public abstract function acceptVisitor( MustacheNodeVisitor $visitor );
  public abstract function originalText();

  public function __construct() {}
}

abstract class MustacheNodeTag extends MustacheNode
{
  private $tag;

  public function __construct( MustacheParsedTag $tag )
  {
    $this->tag = $tag;
  }

  protected final function tagContent()
  {
    return $this->tag->content();
  }

  public function originalText()
  {
    return $this->tag->originalText();
  }

  public final function indent()
  {
    return $this->tag->indent();
  }
}

final class MustacheNodeComment extends MustacheNodeTag
{
  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitComment( $this );
  }

  public final function text()
  {
    return $this->tagContent();
  }
}

final class MustacheNodeSetDelimiters extends MustacheNodeTag
{
  private $openTag;
  private $innerPadding;
  private $closeTag;

  public function __construct( MustacheParsedTag $tag, MustacheParser $parser )
  {
    parent::__construct( $tag );

    $contentScanner = new StringScanner( $this->tagContent() );

    $this->openTag      = $contentScanner->scanText( "^\S+" );
    $this->innerPadding = $contentScanner->scanText( "\s+" );
    $this->closeTag     = $contentScanner->scanText( "\S+$" );

    $parser->setDelimiters( $this->openTag, $this->closeTag );
  }

  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitSetDelimiters( $this );
  }

  public final function openTag()
  {
    return $this->openTag;
  }

  public final function closeTag()
  {
    return $this->closeTag;
  }
}

abstract class MustacheNodeVariable extends MustacheNodeTag
{
  public final function name()
  {
    return $this->tagContent();
  }
}

class MustacheNodeVariableEscaped extends MustacheNodeVariable
{
  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitVariableEscaped( $this );
  }
}

class MustacheNodeVariableUnescaped extends MustacheNodeVariable
{
  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitVariableUnescaped( $this );
  }
}

final class MustacheNodePartial extends MustacheNodeTag
{
  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitPartial( $this );
  }

  public final function name()
  {
    return $this->tagContent();
  }
}

final class MustacheNodeStream implements IteratorAggregate
{
  private $nodes           = array();
  private $closeSectionTag = array();

  public function __construct( MustacheParser $parser )
  {
    while ( !$this->finished( $parser ) )
      $this->parseOneTag( $parser );

    if ( !$this->closeSectionTag )
      $this->addText( $parser->scanText( '.*$' ) );
  }

  private function finished( MustacheParser $parser )
  {
    return $this->closeSectionTag || !$parser->textMatches( '.*' . $parser->openTagRegex() );
  }

  private function parseOneTag( MustacheParser $parser )
  {
    $tag = new MustacheParsedTag( $parser, $text );

    $this->addText( $text );
    $this->addTag( $parser, $tag );
  }

  private function addTag( MustacheParser $parser, MustacheParsedTag $tag )
  {
    if ( $tag->isCloseSectionTag() )
      $this->closeSectionTag[] = $tag;
    else
      $this->nodes[] = $tag->toNode( $parser );
  }

  private function addText( $text )
  {
    if ( $text !== '' )
      $this->nodes[] = new MustacheNodeText( $text );
  }

  public function originalText()
  {
    $result = '';

    foreach ( $this as $node )
      $result .= $node->originalText();

    foreach ( $this->closeSectionTag as $tag )
      $result .= $tag->originalText();

    return $result;
  }

  public function getIterator()
  {
    return new ArrayIterator( $this->nodes );
  }

  public function closeSectionTag()
  {
    return $this->closeSectionTag;
  }
}

final class MustacheDocument implements IteratorAggregate
{
  private $nodes;

  public function __construct( MustacheParser $parser )
  {
    $this->nodes = new MustacheNodeStream( $parser );

    foreach ( $this->nodes->closeSectionTag() as $tag )
      throw new Exception( "Close of unopened section" );
  }

  public function originalText()
  {
    return $this->nodes->originalText();
  }

  public function getIterator()
  {
    return $this->nodes->getIterator();
  }
}

class MustacheNodeText extends MustacheNode
{
  private $text = '';

  public function __construct( $text )
  {
    $this->text = $text;
  }

  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitText( $this );
  }

  public function text()
  {
    return $this->text;
  }

  public function originalText()
  {
    return $this->text;
  }
}

final class MustacheParsedTag
{
  private $spaceBefore;
  private $openTag;
  private $sigil;
  private $paddingBefore;
  private $content;
  private $paddingAfter;
  private $closeSigil;
  private $closeTag;
  private $spaceAfter;

  private $isStandalone;

  public function __construct( MustacheParser $parser, &$textBefore )
  {
    $lineBoundary = $parser->lineBoundaryRegex();

    $textBefore = $this->scanUntilNextTag( $parser );

    $this->isStandalone  = $parser->textMatches( "(?<=$lineBoundary)" );
    $this->spaceBefore   = $parser->scanText( "\s*" );
    $this->openTag       = $parser->scanText( $parser->openTagRegex() );
    $this->sigil         = $parser->scanText( $this->sigilRegex() );
    $this->paddingBefore = $parser->scanText( "\s*" );
    $this->content       = $parser->scanText( $this->contentRegex( $parser ) );
    $this->paddingAfter  = $parser->scanText( "\s*" );
    $this->closeSigil    = $parser->scanText( $this->closeSigilRegex( $parser ) );
    $this->closeTag      = $parser->scanText( $parser->closeTagRegex() );

    $this->isStandalone =
      $this->isStandalone &&
      $this->typeAllowsStandalone() &&
      $parser->textMatches( "\s*?($lineBoundary)" );

    if ( $this->isStandalone ) {
      $this->spaceAfter = $parser->scanText( "\s*?($lineBoundary)" );
    } else {
      $textBefore .= $this->spaceBefore;
      $this->spaceBefore = '';
      $this->spaceAfter  = '';
    }
  }

  private function scanUntilNextTag( MustacheParser $parser )
  {
    $lineBoundary = $parser->lineBoundaryRegex();
    $openTag      = $parser->openTagRegex();

    return $parser->scanText( ".*?(?=\s*$openTag)(\s*($lineBoundary))?" );
  }

  public final function toNode( MustacheParser $parser )
  {
    switch ( $this->sigil ) {
      case '#' : return new MustacheNodeSectionNormal( $this, $parser );
      case '^' : return new MustacheNodeSectionInverted( $this, $parser );
      case '<' : return new MustacheNodePartial( $this );
      case '>' : return new MustacheNodePartial( $this );
      case '!' : return new MustacheNodeComment( $this );
      case '=' : return new MustacheNodeSetDelimiters( $this, $parser );
      case '&' : return new MustacheNodeVariableUnescaped( $this );
      case '{' : return new MustacheNodeVariableUnescaped( $this );
      case ''  : return new MustacheNodeVariableEscaped( $this );
      default  : assert( false );
    }
  }

  public function isCloseSectionTag()
  {
    return $this->sigil == '/';
  }

  private function typeAllowsStandalone()
  {
    return $this->sigil != '{'
      && $this->sigil != '&'
      && $this->sigil != '';
  }

  private function sigilRegex()
  {
    return "(#|\^|\/|\<|\>|\=|\!|&|\{)?";
  }

  private function contentRegex( MustacheParser $parser )
  {
    if ( $this->sigil == '!' || $this->sigil == '=' )
      return ".*?(?=\s*" . $this->closeSigilRegex( $parser ) . $parser->closeTagRegex() . ")";
    else
      return '(\w|[?!\/.-])*';
  }

  private function closeSigilRegex( MustacheParser $parser )
  {
    return '(' . $parser->escape( $this->sigil === '{' ? '}' : $this->sigil ) . ')?';
  }

  public function content()
  {
    return $this->content;
  }

  public function originalText()
  {
    return $this->spaceBefore
      . $this->openTag
      . $this->sigil
      . $this->paddingBefore
      . $this->content
      . $this->paddingAfter
      . $this->closeSigil
      . $this->closeTag
      . $this->spaceAfter;
  }

  public function indent()
  {
    return $this->spaceBefore;
  }
}

abstract class MustacheNodeSection extends MustacheNodeTag implements IteratorAggregate
{
  private $nodes;

  public function __construct( MustacheParsedTag $startTag, MustacheParser $parser )
  {
    parent::__construct( $startTag );

    $this->nodes = new MustacheNodeStream( $parser );

    foreach ( $this->nodes->closeSectionTag() as $tag ) {
      if ( $tag->content() != $this->tagContent() )
        throw new Exception( "Open section/close section mismatch" );

      return;
    }

    throw new Exception( "Section left unclosed" );
  }

  public function originalText()
  {
    return parent::originalText() . $this->nodes->originalText();
  }

  public function getIterator()
  {
    return $this->nodes->getIterator();
  }

  public final function name()
  {
    return $this->tagContent();
  }
}

final class MustacheNodeSectionNormal extends MustacheNodeSection
{
  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitSectionNormal( $this );
  }
}

final class MustacheNodeSectionInverted extends MustacheNodeSection
{
  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitSectionInverted( $this );
  }
}

