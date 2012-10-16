<?php
/**
 * Some license stuff here
 */
namespace Secoya\Rest;

/**
 * This is a REST Client based on cURL
 * @link http://dk.php.net/manual/en/book.curl.php
 *
 * The API is method chainable for methods returning Client,
 * which is the current instance of the Client.
 *
 * @api
 * @package Secoya
 * @subpackage Rest
 * @author Brian K. Christensen, Secoya A/S <bkc@secoya.dk>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 * @example
 * <?php
 *   $rest = new Secoya\Rest\Client('http://example.com/rest/handler',\Secoya\Rest\Client::METHOD_POST);
 *   try{
 *     $rest->set_body(json_encode(array('test' => 'message')))->send();
 *   } catch(\Rest\RestException $e) {
 *     // handle exception
 *   }
 */
class Client
{
	const METHOD_HEAD    = 0;
	const METHOD_GET     = 1;
	const METHOD_POST    = 2;
	const METHOD_PUT     = 3;
	const METHOD_DELETE  = 4;
	const METHOD_OPTIONS = 5;
	const METHOD_PATCH   = 6;

	const CONTENT_JSON      = 'application/json';
	const CONTENT_XML       = 'application/xml';
	const CONTENT_PLAIN     = 'text/plain';
	const CONTENT_OCTET     = 'application/octet-stream';
	const CONTENT_HTML      = 'text/html';
	const CONTENT_CSS       = 'text/css';
	const CONTENT_JS        = 'text/javascript';
	const CONTENT_FORM      = 'application/x-www-form-urlencoded';
	const CONTENT_MULTIPART = 'multipart/form-data';

	/**
	 * Request timeout interval for connect
	 *
	 * @var int The timeout interval in ms for how long a connect may take
	 */
	protected $_connect_timeout_ms = 5000;

	/**
	 * For fileupload this should be set to 0, to prevent the request
	 * times out, it can be done by using the set_request_timeout()
	 * @see Rest\Client::set_request_timeout()
	 *
	 * @var int The timeout interval in ms for how long a request may take
	 */
	protected $_request_timeout_ms = 10000;

	/**
	 * @var string The request URL
	 */
	protected $_request_url = null;
	
	/**
	 * @var int The request port
	 */
	protected $_request_port = 80;
	
	/**
	 * For setting the request method, use the provided class constants
	 * @var int The HTTP Method
	 */
	protected $_request_method = null;
	
	/**
	 * For setting the content-type, use the provided class constants
	 *
	 * @var string The request Content-Type
	 */
	protected $_request_content_type = null;
	
	/**
	 * The request headers
	 * @var array Request headers as key-value pairs
	 *
	 * @example
	 * <?php
	 *   array(
	 *     'X-Override-Method' => 'DELETE',
	 *     'X-Requested-With' => 'xmlhttprequest'
	 *   )
	 */
	protected $_request_headers = array();

	/**
	 * An array is provided when tranfering files
	 * else a string is provided as raw post data
	 *
	 * @var mixed The body of the request.
	 */
	protected $_request_body = null;

	/**
	 * Contains the response status code after a request
	 * @var int The reponse code, the status code is negative, if the request has not yet finished.
	 */
	protected $_response_status_code = -1;

	/**
	 * Contains the response headers after a request
	 * @var array The headers of the response, after a request has been made.
	 */
	protected $_response_headers = null;

	/**
	 * @var string The response body after a request
	 */
	protected $_response_body = '';

	/**
	 * cURL handle
	 * @link http://dk.php.net/manual/en/book.curl.php
	 * @var resource The cURL handle
	 */
	protected $_http = null;

	/**
	 * Constructs a REST client
	 * based on cURL
	 * @link http://dk.php.net/manual/en/book.curl.php
	 * 
	 * @api
	 * @param string $url The URL to call, can explicitly contain port
	 * @param int $method HTTP method, use the provided class constants
	 */
	public function __construct($url, $method = Client::METHOD_GET)
	{
		$this->_request_url = $url;
		$this->_request_method = $method;
		$this->create_handle();
		$this->set_url($url);
		$this->set_method($method);
		$this->set_content_type(Client::CONTENT_JSON);
	}

	/**
	 * Sets the body of the HTTP request
	 * and internally sets the content-length header
	 * so no need to do that manually.
	 * If an array is provied, it is treated as multipart/form-data, used for file transporting
	 * @link http://dk2.php.net/manual/en/function.curl-setopt.php check out the option CURLOPT_POSTFIELDS
	 *
	 * @api
	 * @param mixed $body The body of the HTTP request
	 * @return Rest\Client
	 */
	public function set_body($body)
	{
		if(is_string($body)) {
			$this->set_content_length($body);
		}
		$this->_request_body = $body;
		return $this;
	}

	/**
	 * Sends the request and stores the result in $_response_body
	 * the result can be accessed by calling get_response_body()
	 * @see \Rest\Clinet::get_response_body()
	 *
	 * @api
	 * @return Rest\Client
	 * @throws Rest\RestException
	 */
	public function send()
	{
		$headers = $this->compile_headers();
		curl_setopt($this->_http, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->_http, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->_http, CURLOPT_CONNECTTIMEOUT_MS, $this->_connect_timeout_ms);
		curl_setopt($this->_http, CURLOPT_POSTFIELDS, $this->_request_body);
		curl_setopt($this->_http, CURLOPT_CAINFO, __DIR__ . DS . '../' . 'cacert.pem');
		
		$result = curl_exec($this->_http);
		if($result === false) {
			$error = curl_error($this->_http);
			throw new RestException('cURL error: ' . $error);
		} else {
			$this->_response_headers = curl_getinfo($this->_http);
			$this->_response_status_code = curl_getinfo($this->_http, CURLINFO_HTTP_CODE);
			$this->_response_body = $result;
			
			if($this->_response_status_code > 399) {
				throw new HttpException("The request came back with an error", $this->_response_status_code, $this->_response_headers, $this);
			}
			
			return $this;
		}
	}

	/**
	 * Returns the reponse headers of the request
	 *
	 * @api 
	 * @return array Key-Value representation of the response headers
	 */
	public function get_response_headers()
	{
		return $this->_response_headers;
	}

	/**
	 * Returns the response body of the request
	 *
	 * @api
	 * @return string
	 */
	public function get_response_body()
	{
		return $this->_response_body;
	}

	public function get_response_status_code()
	{
		return $this->_response_status_code;
	}

	/**
	 * Sets the timeout for how long a request may take to process
	 *
	 * @api
	 * @param int $timeout The timeout is provied in ms, use 0 for infinite
	 * @return Rest\Client
	 */
	public function set_request_timeout($timeout) {
		$this->_request_timeout_ms = $timeout;
		curl_setopt($this->_http, CURLOPT_TIMEOUT_MS, $timeout);
		return $this;
	}

	/**
	 * Sets the timeout for how long connect may take.
	 *
	 * @api
	 * @param int $timeout The timeout is provided in ms, use 0 for infinite
	 * @return Rest\Client
	 */
	public function set_connect_timeout($timeout) {
		$this->_connect_timeout_ms = $timeout;
		curl_setopt($this->_http, CURLOPT_CONNECTTIMEOUT_MS, $timeout);
		return $this;
	}

	/**
	 * Set URL
	 *
	 * @api
	 * @param string $url Sets the URL to call
	 * @return Rest\Client
	 */
	public function set_url($url)
	{
		$this->_request_url = $url;
		curl_setopt($this->_http, CURLOPT_URL, $url);
		return $this;
	}

	/**
	 * Set HTTP Method
	 *
	 * @api
	 * @param int $method The HTTP Method to use. Use the provided class constants.
	 * @return Rest\Client
	 * @throws Rest\RestException
	 */
	public function set_method($method)
	{
		$this->clear_method();
		switch ($method) {
			case Client::METHOD_HEAD:
				curl_setopt($this->_http, CURLOPT_CUSTOMREQUEST, "HEAD");
			break;
			case Client::METHOD_GET:
				curl_setopt($this->_http, CURLOPT_HTTPGET, true);
			break;
			case Client::METHOD_POST:
				curl_setopt($this->_http, CURLOPT_POST, true);
			break;
			case Client::METHOD_PUT:
				curl_setopt($this->_http, CURLOPT_PUT, true);
			break;
			case Client::METHOD_DELETE:
				curl_setopt($this->_http, CURLOPT_CUSTOMREQUEST, "DELETE");
			break;
			case Client::METHOD_OPTIONS:
				curl_setopt($this->_http, CURLOPT_CUSTOMREQUEST, "OPTIONS");
			break;
			case Client::METHOD_PATCH:
				curl_setopt($this->_http, CURLOPT_CUSTOMREQUEST, "PATCH");
			break;
			default:
				throw new RestException("Invalid HTTP method given: $method, use the class constants");
			break;
		}
		$this->_request_method = $method;
		return $this;
	}

	/**
	 * Sets the port of the request explicitly
	 * can also just be provided as a part of the url
	 * this overrides the port given in the URL
	 * @see Rest\Client::set_url()
	 *
	 * @api
	 * @param int $port The port to point the request to
	 * @return Rest\Client
	 * @throws Rest\RestException
	 */
	public function set_port($port)
	{
		$port = (int) $port;
		if($port > 0) {
			$this->_request_port = $port;
			curl_setopt($this->_http, CURLOPT_PORT, $port);
		}	else {
			throw new RestException("Invalid port: $port");
		}
		return $this;
	}
	/**
	 * Set the content type for the request.
	 *
	 * @api
	 * @param string $content_type The content type as a string, use the provided class constants
	 * @return Rest\Client
	 */
	public function set_content_type($content_type)
	{
		$this->_request_headers['Content-Type'] = $content_type;
		$this->_request_content_type = $content_type;
		return $this;
	}

	/**
	 * Sets headers as array in key value pairs, e.g.
	 * array('X-Requested-With' => 'xmlhttprequest', '...' => '...')
	 *
	 * Overrides all already existing headers.
	 * To add header(s) to already existing headers
	 * use add_headers()
	 * @see Rest\Client::add_headers()
	 *
	 * @api
	 * @param array $headers An array of key-value pairs representing HTTP headers
	 * @return Rest\Client
	 */
	public function set_headers(array $headers)
	{
		$this->_request_headers = $headers;
		return $this;
	}

	/**
	 * Add header(s) to already existing headers.
	 * The headers should be represented in an array data-structure like this
	 * array('X-Requested-With' => 'xmlhttprequest', '...' => '...')
	 * For overriding the already exisiting headers
	 * @see Rest\Client::set_headers()
	 *
	 * @api
	 * @param array $headers An array of key-value paris representing HTTP headers
	 * @return Rest\Client
	 */
	public function add_headers(array $headers)
	{
		$current_headers = $this->_request_headers;
		$this->_request_headers = array_merge($current_headers, $headers);
		return $this;
	}

	/**
	 * Sets the content length,
	 * calculated based on data given in $body
	 *
	 * @param string $body The content to calculate the content-length from. 
	 * @return void
	 */
	protected function set_content_length($body)
	{
		$this->_request_headers['Content-Length'] = strlen($body);
	}

	/**
	 * Compiles the headers, to a form cURL understatnds
	 * array('Content-Type: application/json','Content-Length: 28')
	 * uses data from already provided headers
	 * used internally inside the set_body() method
	 * @see Rest\Client::$_request_headers
	 * @see Rest\Client::set_body()
	 * 
	 * @return array cURL headers array
	 */
	protected function compile_headers()
	{
		$r = array();
		foreach($this->_request_headers as $k => $v) {
			$r[] = "$k: $v";
		}
		return $r;
	}

	/**
	 * Clears the current set method,
	 * this is necessary because, cURL uses different methods
	 * to identify request methods, depending on type,
	 * so when changing the type, this is called, to fuck up the handle
	 *
	 * @return void
	 */
	protected function clear_method()
	{
		switch($this->_request_method) {
			case Client::METHOD_GET:
				curl_setopt($this->_http, CURLOPT_HTTPGET, false);
			break;
			case Client::METHOD_POST:
				curl_setopt($this->_http, CURLOPT_POST, false);
			break;
			case Client::METHOD_PUT:
				curl_setopt($this->_http, CURLOPT_PUT, false);
			break;
			case Client::METHOD_HEAD:
			case Client::METHOD_DELETE:
			case Client::METHOD_OPTIONS:
			case Client::METHOD_PATCH:
				curl_setopt($this->_http, CURLOPT_CUSTOMREQUEST, null);
			break;
		}
	}

	/**
	 * Creates a new fresh handle, closes the old if it exists.
	 * @see Rest\Client::$_http
	 * @return void
	 */
	protected function create_handle()
	{
		if(!is_null($this->_http)) {
			$this->close_handle();
		}
		$this->_http = curl_init();
	}

	/**
	 * Closes the cURL handle, and removes the reference to it.
	 * @see Rest\Client::$_http
	 * @return void
	 */
	protected function close_handle()
	{
		curl_close($this->_http);
		$this->_http = null;
	}

	/**
	 * Closes the cURL handle
	 * @return void
	 */
	public function __destruct()
	{
		$this->close_handle();
	}
}