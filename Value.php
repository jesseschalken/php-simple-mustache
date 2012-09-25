<?php

abstract class MustacheValue
{
  public static function reflect( $v )
  {
    if ( is_null( $v ) )   return new MustacheValueFalsey;
    if ( is_bool( $v ) )   return $v ? new MustacheValueTruthy : new MustacheValueFalsey;
    if ( is_string( $v ) ) return new MustacheValueText( $v );
    if ( is_int( $v ) )    return new MustacheValueText( (string) $v );
    if ( is_float( $v ) )  return new MustacheValueText( (string) $v );
    if ( is_array( $v ) )  return self::reflectArray( $v );
    if ( is_object( $v ) ) return self::reflectArray( (array) $v );

    assert( false );
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

  public function hasProperty( $name )
  {
    return false;
  }

  public function property( $name )
  {
    return new MustacheValueFalsey;
  }

  public function text()
  {
    return '';
  }

  public function toList()
  {
    return array();
  }
}

final class MustacheValueFalsey extends MustacheValue
{
}

final class MustacheValueTruthy extends MustacheValue
{
  public function toList()
  {
    return array( new MustacheValueFalsey );
  }
}

final class MustacheValueText extends MustacheValue
{
  private $text;

  public function __construct( $text )
  {
    $this->text = $text;
  }

  public function text()
  {
    return $this->text;
  }
}

final class MustacheValueList extends MustacheValue
{
  private $array;

  public function __construct( array $array )
  {
    $this->array = $array;
  }

  public function toList()
  {
    return $this->array;
  }
}

final class MustacheValueObject extends MustacheValue
{
  private $object;

  public function __construct( array $object )
  {
    $this->object = $object;
  }

  public function hasProperty( $name )
  {
    return isset( $this->object[$name] );
  }

  public function property( $name )
  {
    if ( isset( $this->object[$name] ) )
      return $this->object[$name];
    else
      return parent::property( $name );
  }

  public function toList()
  {
    return array( $this );
  }
}

