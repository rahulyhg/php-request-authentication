<?php

namespace mle86\RequestAuthentication;

use mle86\RequestAuthentication\AuthenticationMethod\AuthenticationMethod;
use mle86\RequestAuthentication\DTO\RequestInfo;
use mle86\RequestAuthentication\Exception\CryptoErrorException;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\HandlerStack;

/**
 * Wraps an {@see AuthenticationMethod} instance to authenticate outbound requests.
 *
 *  - Can be used to add authentication data to any PSR-7 {@see RequestInterface}
 *    with the {@see authenticate()} method.
 *  - Can be used to add authentication data to any Symfony HttpFoundation {@see Request}
 *    with the {@see authenticateSymfonyRequest()} method.
 *  - The instance itself is a valid Guzzle middleware (see {@see __invoke}).
 */
class RequestAuthenticator
{

    private $apiClientId;
    private $apiClientKey;
    private $method;

    public function __construct(AuthenticationMethod $method, string $apiClientId, string $apiClientKey)
    {
        $this->method       = $method;
        $this->apiClientId  = $apiClientId;
        $this->apiClientKey = $apiClientKey;
    }


    /**
     * Takes a PSR-7 RequestInterface instance
     * and returns a new RequestInterface instance with added authentication data.
     *
     * SIDE EFFECT: This will cause a {@see StreamInterface::rewind()} call
     *  on {@see RequestInterface::getBody()}.
     *
     * @param RequestInterface $request  The request to authenticate. The instance will not be modified.
     * @return RequestInterface  Contains added authentication data.
     *                           PSR-7 promises that {@see MessageInterface::withHeader} will not alter the original instance.
     * @throws CryptoErrorException  if there was a problem with a low-level cryptographic function.
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        $ri = RequestInfo::fromPsr7($request);

        $addHeaders = $this->method->authenticate($ri, $this->apiClientId, $this->apiClientKey);
        foreach ($addHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * Takes a Symfony HttpFoundation Request instance
     * and returns a new Request instance with added authentication data.
     *
     * @param Request $request  The request to authenticate. The instance will not be modified.
     * @return Request  Contains added authentication data. Cloned from the input request instance.
     * @throws CryptoErrorException  if there was a problem with a low-level cryptographic function.
     */
    public function authenticateSymfonyRequest(Request $request): Request
    {
        $request = clone $request;  // don't modify original
        $ri      = RequestInfo::fromSymfonyRequest($request);

        $addHeaders = $this->method->authenticate($ri, $this->apiClientId, $this->apiClientKey);
        $request->headers->add($addHeaders);

        return $request;
    }

    /**
     * Returns a GuzzleHttp middleware handler
     * that will add authentication data to all requests
     * according to the constructor settings.
     *
     * @see HandlerStack::push()  can be used to add this RequestAuthenticator instance to a middleware handler stack.
     *
     * @param callable $handler
     * @return \Closure
     */
    public function __invoke(callable $handler): \Closure
    {
        return function(RequestInterface $request, array $options) use($handler) {
            $request = $this->authenticate($request);
            return $handler($request, $options);
        };
    }

}
