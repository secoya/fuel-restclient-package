<?php

namespace Secoya\Rest;

/**
 * @api
 * @author Brian K. Christensen, Secoya A/S <bkc@secoya.dk>
 * @package Secoya
 * @subpackage Rest
 */
class HttpException extends RestException
{
	/**
	 * The HTTP response status code.
	 * @var int $statusCode
	 */
	protected $statusCode;

	/**
	 * Key/value of HTTP response headers.
	 * @var array<string, string> $headers
	 */
	protected $headers;

	/**
	 * The rest client causing the exception.
	 * @var Secoya\Rest\Client $client
	 */
	protected $client;

	/**
	 * Constructs the the HTTP exception.
	 * @api
	 * @param string $message
	 * @param int $statusCode
	 * @param array<string, string> $headers
	 * @param Secoya\Rest\Client $client
	 * @return void
	 */
	public function __construct($message, $statusCode, $headers, Client $client)
	{
		$msg = "{$message}\nstatus: {$statusCode}\nheaders:\n".$this->headersToString($headers);
		parent::__construct($msg);
		$this->statusCode = $statusCode;
		$this->headers = $headers;
		$this->client = $client;
	}

	/**
	 * @api
	 * The HTTP response status code of the request.
	 * @return int HTTP status code
	 */
	public function getStatusCode()
	{
		return $this->statusCode;
	}

	/**
	 * @api
	 * Key/value of HTTP response headers.
	 * @return array<string, string> headers
	 */
	public function getHeaders()
	{
		return $this->headers;
	}

	/**
	 * Headers to string
	 *
	 * @param array<string,string> $headers
	 * @return string
	 */
	private function headersToString(array $headers) {
		$res = "";
		foreach($headers as $key => $value) {
			if(!is_array($value)) {
				$res .= "\t{$key}:  {$value}\n";
			}
		}
		return $res;
	}

	/**
	 * @api
	 * The rest client the caused the exception, used for further information about the cause.
	 * @return Secoya\Rest\Client The RESt Client
	 */
	public function getClient()
	{
		return $this->client;
	}
}