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

    public function testGetHeadersArray(): void
    {
        $headers = $this->part->getHeadersArray();
        $this->assertIsArray($headers);

        $headerMap = [];
        foreach ($headers as $header) {
            $headerMap[$header[0]] = $header[1];
        }

        $this->assertStringContainsString('text/plain', $headerMap['Content-Type']);
        $this->assertStringContainsString('iso8859-1', $headerMap['Content-Type']);
        $this->assertEquals(Zend_Mime::ENCODING_BASE64, $headerMap['Content-Transfer-Encoding']);
        $this->assertEquals('<4711>', $headerMap['Content-ID']);
        $this->assertStringContainsString('attachment', $headerMap['Content-Disposition']);
        $this->assertStringContainsString('test.txt', $headerMap['Content-Disposition']);
    }

    public function testGetHeadersArrayWithDescription(): void
    {
        $part = new Zend_Mime_Part('content');
        $part->type = 'text/plain';
        $part->description = 'A test description';

        $headers = $part->getHeadersArray();
        $headerMap = [];
        foreach ($headers as $header) {
            $headerMap[$header[0]] = $header[1];
        }

        $this->assertEquals('A test description', $headerMap['Content-Description']);
    }

    public function testGetHeadersArrayWithLocation(): void
    {
        $part = new Zend_Mime_Part('content');
        $part->type = 'text/plain';
        $part->location = 'http://example.com/file';

        $headers = $part->getHeadersArray();
        $headerMap = [];
        foreach ($headers as $header) {
            $headerMap[$header[0]] = $header[1];
        }

        $this->assertEquals('http://example.com/file', $headerMap['Content-Location']);
    }

    public function testGetHeadersArrayWithLanguage(): void
    {
        $part = new Zend_Mime_Part('content');
        $part->type = 'text/plain';
        $part->language = 'en-US';

        $headers = $part->getHeadersArray();
        $headerMap = [];
        foreach ($headers as $header) {
            $headerMap[$header[0]] = $header[1];
        }

        $this->assertEquals('en-US', $headerMap['Content-Language']);
    }

    public function testGetHeadersArrayWithBoundary(): void
    {
        $part = new Zend_Mime_Part('content');
        $part->type = 'multipart/mixed';
        $part->boundary = 'myboundary123';

        $headers = $part->getHeadersArray();
        $headerMap = [];
        foreach ($headers as $header) {
            $headerMap[$header[0]] = $header[1];
        }

        $this->assertStringContainsString('myboundary123', $headerMap['Content-Type']);
        $this->assertStringContainsString('boundary=', $headerMap['Content-Type']);
    }

    public function testGetEncodedStream8bit(): void
    {
        $testfile = realpath(__FILE__);
        $original = file_get_contents($testfile);

        $fp = fopen($testfile, 'rb');
        $this->assertTrue(is_resource($fp));

        $part = new Zend_Mime_Part($fp);
        $part->encoding = Zend_Mime::ENCODING_8BIT;

        $stream = $part->getEncodedStream();
        $this->assertTrue(is_resource($stream));

        $content = stream_get_contents($stream);
        fclose($fp);

        $this->assertEquals($original, $content);
    }

    public function testGetEncodedStreamThrowsForStringContent(): void
    {
        $part = new Zend_Mime_Part('string content');
        $this->expectException(Zend_Mime_Exception::class);
        $part->getEncodedStream();
    }

    public function testIsStreamReturnsFalseForString(): void
    {
        $part = new Zend_Mime_Part('string content');
        $this->assertFalse($part->isStream());
    }

    public function testIsStreamReturnsTrueForResource(): void
    {
        $fp = fopen('php://memory', 'r');
        $part = new Zend_Mime_Part($fp);
        $this->assertTrue($part->isStream());
        fclose($fp);
    }

    public function testGetHeadersArrayMinimal(): void
    {
        $part = new Zend_Mime_Part('content');
        $part->type = 'text/plain';
        $part->encoding = null;
        $part->id = null;
        $part->disposition = null;

        $headers = $part->getHeadersArray();
        $headerNames = array_column($headers, 0);

        $this->assertContains('Content-Type', $headerNames);
        $this->assertNotContains('Content-Transfer-Encoding', $headerNames);
        $this->assertNotContains('Content-ID', $headerNames);
        $this->assertNotContains('Content-Disposition', $headerNames);
    }

    public function testGetRawContentFromStream(): void
    {
        $fp = fopen('php://memory', 'r+');
        fwrite($fp, 'stream content');
        rewind($fp);

        $part = new Zend_Mime_Part($fp);
        $this->assertEquals('stream content', $part->getRawContent());
        fclose($fp);
    }

    public function testGetContentFromStream(): void
    {
        $fp = fopen('php://memory', 'r+');
        fwrite($fp, 'stream content');
        rewind($fp);

        $part = new Zend_Mime_Part($fp);
        $part->encoding = Zend_Mime::ENCODING_BASE64;

        $content = $part->getContent();
        $this->assertEquals('stream content', base64_decode($content));
        fclose($fp);
    }
}
