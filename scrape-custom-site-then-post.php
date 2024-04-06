<?php
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, "https://--.com/en/-?page=1");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($curl);

if ($response === false) {
    echo "cURL Error: " . curl_error($curl);
}

curl_close($curl);

mb_internal_encoding("UTF-8");

$host = '--'; // IP address
$username = '--';
$password = '--';
$database = '--';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$doc = new DOMDocument();
libxml_use_internal_errors(true);
$doc->loadHTML('<?xml encoding="UTF-8">' . $response);

libxml_clear_errors();
$xpath = new DOMXPath($doc);


$titleQuery = ".//a[contains(@class, 'woocommerce-LoopProduct-link')]";
$productQuery = "//div[contains(@class, 'products') and contains(@class, 'row')]/article[contains(@class, 'product-z')]";
$products = $xpath->query($productQuery);


$message = "";
$counter = 0;
$maxPerPost = 5;
$threadId = 3;
$itemCount = $products->length;

for ($index = $itemCount - 1; $index >= 0; $index--)
{
    $product = $products->item($index);

    $titleNode = $xpath->query(".//h2[contains(@class, 'product-title')]/a/@href", $product)->item(0);
    $title = $titleNode ? trim($titleNode->nodeValue) : 'No title';

    $imgNode = $xpath->query(".//a[contains(@class, 'product-thumbnail')]/img/@src", $product)->item(0);
    $img = $imgNode ? trim($imgNode->nodeValue) : 'No img';
    $img = str_replace("home_default", "large_default", $img);
    
    $priceNode = $xpath->query(".//div[contains(@class, 'product-price')]/span[@class='price']", $product)->item(0);
    $price = $priceNode ? trim($priceNode->nodeValue) : 'No price';

    $info = extractInfo($title);
    echo "Category: " . $info['category'] . "\n";
    echo "Title: " . $info['title'] . "\n";

    if (!productExists($conn, $title)) {
        $stmt = $conn->prepare("INSERT INTO b_products (title, href, image_path, price, category) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $info['title'], $title, $img, $price, $info['category']);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $dest = "";
            try {
                $dest = downloadImage($img, $title);
            } catch (Exception $e) {
                echo "Caught exception: " . $e->getMessage();
            }
            
            $message .= $info['category'] . "\n[imgnof='" . $title . "']" . $info['title'] . "[/imgnof]\n (" . $price . ")\n\n" . "[IMG]" . $dest . "[/IMG]" . "\n\n\n";
            //$message .= $info['category'] . "\n[URL='" . $title . "']" . $info['title'] . "[/URL]\n (" . $price . ")\n\n" . "[IMG]" . $dest . "[/IMG]" . "\n\n\n";
            $counter++;
            // Check if maxPerPost items have been added or it's the last item in the loop
            if ($counter >= $maxPerPost || $index == 0) {
                $response = postToForum($threadId, $message);
                $message = "";
                $counter = 0;
            }
        }
        $stmt->close();
    }
}

// Function to check if product exists in the database
function productExists($conn, $title) {

    $stmt = $conn->prepare("SELECT COUNT(*) FROM b_products WHERE title = ?");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count > 0;
}

function postToForum($threadId, $message) {
    $url = 'https://---.net/api/posts/';
    $apiKey = '--'; // Your API key
    $apiUser = '1'; // Your API user ID

    $curl = curl_init();

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

function downloadImage($url, $title)
{
    // Sanitize title to create a valid filename
    $sanitizedTitle = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $title);
    echo $sanitizedTitle;
    // Get current month as an integer
    $currentMonth = date('m');

    $filename = $sanitizedTitle . ".jpg";

    // Rest of your code for directory path
    $directoryPath = '/home/sites/71v/d/--/public_html/data/uploads/--/' . $currentMonth;

    if (!is_dir($directoryPath)) {
        if (!mkdir($directoryPath, 0755, true)) {
            echo "Error: Unable to create directory.\n";
            exit;
        }
    }

    $destination = $directoryPath . '/' . $filename;
    echo $destination;
    $ft = "https://--.net/data/uploads/-/" . $currentMonth . "/" . $filename;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $data = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "Error downloading file: $error\n";
        exit;
    }

    $file = fopen($destination, 'w+');
    fwrite($file, $data);
    fclose($file);

    return $ft;
}
function extractInfo($string) {
    // Extract the category part from the URL
    $pattern = '/https:\/\/--\.com\/en\/([^\/]+)\/\d+-/';
    preg_match($pattern, $string, $matches);
    $category = isset($matches[1]) ? trim($matches[1]) : 'Unknown Category';

    $title = preg_replace($pattern, '', $string);

    $title = preg_replace('/\d{13}\.html$/', '', $title);

    $title = ucwords(str_replace('-', ' ', $title));
    $category = ucwords(str_replace('-', ' ', $category));

    $title = trim($title);
    $category = trim($category);

    return array('category' => $category, 'title' => $title);
}


$conn->close();
?>