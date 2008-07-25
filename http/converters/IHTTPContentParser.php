<?php

interface IHTTPContentParser
{
	public function parse($string, MIMEType $type);
	public function serialize($object, MIMEType $type);
}

?>