<?php

class MustacheNodeVisitorDumpAst extends MustacheNodeVisitor
{
  private $indentLevel = 0;

  public static function dumpDocument( MustacheDocument $document )
  {
    return join( '', $document->acceptVisitor( new self ) );
  }

  private function dump( $string )
  {
    return json_encode( (string) $string );
  }

  private function indentLine( $text )
  {
    return str_repeat( '  ', $this->indentLevel ) . $text . "\n";
  }

  public function visitText( MustacheNodeText $text )
  {
    return $this->indentLine( 'text: ' . $this->dump( $text->text() ) );
  }

  public function visitComment( MustacheNodeComment $comment )
  {
    return $this->indentLine( 'comment: ' . $this->dump( $comment->text() ) );
  }

  public function visitSetDelimiters( MustacheNodeSetDelimiters $setDelimiter )
  {
    return $this->indentLine( 'set delimiters: ' . $this->dump( $setDelimiter->openTag() ) . ', ' . $this->dump( $setDelimiter->closeTag() ) );
  }

  public function visitPartial( MustacheNodePartial $partial )
  {
    return $this->indentLine( 'partial: ' . $this->dump( $partial->name() ) );
  }

  public function visitVariable( MustacheNodeVariable $variable )
  {
    if ( $variable->isEscaped() )
      return $this->indentLine( 'variable: ' . $this->dump( $variable->name() ) );
    else
      return $this->indentLine( 'unescaped variable: ' . $this->dump( $variable->name() ) );
  }

  public function visitSection( MustacheNodeSection $section )
  {
    $result = '';

    if ( $section->isInverted() )
      $result .= $this->indentLine( 'inverted section: ' . $this->dump( $section->name() ) );
    else
      $result .= $this->indentLine( 'section: ' . $this->dump( $section->name() ) );

    $this->indentLevel++;

    $result .= join( '', $section->innerNodes()->acceptVisitor( $this ) );

    $this->indentLevel--;

    return $result;
  }
}

