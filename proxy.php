<?php

/*
proxy.php by @aaviator42
v0.1, 2024-01-18
License: AGPLv3

*/

// pass client HTTP headers received by the proxy script through to $url
const PASS_HEADERS_FORWARD = true;

// return HTTP headers received from $url back to the client with the proxied response
const RELAY_HEADERS_BACK = true;

// add a header to disable all CORS restrictions
const ACCESS_CONTROL_ALL = true;

// $url = trim($_GET['u']); 						// use this if you want to use proxy.php?u=<URL> format
$url = trim(ltrim($_SERVER['PATH_INFO'], '/'));		// use this if you want to use proxy.php/<URL> format

$extraHeaders = getallheaders(); // get all headers from the original request

$ch = curl_init();

$fetchedHeaders = [];
$fetchedData = NULL;

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 100);

// set the request method based on the client's request
$requestMethod = $_SERVER['REQUEST_METHOD'];
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestMethod);

// pass received headers to cURL request if enabled
if (PASS_HEADERS_FORWARD) {
	//don't send these headers
	unset($extraHeaders['Host']);
	unset($extraHeaders['cdn-loop']);
	unset($extraHeaders['X-Forwarded-For']);
	
	// remove elements with keys starting with "cf-"
	$extraHeaders = array_filter($extraHeaders, function ($key) {
		return strpos($key, 'cf-') !== 0;
	}, ARRAY_FILTER_USE_KEY);
	
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($key, $value) {
        return "$key: $value";
    }, array_keys($extraHeaders), $extraHeaders));
}

// this function is called by curl for each header received
if (RELAY_HEADERS_BACK) {
    curl_setopt($ch, CURLOPT_HEADERFUNCTION,
        function ($curl, $header) use (&$fetchedHeaders) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) // ignore invalid headers
                return $len;

            $fetchedHeaders[strtolower(trim($header[0]))][] = trim($header[1]);

            return $len;
        }
    );
}

$fetchedData = curl_exec($ch);

unset($fetchedHeaders['location']);
unset($fetchedHeaders['x-robots-tag']);

// strip CORS restrictions
if (ACCESS_CONTROL_ALL) {
	header('access-control-allow-origin: *');
} 

// stop indexing by bots
header('x-robots-tag: noindex, nofollow, noarchive');

// relay headers received from $url back to the client
if (RELAY_HEADERS_BACK) {
    foreach ($fetchedHeaders as $name => $content) {
        header($name . ": " . end($content));
    }
}

echo $fetchedData;
?>
