<?php
// Adjust this path to where your library is installed
require_once '/usr/local/php-libraries/vendor/autoload.php';

// Import QR code library classes
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Initialize variables
// Get room code from URL parameter or set empty if not provided
$roomCode = isset($_GET['room_code']) ? trim($_GET['room_code']) : '';

// Define the base URL that will be used for the QR code
// Change this to your actual application URL
$baseUrl = 'https://sibaav.uniarts.fi/user_instructions.php?lang=en&free=';

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
        echo "Error creating QR code: " . $e->getMessage();
    }
}

// Function to safely escape output to prevent XSS attacks
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <title><?php echo $showQrCode ? 'Room ' . e($roomCode) . ' Instructions' : 'Room QR Code Generator'; ?></title>
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
    <img src="uniartslogo.png" alt="UniArts Logo">
    <?php
    if (!empty($roomCode)) {
        // Jos huonekoodi on annettu, käytä samaa otsikkomuotoa kuin ohjeissa
        $instructionTitles = [
            'fi' => 'Käyttöohje huoneelle %s',
            'sv' => 'Bruksanvisning för rum %s',
            'en' => 'User Manual for Room %s'
        ];
        
        // Valitse oikea otsikko kielen perusteella
        $titleTemplate = isset($instructionTitles[$lang]) ? $instructionTitles[$lang] : $instructionTitles['en'];
        
        // Muodosta otsikko huonekoodin kanssa
        echo '<h2>' . sprintf($titleTemplate, e($roomCode)) . '</h2>';
    } else {
        // Jos huonekoodia ei ole, käytä oletusotsikkoa
        $defaultTitles = [
            'fi' => 'Huoneen QR-koodigeneraattori',
            'sv' => 'QR-kodgenerator för rum',
            'en' => 'Room QR Code Generator'
        ];
        $headerTitle = isset($defaultTitles[$lang]) ? $defaultTitles[$lang] : $defaultTitles['en'];
        echo '<h2>' . $headerTitle . '</h2>';
    }
    ?>
</div>

<?php if(!$showQrCode): ?>
<!-- Show form only if user has not given room code -->
<div class="container" style="margin-top: 32px;">
    <form action="" method="GET" style="padding: 32px; display: flex; width: 100%; justify-content: left;">
        <?php
        // Language support for form texts
        $formTexts = [
            'fi' => [
                'placeholder' => 'Syötä huonekoodi',
                'button' => 'Luo QR'
            ],
            'sv' => [
                'placeholder' => 'Ange rumskod',
                'button' => 'Generera'
            ],
            'en' => [
                'placeholder' => 'Enter room code',
                'button' => 'Generate'
            ]
        ];
        
        $formText = isset($formTexts[$lang]) ? $formTexts[$lang] : $formTexts['en'];
        ?>
        <input type="text" name="room_code" placeholder="<?php echo $formText['placeholder']; ?>" required>
        <button type="submit" style="padding: 10px 32px;"><?php echo $formText['button']; ?></button>
        <?php if (isset($_GET['lang'])) { ?>
        <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
        <?php } ?>
    </form>
</div>

<?php
    // Instruction texts for different languages
    $instructionTexts = [
        'fi' => 'Syötä huonekoodi yllä luodaksesi QR-koodin ja käyttöohjeet.',
        'sv' => 'Ange en rumskod ovan för att generera en QR-kod och instruktioner.',
        'en' => 'Enter a room code above to generate a QR code and instructions.'
    ];

    // Choose the appropriate instruction text based on the selected language
    $instructionText = isset($instructionTexts[$lang]) ? $instructionTexts[$lang] : $instructionTexts['en'];
    ?>
    <?php echo $instructionText; ?>
    <?php else: ?>

<!-- QR code and instructions -->        
<div class="container">
    <div class="qrcode">
        <p><?php echo $currentLangTexts['instructions'][0]; ?></p>
    <img src="<?php echo $qrImageSrc; ?>" alt="QR code for room <?php echo e($roomCode); ?>">
    </div>
</div>

<div class="container">
    <div class="instructions">
        <?php
        // Määrittele ohjeiden tekstit eri kielillä
        $instructionsTexts = [
            'fi' => [
                'instructions' => [
                    'Skannaa yllä oleva QR-koodi mobiililaitteellasi.',
                    'Tämä avaa automaattisesti luodut käyttöohjeet huoneelle.',
                    'Seuraa käyttöohjeita.'
                ]
            ],
            'sv' => [
                'instructions' => [
                    'Skanna QR-koden ovan med din mobila enhet.',
                    'Detta öppnar automatiskt genererade användarinstruktioner för rummet.',
                    'Följ användarinstruktionerna.'
                ]
            ],
            'en' => [
                'instructions' => [
                    'Scan the QR code above with your mobile device.',
                    'This opens automatically generated user instructions for the room.',
                    'Follow the user instructions.'
                ]
            ]
        ];

        // Choose the appropriate instruction texts based on the selected language
        $currentLangTexts = isset($instructionsTexts[$lang]) ? $instructionsTexts[$lang] : $instructionsTexts['en'];

        $formattedTitle = sprintf($currentLangTexts['title'], e($roomCode));
        ?>
        <ol>
        <?php foreach ($currentLangTexts['instructions'] as $instruction): ?>
            <li><?php echo $instruction; ?></li>
        <?php endforeach; ?>
        </ol>
        </div>
    </div>
        <?php endif; ?>
<?php
// Get the language setting - use 'en' as default if not specified in URL
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';

// Set footer text based on the selected language
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

// Generate the footer HTML
echo '<div class="footer">';
echo '<div class="footer-heading">' . $helpText . '</div>';
echo '<div class="footer-contact">' . $contactText;

// Get room name if available
$roomName = isset($array['data'][0]['location']['location']['name']) ? $array['data'][0]['location']['location']['name'] : '';
$subject = rawurlencode($subjectText . $roomName);
$mailto = 'mailto:siba.avhelp@uniarts.fi?subject=' . $subject;

echo '<a href="' . $mailto . '">siba.avhelp@uniarts.fi</a>';
echo '</div>';
echo '</div>';
?>
</body>
</html>