<?php
require_once 'vendor/autoload.php';

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
        'maxResults' => 15
    ));

    $videos = [];
    foreach ($playlistItemsResponse['items'] as $item) {
        $videoId = $item['snippet']['resourceId']['videoId'];
        $title = $item['snippet']['title'];
        array_push($videos, array('id' => $videoId, 'title' => $title));
    }

    return $videos;
}

function videoExists($conn, $videoId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM youtube_videos WHERE video_id = ?");
    $stmt->bind_param("s", $videoId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count > 0;
}

function postToForum($threadId, $message) {
    $url = 'https://mysite.net/api/posts/';
    $apiKey = 'CijefifeEFIFE_FEIJefifkeo_TV';
    $apiUser = '3';

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

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
        return false;
    } else {
        return $response;
    }
}


$host = 'fun.private-host.net'; // IP address
$username = 'cronjobs-99660a06';
$password = 'eijcjunfrrfis';
$database = 'cronjobs-4078552a06';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully\n";


$client = new Google_Client();
$client->setDeveloperKey('mykey');

$videos = getChannelUploads($client, 'channelid');
foreach ($videos as $video) {

    if (!videoExists($conn, $video['id'])) {

        $stmt = $conn->prepare("INSERT INTO youtube_videos (video_id, video_title) VALUES (?, ?)");

        $stmt->bind_param("ss", $videoId, $videoTitle);

        $videoId = $video['id'];
        $videoTitle = $video['title'];
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo "New record created successfully";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
        
        $threadId = 1;
        $message = $videoTitle . ".\n\n" . "[MEDIA=youtube]" . $videoId . "[/MEDIA]"; // Your message
        $response = postToForum($threadId, $message);
        
        if ($response) {
            echo "Response: " . $response;
        } else {
            echo "Failed to post to forum";
        }
        
        
    } else {
        echo "Video already exists in the database.\n";
    }
}
?>