<?php

abstract class MustachePartialProvider
{
	function partial( $name )
	{
		return '';
	}
}

final class MustachePartialProviderArray extends MustachePartialProvider
{
	private $partials = array();

	function __construct( array $partials )
	{
		$this->partials = $partials;
	}

	function partial( $name )
	{
		if ( isset( $this->partials[$name] ) )
			return $this->partials[$name];

		return parent::partial( $name );
	}
}

