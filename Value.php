<?php

abstract class MustacheValue
{
	static function reflect( $v )
	{
		if ( is_null( $v ) )
			return new MustacheValueFalsey;

		if ( is_bool( $v ) )
			return $v ? new MustacheValueTruthy : new MustacheValueFalsey;

		if ( is_string( $v ) )
			return new MustacheValueText( $v );

		if ( is_int( $v ) )
			return new MustacheValueText( (string) $v );

		if ( is_float( $v ) )
			return new MustacheValueText( (string) $v );

		if ( is_array( $v ) )
			return self::reflectArray( $v );

		if ( is_object( $v ) )
			return self::reflectArray( (array) $v );

		throw new Exception( "Unhandled type: " . gettype( $v ) );
	}

	private static function reflectArray( array $array )
	{
		foreach ( $array as &$v )
			$v = self::reflect( $v );

		if ( array_values( $array ) === $array )
			return new MustacheValueList( $array );
		else
			return new MustacheValueObject( $array );
	}

	function hasProperty( $name )
	{
		return false;
	}

	function property( $name )
	{
		return new MustacheValueFalsey;
	}

	function text()
	{
		return '';
	}

	function toList()
	{
		return array();
	}
}

final class MustacheValueFalsey extends MustacheValue
{
}

final class MustacheValueTruthy extends MustacheValue
{
	function toList()
	{
		return array( new MustacheValueFalsey );
	}
}

final class MustacheValueText extends MustacheValue
{
	private $text;

	function __construct( $text )
	{
		$this->text = $text;
	}

	function text()
	{
		return $this->text;
	}
}

final class MustacheValueList extends MustacheValue
{
	private $array;

	function __construct( array $array )
	{
		$this->array = $array;
	}

	function toList()
	{
		return $this->array;
	}
}

final class MustacheValueObject extends MustacheValue
{
	private $object;

	function __construct( array $object )
	{
		$this->object = $object;
	}

	function hasProperty( $name )
	{
		return isset( $this->object[ $name ] );
	}

	function property( $name )
	{
		if ( isset( $this->object[ $name ] ) )
			return $this->object[ $name ];
		else
			return parent::property( $name );
	}

	function toList()
	{
		return array( $this );
	}
}

