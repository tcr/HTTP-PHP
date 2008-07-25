<?php

/**
 * HTTP Message Base Class
 * @package chowdah.http
 */

abstract class HTTPMessage {
	// message data
	protected $version = 1.0;
	protected $headers;
	protected $content = null;
	// cookies array
	protected $cookies;

	function __construct() {
		// initialize array objects
		$this->headers = new HTTPHeaderArray(array());
		$this->cookies = new HTTPCookieArray($this);
	}

	// abstract functions
	abstract public function send();
	abstract static public function getCurrent();
	abstract static public function parse($data);

	//----------------------------------------------------------------------
	// HTTP version
	//----------------------------------------------------------------------

	public function getHTTPVersion() {
		// return the http version
		return $this->version;
	}

	public function setHTTPVersion($version) {
		// set the http version
		return $this->version = (float) $version;
	}

	//----------------------------------------------------------------------
	// headers
	//----------------------------------------------------------------------

	public function getHeader($key) {
		return isset($this->headers[$key]) ? $this->headers[$key] : false;
	}

	public function setHeader($key, $value, $overwrite = true) {
		return $this->headers->offsetSet($key, $value, $overwrite);
	}

	public function deleteHeader($key) {
		unset($this->headers[$key]);
	}

	//----------------------------------------------------------------------
	// content
	//----------------------------------------------------------------------

#[TODO] make getDecodedContent a parameter option?
	public function getContent() {
		// return the content
		return $this->content;
	}

	public function setContent($content, $encoding = null) {
		// set the content
		$this->content = $content !== null ? (string) $content : null;

		// set encoding type
		if ($encoding && $encoding->type != 'identity')
			$this->setHeader('Content-Encoding', $encoding->serialize(false));
		else
			$this->deleteHeader('Content-Encoding');

		// set content length
		if (strlen($content))
			$this->setHeader('Content-Length', strlen($content));
		else
			$this->deleteHeader('Content-Length');

		// set MD5
		if ($this->getHeader('Content-MD5'))
			$this->generateMD5Digest();
		
		// clear parsed content cache
		$this->parsedContentCache = null;
	}

	public function deleteContent() {
		// clear content
		$this->content = null;
		// clear content headers
		$this->deleteHeader('Content-Encoding');
		$this->deleteHeader('Content-Length');
		$this->deleteHeader('Content-MD5');
	}

	public function appendContent() {
		$args = (array) func_get_args();
		return $this->content .= implode('', $args);
	}

	public function prependContent() {
		$args = (array) func_get_args();
		return $this->content = implode('', $args) . $this->content;
	}

	//----------------------------------------------------------------------
	// content type
	//----------------------------------------------------------------------

	public function getContentType() {
		if ($this->getHeader('Content-Type'))
			return MIMEType::parse($this->getHeader('Content-Type'));
		else
			return MIMEType::create('application', 'octet-stream');
	}

	public function setContentType(MIMEType $mimetype) {
		return $this->setHeader('Content-Type', $mimetype->serialize(true));
	}

	public function deleteContentType() {
		$this->deleteHeader('Content-Type');
	}

	//----------------------------------------------------------------------
	// content language
	//----------------------------------------------------------------------

	public function getContentLanguage() {
		$lang = $this->getHeader('Content-Language');
		return strpos($lang, ',') !== false ? preg_split('/[\s,]+/', $lang) : $lang;
	}

	public function setContentLanguage($lang) {
		return $this->setHeader('Content-Language', implode(', ', (array) $lang));
	}

	public function deleteContentLanguage() {
		$this->deleteHeader('Content-Language');
	}

	//----------------------------------------------------------------------
	// content ranges
	//----------------------------------------------------------------------

	public function getContentRange() {
		// split ranges
		$rangeArray = preg_split('/[\s,]+/', (string) $this->getHeader('Content-Range'));

		// iterate ranges
		$ranges = array();
		foreach ($rangeArray as $rangeString) {
			// parse the range
			if (!preg_match('/^\s*bytes (?P<range>(?P<start>\d+)-(?P<end>\d+)|\*)' .
			    '\/(?P<length>(\d+)|\*)\s*$/', $rangeString, $matches))
				continue;

			// prevent invalid ranges
			extract($matches);
			if (($range != '*' && $start < $end) || ($length != '*' && $length <= $end))
				continue;
			// add it to the range array
			$ranges[] = (object) $matches;
		}

		// return ranges
		return $ranges;
	}

#[TODO] setContentRange

	public function deleteContentRange() {
		$this->deleteHeader('Content-Range');
	}

	//----------------------------------------------------------------------
	// content location
	//----------------------------------------------------------------------

	public function getContentLocation() {
		return URL::parse($this->getHeader('Content-Location'));
	}

	public function setContentLocation(URL $url) {
		return $this->setHeader('Content-Location', $url->serialize());
	}

	public function deleteContentLocation() {
		$this->deleteHeader('Content-Location');
	}

	//----------------------------------------------------------------------
	// content md5
	//----------------------------------------------------------------------

	public function generateMD5Digest() {
		// set the Content-MD5 header
		return $this->getContent() === null ? false :
		    $this->setHeader('Content-MD5', base64_encode(md5($this->getContent(), true)));
	}
	
	public function deleteMD5Digest() {
		$this->deleteHeader('Content-MD5');
	}

	//----------------------------------------------------------------------
	// content encoding
	//----------------------------------------------------------------------

	public function getEncodedContent(EncodingType $type) {
		// get the decoded content
		if (($content = $this->getDecodedContent()) === false)
			return false;

		// encode and return the content
		switch ($encodingtype->serialize(false)) {
			case 'gzip':
				return gzencode($content);
				break;

			case 'deflate':
				return gzdeflate($content, $encodingtype->params['level']);
				break;

			case 'identity':
				return $content;

			default:
				return false;
		}
	}
	
#[TODO] registerContentEncoder
	public function getDecodedContent() {
		// check that there is content to decode
		if ($this->getContent() === null)
			return false;
		// check if there is any encoding
		if (!$this->getHeader('Content-Encoding'))
			return $this->getContent();
		// check that any specified encoding is valid
		if (!($encoding = EncodingType::parse($this->getHeader('Content-Encoding'))))
			return false;
		
		// revert any encoding applied to the content
		switch ($encoding->type) {
			case 'gzip':
				return gzinflate(substr($this->getContent(), 10, -4));

			case 'deflate':
				return gzuncompress(gzinflate($this->getContent()));

			case 'identity':
			default:
				return $this->getContent();
		}
	}

#[TODO] support multiple encodings

	public function encodeContent($accepted = array()) {
		// attempt to get the raw content data
		if (!($content = $this->decodeContent()))
			return false;

		// get the most preferred, available compression
		$encodingtypes = EncodingType::findBestMatches((array) $accepted, array(
			EncodingType::create('gzip'),
			EncodingType::create('deflate')
		    ));
		if (!($encodingtype = $encodingtypes[0]))
			$encodingtype = EncodingType::create('identity');

		// set and return the encoded data
		if (($content = $this->getEncodedContent($encodingtype)) !== false)
			return $this->setContent($content, $encodingtype);
		else
			return false;
	}

	public function decodeContent() {
		// set and return the decoded data
		if (($content = $this->getDecodedContent()) !== false)
			return $this->setContent($content);
		else
			return false;
	}

	//----------------------------------------------------------------------
	// parsed content
	//----------------------------------------------------------------------

#[TODO] clear this cache?
	protected $parsedContentCache = null;

	public function getParsedContent() {
		// if this content was cached, return that
		if ($this->parsedContentCache)
			return $this->parsedContentCache;
		
		// set error handler to check for bad requests
		set_error_handler(array('HTTPMessage', 'parsedContentErrorHandler'), error_reporting());
		
		// get a parsed representation of the content (based on MIME type)
		$content = $this->getContent();
		if ($parserClass = HTTPMessage::$contentParser[$type->serialize(false)])
		{
			// parse using a converter
			$parser = new $parserClass;
			$content = $parser->parse($content, $this->getContentType());
		}
		
		// restore Chowdah error handler
		restore_error_handler();	
		// cache and return the content
		return ($this->parsedContentCache = $content);
	}
	
	public static function parsedContentErrorHandler($errno, $errstr, $errfile, $errline)
	{
		// restore Chowdah error handler
		restore_error_handler();
		
		// throw a HTTP 400 Bad Request exception
		if (error_reporting())
			throw new HTTPStatusException(HTTPStatus::BAD_REQUEST, 'Bad Request', 'The submitted content body was malformed.');
	}

	public function setParsedContent($data, MIMEType $type = null) {
		// set content type
		if ($type)
			$this->setContentType($type);
			
		// get a serialized representation of the content (based on MIME type)
		$content = $data;
		if ($parserClass = HTTPMessage::$contentParser[$type->serialize(false)])
		{
			// serialize using a converter
			$parser = new $parserClass;
			$content = $parser->serialize($data, $this->getContentType());
		}
		
		// set content
		$this->setContent($content);
		// save data in cache
		return ($this->parsedContentCache = $data);
	}
	
	static protected function $contentParser = array();
	
	static public function registerContentParser(MIMEType $type, $class)
	{
		HTTPMessage::$contentParser[$type->serialize(false)] = $class;
	}

	//----------------------------------------------------------------------
	// cookies
	//----------------------------------------------------------------------

	public function getCookie($name) {
		return $this->cookies[$name];
	}

	public function setCookie($name, $value) {
		return $this->cookies[$name] = $value;
	}
	
	public function deleteCookie($name) {
		unset($this->cookies[$name]);
	}

	//----------------------------------------------------------------------
	// user agent
	//----------------------------------------------------------------------
	
	public function getUserAgent() {
		return $this->getHeader('User-Agent');
	}

	public function setUserAgent($agent) {
		return $this->setHeader('User-Agent', $agent);
	}

	public function deleteUserAgent() {
		$this->deleteHeader('User-Agent');
	}
	
	public function getUserAgentInfo() {
#[TODO] use workarounds?
		return @get_browser($this->getUserAgent());
	}
	
	//----------------------------------------------------------------------
	// variable overloading
	//----------------------------------------------------------------------

	function __get($key)
	{
		switch ($key)
		{
			case 'headers': return $this->headers;
			case 'cookies': return $this->cookies;
			case 'parsedContent': return $this->getParsedContent();
		}
	}

	function __set($key, $value) {
		switch ($key) {
			case 'parsedContent': return $this->setParsedContent($value);
		}
	}
}

//==============================================================================
// content converters
//==============================================================================

HTTPMessage::registerContentParser(new MIMEType('text', 'xml'), 'ApplicationXMLParser');
HTTPMessage::registerContentParser(new MIMEType('application', 'xml'), 'ApplicationXMLParser');
HTTPMessage::registerContentParser(new MIMEType('application', 'xhtml+xml'), 'ApplicationXMLParser');
HTTPMessage::registerContentParser(new MIMEType('text', 'html'), 'TextHTMLParser');
HTTPMessage::registerContentParser(new MIMEType('multipart', 'form-data'), 'MultipartFormDataParser');
HTTPMessage::registerContentParser(new MIMEType('application', 'x-www-form-urlencoded'), 'ApplicationWWWFormUrlencodedParser');

//==============================================================================
// http functions
//==============================================================================

function http_parse_query($query, $arg_separator = null) 
{
	// get the arg separator
	if (strlen($arg_separator))
		$query = str_replace(array('&', $arg_separator), array('\&', '&'), $query);
	// parse the string
	parse_str($query, $data);
	// parameters without equal signs are set to 'true'
	foreach (explode('&', $query) as $value)
		if (!strpos($value, '='))
			$data[$value] = true;
	// strip quotes
	return strip_magic_quotes($data);
}

function strip_magic_quotes($array, $isTopLevel = true)
{
	// see: http://us2.php.net/manual/en/function.get-magic-quotes-gpc.php#49612
	$isMagic = get_magic_quotes_gpc();
	$cleanArray = array();
	foreach ((array) $array as $key => $value) {
		if (is_array($value))
			$cleanArray[$isMagic && !$isTopLevel ? stripslashes($key) : $key] =
			    strip_magic_quotes($value, false);
		else
			$cleanArray[stripslashes($key)] = ($isMagic) ?
			    stripslashes($value) : $value;
	}
	return $cleanArray;
}

?>
