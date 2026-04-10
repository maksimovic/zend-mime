<?php

use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testMultiPart(): void
    {
        $msg = new Zend_Mime_Message();  // No Parts
        $this->assertFalse($msg->isMultiPart());
    }

    public function testSetGetParts(): void
    {
        $msg = new Zend_Mime_Message();  // No Parts
        $p = $msg->getParts();
        $this->assertIsArray($p);
        $this->assertCount(0, $p);

        $p2 = array();
        $p2[] = new Zend_Mime_Part('This is a test');
        $p2[] = new Zend_Mime_Part('This is another test');
        $msg->setParts($p2);
        $p = $msg->getParts();
        $this->assertIsArray($p);
        $this->assertCount(2, $p);
    }

    public function testGetMime(): void
    {
        $msg = new Zend_Mime_Message();  // No Parts
        $m = $msg->getMime();
        $this->assertInstanceOf(Zend_Mime::class, $m);

        $msg = new Zend_Mime_Message();  // No Parts
        $mime = new Zend_Mime('1234');
        $msg->setMime($mime);
        $m2 = $msg->getMime();
        $this->assertInstanceOf(Zend_Mime::class, $m2);
        $this->assertEquals('1234', $m2->boundary());
    }

    public function testGenerate(): void
    {
        $msg = new Zend_Mime_Message();  // No Parts
        $p1 = new Zend_Mime_Part('This is a test');
        $p2 = new Zend_Mime_Part('This is another test');
        $msg->addPart($p1);
        $msg->addPart($p2);
        $res = $msg->generateMessage();
        $mime = $msg->getMime();
        $boundary = $mime->boundary();
        $p1 = strpos($res, $boundary);
        // $boundary must appear once for every mime part
        $this->assertTrue($p1 !== false);
        if ($p1) {
            $p2 = strpos($res, $boundary, $p1 + strlen($boundary));
            $this->assertTrue($p2 !== false);
        }
        // check if the two test messages appear:
        $this->assertStringContainsString('This is a test', $res);
        $this->assertStringContainsString('This is another test', $res);
        // ... more in ZMailTest
    }

    /**
     * check if decoding a string into a Zend_Mime_Message object works
     */
    public function testDecodeMimeMessage(): void
    {
        $text = <<<EOD
This is a message in Mime Format.  If you see this, your mail reader does not support this format.

--=_af4357ef34b786aae1491b0a2d14399f
Content-Type: application/octet-stream
Content-Transfer-Encoding: 8bit

This is a test
--=_af4357ef34b786aae1491b0a2d14399f
Content-Type: image/gif
Content-Transfer-Encoding: base64
Content-ID: <12>

This is another test
--=_af4357ef34b786aae1491b0a2d14399f--
EOD;
        $res = Zend_Mime_Message::createFromMessage($text, '=_af4357ef34b786aae1491b0a2d14399f');

        $parts = $res->getParts();
        $this->assertEquals(2, count($parts));

        $part1 = $parts[0];
        $this->assertEquals('application/octet-stream', $part1->type);
        $this->assertEquals('8bit', $part1->encoding);

        $part2 = $parts[1];
        $this->assertEquals('image/gif', $part2->type);
        $this->assertEquals('base64', $part2->encoding);
        $this->assertEquals('12', $part2->id);
    }

    public function testGetPartHeadersArray(): void
    {
        $msg = new Zend_Mime_Message();
        $part = new Zend_Mime_Part('Test content');
        $part->type = 'text/plain';
        $part->charset = 'UTF-8';
        $part->encoding = Zend_Mime::ENCODING_8BIT;
        $msg->addPart($part);

        $headers = $msg->getPartHeadersArray(0);
        $this->assertIsArray($headers);

        $headerNames = array_column($headers, 0);
        $this->assertContains('Content-Type', $headerNames);
        $this->assertContains('Content-Transfer-Encoding', $headerNames);
    }

    public function testGetPartHeaders(): void
    {
        $msg = new Zend_Mime_Message();
        $part = new Zend_Mime_Part('Test content');
        $part->type = 'text/plain';
        $part->encoding = Zend_Mime::ENCODING_8BIT;
        $msg->addPart($part);

        $headers = $msg->getPartHeaders(0);
        $this->assertIsString($headers);
        $this->assertStringContainsString('Content-Type: text/plain', $headers);
        $this->assertStringContainsString('Content-Transfer-Encoding: 8bit', $headers);
    }

    public function testGetPartContent(): void
    {
        $msg = new Zend_Mime_Message();
        $part = new Zend_Mime_Part('Test content');
        $part->encoding = Zend_Mime::ENCODING_8BIT;
        $msg->addPart($part);

        $content = $msg->getPartContent(0);
        $this->assertEquals('Test content', $content);
    }

    public function testGetPartContentBase64(): void
    {
        $msg = new Zend_Mime_Message();
        $part = new Zend_Mime_Part('Hello World');
        $part->encoding = Zend_Mime::ENCODING_BASE64;
        $msg->addPart($part);

        $content = $msg->getPartContent(0);
        $this->assertEquals('Hello World', base64_decode($content));
    }

    public function testGenerateSinglePartMessage(): void
    {
        $msg = new Zend_Mime_Message();
        $part = new Zend_Mime_Part('Single part content');
        $part->encoding = Zend_Mime::ENCODING_8BIT;
        $msg->addPart($part);

        $result = $msg->generateMessage();
        $this->assertEquals('Single part content', $result);
    }

    public function testCreateFromMessageThrowsOnUnknownHeader(): void
    {
        $text = <<<EOD
preamble

--=_boundary999
Content-Type: text/plain
X-Unknown-Header: some-value

Body content
--=_boundary999--
EOD;
        $this->expectException(Zend_Exception::class);
        $this->expectExceptionMessage('Unknown header ignored for MimePart');
        Zend_Mime_Message::createFromMessage($text, '=_boundary999');
    }

    public function testCreateFromMessageWithDescriptionLocationLanguage(): void
    {
        $text = <<<EOD
This is a message in Mime Format.

--=_boundary123
Content-Type: text/plain
Content-Transfer-Encoding: 8bit
Content-Disposition: inline
Content-Description: A test file
Content-Location: http://example.com/test
Content-Language: en

Test body
--=_boundary123--
EOD;
        $res = Zend_Mime_Message::createFromMessage($text, '=_boundary123');
        $parts = $res->getParts();
        $this->assertCount(1, $parts);
        $this->assertEquals('A test file', $parts[0]->description);
        $this->assertEquals('http://example.com/test', $parts[0]->location);
        $this->assertEquals('en', $parts[0]->language);
        $this->assertEquals('inline', $parts[0]->disposition);
    }
}
