<?php

class ApplicationXMLParser implements IHTTPContentParser
{
	public function parse($string, MIMEType $type)
	{
		// parse XML string
		$doc = new DOMDocument();
		$doc->loadXML($string);
		return $doc;
	}
	
	public function serialize($data, MIMEType $type)
	{
		// convert simpleXML
		if ($data instanceof SimpleXMLElement)
			$data = dom_import_simplexml($data)->ownerDocument;
		
		// serialize XML
		$data->ownerDocument->formatOutput = true;
		return $data->ownerDocument->saveXML($data);
	}
}

?>