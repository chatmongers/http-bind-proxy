<?php

## Change the URL do match your 4 letter chatwith.it subdomain name.
## This name has to match your chatmongers XMPP Domain name in order for
## chatmongers to route your requests properly.

# Example:
# $destination = "http://host.chatwith.it:5280/http-bind";

$destination = "http://.chatwith.it:5280/http-bind";

function prepare_headers($keep_header_list, $original_headers){
	$keep_headers = Array();
	foreach( $keep_header_list as $hdr){
		if(isset( $original_headers[$hdr])){
			$keep_headers[$hdr] = $original_headers[$hdr];
		}
	}
	return $keep_headers;
}

function prepare_proxy_request_headers(){
	$keep_request_headers = 
		Array(
			'User-Agent',
			'Cookie',
			'Accept',
			'Accept-Language',
			'Accept-Charset',
			'Content-Type',
			'Content-Encoding'
			);
	
	$original_request_headers = apache_request_headers();
	return prepare_headers($keep_request_headers, $original_request_headers);
}

function prepare_proxy_response_headers($original_response_headers){
	$keep_response_headers =
		Array(
			'Set-Cookie',
			'Content-Type',
			'Content-Length',
			'Access-Control-Allow-Origin',
			'Access-Control-Allow-Headers',
			);
	return prepare_headers($keep_response_headers, $original_response_headers);
}

function http_parse_headers( $header )
{
	$retVal = array();
	$fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
	foreach( $fields as $field ) {
		if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
			$match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
			if( isset($retVal[$match[1]]) ) {
				$retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
			} else {
				$retVal[$match[1]] = trim($match[2]);
			}
		}
	}
	return $retVal;
}

function http_header_strings($headers){
	$header_strings = Array();
	foreach( $headers as $header => $value){
		$header_strings[] = sprintf("%s: %s", $header, $value);
	}
	return $header_strings;
}

try{
	error_reporting(E_ALL | E_STRICT);

	$request_headers = prepare_proxy_request_headers();
	$method = strtoupper($_SERVER['REQUEST_METHOD']);

	$ext_path = str_replace($_SERVER["SCRIPT_NAME"],"",$_SERVER["REQUEST_URI"]);
	$final_destination = $destination . $ext_path;

	$curl = curl_init($final_destination);
	if(in_array($method, Array('POST', 'PUT') )){
		curl_setopt($curl, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
	}

	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
	curl_setopt($curl, CURLOPT_AUTOREFERER, true);
	curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, http_header_strings($request_headers));
	curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_UNRESTRICTED_AUTH, true);

	$response = curl_exec($curl);

	$header_length = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	$header_block = substr($response, 0, $header_length);

	$hdr_list = explode("\r\n", $header_block);

	header($hdr_list[0]);

	$headers = http_parse_headers($header_block);
	$headers = prepare_proxy_response_headers(http_parse_headers($header_block));
	foreach( $headers as $hdr => $val){
		header("$hdr: $val");
	}

	$client_response = substr($response, $header_length);
	echo $client_response;

}catch(Error $e)
{
	header("HTTP/1.1 500 Internal Server Error");
	print $e->getMessage();
}

?>
