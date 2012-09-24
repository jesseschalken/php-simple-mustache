<?php

final class MustacheProcessor extends MustacheNodeVisitor
{
  private $context = array();
  private $result = '';
  private $partials;

  public static function process( MustacheDocument $document, MustacheValue $value, MustachePartialProvider $partials )
  {
    $self = new self;
    $self->partials = $partials;
    $self->pushContext( $value );

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

  public function visitVariableEscaped( MustacheNodeVariableEscaped $variable )
  {
    $this->result .= htmlspecialchars( $this->variableText( $variable ), ENT_COMPAT );
  }

  public function visitVariableUnescaped( MustacheNodeVariableUnescaped $variable )
  {
    $this->result .= $this->variableText( $variable );
  }

  public function visitSectionNormal( MustacheNodeSectionNormal $section )
  {
    foreach ( $this->sectionValues( $section ) as $value )
      $this->renderSectionValue( $value, $section );
  }

  public function visitSectionInverted( MustacheNodeSectionInverted $section )
  {
    $values = $this->sectionValues( $section );

    if ( empty( $values ) )
      $this->renderSectionValue( self::falsey(), $section );
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
      if ( !isset( $value ) )
        $value = self::resolveProperty( $this->context, $part );
      else
        $value = self::resolveProperty( array( $value ), $part );

    return $value;
  }

  private function currentContext()
  {
    foreach ( $this->context as $v )
      return $v;

    return self::falsey();
  }

  private static function indentText( $indent, $text )
  {
    return preg_replace( "/(^|\r?\n)(?!$)/su", '\0' . $indent, $text );
  }

  private static function resolveProperty( array $context, $p )
  {
    foreach ( $context as $v )
      if ( $v->hasProperty( $p ) )
        return $v->property( $p );

    return self::falsey();
  }

  private static function falsey()
  {
    return new MustacheValueFalsey;
  }
}

