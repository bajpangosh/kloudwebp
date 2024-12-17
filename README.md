# KloudWebP - WordPress Image Converter

A WordPress plugin to automatically convert JPEG and PNG images to WebP format for better performance and compression.

## Features

- Convert existing JPEG/PNG images to WebP format
- Bulk conversion for all posts and pages
- Individual post/page conversion
- Real-time conversion status tracking
- Debug logging for troubleshooting
- Settings for compression quality
- Automatic WebP serving for supported browsers
- Fallback to original images for unsupported browsers

## Requirements

- WordPress 4.7 or higher
- PHP 7.0 or higher
- Either GD or Imagick PHP extension installed
- Write permissions on the uploads directory

## Installation

1. Download the plugin zip file
2. Go to WordPress admin panel > Plugins > Add New
3. Click "Upload Plugin" and select the zip file
4. Click "Install Now"
5. After installation, click "Activate"

## Usage

1. Go to WordPress admin panel > KloudWebP
2. Configure settings:
   - Set WebP compression quality (1-100)
   - Enable/disable automatic content updates
   - Enable/disable debug logging
3. Use the dashboard to:
   - Convert all images using the "Bulk Convert" button
   - Convert individual posts/pages using their respective "Convert" buttons
   - Monitor conversion status and progress
   - View debug logs for troubleshooting

## Debug Logging

1. Enable debug logging in the settings
2. View logs in the settings page
3. Use refresh and clear buttons to manage logs
4. Logs include:
   - Conversion attempts and results
   - Error messages
   - Post content updates
   - Performance information

## Support

For support, please visit: https://github.com/bajpangosh/kloudwebp/issues

## License

This plugin is licensed under the GPL v2 or later.
