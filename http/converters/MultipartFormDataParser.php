<?php

class MultipartFormDataParser implements IHTTPContentParser
{
	public function parse($string, MIMEType $type)
	{
		// create the form data object
		$data = new MultipartFormData();
		
		// split sections
		$sections = preg_split('/\r\n--' . preg_quote($type->params['boundary']) . '(\r\n|--$)/', $string, -1, PREG_SPLIT_NO_EMPTY);
		// parse each section
		foreach ($sections as $section)
		{
#[TODO] could these be parsed as HTTPMessages?
			// split the header and body
			list ($head, $content) = explode('/\r\n\r\n/', $section, 2);
			// get the headers
			preg_match_all('/(?<=^|\n)([^:]+):\s*(.*?)(?:\r\n|$)/s', $head, $matches, PREG_PATTERN_ORDER);
			$headers = array_change_key_case((array) array_combine($matches[1], $matches[2]), CASE_LOWER);

			// parse the disposition header
			if (!preg_match('/form-data\s*(.*)$/s', $headers['content-disposition'], $matches))
				continue;
			preg_match_all('/\s*;\s*([^=]+)\s*=\s*"((?<=")[^"]*(?=")|[^;]+)/', $matches[1], $matches, PREG_PATTERN_ORDER);
			$disposition = (array) array_combine($matches[1], $matches[2]);

			// parse the content
			$value = $content;
			// parse files
			if (strlen($disposition['filename']))
			{
				// entry is a file, so create an object
				$value = new MultipartFormDataFile();
				$file->content = $content;
				$file->type = $headers['content-type'] ? MIMEType::parse($headers['content-type']) : new MIMEType('text', 'plain');
				$file->filename = $disposition['filename'];
			}
			
			// set data value
			$data[$disposition['name']] = $value;
		}
		
		// return the object
		return $data;
	}
	
	public function serialize(MultipartFormData $data, MIMEType $type)
	{
		// get the boundary key, or generate one
		$boundary = isset($type->params['boundary']) ? $type->params['boundary'] :
			'-----------------------=_' . mt_rand();
			
		// iterate the form data to reconstruct content
		$content = array();
		foreach ((array) $data as $name => $entry)
		{
			// check the type of entry
			if ($entry instanceof MultipartFormDataFile)
			{
				// add the file data
				$content .= 'Content-Disposition: form-data; name="' . $name . '";' .
					'filename="' . urlencode($entry->filename) . "\"\r\n" .
					'Content-Type: ' . $entry->type->serialize() . "\r\n\r\n" . $entry->content;
			}
			else
			{
				// add the form data
				foreach (explode('&', http_build_query($entry, '', '&')) as $value) {
					list ($name, $value) = explode('=', $value);
					$content .= '--' . $boundary . "\r\n" .
						'Content-Disposition: form-data;name="' . $name . "\"\r\n\r\n" . $value;
				}
			}
		}
		// add boundaries
		$content[] = '';
		$content = implode("\r\n--" . $boundary . "--\r\n", $content);
		
		// return serialized data
		return $content;
	}
}

?>