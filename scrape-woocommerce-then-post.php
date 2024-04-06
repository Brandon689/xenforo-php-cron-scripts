<?php
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, "https://crawl.something/");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($curl);

if ($response === false) {
    echo "cURL Error: " . curl_error($curl);
}

curl_close($curl);


// Database connection
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
$doc->loadHTML($response);
libxml_clear_errors();

// Create a new XPath object
$xpath = new DOMXPath($doc);

// Define XPath queries
$srcsetQuery = "//li[contains(@class,'ast-grid-common-col')]/div/a/img[1]/@data-lazy-srcset";
$titleQuery = "//li[contains(@class,'ast-grid-common-col')]//h2[@class='woocommerce-loop-product__title']";

$hrefQuery = "//li[contains(@class,'ast-grid-common-col')]//a[contains(@class,'woocommerce-LoopProduct-link woocommerce-loop-product__link')]/@href";

$regularPriceSpansQuery = "//li[contains(@class,'ast-grid-common-col')]//div[contains(@class,'astra-shop-summary-wrap')]//span[contains(@class,'iscu_reg_price')][1]//span[@class='woocommerce-Price-amount amount']";
$salePriceSpansQuery = "//li[contains(@class,'ast-grid-common-col')]//div[contains(@class,'astra-shop-summary-wrap')]//span[contains(@class,'iscu_reg_price') and contains(., 'Sale')]//span[@class='woocommerce-Price-amount amount']";

// Execute XPath queries
$srcsets = $xpath->query($srcsetQuery);
$titles = $xpath->query($titleQuery);
$hrefs = $xpath->query($hrefQuery);
$regularPriceSpans = $xpath->query($regularPriceSpansQuery);
$salePriceSpans = $xpath->query($salePriceSpansQuery);

$message = "";
$counter = 0;
$threadId = 2;

foreach ($titles as $index => $titleNode) {
    $title = $titleNode->nodeValue;
    $srcset = $srcsets->item($index)->nodeValue ?? 'No srcset';
    $href = $hrefs->item($index)->nodeValue ?? 'No href';

    $removeString = " [Pre-Order]";
    $title = str_replace($removeString, "", $title);

    if (!productExists($conn, $title)) {
        $srcsetArray = explode(',', $srcset);
        $firstImageSrc = trim(explode(' ', $srcsetArray[0])[0]);

        $regularPricePart1 = $regularPriceSpans->item($index * 2)->nodeValue ?? '';
        $regularPricePart2 = $regularPriceSpans->item($index * 2 + 1)->nodeValue ?? '';
        $regularPrice = $regularPricePart1 . ' - ' . $regularPricePart2;

        $salePricePart1 = $salePriceSpans->item($index * 2)->nodeValue ?? '';
        $salePricePart2 = $salePriceSpans->item($index * 2 + 1)->nodeValue ?? '';
        $salePrice = $salePricePart1 && $salePricePart2 ? ($salePricePart1 . ' - ' . $salePricePart2 . ' Sale') : 'No sale price';

        $stmt = $conn->prepare("INSERT INTO products (title, image_path, href, regular_price, sale_price) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $title, $firstImageSrc, $href, $regularPrice, $salePrice);
        $stmt->execute();

 
        if ($stmt->affected_rows > 0) {
            echo "New product inserted successfully\n";
            $dest = downloadImage($firstImageSrc, $title);
    
            $priceStr = "\n (" . $regularPrice . " / " . $salePrice . ")\n\n";
            if ($salePrice == "No sale price") {
                $priceStr = "\n (" . $regularPrice . ")\n\n";
            }
            $message .= $title . $priceStr . "[IMG]" . $dest . "[/IMG]" . "\n\n\n";
    
            $counter++;
    
            // Check if 4 items have been added or it's the last item in the loop
            if ($counter >= 3 || $index == $titles->length - 1) {
                $response = postToForum($threadId, $message);
    
                if ($response) {
                    echo "Posted to forum: " . $response . "\n";
                } else {
                    echo "Failed to post to forum\n";
                }
    
                // Reset message and counter for the next batch
                $message = "";
                $counter = 0;
            }
        } else {
            echo "Error inserting product: " . $stmt->error . "\n";
        }

        $stmt->close();
    } else {
        echo "Product already exists: " . $title . "\n";
    }
}

// Function to check if product exists in the database
function productExists($conn, $title) {
    echo $title;
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM favorgk_products WHERE title = ?");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    echo $count;

    return $count > 0;
}

function postToForum($threadId, $message) {
    $url = 'https://my.xenforo.net/api/posts/';
    $apiKey = '--';
    $apiUser = '1';

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
    $sanitizedTitle = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $title);
    echo $sanitizedTitle;
    $currentMonth = date('m');

    $filename = $sanitizedTitle . ".jpg";

    $directoryPath = '/home/sites/81c/d/--/public_html/data/uploads/adir/' . $currentMonth;

    if (!is_dir($directoryPath)) {
        if (!mkdir($directoryPath, 0755, true)) {
            echo "Error: Unable to create directory.\n";
            exit;
        }
    }

    $destination = $directoryPath . '/' . $filename;
    echo $destination;
    $ft = "https://---.net/data/uploads/---/" . $currentMonth . "/" . $filename;

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
$conn->close();
?>