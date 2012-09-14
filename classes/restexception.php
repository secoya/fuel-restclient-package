<?php
namespace Secoya\Rest;
use \FuelException;
/**
 * Rest specific exception, trown on errors in the REST client
 *
 * @api
 * @package Secoya
 * @subpackage Rest
 * @author Brian K. Christensen, Secoya A/S <bkc@secoya.dk>
 */
class RestException extends FuelException {}