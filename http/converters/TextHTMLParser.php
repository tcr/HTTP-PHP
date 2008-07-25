<?php

class TextHTMLParser implements IHTTPContentParser
{
	public function parse($string, MIMEType $type)
	{
		// parse HTML string
		$doc = new DOMDocument();
		if (function_exists('mb_convert_encoding'))
			$string = mb_convert_encoding($string, 'HTML-ENTITIES', mb_detect_encoding($string)); 
		else 
			$string = '<meta http-equiv="content-type" content="text/html; charset=utf-8">' . $string;
		$doc->loadHTML($string);
		return $doc;
	}
	
	public function serialize($data, MIMEType $type)
	{
		// serialize HTML
		$data->ownerDocument->formatOutput = true;
		return $data->ownerDocument->saveHTML();
	}
}

?>