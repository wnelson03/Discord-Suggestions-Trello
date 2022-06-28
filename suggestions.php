<?php
/*
  _   _ _____ _     ____   ___  _   _    ______   ______  _____ ____  ____  _____ ____ _   _ ____  ___ _______   __  _     _     ____ 
 | \ | | ____| |   / ___| / _ \| \ | |  / ___\ \ / / __ )| ____|  _ \/ ___|| ____/ ___| | | |  _ \|_ _|_   _\ \ / / | |   | |   / ___|
 |  \| |  _| | |   \___ \| | | |  \| | | |    \ V /|  _ \|  _| | |_) \___ \|  _|| |   | | | | |_) || |  | |  \ V /  | |   | |  | |    
 | |\  | |___| |___ ___) | |_| | |\  | | |___  | | | |_) | |___|  _ < ___) | |__| |___| |_| |  _ < | |  | |   | |   | |___| |__| |___ 
 |_| \_|_____|_____|____/ \___/|_| \_|  \____| |_| |____/|_____|_| \_\____/|_____\____|\___/|_| \_\___| |_|   |_|   |_____|_____\____|
                                                                                                                                      
																																	  
  Authored by Nelson Cybersecurity LLC - behind projects such as https://keyauth.win and https://letoa.me

  I would appreciate it if you kept this notice while you shared this with other people. I believe this method of having
  suggestions in a trello to-do list is far easier than the standard suggestion format where you can't "check off" a suggestion to signify it's been done.
  
  Tutorial video:
  
  Credits to https://stackoverflow.com/a/71282217 for Discord webhook authentication code
																																	  
*/
$discordPubKey = "";
$trelloIdList = "";
$trelloApiKey = "";
$trelloApiToken = "";
$bannedIds = [];


$payload = file_get_contents('php://input');

if (!isset($_SERVER['HTTP_X_SIGNATURE_ED25519']) || !isset($_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'])) {
	http_response_code(401);
	die();
}

$signature = $_SERVER['HTTP_X_SIGNATURE_ED25519'];
$timestamp = $_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'];

if (!trim($signature, '0..9A..Fa..f') == '')  {
	http_response_code(401);
	die();
}

$message = $timestamp . $payload;
$binarySignature = sodium_hex2bin($signature);
$binaryKey = sodium_hex2bin($discordPubKey);

if (!sodium_crypto_sign_verify_detached($binarySignature, $message, $binaryKey)) {
	http_response_code(401);
	die();
}

$json = json_decode($payload);

$type = $json->type;

if ($type == 1)
{
    die(json_encode(array(
        "type" => 1
    )));
}

$token = $json->token;
$id = $json->id;

if(isset($json->data->components)) {
	
	$body = $json->data->components[0]->components[0]->value;
	
	$userID = $json->member->user->id;
	$username = $json->member->user->username;
	$discrim = $json->member->user->discriminator;

	$body .= " - {$username}#{$discrim} (ID: $userID)";
	$body = urlencode($body);

	$url = "https://api.trello.com/1/cards?idList={$trelloIdList}&key={$trelloApiKey}&token={$trelloApiToken}&name={$body}";
	
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	
	$headers = array(
	"Content-Type: application/json",
	"Content-Length: 0",
	);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	
	$resp = curl_exec($curl);
	$json = json_decode($resp);
	
	$trelloCard = $json->shortUrl;
	
	$post = [
		"type" => 4,
		"data" => [
			"content" => "Successfully submitted suggestion. You can view here {$trelloCard}",
			"flags" => 64
		]
	];
	$url = "https://discord.com/api/v10/interactions/$id/$token/callback";

	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	
	$headers = array(
	"Content-Type: application/json"
	);
	
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	
	$data = json_encode($post);
	
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	
	curl_exec($curl);

	die();
}
$userID = $json->member->user->id;

$post = [];

if(in_array($userID, $bannedIds)) {
	$post = [
		"type" => 4,
		"data" => [
			"content" => "You've been blacklisted from creating suggestions. Contact <@937123634839969854> if you think this is a mistake",
			"flags" => 64
		]
	];
}
else {
	$post = [
	"type" => 9,
	"data" => [
			"title" => "KeyAuth Suggestions", 
			"custom_id" => "keyauth-suggestions", 
			"components" => [
					[
						"type" => 1, 
						"components" => [
						[
							"type" => 4, 
							"custom_id" => "body", 
							"label" => "Enter suggestion:", 
							"style" => 2,
							"min_length" => 5,
							"required" => true, 
							"value" => null, 
							"placeholder" => "Please include images if you think it makes sense to. https://imgur.com good image upload" 
						] 
						] 
					]
				]
		]
	];
}

$url = "https://discord.com/api/v10/interactions/$id/$token/callback";

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$headers = array(
   "Content-Type: application/json"
);

curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

$data = json_encode($post);

curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

curl_exec($curl);

die();

?>