<?php

class ApplicationWWWFormUrlencodedParser implements IHTTPContentParser
{
	public function parse($string, MIMEType $type)
	{
		// parse form data
		return http_parse_query($string);
	}
	
	public function serialize($data, MIMEType $type)
	{
		// serialize form data
		return http_build_query($data);
	}
}

?>