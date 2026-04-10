<?php

use PHPUnit\Framework\TestCase;

class ZendMimeTest extends TestCase
{
    public function testBoundary(): void
    {
        // check boundary for uniqueness
        $m1 = new Zend_Mime();
        $m2 = new Zend_Mime();
        $this->assertNotEquals($m1->boundary(), $m2->boundary());

        // check instantiating with arbitrary boundary string
        $myBoundary = 'mySpecificBoundary';
        $m3 = new Zend_Mime($myBoundary);
        $this->assertEquals($m3->boundary(), $myBoundary);
    }

    public function testIsPrintable_notPrintable(): void
    {
        $this->assertFalse(Zend_Mime::isPrintable("Test with special chars: \xE4\xF6\xFC\xE4\xF6"));
    }

    public function testIsPrintable_isPrintable(): void
    {
        $this->assertTrue(Zend_Mime::isPrintable('Test without special chars'));
    }

    public function testQP(): void
    {
        $text = "This is a cool Test Text with special chars: \xE4\xF6\xFC\xE4\n"
              . "and with multiple lines\xE4\xF6\xFC\xE4 some of the Lines are long, long"
              . ", long, long, long, long, long, long, long, long, long, long"
              . ", long, long, long, long, long, long, long, long, long, long"
              . ", long, long, long, long, long, long, long, long, long, long"
              . ", long, long, long, long and with \xE4\xF6\xFC\xE4";

        $qp = Zend_Mime::encodeQuotedPrintable($text);
        $this->assertEquals(quoted_printable_decode($qp), $text);
    }

    public function testEncodeQuotedPrintableWhenTextHasZeroAtTheEnd(): void
    {
        $raw = str_repeat('x', 72) . '0';
        $quoted = Zend_Mime::encodeQuotedPrintable($raw, 72);
        $expected = quoted_printable_decode($quoted);
        $this->assertEquals($expected, $raw);
    }

    public function testBase64(): void
    {
        $content = str_repeat("\x88\xAA\xAF\xBF\x29\x88\xAA\xAF\xBF\x29\x88\xAA\xAF", 4);
        $encoded = Zend_Mime::encodeBase64($content);
        $this->assertEquals($content, base64_decode($encoded));
    }

    /**
     * @dataProvider dataTestEncodeMailHeaderQuotedPrintable
     */
    public function testEncodeMailHeaderQuotedPrintable(string $str, string $charset, string $result): void
    {
        $this->assertEquals($result, Zend_Mime::encodeQuotedPrintableHeader($str, $charset));
    }

    public static function dataTestEncodeMailHeaderQuotedPrintable(): array
    {
        return array(
            array("\xC3\xA4\xC3\xB6\xC3\xBC", "UTF-8", "=?UTF-8?Q?=C3=A4=C3=B6=C3=BC?="),
            array("\xC3\xA4\xC3\xB6\xC3\xBC ", "UTF-8", "=?UTF-8?Q?=C3=A4=C3=B6=C3=BC?="),
            array("Gimme more \xE2\x82\xAC", "UTF-8", "=?UTF-8?Q?Gimme=20more=20=E2=82=AC?="),
            array("Alle meine Entchen schwimmen in dem See, schwimmen in dem See, K\xC3\xB6pfchen in das Wasser, Schw\xC3\xA4nzchen in die H\xC3\xB6h!", "UTF-8", "=?UTF-8?Q?Alle=20meine=20Entchen=20schwimmen=20in=20dem=20See=2C=20?=\n =?UTF-8?Q?schwimmen=20in=20dem=20See=2C=20K=C3=B6pfchen=20in=20das=20?=\n =?UTF-8?Q?Wasser=2C=20Schw=C3=A4nzchen=20in=20die=20H=C3=B6h!?="),
            array("\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4\xC3\xA4", "UTF-8", "=?UTF-8?Q?=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4=C3=A4?="),
        );
    }

    /**
     * @dataProvider dataTestEncodeMailHeaderBase64
     */
    public function testEncodeMailHeaderBase64(string $str, string $charset, string $result): void
    {
        $this->assertEquals($result, Zend_Mime::encodeBase64Header($str, $charset));
    }

    public static function dataTestEncodeMailHeaderBase64(): array
    {
        return array(
            array("\xC3\xA4\xC3\xB6\xC3\xBC", "UTF-8", "=?UTF-8?B?w6TDtsO8?="),
            array("Alle meine Entchen schwimmen in dem See, schwimmen in dem See, K\xC3\xB6pfchen in das Wasser, Schw\xC3\xA4nzchen in die H\xC3\xB6h!", "UTF-8", "=?UTF-8?B?QWxsZSBtZWluZSBFbnRjaGVuIHNjaHdpbW1lbiBpbiBkZW0gU2VlLCBzY2h3?=\n =?UTF-8?B?aW1tZW4gaW4gZGVtIFNlZSwgS8O2cGZjaGVuIGluIGRhcyBXYXNzZXIsIFNj?=\n =?UTF-8?B?aHfDpG56Y2hlbiBpbiBkaWUgSMO2aCE=?="),
        );
    }

    public function testLineLengthInQuotedPrintableHeaderEncoding(): void
    {
        $subject = "Alle meine Entchen schwimmen in dem See, schwimmen in dem See, K\xC3\xB6pfchen in das Wasser, Schw\xC3\xA4nzchen in die H\xC3\xB6h!";
        $encoded = Zend_Mime::encodeQuotedPrintableHeader($subject, "UTF-8", 100);
        foreach (explode(Zend_Mime::LINEEND, $encoded) as $line) {
            if (strlen($line) > 100) {
                $this->fail("Line '" . $line . "' is " . strlen($line) . " chars long, only 100 allowed.");
            }
        }
        $encoded = Zend_Mime::encodeQuotedPrintableHeader($subject, "UTF-8", 40);
        foreach (explode(Zend_Mime::LINEEND, $encoded) as $line) {
            if (strlen($line) > 40) {
                $this->fail("Line '" . $line . "' is " . strlen($line) . " chars long, only 40 allowed.");
            }
        }
        $this->assertTrue(true);
    }
}
