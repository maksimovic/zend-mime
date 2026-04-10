<?php

use PHPUnit\Framework\TestCase;

class PartTest extends TestCase
{
    /**
     * MIME part test object
     *
     * @var Zend_Mime_Part
     */
    protected $part = null;
    protected $testText;

    protected function setUp(): void
    {
        $this->testText = "safdsafsa\xE4lg \xE4\xF6gd\xFC\xE4 sd\xFCjg\xE4sdjg\xE4ld\xF6gksd\xFCgj\xE4sdfg\xF6dsj\xE4gjsd\xF6gj\xFCdfsjg\xE4dsfj\xFCdjs\xF6g kjhdkj "
                       . 'fgaskjfdh gksjhgjkdh gjhfsdghdhgksdjhg';
        $this->part = new Zend_Mime_Part($this->testText);
        $this->part->encoding = Zend_Mime::ENCODING_BASE64;
        $this->part->type = "text/plain";
        $this->part->filename = 'test.txt';
        $this->part->disposition = 'attachment';
        $this->part->charset = 'iso8859-1';
        $this->part->id = '4711';
    }

    public function testHeaders(): void
    {
        $expectedHeaders = array('Content-Type: text/plain',
                                 'Content-Transfer-Encoding: ' . Zend_Mime::ENCODING_BASE64,
                                 'Content-Disposition: attachment',
                                 'filename="test.txt"',
                                 'charset=iso8859-1',
                                 'Content-ID: <4711>');

        $actual = $this->part->getHeaders();

        foreach ($expectedHeaders as $expected) {
            $this->assertStringContainsString($expected, $actual);
        }
    }

    public function testContentEncoding(): void
    {
        // Test with base64 encoding
        $content = $this->part->getContent();
        $this->assertEquals($this->testText, base64_decode($content));
        // Test with quotedPrintable Encoding:
        $this->part->encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE;
        $content = $this->part->getContent();
        $this->assertEquals($this->testText, quoted_printable_decode($content));
        // Test with 8Bit encoding
        $this->part->encoding = Zend_Mime::ENCODING_8BIT;
        $content = $this->part->getContent();
        $this->assertEquals($this->testText, $content);
    }

    public function testStreamEncoding(): void
    {
        $testfile = realpath(__FILE__);
        $original = file_get_contents($testfile);

        // Test Base64
        $fp = fopen($testfile, 'rb');
        $this->assertTrue(is_resource($fp));
        $part = new Zend_Mime_Part($fp);
        $part->encoding = Zend_Mime::ENCODING_BASE64;
        $fp2 = $part->getEncodedStream();
        $this->assertTrue(is_resource($fp2));
        $encoded = stream_get_contents($fp2);
        fclose($fp);
        $this->assertEquals(base64_decode($encoded), $original);

        // test QuotedPrintable
        $fp = fopen($testfile, 'rb');
        $this->assertTrue(is_resource($fp));
        $part = new Zend_Mime_Part($fp);
        $part->encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE;
        $fp2 = $part->getEncodedStream();
        $this->assertTrue(is_resource($fp2));
        $encoded = stream_get_contents($fp2);
        fclose($fp);
        $this->assertEquals(quoted_printable_decode($encoded), $original);
    }

    public function testGetRawContentFromPart(): void
    {
        $this->assertEquals($this->testText, $this->part->getRawContent());
    }
}
