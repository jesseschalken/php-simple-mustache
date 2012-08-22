<?php

abstract class MustacheNodeVisitor
{
  public abstract function visitText( MustacheNodeText $text );
  public abstract function visitComment( MustacheNodeComment $comment );
  public abstract function visitSetDelimiters( MustacheNodeSetDelimiters $setDelimiter );
  public abstract function visitPartial( MustacheNodePartial $partial );
  public abstract function visitVariable( MustacheNodeVariable $variable );
  public abstract function visitSection( MustacheNodeSection $section );
}

abstract class MustacheNode
{
  public abstract function acceptVisitor( MustacheNodeVisitor $visitor );
  public abstract function originalText();

  protected function __construct() {}
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

  public function __construct( MustacheParser $parser, MustacheParsedTag $tag )
  {
    parent::__construct( $tag );

    $contentScanner = StringScanner::create( $this->tagContent() );

    $this->openTag      = $contentScanner->scanText( '^[^ ]+' );
    $this->innerPadding = $contentScanner->scanText( ' +' );
    $this->closeTag     = $contentScanner->scanText( '[^ ]+$' );

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
  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitVariable( $this );
  }

  public abstract function isEscaped();

  public final function name()
  {
    return $this->tagContent();
  }
}

final class MustacheNodeVariableUnescaped extends MustacheNodeVariable
{
  public final function isEscaped()
  {
    return false;
  }
}

final class MustacheNodeVariableEscaped extends MustacheNodeVariable
{
  public final function isEscaped()
  {
    return true;
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

final class MustacheNodeStream extends MustacheNode implements IteratorAggregate
{
  private $nodes = array();

  public static function parse( MustacheParser $parser, &$closeSectionTag )
  {
    return new self( $parser, $closeSectionTag );
  }

  protected function __construct( MustacheParser $parser, &$closeSectionTag )
  {
    for (;;) {
      $text          = $this->scanUntilNextTagOrEof( $parser );
      $isStartOfLine = $parser->textMatches( "(?<=" . $parser->lineBoundaryRegex() . ")" );
      $indent        = $parser->scanText( ' *' );

      if ( $parser->textMatches( $parser->openTagRegex() ) ) {
        $tag = MustacheParsedTag::parse( $parser, $isStartOfLine, $indent );

        $text .= $indent;

        if ( $text !== '' )
          $this->nodes[] = MustacheNodeText::create( $parser, $text );

        if ( $tag->isCloseSectionTag() ) {
          $closeSectionTag = $tag;
          break;
        }

        $this->nodes[] = $tag->toNode( $parser );
      } else {
        $this->nodes[] = MustacheNodeText::create( $parser, $text . $indent . $parser->scanText( '.*$' ) );
        break;
      }
    }
  }

  private function scanUntilNextTagOrEof( MustacheParser $parser )
  {
    $lineBoundary = $parser->lineBoundaryRegex();
    $openTag      = $parser->openTagRegex();

    return $parser->scanText( ".*?(?<=$lineBoundary|)(?= *?$openTag|$)" );
  }

  public function originalText()
  {
    $text = '';

    foreach ( $this as $node )
      $text .= $node->originalText();

    return $text;
  }

  public function getIterator()
  {
    return new ArrayIterator( $this->nodes );
  }

  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    $results = array();

    foreach ( $this as $node )
      $results[] = $node->acceptVisitor( $visitor );

    return $results;
  }
}

final class MustacheDocument extends MustacheNode implements IteratorAggregate
{
  private $nodes;

  public static function parse( MustacheParser $parser )
  {
    return new self( $parser );
  }

  protected function __construct( MustacheParser $parser )
  {
    $closeSectionTag = null;

    $this->nodes = MustacheNodeStream::parse( $parser, $closeSectionTag );

    if ( $closeSectionTag !== null )
      throw new Exception( "Close of unopened section" );
  }

  public function originalText()
  {
    return $this->nodes->originalText();
  }

  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $this->nodes->acceptVisitor( $visitor );
  }

  public function getIterator()
  {
    return $this->nodes->getIterator();
  }

  public function nodes()
  {
    return $this->nodes;
  }
}

class MustacheNodeText extends MustacheNode
{
  private $text = '';

  public static function create( MustacheParser $parser, $text )
  {
    return new self( $text );
  }

  protected function __construct( $text )
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
  private $openTag;
  private $closeTag;

  private $sigil;
  private $closeSigil;

  private $paddingBefore;
  private $paddingAfter;

  private $content;

  private $isStandalone;

  private $spaceBefore;
  private $spaceAfter;

  public static function parse( MustacheParser $parser, $isStartOfLine, &$indent )
  {
    return new self( $parser, $isStartOfLine, $indent );
  }

  protected function __construct( MustacheParser $parser, $isStartOfLine, &$indent )
  {
    $this->openTag       = $parser->scanText( $parser->openTagRegex() );
    $this->sigil         = $parser->scanText( $this->sigilRegex() );
    $this->paddingBefore = $parser->scanText( ' *' );
    $this->content       = $parser->scanText( $this->contentRegex( $parser ) );
    $this->paddingAfter  = $parser->scanText( ' *' );
    $this->closeSigil    = $parser->scanText( $this->closeSigilRegex( $parser ) );
    $this->closeTag      = $parser->scanText( $parser->closeTagRegex() );

    $this->isStandalone = $isStartOfLine
      && $this->typeAllowsStandalone()
      && $parser->textMatches( $this->eolSpaceRegex( $parser ) );

    if ( $this->isStandalone ) {
      $this->spaceBefore = $indent;
      $this->spaceAfter  = $parser->scanText( $this->eolSpaceRegex( $parser ) );
      $indent            = '';
    } else {
      $this->spaceBefore = '';
      $this->spaceAfter  = '';
    }
  }

  private function eolSpaceRegex( MustacheParser $parser )
  {
    return " *?(" . $parser->lineBoundaryRegex() . "|$)";
  }

  public final function toNode( MustacheParser $parser )
  {
    switch ( $this->sigil ) {
      case '#':
        return new MustacheNodeSectionNormal( $parser, $this );
      case '^':
        return new MustacheNodeSectionInverted( $parser, $this );
      case '<':
      case '>':
        return new MustacheNodePartial( $this );
      case '!':
        return new MustacheNodeComment( $this );
      case '=':
        return new MustacheNodeSetDelimiters( $parser, $this );
      case '&':
      case '{':
        return new MustacheNodeVariableUnescaped( $this );
      case '':
        return new MustacheNodeVariableEscaped( $this );
      default:
        assert( false );
    }
  }

  public function isCloseSectionTag()
  {
    return $this->sigil === '/';
  }

  private function typeAllowsStandalone()
  {
    return $this->sigil !== '{'
      && $this->sigil !== '&'
      && $this->sigil !== '';
  }

  private function sigilRegex()
  {
    return "(#|\^|\/|\<|\>|\=|\!|&|\{)?";
  }

  private function contentRegex( MustacheParser $parser )
  {
    if ( $this->sigil === '!' || $this->sigil === '=' )
      return ".*?(?= *" . $this->closeSigilRegex( $parser ) . $parser->closeTagRegex() . ")";
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
}

abstract class MustacheNodeSection extends MustacheNode implements IteratorAggregate
{
  private $startTag;
  private $endTag;
  private $innerNodes;

  public function __construct( MustacheParser $parser, MustacheParsedTag $startTag )
  {
    $this->startTag   = $startTag;
    $this->innerNodes = MustacheNodeStream::parse( $parser, $this->endTag );

    if ( !isset( $this->endTag ) )
      throw new Exception( "Section left unclosed" );

    if ( $this->endTag->content() !== $this->startTag->content() )
      throw new Exception( "Open section/close section mismatch" );
  }

  public function originalText()
  {
    return $this->startTag->originalText()
      . $this->innerNodes->originalText()
      . $this->endTag->originalText();
  }

  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitSection( $this );
  }

  public function getIterator()
  {
    return $this->nodes->getIterator();
  }

  public final function name()
  {
    return $this->startTag->content();
  }

  public final function innerNodes() 
  {
    return $this->innerNodes;
  }

  public abstract function isInverted();
}

final class MustacheNodeSectionNormal extends MustacheNodeSection
{
  public final function isInverted()
  {
    return false;
  }
}

final class MustacheNodeSectionInverted extends MustacheNodeSection
{
  public final function isInverted()
  {
    return true;
  }
}

