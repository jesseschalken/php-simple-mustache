<?php

class MustacheTest extends Test
{
  protected function name()
  {
    return 'Official Mustache spec';
  }

  protected function description()
  {
    return "These are the official mustache specs\n";
  }

  protected function subTests()
  {
    $tests = array();

    foreach ( $this->specFiles() as $file )
    {
      $test = new MustacheSpecFile;
      $test->specFileName = $file->getFilename();
      $test->specFileJson = json_decode( file_get_contents( $file->getPathname() ) );
      $tests[] = $test;
    }

    return $tests;
  }

  private function specFiles()
  {
    return new RegexIterator( new DirectoryIterator( dirname( __FILE__ ) . "/spec/specs" ), '/^[^~].*\.json$/' );
  }
}

class MustacheSpecFile extends Test
{
  public $specFileName;
  public $specFileJson;

  protected function name()
  {
    return $this->specFileName;
  }

  protected function description()
  {
    return $this->specFileJson->overview;
  }

  protected function subTests()
  {
    $tests = array();

    foreach ( $this->specFileJson->tests as $jsonTest )
    {
      $test = new MustacheTestCase;
      $test->jsonTest = $jsonTest;
      $tests[] = $test;
    }

    return $tests;
  }
}

class MustacheTestCase extends Test
{
  public $jsonTest;

  protected function name()
  {
    return $this->jsonTest->name;
  }

  protected function description()
  {
    return $this->jsonTest->desc . "\n";
  }

  protected function runTest()
  {
    $data     = $this->jsonTest->data;
    $template = $this->jsonTest->template;
    $expected = $this->jsonTest->expected;
    $result   = Mustache::run( $template, $data );

    // $this->assertEquals( $expected, $result );
  }
}
