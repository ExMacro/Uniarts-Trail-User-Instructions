# UniArts AV Equipment Instructions

This repository contains a system for generating AV equipment instructions for rooms at UniArts Helsinki. The system consists of two main components:

1. A QR code generator that creates room-specific QR codes
2. An equipment instructions viewer that displays device-specific instructions for AV equipment in each room

## Files

| File | Description |
|---|---|
| `qrcodegenerator.php` | Creates QR codes linked to room-specific instructions |
| `user_instructions.php` | Displays AV equipment instructions for a specific room |
| `default_devices.php` | Contains device information and multilingual instructions |
| `config.php` | Trail API configuration and credentials *(not included, see `config.example.php`)* |
| `styles.css` | Styling for both the QR generator and instructions pages |

## Features

- **Multilingual Support**: All content is available in Finnish, Swedish, and English
- **Language-specific Logos**: Displays different logo files based on the selected language
- **QR Code Generation**: Creates QR codes that link to room-specific instructions
- **Responsive Design**: Works on both desktop and mobile devices
- **Device Categorization**: Organizes devices by type (projectors, displays, etc.)
- **API Integration**: Pulls room and equipment data from the Trail API

## Usage

### QR Code Generator

1. Navigate to the QR code generator page
2. Enter a room code
3. Select the desired language
4. Click "Generate"
5. A QR code will be created that links to the instructions for that room

### Equipment Instructions

1. Access by either scanning a QR code or navigating directly to the URL
2. Select preferred language from the language bar
3. View equipment instructions specific to the room
4. Contact AV support via the footer if additional help is needed

## Installation

1. Upload all files to your web server
2. Copy `config.example.php` to `config.php` and fill in your Trail API credentials
3. Create language-specific logo files
   - `logo_fi.png`
   - `logo_sv.png`
   - `logo_en.png`
  
## Device Images

Device images are stored and managed in Trail. The system fetches them automatically via the Trail API when displaying instructions.

### Image Naming Convention

Images must be uploaded to Trail and attached to the correct device item. The filename must follow this convention for the system to recognize it:

- Use the **device model name** as the filename
- Convert to **lowercase**
- Replace **spaces with underscores**
- Use **PNG format** with transparent background

**Examples:**

| Model name in Trail | Required filename |
|---|---|
| `MLC Plus 100` | `mlc_plus_100.png` |
| `BTone analog 2` | `btone_analog_2.png` |
| `Meeting Owl 3` | `meeting_owl_3.png` |
| `4030C` | `4030c.png` |

> If no matching image is found, the instructions page displays a localised "No device image" message.
> 
## Author

Marko Myöhänen, University of the Arts Helsinki
