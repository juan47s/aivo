<?php

$clientId = '0e0bd8ad6c144ffdb9ca59091756810a';
$clientSecret = '36ca4410f881409298f53dcc3f489c7a';
$token = '';

function getToken()
{
	global $clientId;
	global $clientSecret;
	$url = 'https://accounts.spotify.com/api/token';
	$contentType = 'application/x-www-form-urlencoded';
	$data = [
        'grant_type' => 'client_credentials',
    ];
	$query = http_build_query($data);
	$auth = "Basic " . base64_encode($clientId . ":" . $clientSecret);
	$response = request($url, 'POST', 'application/x-www-form-urlencoded', $query, $auth);
	$objRes = json_decode($response);
	$tokenId = $objRes->access_token;
	$tokenType = $objRes->token_type;
    $_SESSION["spotify_token"] = $tokenType . ' ' . $tokenId;
    $_SESSION["spotify_token_expire"] = strtotime(date('Y-m-d H:i:s')) + $objRes->expires_in;
}

function init()
{
	global $token;
	if (session_status() === PHP_SESSION_NONE) {
	    session_start();
	}
	if (!isset($_SESSION["spotify_token"]) || !isset($_SESSION["spotify_token_expire"]) || strtotime(date('Y-m-d H:i:s')) >= $_SESSION["spotify_token_expire"])
	{
	    ini_set('session.gc_maxlifetime', 3600);
	    session_set_cookie_params(3600);
	    getToken();
	}
	$token = $_SESSION["spotify_token"];
}

function request($url = '', $method = 'GET', $contentType = 'application/json', $query = null, $auth = 'default')
{
	global $token;
	if ($auth == 'default')
	{
		$auth = $token;
	}
	$header = [
        'header'  => "Content-Type: ".$contentType."\r\n".
        "Authorization: " . $auth . "\n",
        'method'  => $method,
    ];
    if ($method == 'POST')
    {
    	$header['content'] = $query;
    }
    $context = stream_context_create(['http' => $header]);
    $res = file_get_contents($url, false, $context);
    return $res;
}

function albums($artist)
{
	$res = [];
	$url = 'https://api.spotify.com/v1/artists/'.$artist.'/albums?limit=50';
	$continue = true;
	while ($continue)
	{
		$response = request($url);
	    $objAlbums = json_decode($response);
	    foreach ($objAlbums->items as $item)
	    {
	        $obj = new \stdClass();
	        $obj->name = $item->name;
	        $obj->released = $item->release_date;
	        $obj->tracks = $item->total_tracks;
	        $obj->cover = $item->images[0] ?? '{}';
	        $res[] = $obj;
	    }
	    if ($objAlbums->next != "")
	    {
	        $url = $objAlbums->next;
	        $continue = true;
	    }
	    else
	    {
	        $continue = false;
	    }
	}
	return $res;
}

function artist($name)
{
	$url = 'https://api.spotify.com/v1/search?type=artist&q='.str_replace(' ', '%20', $name);
	$response = request($url);
	$objArtist = json_decode($response);
	$firstArtist = $objArtist->artists->items[0] ?? null;
	if ($firstArtist === null)
	{
		echo '[]';
		die();
	}
	return $firstArtist->id;
}

function main()
{
	init();
	if (!isset($_GET['q']))
	{
		echo '[]';
		die();
	}
	$artistName = $_GET['q'];
	$artist = artist($artistName);
	$albums = albums($artist);
	echo json_encode($albums, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

main();