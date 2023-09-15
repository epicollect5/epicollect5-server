<?php

namespace Tests\Downloads\FilenameTest;

use Tests\TestCase;

class CommonTest extends TestCase
{
    protected $dataMappingHelper;
    protected $entrySearch;
    protected $branchEntrySearch;
    protected $generateFilenameMethod;
    protected $fileCreateRepository;

    /**
     * // This method will automatically be called prior to any test cases
     */
    public function setUp()
    {
        parent::setUp();
        $this->generateFilenameMethod = self::getMethod('generateFilename');
        $this->fileCreateRepository = \Mockery::mock('\ec5\Repositories\QueryBuilder\Entry\ToFile\CreateRepository');
    }

    /**
     * Helper to get protected/private method for testing
     * todo put it in parent class and make it more dynamic??
     *
     * @param $name
     * @return mixed
     */
    protected static function getMethod($name)
    {
        $class = new \ReflectionClass('\ec5\Repositories\QueryBuilder\Entry\ToFile\CreateRepository');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Test lenght of generated filename
     *
     * @return void
     */
    public function test_it_should_be_within_system_length()
    {
        $systemMaxLength = 255;
        $form_prefix = 'form';
        $form_index = rand(1, 5);
        $branch_prefix = 'branch';
        $branch_index = rand(1, 300);

        //form name with lenght 50 (max)
        $formName = 'Far far away, behind the word mountains, far from.';

        //form name with lenght 255 (max)
        $branchName = 'Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean. A small river named Duden flows by thei';


        fwrite(STDOUT, '***** Testing download file names ************************************' . "\n");

        //generate filename (protected method, via reflection)
        $generatedFilename = $this->generateFilenameMethod
            ->invokeArgs($this->fileCreateRepository, array($form_prefix . '-' . $form_index, $formName));
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);
        fwrite(STDOUT, $generatedFilename . "\n");

        //generate filename (protected method, via reflection)
        $generatedFilename = $this->generateFilenameMethod
            ->invokeArgs($this->fileCreateRepository, array($branch_prefix . '-' . $branch_index, $branchName));
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);
        fwrite(STDOUT, $generatedFilename . "\n");


        //form name with lenght 50 (max)
        $formName = 'Person';

        //form name with lenght 255 (max)
        $branchName = 'Family Members';

        //generate filename (protected method, via reflection)
        $generatedFilename = $this->generateFilenameMethod
            ->invokeArgs($this->fileCreateRepository, array($form_prefix . '-' . $form_index, $formName));
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);
        fwrite(STDOUT, $generatedFilename . "\n");

        //generate filename (protected method, via reflection)
        $generatedFilename = $this->generateFilenameMethod
            ->invokeArgs($this->fileCreateRepository, array($branch_prefix . '-' . $branch_index, $branchName));
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);
        fwrite(STDOUT, $generatedFilename . "\n");

        fwrite(STDOUT, '**************************************************************************' . "\n");
    }
}
