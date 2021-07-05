<?php

namespace Trello\HttpClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use Psr\Http\Message\RequestInterface;
use Trello\Exception\ErrorException;
use Trello\Exception\RuntimeException;
use Trello\HttpClient\Listener\AuthListener;
use Trello\HttpClient\Listener\ErrorListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;


class HttpClient implements HttpClientInterface
{
    protected $options = [
        'base_url' => 'https://api.trello.com/',
        'user_agent' => 'php-trello-api (http://github.com/cdaguerre/php-trello-api)',
        'timeout' => 10,
        'api_version' => 1,
    ];

    /**
     * @var ClientInterface
     */
    protected $client;

    protected $headers = [];

    private $lastResponse;
    private $lastRequest;

    /**
     * @param array $options
     */
    public function __construct(array $options = [], ?callable $middleware = null)
    {
        $handler = HandlerStack::create();
        $handler->push(Middleware::httpErrors(), 'http_errors');

        if($middleware) {
            $handler->push(Middleware::mapRequest($middleware));
        }

        $this->options = array_merge($this->options, $options, ['handler' => $handler]);
        $client = new GuzzleClient($this->options);

        $this->client = $client;

        $this->clearHeaders();
    }

    static public function createWithAuth(array $options = [], string $tokenOrLogin, string $password): self {
        $authMiddleware = function (\GuzzleHttp\Psr7\Request $request) use($tokenOrLogin, $password) {
            $uriInterface = $request->getUri();
            $url = $uriInterface->getPath();

            $parameters = [
                'key' => $tokenOrLogin,
                'token' => $password,
            ];

            $url .= (false === strpos($url, '?') ? '?' : '&');
            $url .= utf8_encode(http_build_query($parameters, '', '&'));

            return $request->withUri(new \GuzzleHttp\Psr7\Uri($url));
        };
        return new self($options, $authMiddleware);
    }

    /**
     * {@inheritDoc}
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function setHeaders(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);
    }

    /**
     * Clears used headers
     */
    public function clearHeaders()
    {
        $this->headers = [
            'Accept' => sprintf('application/vnd.orcid.%s+json', $this->options['api_version']),
            'User-Agent' => sprintf('%s', $this->options['user_agent']),
        ];
    }

    /**
     * @param string $eventName
     * @param callable $listener
     */
    public function addListener($eventName, $listener)
    {
        $this->client->getEmitter()->on($eventName, $listener);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->client->getEmitter()->attach($subscriber);
    }

    /**
     * {@inheritDoc}
     */
    public function get($path, array $parameters = [], array $headers = [])
    {
        return $this->request($path, $parameters, 'GET', $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function post($path, $body = null, array $headers = [])
    {
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        return $this->request($path, $body, 'POST', $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function patch($path, $body = null, array $headers = [])
    {
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        return $this->request($path, $body, 'PATCH', $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function delete($path, $body = null, array $headers = [])
    {
        return $this->request($path, $body, 'DELETE', $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function put($path, $body, array $headers = [])
    {
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        return $this->request($path, $body, 'PUT', $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function request($path, $body = null, $httpMethod = 'GET', array $headers = [], array $options = [])
    {
        $request = $this->createRequest($httpMethod, $path, $body, $headers, $options);

        try {
            $response = $this->client->send($request);
        } catch (\LogicException $e) {
            throw new ErrorException($e->getMessage(), $e->getCode(), $e);
        } catch (\RuntimeException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        $this->lastRequest = $request;
        $this->lastResponse = $response;

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate($tokenOrLogin, $password, $method)
    {
    }

    /**
     * @return Request
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * @return Response
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * @param string $httpMethod
     * @param string $path
     *
     * @return Request|\GuzzleHttp\Message\RequestInterface
     */
    protected function createRequest($httpMethod, $path, $body = null, array $headers = [], array $options = [])
    {
        $path = $this->options['api_version'] . '/' . $path;

        if ($httpMethod === 'GET' && $body) {
            $path .= (false === strpos($path, '?') ? '?' : '&');
            $path .= utf8_encode(http_build_query($body, '', '&'));
        }

        $options['body'] = is_array($body) ? json_encode($body) : $body;
        $options['headers'] = array_merge($this->headers, $headers);

        return $this->client->request($httpMethod, $path, $options);
    }
}
