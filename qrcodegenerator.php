<?php
// Adjust this path to where your library is installed
require_once '/usr/local/php-libraries/vendor/autoload.php';

// Import QR code library classes
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Define supported languages and their names
$supportedLanguages = ['fi', 'sv', 'en'];
$languageNames = [
    'fi' => 'Suomi',
    'sv' => 'Svenska',
    'en' => 'English'
];

// Get language from URL parameter, default to Finnish if not specified
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'fi';
// Make sure we only accept supported languages
if (!in_array($lang, $supportedLanguages)) {
    $lang = 'fi'; // Fallback to Finnish
}

// Get room code from URL parameter or set empty if not provided
$roomCode = isset($_GET['room_code']) ? trim($_GET['room_code']) : '';
if ($roomCode && !preg_match('/^[A-Za-z0-9\s\-_åäöÅÄÖ]+$/u', $roomCode)) {
    $roomCode = '';
}

// Define the base URL that will be used for the QR code
$baseUrl = 'https://sibaav.uniarts.fi/user_instructions.php?lang=' . $lang . '&room=';

// Combine base URL with room code to create the full URL
$fullUrl = $baseUrl . $roomCode;

// Flag to determine if we should show the QR code section
$showQrCode = !empty($roomCode);

// Variable to store the generated QR code image
$qrImageSrc = '';

// Generate QR code if a room code was provided
if($showQrCode) {
    try {
        // Configure QR code options
        $options = new QROptions([
            // Output type: PNG image (requires GD extension)
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            
            // Error correction level: L = Low (7% of codewords can be restored)
            'eccLevel' => QRCode::ECC_L,
            
            // Scale factor: determines the size of the QR code
            'scale' => 5,
            
            // Whether to add white space around the QR code (improves readability)
            'addQuietzone' => true,
        ]);
        
        // Create QR code instance and render it as base64 image
        $qrcode = new QRCode($options);
        $qrImageSrc = $qrcode->render($fullUrl);
    } catch (Exception $e) {
        // Display error message if QR code generation fails
        echo "Error creating QR code: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}

// Define all text content based on language
$texts = [
    'fi' => [
        'instructions' => 'Avaa käyttöohje skannaamalla QR-koodi puhelimesi kamerasovelluksella.',
        'instruction_text' => 'Syötä huonekoodi luodaksesi QR-koodin',
        'form' => [
            'placeholder' => 'Syötä huonekoodi',
            'button' => 'Luo QR'
        ],
        'title_template' => 'Käyttöohje huoneelle %s',
        'default_title' => 'Huoneen QR-koodigeneraattori',
    ],
    'sv' => [
        'instructions' => 'Öppna bruksanvisningen genom att skanna QR-koden med din telefons kameraapp.',
        'instruction_text' => 'Ange en rumskod för att generera en QR-kod',
        'form' => [
            'placeholder' => 'Ange rumskod',
            'button' => 'Generera'
        ],
        'title_template' => 'Bruksanvisning för rum %s',
        'default_title' => 'QR-kodgenerator för rum',
    ],
    'en' => [
        'instructions' => 'Open the user manual by scanning the QR code with your phone\'s camera app.',
        'instruction_text' => 'Enter a room code to generate a QR code',
        'form' => [
            'placeholder' => 'Enter room code',
            'button' => 'Generate'
        ],
        'title_template' => 'User Manual for Room %s',
        'default_title' => 'Room QR Code Generator',
    ]
];

// Choose the appropriate texts based on the selected language
$currentTexts = isset($texts[$lang]) ? $texts[$lang] : $texts['en'];

// Logo files for different languages
$logoFiles = [
    'fi' => 'logo_fi.png',
    'sv' => 'logo_sv.png',
    'en' => 'logo_en.png'
];

// Select logo file based on the current language
$logoFile = isset($logoFiles[$lang]) ? $logoFiles[$lang] : 'uniartslogo_en.png';

// Function to safely escape output to prevent XSS attacks
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <title><?php echo $showQrCode ? sprintf(e($currentTexts['title_template']), e($roomCode)) : e($currentTexts['default_title']); ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- Language selector in gray bar at top -->
<?php
$langBarLabels = [
    'fi' => 'Kielivalinta',
    'sv' => 'Språkval',
    'en' => 'Language selection'
];
?>
<nav class="lang-bar" aria-label="<?php echo e($langBarLabels[$lang]); ?>">
    <div class="lang-bar-content">
        <?php
        foreach ($supportedLanguages as $language) {
            $class = ($language === $lang) ? "class='active'" : "";
            
            // Copy all current GET parameters
            $params = $_GET;
            // Update only the language parameter
            $params['lang'] = $language;
            
            // Build URL from parameters
            $queryString = http_build_query($params);
            
            echo "<a href='?" . $queryString . "' lang='" . e($language) . "' $class>" . $languageNames[$language] . "</a> ";
        }
        ?>
    </div>
</nav>

<!-- Header with logo and title -->
<header class="header">
    <img src="<?php echo $logoFile; ?>" alt="UniArts Logo">
    <?php
    if (!empty($roomCode)) {
        // If room code is provided, show the instructions title
        echo '<span class="header-title" tabindex="0">' . sprintf($currentTexts['title_template'], e($roomCode)) . '</span>';
    } else {
        // If no room code is provided, show the QR code generator title
        echo '<span class="header-title" tabindex="0">' . $currentTexts['default_title'] . '</span>';
    }
    ?>
</header>

<?php if(!$showQrCode): ?>
<!-- Show form only if user has not given room code -->

<div class="container" style="margin-top: 32px;">
    <div>
        <h1 class="room-title">
            <?php echo $currentTexts['instruction_text']; ?>
        </h1>
    </div>
    <form action="" method="GET" style="padding: 32px; display: flex; width: 100%; justify-content: left;">
        <input type="text" name="room_code" placeholder="<?php echo $currentTexts['form']['placeholder']; ?>" required>
        <button type="submit" style="padding: 10px 32px;"><?php echo $currentTexts['form']['button']; ?></button>
        <?php if (isset($_GET['lang'])) { ?>
        <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
        <?php } ?>
    </form>
</div>
<?php else: ?>

<!-- QR code and instructions -->        
<div class="container">
    <div class="qrcode">
        <img src="<?php echo $qrImageSrc; ?>" alt="QR code for room <?php echo e($roomCode); ?>">
    </div>
</div>

<div class="container">
    <div class="instructions">
        <?php 
        foreach ($texts as $language => $content) {
            echo '<div class="instruction-' . $language . '">';
            echo '<strong>' . $languageNames[$language] . ':</strong><br>';
            echo $content['instructions'];
            echo '</div>';
        }
        ?>
    </div>
</div>
<?php endif; ?>
</body>
</html>