<?php

class MustacheNodeVisitorDumpAst extends MustacheNodeVisitor
{
  private $indentLevel = 0;

  public static function dumpDocument( MustacheDocument $document )
  {
    return join( '', $document->iterateVisitor( new self ) );
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

  public function visitVariableEscaped( MustacheNodeVariableEscaped $variable )
  {
    return $this->indentLine( 'variable: ' . $this->dump( $variable->name() ) );
  }

  public function visitVariableUnescaped( MustacheNodeVariableUnescaped $variable )
  {
    return $this->indentLine( 'unescaped variable: ' . $this->dump( $variable->name() ) );
  }

  public function visitSectionNormal( MustacheNodeSectionNormal $section )
  {
    return $this->indentLine( 'section: ' . $this->dump( $section->name() ) ) . $this->dumpSection( $section );
  }

  public function visitSectionInverted( MustacheNodeSectionInverted $section )
  {
    return $this->indentLine( 'inverted section: ' . $this->dump( $section->name() ) ) . $this->dumpSection( $section );
  }

  private function dumpSection( MustacheNodeSection $section )
  {
    $this->indentLevel++;

    $result = join( '', $section->iterateVisitor( $this ) );

    $this->indentLevel--;

    return $result;
  }
}

