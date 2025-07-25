# WhatsApp to Mattermost Converter

[![CI](https://github.com/SkyLostTR/whatsapp-mattermost/workflows/CI/badge.svg)](https://github.com/SkyLostTR/whatsapp-mattermost/actions)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)

A robust tool to convert WhatsApp chat exports into Mattermost-compatible format, allowing seamless migration of chat history with media attachments.

> **Note**: This is a fork of [witchi/whatsapp-mattermost](https://github.com/witchi/whatsapp-mattermost) with enhanced features and improved documentation.

## ✨ Features

- 📱 **WhatsApp Export Processing**: Parse WhatsApp chat exports (text + media)
- 🔄 **Multiple Import Methods**: Bulk import via API or individual post migration
- 📎 **Media Support**: Import images, videos, documents, and audio files
- 👥 **User Mapping**: Map WhatsApp users to Mattermost usernames
- 📞 **Phone Number Mapping**: Handle @mentions via phone numbers
- 😀 **Emoji Conversion**: Convert WhatsApp emojis to Mattermost format
- 🔧 **Flexible Output**: Generate import files or direct API import

### Prerequisites

- PHP 7.4 or higher
- Composer
- PHP Zip extension (for creating import packages)
- WhatsApp chat export (text file with optional media folder)

### Installation

1. **Clone the repository**
   ```powershell
   git clone https://github.com/SkyLostTR/whatsapp-mattermost.git
   cd whatsapp-mattermost
   ```

2. **Install dependencies**
   ```powershell
   composer install
   ```

3. **Check PHP Zip extension** (optional but recommended)
   ```powershell
   php test_ziparchive.php
   ```

## ⚙️ Configuration

### Environment Setup

1. **Copy the sample environment file**
   ```powershell
   Copy-Item .env.sample .env
   ```

2. **Edit the `.env` file** with your configuration:
   ```env
   # Mattermost Configuration
   MATTERMOST_URL=https://your-mattermost-server.com
   MATTERMOST_API_TOKEN=your-api-token-here
   MATTERMOST_TEAM_NAME=your-team-name
   MATTERMOST_CHANNEL_NAME=your-channel-name

   # File Paths (use double backslashes for Windows paths)
   WHATSAPP_CHAT_FILE="C:\\path\\to\\your\\whatsapp\\chat\\export.txt"
   IMPORT_ZIP_PATH="C:\\path\\to\\save\\import.zip"

   # User Mappings (format: "Display Name"="@username")
   USER_MAPPINGS='"John Doe"="@john.doe";"Jane Smith"="@jane.smith"'

   # Phone Number Mappings (format: "phone_number"="@username")
   PHONE_MAPPINGS='"1234567890"="@john.doe";"0987654321"="@jane.smith"'
   ```

> **Important**: The `.env` file contains sensitive information and is automatically ignored by git. Never commit it to version control.

## 📋 Usage

### Step 1: Export WhatsApp Chat

1. Open WhatsApp on your phone
2. Go to the chat you want to export
3. Tap on chat name → Export Chat
4. Choose "Include Media" for complete migration
5. Save the export to your computer

### Step 2: Configure Environment

Make sure your `.env` file is properly configured (see Configuration section above).

### Step 3: Run the Converter

```powershell
php src/convert.php
```

Choose from three import methods:
1. **Bulk Import** - Uses Mattermost's import API (recommended)
2. **Individual Posts** - Posts messages one by one via API
3. **File Export** - Creates import package for manual upload

## 🔧 Configuration Details

### User Mapping

Configure user mappings in your `.env` file using the `USER_MAPPINGS` variable:

```env
USER_MAPPINGS="WhatsApp Display Name"="@mattermost-username";"Another User"="@another.user"
```

**Finding WhatsApp Names:**
- Open your exported chat text file
- Look for names after timestamps (e.g., `[25/07/2025, 13:45:32] John Doe: Hello`)

### Phone Number Mapping

For @mentions in WhatsApp (which use phone numbers), configure the `PHONE_MAPPINGS` variable:

```env
PHONE_MAPPINGS="1234567890"="@mattermost-username";"0987654321"="@another.user"
```

**Phone Number Format:**
- Use the exact format from your WhatsApp export
- Usually includes country code (e.g., `491635552056` for Germany)
- Check your export file for the exact format used

### Mattermost API Token

To get your Mattermost API token:

1. Log into your Mattermost instance
2. Go to **Account Settings** → **Security** → **Personal Access Tokens**
3. Create a new token with appropriate permissions
4. Copy the token to your `.env` file

### Media Files

Supported media types:
- Images: JPG, JPEG, PNG, GIF, WebP
- Videos: MP4
- Audio: Opus, AAC, M4A
- Documents: PDF, DOC, DOCX

## 🔄 Import Methods

### Method 1: Bulk Import (Recommended)

Uses Mattermost's bulk import API for faster processing:

- ✅ Faster import for large chats
- ✅ Preserves message timestamps
- ✅ Handles media attachments
- ❌ Requires admin permissions

### Method 2: Individual Posts

Posts messages one by one using the regular API:

- ✅ Works with standard user permissions
- ✅ Good for smaller chats
- ❌ Slower for large chats
- ❌ May hit rate limits

### Method 3: File Export

Creates an import package for manual upload:

- ✅ Works offline
- ✅ Can be imported later
- ✅ Good for review before import
- ❌ Manual upload required

## 😀 Emoji Handling

WhatsApp and Mattermost use different emoji formats. The tool includes automatic emoji conversion:

**How it works:**
- WhatsApp exports emojis as Unicode sequences
- The tool maps them to Mattermost emoji names (`:emoji-name:`)
- Common emojis are pre-mapped in `WhatsAppEmojiMap`

**Adding custom emoji mappings:**
```php
$emojiMap = new WhatsAppEmojiMap();
$emojiMap->add("🎉", ":tada:");
$emojiMap->add("❤️", ":heart:");
```

**Finding unmapped emojis:**
- Check the error log for Unicode sequences
- Compare with Mattermost's emoji picker
- Add mappings to `WhatsAppEmojiMap` class

## 🛠️ Troubleshooting

### Common Issues

**"Unknown user" error:**
- Check WhatsApp display names in your export file
- Add missing users to the user mapping
- Ensure exact name matching (case-sensitive)

**"Unknown telephone number" error:**
- Look for @mentions in your chat export
- Add phone numbers to the phone mapping
- Use exact format from export (including country code)

**Import fails:**
- Verify Mattermost URL and API token
- Check team and channel names
- Ensure you have proper permissions

**Missing media files:**
- Ensure media folder is in the same directory as chat export
- Check supported file formats
- Verify file permissions

### Debug Mode

Enable verbose output by modifying the script:
```php
// Add at the top of convert.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📁 File Structure

```
whatsapp-mattermost/
├── src/
│   ├── convert.php              # Main conversion script
│   └── de/phosco/mattermost/whatsapp/
│       ├── WhatsAppChat.php     # Chat parser
│       ├── JsonLConverter.php   # Format converter
│       ├── WhatsAppUserMap.php  # User mapping
│       ├── WhatsAppPhoneMap.php # Phone mapping
│       └── WhatsAppEmojiMap.php # Emoji mapping
├── composer.json                # Dependencies
└── README.md                    # This file
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Setup

```powershell
git clone https://github.com/SkyLostTR/whatsapp-mattermost.git
cd whatsapp-mattermost
composer install
```

### Running Tests

```powershell
# Check PHP syntax
Get-ChildItem -Path src -Filter "*.php" -Recurse | ForEach-Object { php -l $_.FullName }

# Test ZIP functionality
php test_ziparchive.php
```

## 📄 License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

## 🔒 Security

Please review our [Security Policy](SECURITY.md) for information about reporting vulnerabilities and data privacy considerations.

## 📚 Resources

- [Mattermost Import Documentation](https://docs.mattermost.com/administration/bulk-export.html)
- [WhatsApp Export Guide](https://faq.whatsapp.com/1180414079177245)
- [Mattermost API Documentation](https://api.mattermost.com/)

## 📞 Support

- 🐛 [Report Issues](https://github.com/SkyLostTR/whatsapp-mattermost/issues)
- 💡 [Request Features](https://github.com/SkyLostTR/whatsapp-mattermost/issues/new?template=feature_request.md)
- ❓ [Ask Questions](https://github.com/SkyLostTR/whatsapp-mattermost/issues/new?template=support_question.md)

---

**Attribution:**
- Originally created by [André Rothe](https://www.phosco.info) ([phosco/mattermost](https://github.com/phosco/mattermost))
- Enhanced by [witchi](https://github.com/witchi) ([witchi/whatsapp-mattermost](https://github.com/witchi/whatsapp-mattermost))
- Maintained and improved by [SkyLostTR](https://github.com/SkyLostTR)

Version 1.1.0
