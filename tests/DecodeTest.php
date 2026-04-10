<?php

use PHPUnit\Framework\TestCase;

class DecodeTest extends TestCase
{
    // --- decodeQuotedPrintable ---

    public function testDecodeQuotedPrintable(): void
    {
        $encoded = '=E4=F6=FC';
        $this->assertEquals(quoted_printable_decode($encoded), Zend_Mime_Decode::decodeQuotedPrintable($encoded));
    }

    // --- splitContentType ---

    public function testSplitContentTypeReturnsType(): void
    {
        $this->assertEquals(
            'text/plain',
            Zend_Mime_Decode::splitContentType('text/plain; charset=UTF-8', 'type')
        );
    }

    public function testSplitContentTypeReturnsParameter(): void
    {
        $this->assertEquals(
            'UTF-8',
            Zend_Mime_Decode::splitContentType('text/plain; charset=UTF-8', 'charset')
        );
    }

    public function testSplitContentTypeReturnsAllParts(): void
    {
        $result = Zend_Mime_Decode::splitContentType('text/html; charset=UTF-8; boundary="abc123"');
        $this->assertIsArray($result);
        $this->assertEquals('text/html', $result['type']);
        $this->assertEquals('UTF-8', $result['charset']);
        $this->assertEquals('abc123', $result['boundary']);
    }

    // --- splitHeaderField ---

    public function testSplitHeaderFieldFirstNameMatch(): void
    {
        // When wantedPart === firstName, it uses the optimized path
        $result = Zend_Mime_Decode::splitHeaderField('text/plain; charset=UTF-8', 'type', 'type');
        $this->assertEquals('text/plain', $result);
    }

    public function testSplitHeaderFieldFirstNameMatchQuoted(): void
    {
        $result = Zend_Mime_Decode::splitHeaderField('"text/plain"; charset=UTF-8', 'type', 'type');
        $this->assertEquals('text/plain', $result);
    }

    public function testSplitHeaderFieldWantedPartReturnsValue(): void
    {
        $result = Zend_Mime_Decode::splitHeaderField(
            'attachment; filename="report.pdf"',
            'filename',
            'disposition'
        );
        $this->assertEquals('report.pdf', $result);
    }

    public function testSplitHeaderFieldWantedPartNotFound(): void
    {
        $result = Zend_Mime_Decode::splitHeaderField(
            'attachment; filename="report.pdf"',
            'charset',
            'disposition'
        );
        $this->assertNull($result);
    }

    public function testSplitHeaderFieldReturnsAllParts(): void
    {
        $result = Zend_Mime_Decode::splitHeaderField(
            'attachment; filename="report.pdf"; size=1024',
            null,
            'disposition'
        );
        $this->assertIsArray($result);
        $this->assertEquals('attachment', $result['disposition']);
        $this->assertEquals('report.pdf', $result['filename']);
        $this->assertEquals('1024', $result['size']);
    }

    public function testSplitHeaderFieldUnquotedWantedPart(): void
    {
        $result = Zend_Mime_Decode::splitHeaderField(
            'attachment; size=1024',
            'size',
            'disposition'
        );
        $this->assertEquals('1024', $result);
    }

    public function testSplitHeaderFieldThrowsOnInvalidInput(): void
    {
        $this->expectException(Zend_Exception::class);
        Zend_Mime_Decode::splitHeaderField('', 'something', 'first');
    }

    // --- splitMime ---

    public function testSplitMimeTwoParts(): void
    {
        $boundary = 'BOUNDARY';
        $body = "preamble\n"
              . "--BOUNDARY\n"
              . "Part one content\n"
              . "--BOUNDARY\n"
              . "Part two content\n"
              . "--BOUNDARY--\n";

        $parts = Zend_Mime_Decode::splitMime($body, $boundary);
        $this->assertCount(2, $parts);
        $this->assertStringContainsString('Part one content', $parts[0]);
        $this->assertStringContainsString('Part two content', $parts[1]);
    }

    public function testSplitMimeSinglePart(): void
    {
        $boundary = 'BOUNDARY';
        $body = "preamble\n"
              . "--BOUNDARY\n"
              . "Only part\n"
              . "--BOUNDARY--\n";

        $parts = Zend_Mime_Decode::splitMime($body, $boundary);
        $this->assertCount(1, $parts);
        $this->assertStringContainsString('Only part', $parts[0]);
    }

    public function testSplitMimeNoParts(): void
    {
        $parts = Zend_Mime_Decode::splitMime('no boundary here', 'BOUNDARY');
        $this->assertCount(0, $parts);
    }

    public function testSplitMimeThrowsOnMissingEnd(): void
    {
        $boundary = 'BOUNDARY';
        $body = "preamble\n"
              . "--BOUNDARY\n"
              . "Part content\n";

        $this->expectException(Zend_Exception::class);
        $this->expectExceptionMessage('End Missing');
        Zend_Mime_Decode::splitMime($body, $boundary);
    }

    public function testSplitMimeStripsCarriageReturns(): void
    {
        $boundary = 'BOUNDARY';
        $body = "preamble\r\n"
              . "--BOUNDARY\r\n"
              . "Part content\r\n"
              . "--BOUNDARY--\r\n";

        $parts = Zend_Mime_Decode::splitMime($body, $boundary);
        $this->assertCount(1, $parts);
        $this->assertStringNotContainsString("\r", $parts[0]);
    }

    // --- splitMessage ---

    public function testSplitMessageWithValidHeaders(): void
    {
        $message = "Content-Type: text/plain\n"
                 . "Content-Transfer-Encoding: 8bit\n"
                 . "\n"
                 . "This is the body";

        Zend_Mime_Decode::splitMessage($message, $headers, $body, "\n");

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertEquals('text/plain', $headers['content-type']);
        $this->assertEquals('This is the body', $body);
    }

    public function testSplitMessageWithNoHeaders(): void
    {
        // A message that doesn't start with a valid header line
        $message = "This is just a body with no headers";

        Zend_Mime_Decode::splitMessage($message, $headers, $body, "\n");

        $this->assertIsArray($headers);
        $this->assertCount(0, $headers);
        $this->assertStringContainsString('This is just a body', $body);
    }

    public function testSplitMessageWithCrLfSeparator(): void
    {
        $message = "Content-Type: text/html\r\n"
                 . "\r\n"
                 . "Body content here";

        Zend_Mime_Decode::splitMessage($message, $headers, $body, "\r\n");

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertEquals('Body content here', $body);
    }

    public function testSplitMessageFallsBackToLfSeparator(): void
    {
        // Use \r\n as EOL but the message only has \n\n — triggers the else-if branch
        $message = "Content-Type: text/plain\n"
                 . "\n"
                 . "Body with LF only";

        Zend_Mime_Decode::splitMessage($message, $headers, $body, "\r\n");

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertStringContainsString('Body with LF only', $body);
    }

    public function testSplitMessageNormalizesHeaderNames(): void
    {
        $message = "Content-Type: text/plain\n"
                 . "X-Custom-Header: value1\n"
                 . "\n"
                 . "body";

        Zend_Mime_Decode::splitMessage($message, $headers, $body, "\n");

        $this->assertArrayHasKey('content-type', $headers);
        $this->assertArrayHasKey('x-custom-header', $headers);
    }

    public function testSplitMessageCrLnFallbackWhenEolIsCustom(): void
    {
        // EOL is not \r\n and not \n, message has \r\n\r\n separator
        // This triggers the second branch: $EOL != "\r\n" && strpos($message, "\r\n\r\n")
        $message = "Content-Type: text/plain\r\n\r\nBody content";

        Zend_Mime_Decode::splitMessage($message, $headers, $body, "CUSTOM");

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertStringContainsString('Body content', $body);
    }

    public function testSplitMessageLastResortFallback(): void
    {
        // EOL is \r\n, message uses \n\n — not matching $EOL.$EOL or \r\n\r\n
        // But $EOL == \r\n, so the \n\n branch also won't match ($EOL != "\n" is true, but \n\n would match).
        // Actually let me think: EOL=\r\n, message has \n\n only.
        // First check: strpos($message, "\r\n\r\n") — false
        // Second: $EOL != "\r\n" — false (EOL IS \r\n), so skip
        // Third: $EOL != "\n" — true, and strpos($message, "\n\n") — true
        // So this hits the third branch (line 155-156).
        $message = "Content-Type: text/plain\n\nBody content";

        Zend_Mime_Decode::splitMessage($message, $headers, $body, "\r\n");

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertStringContainsString('Body content', $body);
    }

    public function testSplitMessagePregSplitFallback(): void
    {
        // To hit the preg_split fallback (line 159-160):
        // - $EOL . $EOL must not be found
        // - \r\n\r\n must not be found (or $EOL == \r\n)
        // - \n\n must not be found (or $EOL == \n)
        // Use EOL=\n so the third branch condition ($EOL != "\n") is false
        // Use \r\r as the separator in the message (not \n\n, not \r\n\r\n)
        $message = "Content-Type: text/plain\r\rBody content";

        Zend_Mime_Decode::splitMessage($message, $headers, $body, "\n");

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertStringContainsString('Body content', $body);
    }

    public function testSplitMessageDuplicateHeadersMergedAsArray(): void
    {
        // Test the header normalization branch where a lowercase key already exists
        // and becomes an array
        $message = "X-Custom: value1\n"
                 . "X-Custom: value2\n"
                 . "\n"
                 . "body";

        Zend_Mime_Decode::splitMessage($message, $headers, $body, "\n");

        $this->assertArrayHasKey('x-custom', $headers);
        // iconv_mime_decode_headers with multiple same-name headers returns an array
        $this->assertIsArray($headers['x-custom']);
        $this->assertContains('value1', $headers['x-custom']);
        $this->assertContains('value2', $headers['x-custom']);
    }

    public function testSplitMessageHeaderNormalizationWithMixedCase(): void
    {
        // iconv_mime_decode_headers may return mixed-case keys.
        // When there's a header like "Content-Type" it gets normalized to "content-type".
        // The normalization code (lines 175-193) handles:
        // 1. Already lowercase -> skip (line 178)
        // 2. Uppercase key, no lowercase exists -> rename (line 182)
        // 3. Uppercase key, lowercase exists as array -> append (line 186)
        // 4. Uppercase key, lowercase exists as string -> merge into array (line 189)
        $message = "Content-Type: text/plain\n"
                 . "X-Test: value1\n"
                 . "\n"
                 . "body";

        Zend_Mime_Decode::splitMessage($message, $headers, $body, "\n");

        // Content-Type gets normalized to content-type
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertEquals('text/plain', $headers['content-type']);
    }

    // --- splitMessageStruct ---

    public function testSplitMessageStructReturnsNull(): void
    {
        $result = Zend_Mime_Decode::splitMessageStruct('no boundary here', 'BOUNDARY');
        $this->assertNull($result);
    }

    public function testSplitMessageStructReturnsParts(): void
    {
        $boundary = 'MYBOUNDARY';
        $message = "preamble\n"
                 . "--MYBOUNDARY\n"
                 . "Content-Type: text/plain\n"
                 . "\n"
                 . "First part body\n"
                 . "--MYBOUNDARY\n"
                 . "Content-Type: text/html\n"
                 . "\n"
                 . "Second part body\n"
                 . "--MYBOUNDARY--\n";

        $result = Zend_Mime_Decode::splitMessageStruct($message, $boundary, "\n");

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $this->assertArrayHasKey('header', $result[0]);
        $this->assertArrayHasKey('body', $result[0]);
        $this->assertEquals('text/plain', $result[0]['header']['content-type']);
        $this->assertStringContainsString('First part body', $result[0]['body']);

        $this->assertEquals('text/html', $result[1]['header']['content-type']);
        $this->assertStringContainsString('Second part body', $result[1]['body']);
    }
}
