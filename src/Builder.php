<?php

namespace DSentker\Uri;

use DSentker\Uri\Exception\InvalidQuery;
use DSentker\Uri\Exception\InvalidTimeout;
use DSentker\Uri\Exception\SignatureExpired;
use DSentker\Uri\Exception\SignatureInvalid;

/**
 * The URL Builder class. Adopted by https://github.com/psecio/uri
 *
 * @package DSentker\Uri
 */
class Builder
{
    /**
     * Current hashing algorithm
     *
     * @var string
     */
    protected $algorithm = 'SHA256';

    /**
     * Current secret string
     *
     * @var string
     */
    protected $secret;

    /**
     * The name for the URL parameter which stores the hash
     *
     * @var string
     */
    public static $signatureName = '_signature';

    /**
     * @var string The name for the URL parameter which stores the expire date, if set
     */
    public static $expiresName = '_expires';

    /**
     * Initialize the object and set the secret
     *
     * @param string $secret Secret value
     */
    public function __construct(string $secret)
    {
        $this->setSecret($secret);
    }

    /**
     * Set the current secret value
     *
     * @param string $secret
     * @return void
     */
    public function setSecret(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * Return the current secret value
     *
     * @return string Secret string
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    public function create(string $base, array $data = [], $timeout = null)
    {
        // If we're not given data, try to break apart the URL provided
        if (empty($data)) {
            // This might throw an exception if no query params are given
            $data = $this->buildFromUrlString($base);

            // Replace the current query value
            $uri = parse_url($base);
            $base = str_replace('?'.$uri['query'], '', $base);
        }

        // Check for the timeout
        if ($timeout !== null) {

            if(is_int($timeout)) {
                $timeoutTimestamp = $timeout;
            } elseif(is_string($timeout)) {
                $timeoutTimestamp = strtotime($timeout);
                if(false === $timeoutTimestamp) {
                    throw new InvalidTimeout('The timeout cannot be parsed via strtotime() and is evidently not a valid date format (as defined via http://php.net/manual/de/datetime.formats.php)');
                }
            } elseif($timeout instanceof \DateTimeInterface) {
                $timeoutTimestamp = $timeout->getTimestamp();
            } else {
                throw new InvalidTimeout('Unknown timeout type given: "%s" (expected: int|string|\DateTimeInterface)!', gettype($timeout));
            }

            if ($timeoutTimestamp < time()) {
                throw new InvalidTimeout('Timeout cannot be in the past');
            }
            $data[static::$expiresName] = $timeoutTimestamp;
        }

        $query = http_build_query($data);
        $signature = $this->buildHash($query);

        $uri = $base.'?'.$query.'&'.static::$signatureName.'='.$signature;
        return $uri;
    }

    /**
     * Build the data array from a provided URL, parsing out the current GET params
     *
     * @param string $base
     * @throws \InvalidArgumentException If no query params exist
     * @return array Set of key/value pairs from the URL
     */
    public function buildFromUrlString($base) : array
    {
        $uri = parse_url($base);
        if (!isset($uri['query'])) {
            throw new InvalidQuery('No query parameters specified');
        }

        return $this->parseQueryData($uri['query']);
    }

    public function verify($url)
    {
        $uri = parse_url($url);

        if (!isset($uri['query']) || empty($uri['query'])) {
            throw new InvalidQuery('No URI parameters provided, cannot validate');
        }
        $data = $this->parseQueryData($uri['query']);

        // Try to find our signature
        if (!isset($data[static::$signatureName]) || empty($data[static::$signatureName])) {
            throw new SignatureInvalid('No signature found!');
        } else {
            // Remove it
            $signature = $data[static::$signatureName];
            unset($data[static::$signatureName]);

            $uri['query'] = http_build_query($data);
        }

        // Do we need to validate the "expires" value?
        if (isset($data[static::$expiresName])) {
            if ($data[static::$expiresName] < time()) {
                throw new SignatureExpired('Signature has expired');
            }
        }

        $check = $this->buildHash($uri['query']);
        return hash_equals($check, $signature);
    }

    public function buildHash($queryString)
    {
        if (empty($queryString)) {
            throw new InvalidQuery('Hash cannot be created, query string empty');
        }
        $signature = hash_hmac($this->algorithm, $queryString, $this->getSecret());
        return $signature;
    }

    public function parseQueryData($query)
    {
        $data = [];
        foreach (explode('&', $query) as $param) {
            $parts = explode('=', $param);
            $data[$parts[0]] = (isset($parts[1])) ? $parts[1] : null;
        }

        return $data;
    }
}
