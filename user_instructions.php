<!DOCTYPE html>
<?php
// Get language from URL parameter, default to Finnish if not specified
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'fi';
// Make sure we only accept supported languages
$supportedLanguages = ['fi', 'sv', 'en'];
if (!in_array($lang, $supportedLanguages)) {
    $lang = 'fi'; // Fallback to Finnish
}
?>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>">
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
        'fi' => 'uniartslogo_fi.png',
        'sv' => 'uniartslogo_sv.png',
        'en' => 'uniartslogo_en.png'
    ];
    
    // Alt texts for different languages
    $altTexts = [
        'fi' => 'Taideyliopiston logo',
        'sv' => 'Konstuniversitetets logo',
        'en' => 'University of the Art Helsinki logo'
    ];
    
    // Make sure we only accept supported languages
    $supportedLanguages = ['fi', 'sv', 'en'];
    if (!in_array($lang, $supportedLanguages)) {
        $lang = 'fi'; // Fallback to Finnish
    }
    
    // Select logo file and alt text based on the current language
    $logoFile = $logoFiles[$lang];
    $altText = $altTexts[$lang];
    ?>
    <img src="<?php echo htmlspecialchars($logoFile, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($altText, ENT_QUOTES, 'UTF-8'); ?>" tabindex="0">
    <?php
    switch ($lang) {
        case 'fi':
            echo '<span class="header-title" tabindex="0">Käyttöohje AV-laitteille</span>';
            break;
        case 'sv':
            echo '<span class="header-title" tabindex="0">Bruksanvisning för AV-utrustning</span>';
            break;
        case 'en':
        default:
            echo '<span class="header-title" tabindex="0">User Manual for AV Equipment</span>';
            break;
    }
    ?>
</div>

<?php
// Include configuration file with API credentials and settings
require_once('./config.php');

// Process URL parameters and handle Finnish characters
$room = isset($_GET['room']) ? $_GET['room'] : '';
if($room) {
    $room = str_replace(['å', 'Å', 'ä', 'Ä', 'ö', 'Ö'],
                         ['%C3%A5', '%C3%85', '%C3%A4', '%C3%84', '%C3%B6', '%C3%96'],
                         $room);
}

// Check for API key
if(empty($code)) {
    echo '<p>API key not set.</p>';
    die;
}

// Function to make API requests
function makeApiRequest($url, $code) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Basic '.$code]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $json = curl_exec($ch);
    curl_close($ch);
    return json_decode($json, true);
}

// 1. Search for locations using the room code
$locationSearch = makeApiRequest("https://api.trail.fi/api/v1/locations?search%5Bfree%5D=$room", $code);

// 2. Find the correct location where code or name matches the search term
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
            break; // Stop searching once we find the first match
        }
    }
}

// 3. Get inventory list for the found location, or return empty array if location not found
if (!empty($locationId)) {
    // Fetch all items in this specific location using the location ID
    $array = makeApiRequest("https://api.trail.fi/api/v1/items?search%5Blocations%5D%5B%5D=$locationId", $code);
} else {
    // If no matching location was found, set empty array
    $array = ['data' => []];
}

// Function to check if a specific model exists in the API response and count its quantity
function checkModelExists($array, $model) {
    $quantity = 0;
    foreach ($array['data'] as $item) {
        if (isset($item['model']['name']) && $item['model']['name'] == $model) {
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
    $VideoSwitchers = ["Video Switcher"];
    if (in_array($type, $displayDevices)) return "display device";
    if (in_array($type, $controlSystems)) return "control system";
    if (in_array($type, $loudspeakers)) return "loudspeaker";
    if (in_array($type, $bluetoothDevices)) return "bluetooth";
    if (in_array($type, $mixingDevices)) return "mixer";
    if (in_array($type, $VideoSwitchers)) return "video switcher";

    return "other"; // Default group for unknown types
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

// Define list of devices to display on the page
$userInputDevices = array_keys($devices);

// Group devices by type for organized display
$deviceGroups = [
    'display device' => [],
    'video switcher' => [],
    'control system' => [],
    'bluetooth' => [],
    'mixer' => [],
    'loudspeaker' => []
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
echo "<h1 class='room-title' tabindex='0'>" . (!empty($roomName) ? htmlspecialchars($roomName, ENT_QUOTES, 'UTF-8') : "Tilan tietoja ei löytynyt") . "</h1>";

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
            // Create image filename by converting the device name to lowercase and replacing spaces with underscores
            $baseImageName = strtolower(str_replace(' ', '_', $deviceName));
            // Check if PNG exists, otherwise use JPG
            $imageName = file_exists("images/$baseImageName.png") ? "$baseImageName.png" : "$baseImageName.jpg";
            // Start the device section container
            echo "<div class='device-section'>";
            // Display the device title (manufacturer + model + translated type)
            echo "<h2 class='device-title' tabindex='0'>" . htmlspecialchars(translateDeviceType($device['type'], $lang), ENT_QUOTES, 'UTF-8') . "</h2>";
            // Create a flexbox container for the content (instructions + images)
            echo "<div class='device-content'>";
            // Instructions section
            echo "<div class='device-instructions' tabindex='0'>";
            // Check if instructions exist for the current language
            if (isset($device['instructions'][$lang]) && !empty($device['instructions'][$lang])) {
                echo "<div class='instructions'>";
                echo "<ol>";
                // Loop through each instruction and display as a list item
                foreach ($device['instructions'][$lang] as $instruction) {
                    echo "<li>" . htmlspecialchars($instruction, ENT_QUOTES, 'UTF-8') . "</li>";
                }
                echo "</ol>";
                echo "</div>";
            } else if (isset($device['instructions']['en']) && !empty($device['instructions']['en'])) {
                // Fallback to English if the selected language is not available
                echo "<div class='instructions'>";
                echo "<ol>";
                foreach ($device['instructions']['en'] as $instruction) {
                    echo "<li>" . htmlspecialchars($instruction, ENT_QUOTES, 'UTF-8') . "</li>";
                }
                echo "</ol>";
                echo "</div>";
            } else {
                // Display message if no instructions are available
                echo "<div class='instructions'>";
                echo "<p>No specific instructions available for this device.</p>";
                echo "</div>";
            }
            echo "</div>"; // End instructions div
            // Images section
            echo "<div class='device-images' tabindex='0'>";
            // Loop through the quantity to display multiple instances of the same device
            for ($i = 0; $i < $quantity; $i++) {
                $deviceTypeText = translateDeviceType($device['type'], $lang);
//                $altText = $manufacturerName . " " . $deviceName . " " . $deviceTypeText;
                $altText = $deviceTypeText;
                echo "<img class='centered-image' src='images/" . htmlspecialchars($imageName, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($altText, ENT_QUOTES, 'UTF-8') . "'>";
            }
            echo "</div>"; // End image div
            echo "</div>"; // End device-content div
            echo "</div>"; // End device-section div
            // Add spacer between device sections, but not after the last device
            if ($deviceCount < $totalDevices) {
                echo "<div class='device-spacer'></div>";
            }
        }
    }
}
echo "</div>"; // End container

// Always show the footer with contact information
switch ($lang) {
    case 'fi':
        $helpText = 'Tarvitsetko lisää apua?';
        $contactText = 'Ota yhteyttä AV-tukeen: ';
        $subjectText = 'Kysymys tilasta ';
        break;
    case 'sv':
        $helpText = 'Behöver du mer hjälp?';
        $contactText = 'Kontakta AV-supporten: ';
        $subjectText = 'En fråga om rum ';
        break;
    case 'en':
        $helpText = 'Do you need more help?';
        $contactText = 'Please contact AV support: ';
        $subjectText = 'A question about room ';
        break;
    default:
        $helpText = 'Tarvitsetko lisää apua?';
        $contactText = 'Ota yhteyttä AV-tukeen: ';
        $subjectText = 'Kysymys tilasta ';
        break;
}

echo '<div class="footer">';
echo '<div class="footer-heading" tabindex="0">' . htmlspecialchars($helpText, ENT_QUOTES, 'UTF-8') . '</div>';
echo '<div class="footer-contact" tabindex="0">' . htmlspecialchars($contactText, ENT_QUOTES, 'UTF-8');

$roomName = isset($array['data'][0]['location']['location']['name']) ? $array['data'][0]['location']['location']['name'] : '';
$subject = rawurlencode($subjectText . $roomName);
$mailto = 'mailto:siba.avhelp@uniarts.fi?subject=' . $subject;

echo '<a href="' . htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8') . '">siba.avhelp@uniarts.fi</a>';
echo '</div>';
echo '</div>';

// print API URI and PHP array for debugging purposes, set debug as url parameter
if(isset($_GET['debug'])) {
     echo '<h3>Query URL</h4>';
     echo $url;
     echo '<h3>PHP array</h4>';
     echo '<pre>'; print_r($array); echo '</pre>';
     echo '<p>end of report</p>';
};
?>
</body>
</html>