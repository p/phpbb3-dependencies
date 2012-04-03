<?php
 namespace Zend\Http\Client\Adapter; use Zend\Http\Client\Adapter as HttpAdapter, Zend\Http\Client\Adapter\Exception as AdapterException, Zend\Http\Client; class Curl implements HttpAdapter, Stream { protected $_config = array(); protected $_connected_to = array(null, null); protected $_curl = null; protected $_invalidOverwritableCurlOptions; protected $_response = null; protected $out_stream; public function __construct() { if (!extension_loaded('curl')) { throw new AdapterException\InitializationException('cURL extension has to be loaded to use this Zend\Http\Client adapter'); } $this->_invalidOverwritableCurlOptions = array( CURLOPT_HTTPGET, CURLOPT_POST, CURLOPT_PUT, CURLOPT_CUSTOMREQUEST, CURLOPT_HEADER, CURLOPT_RETURNTRANSFER, CURLOPT_HTTPHEADER, CURLOPT_POSTFIELDS, CURLOPT_INFILE, CURLOPT_INFILESIZE, CURLOPT_PORT, CURLOPT_MAXREDIRS, CURLOPT_CONNECTTIMEOUT, CURL_HTTP_VERSION_1_1, CURL_HTTP_VERSION_1_0, ); } public function setConfig($config = array()) { if ($config instanceof \Zend\Config\Config) { $config = $config->toArray(); } elseif (! is_array($config)) { throw new AdapterException\InvalidArgumentException( 'Array or Zend\Config\Config object expected, got ' . gettype($config) ); } if(isset($config['proxy_user']) && isset($config['proxy_pass'])) { $this->setCurlOption(CURLOPT_PROXYUSERPWD, $config['proxy_user'].":".$config['proxy_pass']); unset($config['proxy_user'], $config['proxy_pass']); } foreach ($config as $k => $v) { $option = strtolower($k); switch($option) { case 'proxy_host': $this->setCurlOption(CURLOPT_PROXY, $v); break; case 'proxy_port': $this->setCurlOption(CURLOPT_PROXYPORT, $v); break; default: $this->_config[$option] = $v; break; } } return $this; } public function getConfig() { return $this->_config; } public function setCurlOption($option, $value) { if (!isset($this->_config['curloptions'])) { $this->_config['curloptions'] = array(); } $this->_config['curloptions'][$option] = $value; return $this; } public function connect($host, $port = 80, $secure = false) { if ($this->_curl) { $this->close(); } if ($this->_curl && is_array($this->_connected_to) && ($this->_connected_to[0] != $host || $this->_connected_to[1] != $port) ) { $this->close(); } $this->_curl = curl_init(); if ($port != 80) { curl_setopt($this->_curl, CURLOPT_PORT, intval($port)); } curl_setopt($this->_curl, CURLOPT_CONNECTTIMEOUT, $this->_config['timeout']); curl_setopt($this->_curl, CURLOPT_MAXREDIRS, $this->_config['maxredirects']); if (!$this->_curl) { $this->close(); throw new AdapterException\RuntimeException('Unable to Connect to ' . $host . ':' . $port); } if ($secure !== false) { if (isset($this->_config['sslcert'])) { curl_setopt($this->_curl, CURLOPT_SSLCERT, $this->_config['sslcert']); } if (isset($this->_config['sslpassphrase'])) { curl_setopt($this->_curl, CURLOPT_SSLCERTPASSWD, $this->_config['sslpassphrase']); } } $this->_connected_to = array($host, $port); } public function write($method, $uri, $httpVersion = 1.1, $headers = array(), $body = '') { if (!$this->_curl) { throw new AdapterException\RuntimeException("Trying to write but we are not connected"); } if ($this->_connected_to[0] != $uri->getHost() || $this->_connected_to[1] != $uri->getPort()) { throw new AdapterException\RuntimeException("Trying to write but we are connected to the wrong host"); } curl_setopt($this->_curl, CURLOPT_URL, $uri->__toString()); $curlValue = true; switch ($method) { case Client::GET: $curlMethod = CURLOPT_HTTPGET; break; case Client::POST: $curlMethod = CURLOPT_POST; break; case Client::PUT: if(is_resource($body)) { $this->_config['curloptions'][CURLOPT_INFILE] = $body; } if (isset($this->_config['curloptions'][CURLOPT_INFILE])) { foreach ($headers AS $k => $header) { if (preg_match('/Content-Length:\s*(\d+)/i', $header, $m)) { if(is_resource($body)) { $this->_config['curloptions'][CURLOPT_INFILESIZE] = (int)$m[1]; } unset($headers[$k]); } } if (!isset($this->_config['curloptions'][CURLOPT_INFILESIZE])) { throw new AdapterException\RuntimeException("Cannot set a file-handle for cURL option CURLOPT_INFILE without also setting its size in CURLOPT_INFILESIZE."); } if(is_resource($body)) { $body = ''; } $curlMethod = CURLOPT_PUT; } else { $curlMethod = CURLOPT_CUSTOMREQUEST; $curlValue = "PUT"; } break; case Client::DELETE: $curlMethod = CURLOPT_CUSTOMREQUEST; $curlValue = "DELETE"; break; case Client::OPTIONS: $curlMethod = CURLOPT_CUSTOMREQUEST; $curlValue = "OPTIONS"; break; case Client::TRACE: $curlMethod = CURLOPT_CUSTOMREQUEST; $curlValue = "TRACE"; break; case Client::HEAD: $curlMethod = CURLOPT_CUSTOMREQUEST; $curlValue = "HEAD"; break; default: throw new AdapterException\InvalidArgumentException("Method currently not supported"); } if(is_resource($body) && $curlMethod != CURLOPT_PUT) { throw new AdapterException\RuntimeException("Streaming requests are allowed only with PUT"); } $curlHttp = ($httpVersion == 1.1) ? CURL_HTTP_VERSION_1_1 : CURL_HTTP_VERSION_1_0; curl_setopt($this->_curl, $curlHttp, true); curl_setopt($this->_curl, $curlMethod, $curlValue); if($this->out_stream) { curl_setopt($this->_curl, CURLOPT_HEADER, false); curl_setopt($this->_curl, CURLOPT_HEADERFUNCTION, array($this, "readHeader")); curl_setopt($this->_curl, CURLOPT_FILE, $this->out_stream); } else { curl_setopt($this->_curl, CURLOPT_HEADER, true); curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true); } $headers['Accept'] = ''; curl_setopt($this->_curl, CURLOPT_HTTPHEADER, $headers); if ($method == Client::POST) { curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $body); } elseif ($curlMethod == CURLOPT_PUT) { curl_setopt($this->_curl, CURLOPT_INFILE, $this->_config['curloptions'][CURLOPT_INFILE]); curl_setopt($this->_curl, CURLOPT_INFILESIZE, $this->_config['curloptions'][CURLOPT_INFILESIZE]); unset($this->_config['curloptions'][CURLOPT_INFILE]); unset($this->_config['curloptions'][CURLOPT_INFILESIZE]); } elseif ($method == Client::PUT) { curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $body); } if (isset($this->_config['curloptions'])) { foreach ((array)$this->_config['curloptions'] as $k => $v) { if (!in_array($k, $this->_invalidOverwritableCurlOptions)) { if (curl_setopt($this->_curl, $k, $v) == false) { throw new AdapterException\RuntimeException(sprintf("Unknown or erroreous cURL option '%s' set", $k)); } } } } $response = curl_exec($this->_curl); if(!is_resource($this->out_stream)) { $this->_response = $response; } $request = curl_getinfo($this->_curl, CURLINFO_HEADER_OUT); $request .= $body; if (empty($this->_response)) { throw new AdapterException\RuntimeException("Error in cURL request: " . curl_error($this->_curl)); } if (stripos($this->_response, "Transfer-Encoding: chunked\r\n")) { $this->_response = str_ireplace("Transfer-Encoding: chunked\r\n", '', $this->_response); } do { $parts = preg_split('|(?:\r?\n){2}|m', $this->_response, 2); $again = false; if (isset($parts[1]) && preg_match("|^HTTP/1\.[01](.*?)\r\n|mi", $parts[1])) { $this->_response = $parts[1]; $again = true; } } while ($again); if (stripos($this->_response, "HTTP/1.0 200 Connection established\r\n\r\n") !== false) { $this->_response = str_ireplace("HTTP/1.0 200 Connection established\r\n\r\n", '', $this->_response); } return $request; } public function read() { return $this->_response; } public function close() { if(is_resource($this->_curl)) { curl_close($this->_curl); } $this->_curl = null; $this->_connected_to = array(null, null); } public function getHandle() { return $this->_curl; } public function setOutputStream($stream) { $this->out_stream = $stream; return $this; } public function readHeader($curl, $header) { $this->_response .= $header; return strlen($header); } } 