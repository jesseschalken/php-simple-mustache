<?php

abstract class MustacheValue
{
  public static function reflect( $value )
  {
    if ( $value === null )
      return new MustacheValueFalsey;
    else if ( $value === false )
      return new MustacheValueFalsey;
    else if ( $value === true )
      return new MustacheValueTruthy;
    else if ( is_scalar( $value ) )
      return new MustacheValueString( (string) $value );
    else if ( is_array( $value ) )
      return new MustacheValueArray( $value );
    else if ( is_object( $value ) )
      return new MustacheValueObject( $value );

    return new MustacheValueFalsey;
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

final class MustacheValueObject extends MustacheValue
{
  private $object;

  public function __construct( $object )
  {
    $this->object = $object;
  }

  public function hasProperty( $name )
  {
    return isset( $this->object->$name );
  }

  public function property( $name )
  {
    if ( $this->hasProperty( $name ) )
      return MustacheValue::reflect( $this->object->$name );

    return parent::property( $name );
  }

  public function toList()
  {
    return array( $this );
  }
}

final class MustacheValueString extends MustacheValue
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

final class MustacheValueArray extends MustacheValue
{
  private $array;

  public function __construct( array $array )
  {
    $this->array = $array;
  }

  public function hasProperty( $name )
  {
    return array_key_exists( $this->array, $name );
  }

  public function property( $name )
  {
    if ( $this->hasProperty( $name ) )
      return $this->array[$name];

    return parent::property( $name );
  }

  public function toList()
  {
    $values = array();

    foreach ( $this->array as $value )
      $values[] = MustacheValue::reflect( $value );

    return $values;
  }
}

