<?php
require_once 'vendor/autoload.php';

$host = '--'; // IP address
$username = '--';
$password = '--';
$database = '--';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$client = new Google_Client();
$client->setDeveloperKey('--');

ex('channelid1', $client, $conn);

ex('channelid2', $client, $conn);

ex('channelid3', $client, $conn);

ex('channelid4', $client, $conn);

ex('channelid5', $client, $conn);

ex('channelid6', $client, $conn);

ex('channelid7', $client, $conn);

function ex($channelId, $client, $conn)
{
    $videos = getChannelUploads($client, $channelId);
    foreach ($videos as $video) {
        //echo "ID: " . $video['id'] . ", Title: " . $video['title'] . "\n";
        if (!videoExists($conn, $video['id'])) {
            // Prepare an SQL statement
            $stmt = $conn->prepare("INSERT INTO x (video_id, video_title) VALUES (?, ?)");
            // Bind parameters to the SQL statement
            $stmt->bind_param("ss", $videoId, $videoTitle);

            // Set parameters and execute
            $videoId = $video['id'];
            $videoTitle = $video['title'];
            $stmt->execute();
            // Close the statement
            $stmt->close();  
            
            // post a thread reply
            $threadId = 4;
            $message = $videoTitle . "\n\n" . "[MEDIA=youtube]" . $videoId . "[/MEDIA]"; // Your message
            $response = postToForum($threadId, $message); 
        }
    }
}

function getChannelUploads($client, $channelId) {
    $youtube = new Google_Service_YouTube($client);

    // Get channel details
    $channelResponse = $youtube->channels->listChannels('contentDetails', array(
        'id' => $channelId
    ));
    $uploadsPlaylistId = $channelResponse['items'][0]['contentDetails']['relatedPlaylists']['uploads'];

    // Get the list of videos
    $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('snippet', array(
        'playlistId' => $uploadsPlaylistId,
        'maxResults' => 5
    ));

    $videos = [];
    $items = $playlistItemsResponse['items'];
    $itemCount = count($items);

    for ($i = $itemCount - 1; $i >= 0; $i--) {
        $item = $items[$i];
        $videoId = $item['snippet']['resourceId']['videoId'];
        $title = $item['snippet']['title'];
        array_push($videos, array('id' => $videoId, 'title' => $title));
    }
    return $videos;
}

function videoExists($conn, $videoId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM x WHERE video_id = ?");
    $stmt->bind_param("s", $videoId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count > 0;
}

function postToForum($threadId, $message) {
    $url = 'https://--.net/api/posts/';
    $apiKey = '--'; // Your API key
    $apiUser = '1'; // Your API user ID

    // Initialize cURL session
    $curl = curl_init();

    // Set cURL options
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => http_build_query([
            'thread_id' => $threadId,
            'message' => $message
        ]),
        CURLOPT_HTTPHEADER => [
            "XF-Api-Key: $apiKey",
            "XF-Api-User: $apiUser",
            "Content-Type: application/x-www-form-urlencoded"
        ],
    ]);

    // Execute cURL session and get the response
    $response = curl_exec($curl);
    $err = curl_error($curl);

    // Close cURL session
    curl_close($curl);

    // Check for errors and return the response
    if ($err) {
        echo "cURL Error #:" . $err;
        return false;
    } else {
        return $response;
    }
}
?>