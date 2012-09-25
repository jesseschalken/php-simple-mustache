<?php

final class MustacheProcessor extends MustacheNodeVisitor
{
  private $context = array();
  private $result = '';
  private $partials;

  public static function process( MustacheDocument $document, MustacheValue $value, MustachePartialProvider $partials )
  {
    $self           = new self;
    $self->partials = $partials;
    $self->context  = array( $value );

    foreach ( $document as $node )
      $node->acceptVisitor( $self );

    return $self->result;
  }

  private function __construct() {}

  public function visitText( MustacheNodeText $text )
  {
    $this->result .= $text->text();
  }

  public function visitComment( MustacheNodeComment $comment )
  {
  }

  public function visitSetDelimiters( MustacheNodeSetDelimiters $setDelimiter )
  {
  }

  public function visitPartial( MustacheNodePartial $partial )
  {
    $text = $this->partials->partial( $partial->name() );
    $text = self::indentText( $partial->indent(), $text );

    foreach ( MustacheParser::parse( $text ) as $node )
      $node->acceptVisitor( $this );
  }

  public function visitVariableEscaped( MustacheNodeVariableEscaped $var )
  {
    $this->result .= htmlspecialchars( $this->variableText( $var ), ENT_COMPAT );
  }

  public function visitVariableUnescaped( MustacheNodeVariableUnescaped $var )
  {
    $this->result .= $this->variableText( $var );
  }

  public function visitSectionNormal( MustacheNodeSectionNormal $section )
  {
    foreach ( $this->sectionValues( $section ) as $value )
      $this->renderSectionValue( $value, $section );
  }

  public function visitSectionInverted( MustacheNodeSectionInverted $section )
  {
    foreach ( $this->sectionValues( $section ) as $v )
      return;

    $this->renderSectionValue( new MustacheValueFalsey, $section );
  }

  private function sectionValues( MustacheNodeSection $section )
  {
    return $this->resolveName( $section->name() )->toList();
  }

  private function renderSectionValue( MustacheValue $value, MustacheNodeSection $section )
  {
    $this->pushContext( $value );

    foreach ( $section as $node )
      $node->acceptVisitor( $this );

    $this->popContext();
  }

  private function variableText( MustacheNodeVariable $var )
  {
    return $this->resolveName( $var->name() )->text();
  }

  private function pushContext( MustacheValue $v )
  {
    array_unshift( $this->context, $v );
  }

  private function popContext()
  {
    array_shift( $this->context );
  }

  private function resolveName( $name )
  {
    if ( $name === '.' )
      return $this->currentContext();

    foreach ( explode( '.', $name ) as $part )
      $v = self::resolveProperty( isset( $v ) ? array( $v ) : $this->context, $part );

    return $v;
  }

  private function currentContext()
  {
    foreach ( $this->context as $v )
      return $v;

    return new MustacheValueFalsey;
  }

  private static function indentText( $indent, $text )
  {
    return preg_replace( "/(?<=^|\r\n|\n)(?!$)/su", addcslashes( $indent, '\\$' ), $text );
  }

  private static function resolveProperty( array $context, $p )
  {
    foreach ( $context as $v )
      if ( $v->hasProperty( $p ) )
        return $v->property( $p );

    return new MustacheValueFalsey;
  }
}

