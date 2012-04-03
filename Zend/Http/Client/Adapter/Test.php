<?php
 namespace Zend\Http\Client\Adapter; use Zend\Http\Client\Adapter as HttpAdapter, Zend\Http\Client\Adapter\Exception as AdapterException, Zend\Http\Response; class Test implements HttpAdapter { protected $config = array(); protected $responses = array("HTTP/1.1 400 Bad Request\r\n\r\n"); protected $responseIndex = 0; protected $_nextRequestWillFail = false; public function __construct() { } public function setNextRequestWillFail($flag) { $this->_nextRequestWillFail = (bool) $flag; return $this; } public function setConfig($config = array()) { if ($config instanceof \Zend\Config\Config) { $config = $config->toArray(); } elseif (! is_array($config)) { throw new AdapterException\InvalidArgumentException( 'Array or Zend\Config\Config object expected, got ' . gettype($config) ); } foreach ($config as $k => $v) { $this->config[strtolower($k)] = $v; } } public function connect($host, $port = 80, $secure = false) { if ($this->_nextRequestWillFail) { $this->_nextRequestWillFail = false; throw new AdapterException\RuntimeException('Request failed'); } } public function write($method, $uri, $http_ver = '1.1', $headers = array(), $body = '') { $host = $uri->getHost(); $host = (strtolower($uri->getScheme()) == 'https' ? 'sslv2://' . $host : $host); $path = $uri->getPath(); if (empty($path)) { $path = '/'; } if ($uri->getQuery()) $path .= '?' . $uri->getQuery(); $request = "{$method} {$path} HTTP/{$http_ver}\r\n"; foreach ($headers as $k => $v) { if (is_string($k)) $v = ucfirst($k) . ": $v"; $request .= "$v\r\n"; } $request .= "\r\n" . $body; return $request; } public function read() { if ($this->responseIndex >= count($this->responses)) { $this->responseIndex = 0; } return $this->responses[$this->responseIndex++]; } public function close() { } public function setResponse($response) { if ($response instanceof Response) { $response = $response->asString("\r\n"); } $this->responses = (array)$response; $this->responseIndex = 0; } public function addResponse($response) { if ($response instanceof Response) { $response = $response->asString("\r\n"); } $this->responses[] = $response; } public function setResponseIndex($index) { if ($index < 0 || $index >= count($this->responses)) { throw new AdapterException\OutOfRangeException( 'Index out of range of response buffer size'); } $this->responseIndex = $index; } } 