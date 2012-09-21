<?php

abstract class MustachePartialProvider
{
  public function partial( $name )
  {
    return '';
  }
}

final class MustachePartialProviderArray extends MustachePartialProvider
{
  private $partials = array();

  public function __construct( array $partials )
  {
    $this->partials = $partials;
  }

  public function partial( $name )
  {
    if ( isset( $this->partials[$name] ) )
      return $this->partials[$name];

    return parent::partial( $name );
  }
}

