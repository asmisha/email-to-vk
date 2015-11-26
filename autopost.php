#!/usr/bin/php
<?php

require __DIR__ . '/vendor/autoload.php';

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
    $homeDirectory = getenv('HOME');
    if (empty($homeDirectory)) {
        $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
    }
    return str_replace('~', realpath($homeDirectory), $path);
}

class MailParser
{
    const APPLICATION_NAME = 'Gmail API Mail Parser';
    const SCOPES = \Google_Service_Gmail::GMAIL_MODIFY;

    public function getEmails($from)
    {
        // Get the API client and construct the service object.
        $client = $this->getClient();
        $service = new \Google_Service_Gmail($client);

        $emails = $service->users_messages->listUsersMessages('me', array(
            'q' => 'from:'.$from.' is:unread'
        ));
        $messages = array();
        foreach($emails->getMessages() as $msg){
            /** @var Google_Service_Gmail_Message $msg */

            $msg = $service->users_messages->get('me', $msg->getId());

            /** @var Google_Service_Gmail_MessagePart $payload */
            $payload = $msg->getPayload();

            $payload = $payload->getParts()[0];
            $payload = $payload->getParts()[0];


            $rawData = $payload->getBody()->getData();
            $sanitizedData = strtr($rawData,'-_', '+/');
            $decodedMessage = base64_decode($sanitizedData);

            $decodedMessage = trim(preg_replace('#--.*#s', '', $decodedMessage));
            $messages[] = $decodedMessage;

            // set read
            $mods = new Google_Service_Gmail_ModifyMessageRequest();
            $mods->setRemoveLabelIds(array('UNREAD'));
            $service->users_messages->modify('me', $msg->getId(), $mods);
        }
        return $messages;
    }

    /**
     * Returns an authorized API client.
     * @return \Google_Client the authorized client object
     */
    private function getClient()
    {
        $client = new \Google_Client();
        $client->setApplicationName(self::APPLICATION_NAME);
        $client->setScopes(self::SCOPES);
        $client->setAuthConfigFile(__DIR__.'/client_secret.json');
        $client->setAccessType('offline');

        // Load previously authorized credentials from a file.
        $credentialsPath = expandHomeDirectory('~/.config/email-to-vk-gmail.json');

        if (file_exists($credentialsPath)) {
            $accessToken = file_get_contents($credentialsPath);
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->authenticate($authCode);

            // Store the credentials to disk.
            if(!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), 0700, true);
            }
            file_put_contents($credentialsPath, $accessToken);
            printf("Credentials saved to %s\n", $credentialsPath);
        }
        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $client->refreshToken($client->getRefreshToken());
            file_put_contents($credentialsPath, $client->getAccessToken());
        }
        return $client;
    }
}

class VKPoster{
    public function post($to, $message){
        $credentialsPath = expandHomeDirectory('~/.config/email-to-vk-vk.json');

        if(file_exists($credentialsPath)){
            $accessToken = file_get_contents($credentialsPath);
        }else{
            // Request authorization from the user.
            // Let's pretend we are IPad application 8-)
            $authUrl = 'https://oauth.vk.com/authorize?client_id=3682744&v=5.7&scope=wall,offline&redirect_uri=http://oauth.vk.com/blank.html&display=page&response_type=token';
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter access token: ';
            $accessToken = trim(fgets(STDIN));

            // Store the credentials to disk.
            if(!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), 0700, true);
            }
            file_put_contents($credentialsPath, $accessToken);
            printf("Credentials saved to %s\n", $credentialsPath);
        }

        return file_get_contents('https://api.vk.com/method/wall.post?owner_id='.$to.'&from_group=1&message='.urlencode($message).'&access_token='.$accessToken);
    }
}

$gmail = new MailParser();
$messages = $gmail->getEmails('bsuir-olympiadguys-school@googlegroups.com');

$vk = new VKPoster();
foreach($messages as $m) {
    var_dump($vk->post(-104550496, $m));
}