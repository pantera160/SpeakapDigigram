<?php

namespace Speakap\SDK;

use \Speakap\Date\ExtendedDateTime;
use Speakap\SDK\Exception\ExpiredSignatureException;
use Speakap\SDK\Exception\InvalidSignatureException;

class SignedRequest
{
    /**
     * The default window a request is valid, in seconds
     */
    const DEFAULT_WINDOW = 60;

    /**
     * Should be the same value as ini_get('arg_separator.output') and should always be '&'
     */
    const URL_ARG_SEPARATOR = '&';

    /**
     * @var string
     */
    private $appId;

    /**
     * The shared secret
     * @var string
     */
    private $appSecret;

    /**
     * The time window in seconds
     * @var int
     */
    private $signatureWindowSize;

    /**
     * @param string $appId
     * @param string $appSecret
     * @param int    $signatureWindowSize Time in seconds that the window is considered valid
     */
    public function __construct($appId, $appSecret, $signatureWindowSize = null)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->signatureWindowSize = $signatureWindowSize === null ? static::DEFAULT_WINDOW : $signatureWindowSize;
    }

    /**
     * @param array $params
     *
     * @throws \InvalidArgumentException
     *
     * @return bool
     */
    public function validateSignature(array $params)
    {
        if ( ! $this->isValidPayload($params)) {
            throw new \InvalidArgumentException('Missing payload properties, got: '. print_r($params, true));
        }

        if ($params['signature'] !== $this->getSignatureFromParameters($this->appSecret, $params)) {
            throw new InvalidSignatureException('Invalid signature, got: '. print_r($params, true));
        }

        $issuedAt = ExtendedDateTime::createFromFormat(\DateTime::ISO8601, $params['issuedAt']);
        if ( ! $this->isWithinWindow($this->signatureWindowSize, $issuedAt)) {
            throw new ExpiredSignatureException('Expired signature, got: '. print_r($params, true));
        }

        return true;
    }

    /**
     * Get the encoded value. To be used in e.g. the Speakap JavaScript proxy
     *
     * @param array $params
     *
     * @return string
     */
    public function getSignedRequest(array $params)
    {
        $params['signature'] = $this->getSignatureFromParameters($this->appSecret, $params);
        return $this->parametersToQueryString($params);
    }

    /**
     * Get the parameters, including the signature
     *
     * @param array $params
     *
     * @return array
     */
    public function getSignedParameters(array $params)
    {
        $params['signature'] = $this->getSignatureFromParameters($this->appSecret, $params);
        return $params;
    }

    /**
     * Whether or not the request is within a sane window.
     *
     * @param integer   $signatureWindowSize
     * @param \DateTime $issuedAt
     *
     * @throws \InvalidArgumentException
     *
     * @return boolean
     */
    protected function isWithinWindow($signatureWindowSize, \DateTime $issuedAt)
    {
        if (! $issuedAt instanceof ExtendedDateTime) {
            throw new \InvalidArgumentException('Invalid timestamp supplied.');
        }

        $now = new ExtendedDateTime();

        $diff = $now->getTimestamp() - $issuedAt->getTimestamp();

        // The diff must be less than, or equal to the window size. To protect against overflow possibilities
        // we also test if the differences is equal to, or greater than 0.
        if ($diff <= $signatureWindowSize && $diff >= 0) {
            return true;
        }

        return false;
    }

    /**
     * Sign the remote payload with the local (shared) secret. The result should be identical to the one we got
     * from the server.
     *
     * @param string $secret
     * @param array  $requestParameters
     *
     * @return string
     */
    protected function getSelfSignedRequest($secret, array $requestParameters)
    {
        $requestParameters['signature'] = $this->getSignatureFromParameters($secret, $requestParameters);

        return $this->parametersToQueryString($requestParameters);
    }

    /**
     * Generate the signature, based on the request parameters
     *
     * @param string $secret
     * @param array  $requestParameters
     *
     * @return string
     */
    protected function getSignatureFromParameters($secret, array $requestParameters)
    {
        unset($requestParameters['signature']);

        ksort($requestParameters);

        return base64_encode(
            hash_hmac(
                'sha256',
                $this->parametersToQueryString($requestParameters),
                $secret,
                true
            )
        );
    }

    /**
     * Validate the existence of the payload properties.
     *
     * @param array $payloadProperties
     *
     * @return bool
     */
    protected function isValidPayload(array $payloadProperties)
    {
        $defaultPayload = array(
            'appData'    => null,
            'issuedAt'   => null,
            'locale'     => null,
            'networkEID' => null,
            'userEID'    => null,
            'role'       => null,
            'signature'  => null
        );

        return count($defaultPayload) <= count(array_intersect_key($defaultPayload, $payloadProperties));
    }

    /**
     * Convert an array to a query-string, RFC3986 encoded
     *
     * @param array $requestParameters
     *
     * @return string
     */
    protected function parametersToQueryString(array $requestParameters)
    {
        return http_build_query($requestParameters, null, static::URL_ARG_SEPARATOR, PHP_QUERY_RFC3986);
    }
}
