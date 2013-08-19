<?php
use \HodgePodge\Core\Crypt;
use \HodgePodge\Exception;

class CryptTest extends PHPUnit_Framework_TestCase
{
    function testHash() {
        $hash = Crypt::hash('ASampleString');
        $testhash = Crypt::hash('ASampleString', $hash);
        $this->assertEquals($hash, $testhash, "Expected rehash to be the same as original hash");
    }

    function testHashFail() {
        try {
            $hash = Crypt::hash('ASampleString','invalidhash');
            $this->assertFalse(true, "Expected Crypt::hash to throw exception due to invalid hash");
        }
        catch (Exception\Generic $e)
        {
        }
    }
}
