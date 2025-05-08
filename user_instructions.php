<!DOCTYPE html>
<html lang="<?php echo isset($lang) ? $lang : 'en'; ?>">
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
        // Get language from URL parameter, default to Finnish if not specified
        $lang = isset($_GET['lang']) ? $_GET['lang'] : 'fi';
        // Make sure we only accept supported languages
        $supportedLanguages = ['fi', 'sv', 'en'];
        $languageNames = [
            'fi' => 'Suomi',
            'sv' => 'Svenska',
            'en' => 'English'
        ];
        
        if (!in_array($lang, $supportedLanguages)) {
            $lang = 'fi'; // Fallback to Finnish
        }
        
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
    
    // Select logo file based on the current language
    $logoFile = isset($logoFiles[$lang]) ? $logoFiles[$lang] : 'uniartslogo_en.png';
    ?>
    <img src="<?php echo $logoFile; ?>" alt="UniArts Logo">
    <?php
    switch ($lang) {
        case 'fi':
            echo '<h1>Käyttöohje AV-laitteille</h1>';
            break;
        case 'sv':
            echo '<h1>Bruksanvisning för AV-utrustning</h1>';
            break;
        case 'en':
        default:
            echo '<h1>User Manual for AV Equipment</h1>';
            break;
    }
    ?>
</div>

<?php
// Include configuration file with API credentials and settings
require_once('./config.php');

// Process URL parameters and handle Finnish characters
$freematch = isset($_GET['free']) ? $_GET['free'] : '';
if($freematch) {
    $freematch = str_replace(['å', 'Å', 'ä', 'Ä', 'ö', 'Ö'],
                           ['%C3%A5', '%C3%85', '%C3%A4', '%C3%84', '%C3%B6', '%C3%96'],
                           $freematch);
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

// 1. Get initial search results
$initialResults = makeApiRequest("https://api.trail.fi/api/v1/items?&search%5Bfree%5D=$freematch", $code);

// 2. Extract location ID from L/S code
$locationId = '';
if (!empty($initialResults['data'])) {
    foreach ($initialResults['data'] as $item) {
        if (isset($item['location']['location']['identity'], $item['location']['location']['id']) && 
            strpos($item['location']['location']['identity'], 'L/S') === 0) {
            $locationId = $item['location']['location']['id'];
            break;
        }
    }
}

// 3. Get inventory list or use initial results if no location ID found
$array = !empty($locationId) 
    ? makeApiRequest("https://api.trail.fi/api/v1/items?&search%5Blocations%5D%5B%5D=$locationId", $code)
    : $initialResults;

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
echo "<h2>" . (!empty($roomName) ? $roomName : "Tilan tietoja ei löytynyt") . "</h2>";

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
            echo "<h3>" . translateDeviceType($device['type'], $lang) . "</h3>";
            // Create a flexbox container for the content (instructions + images)
            echo "<div class='device-content'>";
            // Instructions section
            echo "<div class='device-instructions'>";
            // Check if instructions exist for the current language
            if (isset($device['instructions'][$lang]) && !empty($device['instructions'][$lang])) {
                echo "<div class='instructions'>";
                echo "<ol>";
                // Loop through each instruction and display as a list item
                foreach ($device['instructions'][$lang] as $instruction) {
                    echo "<li>" . $instruction . "</li>";
                }
                echo "</ol>";
                echo "</div>";
            } else if (isset($device['instructions']['en']) && !empty($device['instructions']['en'])) {
                // Fallback to English if the selected language is not available
                echo "<div class='instructions'>";
                echo "<ol>";
                foreach ($device['instructions']['en'] as $instruction) {
                    echo "<li>" . $instruction . "</li>";
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
            echo "<div class='device-images'>";
            // Loop through the quantity to display multiple instances of the same device
            for ($i = 0; $i < $quantity; $i++) {
                echo "<img class='centered-image' src='images/$imageName' alt='" . $manufacturerName . " " . $deviceName . "'>";
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

if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
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
        default:
            $helpText = 'Do you need more help?';
            $contactText = 'Please contact AV support: ';
            $subjectText = 'A question about room ';
            break;
    }
    
    echo '<div class="footer">';
    echo '<div class="footer-heading">' . $helpText . '</div>';
    echo '<div class="footer-contact">' . $contactText;
    
    $roomName = isset($array['data'][0]['location']['location']['name']) ? $array['data'][0]['location']['location']['name'] : '';
    $subject = rawurlencode($subjectText . $roomName);
    $mailto = 'mailto:siba.avhelp@uniarts.fi?subject=' . $subject;
    
    echo '<a href="' . $mailto . '">siba.avhelp@uniarts.fi</a>';
    echo '</div>';
    echo '</div>';
}

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