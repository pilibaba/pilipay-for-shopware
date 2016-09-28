<?php

/**
 * (c) PILIBABA INTERNATIONAL CO.,LTD. <info@pilibaba.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Shopware_Components_Pilipay_Client extends Zend_Http_Client
{

    /**
     * Update of the track number url
     *
     * @var string
     */
    const URL_UPDATE_TRACK_NUMBER = 'https://www.pilibaba.com/pilipay/updateTrackNo';

    /**
     * Access URL
     *
     * @var string
     */
    const URL_LIVE = 'https://www.pilibaba.com/pilipay/payreq';

    protected $pluginConfig;

    /**
     * Check, whether the redirect is needed
     *
     * @var boolean
     */
    public $redirect;

    /**
     * Constructor methode
     *
     * Expects a configuration parameter
     *
     * @param Enlight_Config $config
     */
    public function __construct($config)
    {
        $this->pluginConfig = $config;
        parent::__construct($this->getBaseUri());
        $this->setAdapter(self::createAdapterFromConfig($config));
    }

    /**
     * @param Enlight_Config $config
     *
     * @return Zend_Http_Client_Adapter_Curl|Zend_Http_Client_Adapter_Socket
     */
    public static function createAdapterFromConfig($config)
    {

        $curl       = true;
        $sslVersion = 0;
        $timeout    = 60;
        $userAgent  = 'Shopware/' . Shopware::VERSION;

        if ($curl && extension_loaded('curl')) {
            $adapter = new Zend_Http_Client_Adapter_Curl();
            $adapter->setConfig(array(
                'useragent' => $userAgent,
                'timeout'   => $timeout,
            ));

            $adapter->setCurlOption(CURLOPT_TIMEOUT, $timeout);
            $adapter->setCurlOption(CURLOPT_SSLVERSION, $sslVersion);

            //$adapter->setCurlOption(CURLOPT_SSL_CIPHER_LIST, 'TLSv1');
            //$adapter->setCurlOption(CURLOPT_SSL_VERIFYPEER, 1);
            //$adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            $adapter = new Zend_Http_Client_Adapter_Socket();
            $adapter->setConfig(array(
                'useragent'    => $userAgent,
                'timeout'      => $timeout,
                'ssltransport' => ($sslVersion > 3 || $sslVersion == 1) ? 'tls' : 'ssl',
            ));
        }

        return $adapter;
    }

    public function getBaseUri()
    {
        return self::URL_LIVE;
    }

    public function create($uri, $params)
    {
        $this->setRawData(json_encode($params), 'application/json');

        return $this->post($uri);
    }

    public function update($uri, $params)
    {
        $this->setRawData(json_encode($params), 'application/json');

        return $this->put($uri);
    }

    public function request($method = null, $uri = null, $params = null)
    {
        if ($method !== null) {
            $this->setMethod($method);
        }
        if ($uri !== null) {
            if (strpos($uri, 'http') !== 0) {
                $uri = $this->getBaseUri() . $uri;
            }
            $this->setUri($uri);
        }
        if ($params !== null) {
            $this->resetParameters();
            if ($this->method == self::POST) {
                $this->setMethod($this->method);
//                $this->setCurlOption(CURLOPT_FOLLOWLOCATION, true);
                $this->setParameterPost($params);
            } else {
                $this->setParameterGet($params);
            }
        }
        $response = parent::request();

        return $this->filterResponse($response);
    }

    private function filterResponse($response)
    {
        $body = $response->getBody();

        $data            = array();
        $data['status']  = $response->getStatus();
        $data['message'] = $response->getMessage();

        if (strpos($response->getHeader('content-type'), 'application/json') === 0) {
            $body = json_decode($body, true);
        }
        if ( ! is_array($body)) {
            $body = array('body' => $body);
        }

        return $data + $body;
    }

    public function get($uri = null, $params = null)
    {
        return $this->request(self::GET, $uri, $params);
    }

    public function post($uri = null, $params = null)
    {
        return $this->request(self::POST, $uri, $params);
    }

}
