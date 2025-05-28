# UniArts AV Equipment Instructions

This repository contains a system for generating AV equipment instructions for rooms at UniArts Helsinki. The system consists of two main components:

1. A QR code generator that creates room-specific QR codes
2. An equipment instructions viewer that displays device-specific instructions for AV equipment in each room

## Files

- `qrcodegenerator.php` - Creates QR codes linked to room-specific instructions
- `user_instructions.php` - Displays AV equipment instructions for a specific room
- `default_devices.php` - Contains device information and multilingual instructions
- `config.php` - Trail API configuration and credentials
- `styles.css` - Styling for both the QR generator and instructions pages

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
2. Configure `config.php` with your Trail API credentials
3. Create an `images` directory and add device images named according to the device model
4. Create language-specific logo files (uniartslogo_fi.png, uniartslogo_sv.png, uniartslogo_en.png)

## Author

Marko Myöhänen, University of the Arts Helsinki
