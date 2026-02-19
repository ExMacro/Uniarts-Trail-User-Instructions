<!DOCTYPE html>
<?php
// Send security header before any HTML output
header('X-Content-Type-Options: nosniff');

// Get language from URL parameter, default to Finnish if not specified
$lang = $_GET['lang'] ?? 'fi';

// Make sure we only accept supported languages
$supportedLanguages = ['fi', 'sv', 'en'];
if (!in_array($lang, $supportedLanguages)) {
    $lang = 'fi';
}

// UI translations for different languages
$translations = [
    'fi' => [
        'header_title' => 'Käyttöohje AV-laitteille',
        'help_text' => 'Tarvitsetko lisää apua?',
        'contact_text' => 'Ota yhteyttä AV-tukeen: ',
        'subject_text' => 'Kysymys tilasta ',
        'no_image' => 'Ei kuvaa'
    ],
    'sv' => [
        'header_title' => 'Bruksanvisning för AV-utrustning',
        'help_text' => 'Behöver du mer hjälp?',
        'contact_text' => 'Kontakta AV-supporten: ',
        'subject_text' => 'En fråga om rum ',
        'no_image' => 'Ingen bild'
    ],
    'en' => [
        'header_title' => 'User Manual for AV Equipment',
        'help_text' => 'Do you need more help?',
        'contact_text' => 'Please contact AV support: ',
        'subject_text' => 'A question about room ',
        'no_image' => 'No device image'
    ]
];

// Function to safely escape output to prevent XSS attacks
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<html lang="<?php echo e($lang); ?>">
    <head>
        <title>Käyttöohje AV-laitteille</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="styles.css">
    </head>
<body>
<!-- Language selector in gray bar at top -->
<div class="lang-bar">
    <div class="lang-bar-content">
        <?php
        $languageNames = [
            'fi' => 'Suomi',
            'sv' => 'Svenska',
            'en' => 'English'
        ];
        
        foreach ($supportedLanguages as $language) {
            $class = ($language === $lang) ? "class='active'" : "";
            
            // Copy all current GET parameters
            $params = $_GET;
            
            // Update only the language parameter
            $params['lang'] = $language;
            
            // Build URL from parameters
            $queryString = http_build_query($params);
            
            echo "<a href='?" . $queryString . "' $class>" . $languageNames[$language] . "</a> ";
        }
        ?>
    </div>
</div>

<!-- Header with logo and title -->
<div class="header">
    <?php
    // Logo files for different languages
    $logoFiles = [
        'fi' => 'logo_fi.png',
        'sv' => 'logo_sv.png',
        'en' => 'logo_en.png'
    ];
    
    // Alt texts for different languages
    $altTexts = [
        'fi' => 'Taideyliopiston logo',
        'sv' => 'Konstuniversitetets logo',
        'en' => 'University of the Art Helsinki logo'
    ];
    
    // Select logo file and alt text based on the current language
    $logoFile = $logoFiles[$lang];
    $altText = $altTexts[$lang];
    ?>
    <img src="<?php echo e($logoFile); ?>" alt="<?php echo e($altText); ?>" tabindex="0">
    <span class="header-title" tabindex="0"><?php echo e($translations[$lang]['header_title']); ?></span>
</div>

<?php
// Include configuration file with API credentials and settings
require_once('./config.php');

// Process URL parameters and validate room code
$room = $_GET['room'] ?? '';
$room = trim($room);

// Validate room code format
if ($room && !preg_match('/^[A-Za-z0-9\s\-_åäöÅÄÖ]+$/u', $room)) {
    $room = '';
}

// Encode room code for API request
if ($room) {
    $room = rawurlencode($room);
}

// Check for API key
if(empty($code)) {
    echo '<p>API key not set.</p>';
    die;
}

// Function to make API requests with automatic pagination
function makeApiRequest($url, $code) {
    $allData = [];
    $page = 1;
    $maxPages = 100; // Safety limit
    
    do {
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        $paginatedUrl = $url . $separator . "page=$page&per_page=100";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $paginatedUrl,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $code
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $json = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($json === false || $httpCode !== 200) {
            error_log("Trail API error: HTTP $httpCode for $paginatedUrl");
            break;
        }
        
        $response = json_decode($json, true) ?? ['data' => []];
        $allData = array_merge($allData, $response['data'] ?? []);
        
        $totalPages = $response['metadata']['total_pages'] ?? 1;
        $page++;
        
    } while ($page <= $totalPages && $page <= $maxPages);
    
    return ['data' => $allData];
}

// Function to get device image URL from items API data
function getDeviceImageUrl($array, $modelName) {
    // Apply naming convention: lowercase, spaces to underscores
    $expectedBasename = strtolower(str_replace(' ', '_', $modelName));
    
    foreach ($array['data'] as $item) {
        if (isset($item['model']['name']) && $item['model']['name'] === $modelName) {
            if (isset($item['images']) && is_array($item['images']) && !empty($item['images'])) {
                // Search for image matching naming convention
                foreach ($item['images'] as $image) {
                    $imageUrl = $image['url'] ?? null;
                    if ($imageUrl) {
                        $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
                        // Remove query string parameters if present
                        $filename = strtok($filename, '?');
                        // Get filename without extension
                        $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
                        
                        // Check if it matches expected naming convention
                        if ($filenameWithoutExt === $expectedBasename) {
                            return $imageUrl;
                        }
                    }
                }
            }
            // If no matching image found, return null
            return null;
        }
    }
    
    return null;
}

// Search for locations using the room code
$locationSearch = makeApiRequest($trail_locations_baseurl . "?search%5Bfree%5D=$room", $code);

// Find the correct location where code or name matches the search term
$locationId = '';
$locationName = '';

if (!empty($locationSearch['data'])) {
    // Loop through location results to find exact match
    foreach ($locationSearch['data'] as $location) {
        // Check if the location 'code' or 'name' field matches the room search term
        if ((isset($location['code']) && $location['code'] === urldecode($room)) || 
            (isset($location['name']) && stripos($location['name'], urldecode($room)) !== false)) {
            // Store the location ID and name when match is found
            $locationId = $location['id'];
            $locationName = $location['name'];
            break;
        }
    }
}

// Get inventory list for the found location, or return empty array if location not found
if (!empty($locationId)) {
    // Fetch all items in this specific location using the location ID
    $array = makeApiRequest($trail_items_baseurl . "?search%5Blocations%5D%5B%5D=$locationId", $code);
} else {
    // If no matching location was found, set empty array
    $array = ['data' => []];
}

// Function to check if a specific model exists in the API response and count its quantity
function checkModelExists($array, $model) {
    $quantity = 0;
    foreach ($array['data'] as $item) {
        if (isset($item['model']['name']) && $item['model']['name'] === $model) {
            // If device found, add to quantity
            $quantity += isset($item['quantity']) ? $item['quantity'] : 1;
        }
    }
    return $quantity;
}

// Function to determine device group based on type
function getDeviceGroup($type) {
    $displayDevices = ["Projector", "Display"];
    $controlSystems = ["Control Panel"];
    $loudspeakers = ["Loudspeakers"];
    $bluetoothDevices = ["Bluetooth audio interface"];
    $mixingDevices = ["Audio Mixer"];
    $videoSwitchers = ["Video Switcher"];
    $conferenceCameras = ["Conference Camera"];
    $documentCameras = ["Document Camera"];
    $multiFormatPlayers = ["Multi-format player"];
    
    if (in_array($type, $displayDevices)) return "display device";
    if (in_array($type, $controlSystems)) return "control system";
    if (in_array($type, $loudspeakers)) return "loudspeaker";
    if (in_array($type, $bluetoothDevices)) return "bluetooth";
    if (in_array($type, $mixingDevices)) return "mixer";
    if (in_array($type, $videoSwitchers)) return "video switcher";
    if (in_array($type, $conferenceCameras)) return "conference camera";
    if (in_array($type, $documentCameras)) return "document camera";
    if (in_array($type, $multiFormatPlayers)) return "multi-format player";

    return "other";
}

// Load device information from external file
$data = include('./default_devices.php');
$devices = $data['devices'];
$deviceTypeTranslations = $data['translations'];

function translateDeviceType($type, $lang) {
    global $deviceTypeTranslations;

    if (isset($deviceTypeTranslations[$lang][$type])) {
        return $deviceTypeTranslations[$lang][$type];
    } elseif (isset($deviceTypeTranslations['en'][$type])) {
        return $deviceTypeTranslations['en'][$type];
    } else {
        return $type;
    }
}

// Function to display instruction list
function displayInstructionList($instructions) {
    echo "<div class='instructions'>";
    echo "<ol>";
    foreach ($instructions as $instruction) {
        echo "<li>" . e($instruction) . "</li>";
    }
    echo "</ol>";
    echo "</div>";
}

// Define list of devices to display on the page
$userInputDevices = array_keys($devices);

// Group devices by type for organized display
$deviceGroups = [
    'display device' => [],
    'video switcher' => [],
    'control system' => [],
    'conference camera' => [],
    'document camera' => [],
    'multi-format player' => [],
    'bluetooth' => [],
    'mixer' => [],
    'loudspeaker' => [],
];

// Group devices based on their type and check if they exist in the location
foreach ($userInputDevices as $deviceName) {
    $quantity = checkModelExists($array, $deviceName);
    if ($quantity > 0) {
        $deviceType = $devices[$deviceName]['type'];
        $group = getDeviceGroup($deviceType);
        $deviceGroups[$group][] = [
            'name' => $deviceName,
            'quantity' => $quantity
        ];
    }
}

// Calculate total number of devices to display
$deviceCount = 0;
$totalDevices = 0;
foreach ($deviceGroups as $groupDevices) {
   $totalDevices += count($groupDevices);
}

// Add room name as H1 element
echo "<div class='container'>";

$roomName = '';

if (!empty($array['data'])) {
    // Go through the data array to find the room name
    foreach ($array['data'] as $item) {
        if (isset($item['location']['location']['name'])) {
            $roomName = $item['location']['location']['name'];
            break;
        } 
    }
}

// Show room name or a default message if not found
echo "<h1 class='room-title' tabindex='0'>" . (!empty($roomName) ? e($roomName) : "Tilan tietoja ei löytynyt") . "</h1>";

// Display devices by group type
foreach ($deviceGroups as $type => $groupDevices) {
    if (!empty($groupDevices)) {
        // Show devices in the group
        foreach ($groupDevices as $deviceData) {
            // Increment the device counter to track which device we're on
            $deviceCount++;
            $deviceName = $deviceData['name'];
            $quantity = $deviceData['quantity'];
            
            // Get device details from the combined devices array
            $device = $devices[$deviceName];
            $manufacturerName = $device['manufacturer'];
            
            // Translate device type once for this device
            $deviceTypeText = translateDeviceType($device['type'], $lang);
            
            // Try to get image URL from API
            $imageUrl = getDeviceImageUrl($array, $deviceName);
            
            // Start the device section container
            echo "<div class='device-section'>";
            
            // Display the device title (manufacturer + model + translated type)
            echo "<h2 class='device-title' tabindex='0'>" . e($deviceTypeText) . "</h2>";
            
            // Create a flexbox container for the content (instructions + images)
            echo "<div class='device-content'>";
            
            // Instructions section
            echo "<div class='device-instructions' tabindex='0'>";
            
            // Check if instructions exist for the current language
            if (isset($device['instructions'][$lang]) && !empty($device['instructions'][$lang])) {
                displayInstructionList($device['instructions'][$lang]);
            } else if (isset($device['instructions']['en']) && !empty($device['instructions']['en'])) {
                // Fallback to English if the selected language is not available
                displayInstructionList($device['instructions']['en']);
            } else {
                // Display message if no instructions are available
                echo "<div class='instructions'>";
                echo "<p>No specific instructions available for this device.</p>";
                echo "</div>";
            }
            echo "</div>";
            
            // Images section
            echo "<div class='device-images' tabindex='0'>";
            
            if ($imageUrl) {
                // Show images if found in API
                $imageCount = ($device['type'] === 'Loudspeakers') ? min($quantity, 2) : $quantity;
                for ($i = 0; $i < $imageCount; $i++) {
                    $altText = $deviceTypeText;
                    echo "<img class='centered-image' src='" . e($imageUrl) . "' alt='" . e($altText) . "'>";
                }
            } else {
                // Show message if no image found
                echo "<div class='instructions'><p>" . e($translations[$lang]['no_image']) . "</p></div>";
            }
            
            echo "</div>";
            
            echo "</div>";
            echo "</div>";
            
            // Add spacer between device sections, but not after the last device
            if ($deviceCount < $totalDevices) {
                echo "<div class='device-spacer'></div>";
            }
        }
    }
}
echo "</div>";

// Always show the footer with contact information
echo '<div class="footer">';
echo '<div class="footer-heading" tabindex="0">' . e($translations[$lang]['help_text']) . '</div>';
echo '<div class="footer-contact" tabindex="0">' . e($translations[$lang]['contact_text']);

$roomName = isset($array['data'][0]['location']['location']['name']) ? $array['data'][0]['location']['location']['name'] : '';
$subject = rawurlencode($translations[$lang]['subject_text'] . $roomName);
$mailto = 'mailto:siba.avhelp@uniarts.fi?subject=' . $subject;

echo '<a href="' . e($mailto) . '">siba.avhelp@uniarts.fi</a>';
echo '</div>';
echo '</div>';

// Print API URI and PHP array for debugging purposes, set debug as url parameter
if(isset($_GET['debug'])) {
     echo '<div style="padding: 32px; text-align: left; background-color: #f8f8f8; margin: 20px 0;">';
     echo '<h2 style="font-size: 20px; font-weight: bold; margin-bottom: 10px;">Query URL</h2>';
     echo '<pre style="overflow-x: auto; font-size: 12px;">' . htmlspecialchars($trail_items_baseurl, ENT_QUOTES, 'UTF-8') . '</pre>';
     echo '<h2 style="font-size: 20px; font-weight: bold; margin: 20px 0 10px 0;">PHP array</h2>';
     echo '<pre style="overflow-x: auto; font-size: 12px;">'; print_r($array); echo '</pre>';
     echo '<p style="margin-top: 20px;">End of report</p>';
     echo '</div>';
}
?>
</body>
</html>