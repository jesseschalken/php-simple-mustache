<?php

final class MustacheProcessor extends MustacheNodeVisitor
{
  private $context;
  private $partials;

  public static function process( MustacheDocument $document, MustacheValue $value, MustachePartialProvider $partials )
  {
    return join( '', $document->iterateVisitor( new self( $value, $partials ) ) );
  }

  private function __construct( MustacheValue $value, MustachePartialProvider $partials )
  {
    $this->context  = MustacheContext::create()->push( $value );
    $this->partials = $partials;
  }

  public function visitText( MustacheNodeText $text )
  {
    return $text->text();
  }

  public function visitComment( MustacheNodeComment $comment )
  {
    return '';
  }

  public function visitSetDelimiters( MustacheNodeSetDelimiters $setDelimiter )
  {
    return '';
  }

  public function visitPartial( MustacheNodePartial $partial )
  {
    $document = MustacheParser::parse( $this->partials->partial( $partial->name() ) );

    return join( '', $document->iterateVisitor( $this ) );
  }

  private function resolveName( $name )
  {
    return $this->context->resolveName( $name );
  }

  private function variableText( MustacheNodeVariable $var )
  {
    return $this->resolveName( $var->name() )->text();
  }

  public function visitVariableEscaped( MustacheNodeVariableEscaped $variable )
  {
    return htmlspecialchars( $this->variableText( $variable ), ENT_COMPAT );
  }

  public function visitVariableUnescaped( MustacheNodeVariableUnescaped $variable )
  {
    return $this->variableText( $variable );
  }

  private function renderSectionValue( MustacheValue $value, MustacheNodeSection $section )
  {
    $this->context->push( $value );

    $result = join( '', $section->iterateVisitor( $this ) );

    $this->context->pop();

    return $result;
  }

  private function renderSectionValues( array $values, MustacheNodeSection $section )
  {
    $result = '';

    foreach ( $values as $value )
      $result .= $this->renderSectionValue( $value, $section );

    return $result;
  }

  private function sectionValues( MustacheNodeSection $section )
  {
    return $this->resolveName( $section->name() )->toList();
  }

  public function visitSectionNormal( MustacheNodeSectionNormal $section )
  {
    return $this->renderSectionValues( $this->sectionValues( $section ), $section );
  }

  public function visitSectionInverted( MustacheNodeSectionInverted $section )
  {
    $values = $this->sectionValues( $section );

    if ( empty( $values ) )
      $values = array( new MustacheValueFalse );
    else
      $values = array();

    return $this->renderSectionValues( $values, $section );
  }
}

final class MustacheContext
{
  public static function create()
  {
    return new self;
  }

  private function __construct() {}

  private $values = array();

  public final function push( MustacheValue $v )
  {
    array_unshift( $this->values, $v );

    return $this;
  }

  public final function pop()
  {
    if ( empty( $this->values ) )
      return $this->undefined();
    else
      return array_shift( $this->values );
  }

  public final function resolveName( $name )
  {
    if ( $name === '.' )
      return $this->top();

    $nameParts = explode( '.', $name );

    $value = $this->resolveProperty( array_shift( $nameParts ) );

    foreach ( $nameParts as $part )
      $value = self::create()->push( $value )->resolveProperty( $part );

    return $value;
  }

  private final function resolveProperty( $p )
  {
    foreach ( $this->values as $v )
      if ( $v->hasProperty( $p ) )
        return $v->property( $p );

    return $this->undefined();
  }

  private final function undefined()
  {
    return new MustacheValueFalse;
  }

  private final function top()
  {
    foreach ( $this->values as $v )
      return $v;

    return $this->undefined();
  }
}

