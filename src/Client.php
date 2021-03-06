<?php

namespace Sisense;

use GuzzleHttp\Exception\GuzzleException;
use Doctrine\Instantiator\Exception\InvalidArgumentException;

/**
 * Class Client
 *
 * @package Sisense
 *
 * @property-read Api\Authentication $authentication
 * @property-read Api\Users|Api\V09\Users $users
 * @property-read Api\Groups|Api\V09\Groups $groups
 * @property-read Api\Application $application
 * @property-read Api\Account $account
 * @property-read Api\Admin $admin
 * @property-read Api\V09\Authorization $authorization
 * @property-read Api\V09\ElastiCubes $elastiCubes
 * @property-read Api\V09\Branding $branding
 * @property-read Api\V09\Reporting $reporting
 * @property-read Api\V09\Palettes $palettes
 * @property-read Api\V09\Settings $settings
 * @property-read Api\V09\Roles $roles
 * @property-read Api\V09\DataSources $dataSources
 * @property-read Api\V09\Geo $geo
 */
class Client implements ClientInterface
{
    use JsonEncodeDecoder;

    private $classes = [
        'v0.9' => [
            'authorization' => 'V09\Authorization',
            'elastiCubes' => 'V09\ElastiCubes',
            'branding' => 'V09\Branding',
            'reporting' => 'V09\Reporting',
            'palettes' => 'V09\Palettes',
            'users' => 'V09\Users',
            'groups' => 'V09\Groups',
            'settings' => 'V09\Settings',
            'roles' => 'V09\Roles',
            'dataSources' => 'V09\DataSources',
            'geo' => 'V09\Geo',
        ],
        'v1.0' => [
            'users' => 'Users',
            'groups' => 'Groups',
            'application' => 'Application',
            'authentication' => 'Authentication',
            'account' => 'Account',
            'admin' => 'Admin',
        ],
    ];

    /**
     * @var \GuzzleHttp\ClientInterface $http
     */
    private $http;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var array APIs
     */
    private $cache = [];

    /**
     * @var array
     */
    private $config = [
        'v' => '', // one-call version
        'access_token' => '',
        'default_version' => 'v1.0'
    ];

    /**
     * Client constructor.
     *
     * @param string                           $baseUrl
     * @param array                            $config
     * @param \GuzzleHttp\ClientInterface|null $http
     */
    public function __construct($baseUrl, array $config = [], \GuzzleHttp\ClientInterface $http = null)
    {
        if (is_null($http)) {
            $http = new \GuzzleHttp\Client();
        }

        $this->http = $http;
        $this->baseUrl = $baseUrl;

        $this->config = array_merge($this->config, $config);
    }

    /**
     * @param string $name
     *
     * @return Api\AbstractApi
     * @throws \InvalidArgumentException
     */
    public function __get($name)
    {
        return $this->api($name);
    }

    /**
     * @param  $path
     * @param  $method
     * @param  array  $options
     * @return array
     * @throws GuzzleException
     */
    public function runRequest($path, $method, $options = [])
    {
        if (!empty($this->config['access_token']) && empty($options['headers']['Authorization'])) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->config['access_token'];
        }

        $response = $this->http->request($method, $this->baseUrl . $path, $options);

        return $this->decode(
            $response->getBody()->getContents()
        );
    }

    /**
     * @inheritDoc
     */
    public function get(string $path, array $options = []) : array
    {
        return $this->runRequest($path, 'GET', $options);
    }

    /**
     * @inheritDoc
     */
    public function post(string $path, array $options = []) : array
    {
        return $this->runRequest($path, 'POST', $options);
    }

    /**
     * @inheritDoc
     */
    public function put(string $path, array $options = []): array
    {
        return $this->runRequest($path, 'PUT', $options);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path, array $options = []): array
    {
        return $this->runRequest($path, 'DELETE', $options);
    }

    /**
     * @inheritDoc
     */
    public function patch(string $path, array $options = []): array
    {
        return $this->runRequest($path, 'PATCH', $options);
    }

    /**
     * @param string $name
     *
     * @throws InvalidArgumentException
     *
     * @return Api\AbstractApi
     */
    public function api(string $name)
    {
        // use one-call version or default one
        $version = $this->config['v'] ?: $this->config['default_version'];

        if (!isset($this->classes[$version])) {
            throw new \InvalidArgumentException('Not supported version');
        }

        $classes = $this->classes[$version];

        if (!isset($classes[$name])) {
            throw new \InvalidArgumentException('Available api : '.implode(', ', array_keys($classes)));
        }

        // clear one-call version
        $this->config['v'] = '';

        $vn = sprintf('%s-%s', $version, $name);
        if (isset($this->cache[$vn])) {
            return $this->cache[$vn];
        }

        $c = 'Sisense\Api\\' . $classes[$name];
        $this->cache[$vn] = new $c($this);
        return $this->cache[$vn];
    }

    /**
     * Helper to authenticate
     *
     * @param  string $username
     * @param  string $password
     */
    public function authenticate(string $username = '', string $password = '')
    {
        if ($username) {
            $this->config['username'] = $username;
        }
        if ($password) {
            $this->config['password'] = $password;
        }

        if (empty($this->config['username']) || empty($this->config['password'])) {
            throw new InvalidArgumentException('Credentials not found');
        }

        $response = $this->v('v1.0')->authentication->login($this->config['username'], $this->config['password']);

        $this->useAccessToken($response['access_token']);
    }

    /**
     * Change API version
     *
     * @param $version
     * @param bool $setAsDefault
     * @return $this
     */
    public function useVersion($version, $setAsDefault = false)
    {
        $this->config['v'] = $version;

        if ($setAsDefault) {
            $this->config['default_version'] = $version;
        }

        return $this;
    }

    /**
     * @param  string $accessToken
     * @return $this
     */
    public function useAccessToken(string $accessToken)
    {
        $this->config['access_token'] = $accessToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getBaseUrl() : string
    {
        return $this->baseUrl;
    }

    /**
     * @return \GuzzleHttp\ClientInterface
     */
    public function getHttp() : \GuzzleHttp\ClientInterface
    {
        return $this->http;
    }

    /**
     * Alias for @useVersion
     *
     * @param $version
     * @param bool $setAsDefault
     * @return $this
     */
    public function v($version, $setAsDefault = false)
    {
        return $this->useVersion($version, $setAsDefault);
    }

    /**
     * Returns access_token
     *
     * @return string
     */
    public function getAccessToken() : string
    {
        return $this->config['access_token'];
    }
}
