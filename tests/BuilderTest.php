<?php

namespace DSentker\Uri;

use DSentker\Uri\Exception\InvalidTimeout;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{

    const DEFAULT_SECRET = 'test';

    public function testSetSecretOnConstruct()
    {
        $b = new Builder(static::DEFAULT_SECRET);
        $this->assertEquals(static::DEFAULT_SECRET, $b->getSecret());
    }

    public function testChangeSecretSet()
    {
        $b = new Builder(static::DEFAULT_SECRET);
        $b->setSecret('foo');

        $this->assertNotEquals(static::DEFAULT_SECRET, $b->getSecret());
        $this->assertEquals('foo', $b->getSecret());
    }

    public function testBuildDataFromUrl()
    {
        $url = 'http://foobar.com?test=this&and=that';

        $b = new Builder(static::DEFAULT_SECRET);
        $data = $b->buildFromUrlString($url);

        $this->assertEquals([
            'test' => 'this',
            'and'  => 'that'
        ], $data);
    }

    /**
     * @expectedException \DSentker\Uri\Exception\InvalidQuery
     */
    public function testBuildDataFromUrlNoParams()
    {
        $url = 'http://foobar.com';

        $b = new Builder(static::DEFAULT_SECRET);
        $b->buildFromUrlString($url);
    }

    public function testBuildDataFromQueryStringWithoutValues()
    {
        $url = 'http://example.com/?foo=bar&baz';

        $b = new Builder(static::DEFAULT_SECRET);
        $data = $b->buildFromUrlString($url);
        $this->assertSame([
            'foo' => 'bar',
            'baz' => null,
        ], $data);
    }

    public function testBuildHash()
    {
        $queryString = 'test=this&and=that';
        $match = '94857a73d16605dc084751a66f8ac05e2be478b79563e49625f0d87b733dcbb1';

        $b = new Builder(static::DEFAULT_SECRET);
        $hash = $b->buildHash($queryString);

        $this->assertEquals($match, $hash);
    }

    /**
     * @expectedException \DSentker\Uri\Exception\InvalidQuery
     */
    public function testBuildHashEmptyString()
    {
        (new Builder(static::DEFAULT_SECRET))->buildHash('');
    }

    public function testCreateFromUrlString()
    {
        $url = 'http://test.com?foo=bar&baz=1';
        $match = 'http://test.com?foo=bar&baz=1&_signature=395150b277ca25dd7a52e9345bb9c7bc4b133f001e912fe3a7ed48316a8f5a29';

        $b = new Builder(static::DEFAULT_SECRET);
        $result = $b->create($url);

        $this->assertEquals($match, $result);
    }

    public function testCreateFromUrlData()
    {
        $base = 'http://test.com';
        $data = [
            'foo' => 'bar',
            'baz' => 1
        ];
        $match = 'http://test.com?foo=bar&baz=1&'
            . '_signature=395150b277ca25dd7a52e9345bb9c7bc4b133f001e912fe3a7ed48316a8f5a29';

        $b = new Builder(static::DEFAULT_SECRET);
        $result = $b->create($base, $data);

        $this->assertEquals($match, $result);
    }

    public function testCreateFromUrlDataTimeout()
    {
        $base = 'http://test.com';
        $timeout = '+1 minute';
        $data = [
            'foo'     => 'bar',
            'baz'     => 1,
            '_expires' => strtotime($timeout)
        ];
        $b = new Builder(static::DEFAULT_SECRET);

        $queryString = http_build_query($data);
        $hash = $b->buildHash($queryString);
        $match = 'http://test.com?' . $queryString . '&_signature=' . $hash;

        $result = $b->create($base, $data, $timeout);
        $this->assertEquals($match, $result);
    }

    /**
     * @expectedException \DSentker\Uri\Exception\InvalidTimeout
     */
    public function testCreateFromUrlDataPastTimeout()
    {
        $base = 'http://test.com';
        $timeout = '-1 minute';
        $data = [
            'foo'     => 'bar',
            'baz'     => 1,
            '_expires' => strtotime($timeout)
        ];
        $b = new Builder(static::DEFAULT_SECRET);

        $result = $b->create($base, $data, $timeout);
        $this->assertEquals(null, $result);
    }

    public function testValidateValidSignedUrl()
    {
        $base = 'http://test.com';
        $data = [
            'foo' => 'bar',
            'baz' => 1
        ];
        $b = new Builder(static::DEFAULT_SECRET);

        $result = $b->create($base, $data);
        $this->assertTrue($b->verify($result));
    }

    public function testValidateInvalidSignedUrl()
    {
        $base = 'http://test.com';
        $data = [
            'foo' => 'bar',
            'baz' => 1
        ];
        $b = new Builder(static::DEFAULT_SECRET);

        $result = $b->create($base, $data);
        $result = preg_replace('/_signature=[0-9a-z]+/', '_signature=1234', $result);

        $this->assertFalse($b->verify($result));
    }

    /**
     * @expectedException \DSentker\Uri\Exception\InvalidQuery
     */
    public function testValidateSignedUrlNoQuery()
    {
        $url = 'http://test.com';
        $b = new Builder(static::DEFAULT_SECRET);

        $b->verify($url);
    }

    /**
     * @expectedException \DSentker\Uri\Exception\SignatureInvalid
     */
    public function testValidateNoSignature()
    {
        $url = 'http://test.com?foo=bar';
        $b = new Builder(static::DEFAULT_SECRET);

        $b->verify($url);
    }

    /**
     * @expectedException \DSentker\Uri\Exception\SignatureExpired
     */
    public function testValidateSignatureExpired()
    {
        $url = 'http://test.com?foo=bar&_signature=1234&_expires=' . strtotime('-1 hour');
        $b = new Builder(static::DEFAULT_SECRET);

        $b->verify($url);
    }

    public function testModifyExpireQueryParam()
    {
        $url = 'https://example.com/?foo=bar';
        $b = new Builder(static::DEFAULT_SECRET);
        $url = $b->create($url, [], '+10 seconds');

        $this->assertTrue($b->verify($url));

        // Modify "expire" param
        $modifiedExpireParam = strtotime('+10 hour');
        $url = preg_replace('/expires=[0-9]+/', '_expires=' . $modifiedExpireParam, $url);
        $this->assertFalse($b->verify($url));

        // Remove "expire" param
        $url = preg_replace('/_expires=[0-9]+/', '', $url);
        $this->assertFalse($b->verify($url));

    }

    /**
     * @expectedException \DSentker\Uri\Exception\InvalidTimeout
     */
    public function testInvalidTimeoutString()
    {
        $url = 'https://example.com/?foo=bar';
        $b = new Builder(static::DEFAULT_SECRET);
        $b->create($url, [], 'YouCannotParseMe');
    }

    public function testDateTimeAsValidTimeout()
    {
        $b = new Builder(static::DEFAULT_SECRET);
        $url = $b->create('https://example.com/?foo=bar', [], new \DateTime('+10 SECONDS'));
        $this->assertTrue($b->verify($url));
    }

    /**
     * @expectedException \DSentker\Uri\Exception\InvalidTimeout
     */
    public function testDateTimeAsInvalidTimeout()
    {
        $b = new Builder(static::DEFAULT_SECRET);
        $url = $b->create('https://example.com/?foo=bar', [], new \DateTime('YESTERDAY'));
        $this->assertTrue($b->verify($url));
    }

    public function testQueryStringAndDataArray() {
        $b = new Builder(static::DEFAULT_SECRET);
        $url = $b->create('https://example.com/?foo=bar', [
            'qux' => 'baz'
        ]);
        $this->assertTrue($b->verify($url));
    }
}