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

  protected function __construct( MustacheParsedTag $tag )
  {
    $this->tag = $tag;
  }

  protected final function tagContent()
  {
    return $this->tag->content();
  }

  public final function originalText()
  {
    return $this->tag->originalText();
  }
}

final class MustacheNodeComment extends MustacheNodeTag
{
  public static function create( MustacheParsedTag $tag )
  {
    return new self( $tag );
  }

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

  public static function create( MustacheParser $parser, MustacheParsedTag $tag )
  {
    $tag = new self( $tag );

    $contentScanner = StringScanner::create( $tag->tagContent() );

    $tag->openTag      = $contentScanner->scanText( "^\S+" );
    $tag->innerPadding = $contentScanner->scanText( "\s+" );
    $tag->closeTag     = $contentScanner->scanText( "\S+$" );

    $parser->setDelimiters( $tag->openTag, $tag->closeTag );

    return $tag;
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

final class MustacheNodeVariable extends MustacheNodeTag
{
  public static function create( MustacheParsedTag $tag, $isEscaped )
  {
    $variable = new self( $tag );
    $variable->isEscaped = $isEscaped;
    return $variable;
  }

  public function acceptVisitor( MustacheNodeVisitor $visitor )
  {
    return $visitor->visitVariable( $this );
  }

  public final function isEscaped()
  {
    return $this->isEscaped;
  }

  public final function name()
  {
    return $this->tagContent();
  }
}

final class MustacheNodePartial extends MustacheNodeTag
{
  public static function create( MustacheParsedTag $tag )
  {
    return new self( $tag );
  }

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
  private $nodes = array();

  public static function parse( MustacheParser $parser, &$closeSectionTag )
  {
    $nodes = array();

    for (;;) {
      $text          = self::scanUntilNextTagOrEof( $parser );
      $isStartOfLine = $parser->textMatches( "(?<=" . $parser->lineBoundaryRegex() . ")" );
      $indent        = $parser->scanText( "\s*" );

      if ( $parser->textMatches( $parser->openTagRegex() ) ) {
        $tag = MustacheParsedTag::parse( $parser, $isStartOfLine, $indent );

        $text .= $indent;

        if ( $text !== '' )
          $nodes[] = MustacheNodeText::create( $parser, $text );

        if ( $tag->isCloseSectionTag() ) {
          $closeSectionTag = $tag;
          break;
        }

        $nodes[] = $tag->toNode( $parser );
      } else {
        $nodes[] = MustacheNodeText::create( $parser, $text . $indent . $parser->scanText( '.*$' ) );
        break;
      }
    }

    return new self( $nodes );
  }

  private static function scanUntilNextTagOrEof( MustacheParser $parser )
  {
    $lineBoundary = $parser->lineBoundaryRegex();
    $openTag      = $parser->openTagRegex();

    return $parser->scanText( ".*?(?<=$lineBoundary|)(?=\s*?$openTag|$)" );
  }

  private function __construct( Array $nodes )
  {
    $this->nodes = $nodes;
  }

  public function originalText()
  {
    $result = '';

    foreach ( $this as $node )
      $result .= $node->originalText();

    return $result;
  }

  public function getIterator()
  {
    return new ArrayIterator( $this->nodes );
  }

  public function iterateVisitor( MustacheNodeVisitor $visitor )
  {
    $results = array();

    foreach ( $this as $node )
      $results[] = $node->acceptVisitor( $visitor );

    return $results;
  }
}

final class MustacheDocument implements IteratorAggregate
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

  public function iterateVisitor( MustacheNodeVisitor $visitor )
  {
    return $this->nodes->iterateVisitor( $visitor );
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
    $textNode = new self;
    $textNode->text = $text;
    return $textNode;
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
    $tag = new self;

    $tag->openTag       = $parser->scanText( $parser->openTagRegex() );
    $tag->sigil         = $parser->scanText( $tag->sigilRegex() );
    $tag->paddingBefore = $parser->scanText( "\s*" );
    $tag->content       = $parser->scanText( $tag->contentRegex( $parser ) );
    $tag->paddingAfter  = $parser->scanText( "\s*" );
    $tag->closeSigil    = $parser->scanText( $tag->closeSigilRegex( $parser ) );
    $tag->closeTag      = $parser->scanText( $parser->closeTagRegex() );

    $tag->isStandalone = $isStartOfLine
      && $tag->typeAllowsStandalone()
      && $parser->textMatches( $tag->eolSpaceRegex( $parser ) );

    if ( $tag->isStandalone ) {
      $tag->spaceBefore = $indent;
      $tag->spaceAfter  = $parser->scanText( $tag->eolSpaceRegex( $parser ) );
      $indent           = '';
    } else {
      $tag->spaceBefore = '';
      $tag->spaceAfter  = '';
    }

    return $tag;
  }

  private function __construct() {}

  private function eolSpaceRegex( MustacheParser $parser )
  {
    return "\s*?" . $parser->lineBoundaryRegex();
  }

  public final function toNode( MustacheParser $parser )
  {
    switch ( $this->sigil ) {
      case '#':
        return MustacheNodeSection::parse( $parser, $this, false );
      case '^':
        return MustacheNodeSection::parse( $parser, $this, true );
      case '<':
      case '>':
        return MustacheNodePartial::create( $this );
      case '!':
        return MustacheNodeComment::create( $this );
      case '=':
        return MustacheNodeSetDelimiters::create( $parser, $this );
      case '&':
      case '{':
        return MustacheNodeVariable::create( $this, false );
      case '':
        return MustacheNodeVariable::create( $this, true );
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
}

final class MustacheNodeSection extends MustacheNode implements IteratorAggregate
{
  private $startTag;
  private $endTag;

  private $isInverted;
  private $innerNodes;

  public static function parse( MustacheParser $parser, MustacheParsedTag $startTag, $isInverted )
  {
    $section = new self;

    $section->startTag   = $startTag;
    $section->endTag     = null;
    $section->isInverted = $isInverted;
    $section->innerNodes = MustacheNodeStream::parse( $parser, $section->endTag );

    if ( $section->endTag === null )
      throw new Exception( "Section left unclosed" );

    if ( $section->endTag->content() !== $section->startTag->content() )
      throw new Exception( "Open section/close section mismatch" );

    return $section;
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

  public function isInverted()
  {
    return $this->isInverted;
  }
}

