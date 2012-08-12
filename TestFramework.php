<?php

abstract class Test
{
  public final function run( $indent = '' )
  {
    $this->runSelf( $indent );

    foreach ( $this->getSubTests() as $test )
      $test->run( "$indent  " );
  }

  private function runSelf( $indent )
  {
    $this->printIndented( $indent, "Testing " . $this->getName() . "...\n" );

    try
    {
      $this->runTest();
    }
    catch ( Exception $e )
    {
      $this->printIndented( "$indent  ", ""
        . "\n"
        . $this->getDescription()
        . "\n"
        . $e->__toString() . "\n"
        . "\n" );
    }
  }

  private function printIndented( $indent, $text )
  {
    print $this->indent( $text, $indent );
  }

  protected function getSubTests()
  {
    return array();
  }

  protected function runTest()
  {
  }

  protected abstract function getName();
  protected abstract function getDescription();

  protected final function assertEquals( $actual, $expected )
  {
    if ( $expected !== $actual )
      $this->fail( "Got " . json_encode( $expected ) . ", expected " . json_encode( $actual ) ."." );
  }

  protected final function fail( $message = 'Test failed.' )
  {
    throw new Exception( $message );
  }

  private function indent( $text, $indent )
  {
    return preg_replace( "/\r\n(?!$)|\n(?!$)|^/su", "$0$indent", $text );
  }

}

