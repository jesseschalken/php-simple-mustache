<?php

abstract class Test
{
	final function run( $indent = '' )
	{
		$this->runSelf( $indent );

		/** @var Test $test */
		foreach ( $this->subTests() as $test )
			$test->run( "$indent  " );
	}

	private function runSelf( $indent )
	{
		$this->printIndented( $indent, "Testing " . $this->name() . "...\n" );

		try
		{
			$this->runTest();
		}
		catch ( Exception $e )
		{
			$this->printIndented( "$indent  ", "\n" . $this->description() . "\n" . $e->__toString() . "\n\n" );
		}
	}

	private function printIndented( $indent, $text )
	{
		print $this->indent( $text, $indent );
	}

	protected function subTests()
	{
		return array();
	}

	protected function runTest()
	{
	}

	protected abstract function name();

	protected abstract function description();

	protected final function assertEquals( $actual, $expected )
	{
		if ( $expected !== $actual )
			$this->fail( "Got " . json_encode( $expected ) . ", expected " . json_encode( $actual ) . "." );
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

