<?php

final class MustacheProcessor extends MustacheNodeVisitor
{
	private $context, $result = '', $partials;

	static function process( MustacheDocument $document, MustacheValue $value, MustachePartialProvider $partials )
	{
		$self = new self( $document, $value, $partials );

		return $self->result;
	}

	private function __construct( MustacheDocument $document, MustacheValue $value, MustachePartialProvider $partials )
	{
		$this->partials = $partials;
		$this->context  = array( $value );

		foreach ( $document->nodes() as $node )
			$node->acceptVisitor( $this );
	}

	function visitText( MustacheNodeText $text )
	{
		$this->result .= $text->text();
	}

	function visitComment( MustacheNodeComment $comment )
	{
	}

	function visitSetDelimiters( MustacheNodeSetDelimiters $setDelimiter )
	{
	}

	function visitPartial( MustacheNodePartial $partial )
	{
		$text = $this->partials->partial( $partial->name() );
		$text = self::indentText( $partial->indent(), $text );

		foreach ( MustacheParser::parse( $text )->nodes() as $node )
			$node->acceptVisitor( $this );
	}

	function visitVariableEscaped( MustacheNodeVariableEscaped $var )
	{
		$this->result .= htmlspecialchars( $this->variableText( $var ), ENT_COMPAT );
	}

	function visitVariableUnEscaped( MustacheNodeVariableUnEscaped $var )
	{
		$this->result .= $this->variableText( $var );
	}

	function visitSectionNormal( MustacheNodeSectionNormal $section )
	{
		foreach ( $this->sectionValues( $section ) as $value )
			$this->renderSectionValue( $value, $section );
	}

	function visitSectionInverted( MustacheNodeSectionInverted $section )
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

		foreach ( $section->nodes() as $node )
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

		$v = null;

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
		/** @var MustacheValue $v */
		foreach ( $context as $v )
			if ( $v->hasProperty( $p ) )
				return $v->property( $p );

		return new MustacheValueFalsey;
	}
}

