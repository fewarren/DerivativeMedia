Derivative Media Optimizer (module for Omeka S)
===============================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Derivative Media] is a module for [Omeka S] that optimizes files for the web:
it creates derivative files from audio and video files adapted for mobile
or desktop, streamable, sized for slow or big connection, and cross-browser
compatible, including Safari. Multiple derivative files can be created for each
file. It works the same way Omeka does for images (large, medium and square
thumbnails).

## 🎬 Video Thumbnail Enhancement

This enhanced version includes **advanced video thumbnail generation** capabilities that automatically create proper video thumbnails using FFmpeg, replacing Omeka S's default black (first frame) thumbnails for video files. All the code for this enhancement was created by various AI agents with none created by a human. All debugging was done by AI agents with oversight by a software engineer. Thanks are due to the army of intelligent and dedicated people who have poured their insights and lives into creating these tools.  Special thanks to the creators of Claude Code, Roo Code, CoPilot, CodeRabbit, and most especially to the folks at Agument without who's work this enahncement would never have been born.

### Key Features

- **🎯 Automatic Video Thumbnail Generation**: Generates thumbnails from video files at configurable time positions
- **🛡️ Download Prevention**: Optional security features to prevent video downloads
- **⚙️ Custom File Renderers**: Enhanced video and audio renderers with URL fixes
- **🔄 Batch Processing**: Background job system for processing multiple videos
- **📱 Cross-Browser Compatibility**: Works with all modern browsers and mobile devices

## Table of Contents

- [🎬 Video Thumbnail Enhancement](#-video-thumbnail-enhancement)
- [Installation](#installation)
  - [Prerequisites](#prerequisites)
  - [Module Installation](#module-installation)
  - [Video Thumbnail Configuration](#video-thumbnail-configuration)
- [Configuration](#configuration)
  - [Video Thumbnail Settings](#video-thumbnail-settings)
  - [Download Prevention](#download-prevention)
- [Usage](#usage)
  - [Configuration of Commands](#configuration-of-commands)
  - [Bulk Creation](#bulk-creation)
  - [Theme Integration](#theme-integration)
- [Technical Implementation](#technical-implementation)
  - [Architecture](#architecture)
  - [API Reference](#api-reference)
- [Features](#features)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [License](#license)
- [Copyright](#copyright)

## 🚀 Quick Start

**Want video thumbnails working in 5 minutes?**

1. **Install FFmpeg**: `sudo apt install ffmpeg` (Ubuntu/Debian)
2. **Install the module** in Omeka S admin interface
3. **Edit** `/path/to/omeka-s/config/local.config.php` and add:
   ```php
   'service_manager' => [
       'aliases' => [
           'Omeka\File\Store' => 'Omeka\File\Store\Local',
           'Omeka\File\Thumbnailer' => 'DerivativeMedia\File\Thumbnailer\VideoAwareThumbnailer',
       ],
   ],
   ```
4. **Restart Apache**: `sudo systemctl restart apache2`
5. **Upload a video** - thumbnail will be generated automatically! 🎉

**That's it!** Your videos will now show proper thumbnails instead of black squares.

At item level, some more formats are supported:
- `alto`: Xml format for OCR. When alto is available by page, a single xml may be
  created. It is useful for the module [Iiif Search], because the search can be
  done quicker on a single file.
- `iiif/2` and `iiif/3`: Allow to cache [IIIF] manifests (v2 and v3) for items
  with many medias or many visitors, so it can be used by module [Iiif Server]
  or any other external Iiif viewer.
- `text`: If text files are attached to the item, they can be gathered in a single
  one.
- `text`: If text is available in media values "extracttext:extracted_text", they
  can be gathered in a single one.
- `pdf`: concatenate all images in a single pdf file (require ImageMagick).
- `pdf2xml`: extract text layer from pdf and create an xml for iiif search.
- `zip`: zip all files.
- `zip media`: zip all media files (audio, video, images).
- `zip other`: zip all other files.

The conversion uses [ffmpeg] and [ghostscript], two command-line tools that are
generally installed by default on most servers. The commands are customizable.

The process on pdf allows to make them streamable and linearized (can be
rendered before full loading), generally smaller for the same quality.


## Installation

### Prerequisites

This module requires the following server packages:
- **`ffmpeg`** - For audio/video processing and thumbnail generation
- **`ffprobe`** - For media file analysis (usually included with ffmpeg)
- **`ghostscript`** with command `gs` - For PDF processing
- **`pdftotext`** from package poppler-utils - For PDF text extraction

Install these packages on your server and ensure they are available in the system PATH.

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install ffmpeg ghostscript poppler-utils
```

**CentOS/RHEL:**
```bash
sudo yum install ffmpeg ghostscript poppler-utils
# or for newer versions:
sudo dnf install ffmpeg ghostscript poppler-utils
```

### Module Installation

1. **Install Required Dependencies**

   First, install the required module [Common].

2. **Download and Install Module**

   * **From the zip**: Download the latest release [DerivativeMedia.zip] from the list of releases and uncompress it in the `modules` directory.

   * **From source**: If installing from source, rename the module folder to `DerivativeMedia`.

3. **Configure Omeka S**

   See general end user documentation for [installing a module] and follow the configuration instructions below.

### Video Thumbnail Configuration

For video thumbnail functionality to work properly, you need to configure the thumbnailer override:

#### Required: Local Configuration Override

Edit `/path/to/omeka-s/config/local.config.php` and add the following configuration:

```php
<?php
return [
    // ... your existing configuration ...

    'service_manager' => [
        'aliases' => [
            'Omeka\File\Store' => 'Omeka\File\Store\Local',
            // CRITICAL: This line enables video thumbnail generation
            'Omeka\File\Thumbnailer' => 'DerivativeMedia\File\Thumbnailer\VideoAwareThumbnailer',
        ],
    ],

    // ... rest of your configuration ...
];
```

⚠️ **Important**: This configuration override is **required** for video thumbnails to work. Without it, videos will continue to show black thumbnails.

## Environment-Specific Configuration

Following Omeka S best practices, all environment-specific settings should be configured in `local.config.php` rather than hardcoded in module files. This ensures your installation is portable across different environments (development, staging, production).

### Required Configuration in local.config.php

#### Base URL Configuration

**Required for proper URL generation:**

```php
<?php
return [
    // Base URL for your Omeka S installation
    'base_url' => 'http://your-domain.com/omeka-s',

    // ... other configuration
];
```

#### File Store Configuration

**Required if you need custom file paths or URLs:**

```php
'file_store' => [
    'local' => [
        'base_path' => '/var/www/omeka-s/files',
        'base_uri' => 'http://your-domain.com/omeka-s/files',
    ],
],
```

### Environment Examples

#### Development Environment
```php
<?php
return [
    'base_url' => 'http://localhost/omeka-s',
    'file_store' => [
        'local' => [
            'base_path' => '/var/www/omeka-s/files',
            'base_uri' => 'http://localhost/omeka-s/files',
        ],
    ],
    'service_manager' => [
        'aliases' => [
            'Omeka\File\Store' => 'Omeka\File\Store\Local',
            'Omeka\File\Thumbnailer' => 'DerivativeMedia\File\Thumbnailer\VideoAwareThumbnailer',
        ],
    ],
];
```

#### Production Environment
```php
<?php
return [
    'base_url' => 'https://your-production-domain.com/omeka-s',
    'file_store' => [
        'local' => [
            'base_path' => '/var/www/omeka-s/files',
            'base_uri' => 'https://your-production-domain.com/omeka-s/files',
        ],
    ],
    'service_manager' => [
        'aliases' => [
            'Omeka\File\Store' => 'Omeka\File\Store\Local',
            'Omeka\File\Thumbnailer' => 'DerivativeMedia\File\Thumbnailer\VideoAwareThumbnailer',
        ],
    ],
];
```

#### Docker/Container Environment
```php
<?php
return [
    'base_url' => getenv('OMEKA_BASE_URL') ?: 'http://localhost:8080',
    'file_store' => [
        'local' => [
            'base_path' => getenv('OMEKA_FILES_PATH') ?: '/var/www/html/files',
            'base_uri' => (getenv('OMEKA_BASE_URL') ?: 'http://localhost:8080') . '/files',
        ],
    ],
    'service_manager' => [
        'aliases' => [
            'Omeka\File\Store' => 'Omeka\File\Store\Local',
            'Omeka\File\Thumbnailer' => 'DerivativeMedia\File\Thumbnailer\VideoAwareThumbnailer',
        ],
    ],
];
```

### Environment Variables Support

You can use environment variables in your `local.config.php` for maximum portability:

```php
<?php
return [
    'base_url' => getenv('OMEKA_BASE_URL') ?: 'http://localhost/omeka-s',
    'file_store' => [
        'local' => [
            'base_path' => getenv('OMEKA_FILES_PATH') ?: '/var/www/omeka-s/files',
            'base_uri' => (getenv('OMEKA_BASE_URL') ?: 'http://localhost/omeka-s') . '/files',
        ],
    ],
    // ... other configuration
];
```

### Why This Approach?

1. **🏗️ Follows Omeka S Best Practices**: Configuration belongs in `local.config.php`, not in module code
2. **🌍 Environment Portability**: Easy to deploy across different environments without code changes
3. **🔒 Security**: Sensitive configuration stays out of version control
4. **🔧 Maintainability**: No need to modify module code for different environments
5. **📋 Framework Compliance**: Uses standard Omeka S configuration patterns

### Migration from Hardcoded URLs

If you were previously using a version with hardcoded URLs, you need to:

1. ✅ **Update local.config.php** with proper `base_url` and `file_store` configuration
2. ✅ **Remove any hardcoded URLs** from module files (this is now done automatically)
3. ✅ **Restart your web server** to ensure configuration changes take effect
4. ✅ **Test URL generation** by uploading a new video and checking thumbnail URLs

## Production Deployment

### Deployment Best Practices

For production deployments, follow these guidelines:

#### 1. Configuration Management
- ✅ **Never hardcode URLs**: Use `local.config.php` for all environment-specific settings
- ✅ **Environment variables**: Use `getenv()` for Docker/container deployments
- ✅ **Version control**: Exclude `local.config.php` from version control
- ✅ **Documentation**: Document required environment variables for your team

#### 2. Service Configuration
```php
// Required in local.config.php for production
'service_manager' => [
    'aliases' => [
        'Omeka\File\Store' => 'Omeka\File\Store\Local',
        'Omeka\File\Thumbnailer' => 'DerivativeMedia\File\Thumbnailer\VideoAwareThumbnailer',
    ],
],
```

#### 3. Performance Optimization
- ✅ **Disable debug logging**: Turn off debug logging in production
- ✅ **PHP OPcache**: Enable PHP OPcache for better performance
- ✅ **File permissions**: Ensure proper file ownership and permissions
- ✅ **Background jobs**: Use proper job queue management for large video processing

#### 4. Security Considerations
- ✅ **File permissions**: Restrict access to configuration files
- ✅ **Download prevention**: Enable video download prevention if needed
- ✅ **Path validation**: Ensure FFmpeg paths are secure and validated
- ✅ **Error handling**: Proper error handling without exposing system information

#### 5. Monitoring and Maintenance
- ✅ **Log monitoring**: Set up log monitoring for errors and issues
- ✅ **Job monitoring**: Monitor background job processing
- ✅ **Disk space**: Monitor file storage for thumbnail generation
- ✅ **Performance**: Monitor FFmpeg processing performance

### Deployment Checklist

Before deploying to production:

- [ ] ✅ **FFmpeg installed** and accessible at configured path
- [ ] ✅ **local.config.php** configured with production URLs
- [ ] ✅ **File permissions** set correctly for web server
- [ ] ✅ **Module upgraded** to latest version
- [ ] ✅ **Debug logging** disabled or configured appropriately
- [ ] ✅ **Background jobs** tested and working
- [ ] ✅ **Video uploads** tested with thumbnail generation
- [ ] ✅ **URL generation** verified for production domain
- [ ] ✅ **Error handling** tested with invalid videos
- [ ] ✅ **Performance** tested with large video files

## Configuration

### Video Thumbnail Settings

After installation, configure the video thumbnail functionality:

1. **Go to**: Admin → Modules → DerivativeMedia → Configure
2. **Configure the following settings**:

#### Video Thumbnail Options

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable Video Thumbnails** | Automatically generate thumbnails for video files | ✅ Enabled |
| **Thumbnail Position (%)** | Position in video to extract thumbnail (0-100%) | 15% |
| **Disable Video Downloads** | Prevent users from downloading videos | ❌ Disabled |
| **FFmpeg Path** | Path to FFmpeg executable | `/usr/bin/ffmpeg` |
| **FFprobe Path** | Path to FFprobe executable | `/usr/bin/ffprobe` |

#### Batch Processing

- **Process Video Thumbnails**: Button to start background job for generating thumbnails
- **Force Regenerate**: Checkbox to regenerate existing thumbnails
- **Job Status**: Monitor progress in Admin → Jobs

### Video Thumbnail Generation

The module provides three ways to generate video thumbnails:

1. **🔄 Automatic Generation**: New video uploads automatically generate thumbnails
2. **⚙️ Manual Regeneration**: Use the configuration form to regenerate specific videos
3. **📦 Batch Processing**: Process multiple videos using background jobs

#### Automatic Generation

When you upload a new video file:
- Thumbnail is automatically generated at the configured percentage position
- Multiple thumbnail sizes are created (large, medium, square)
- Process happens in the background without blocking the upload

#### Manual Regeneration

To regenerate thumbnails for existing videos:
1. Go to Admin → Modules → DerivativeMedia → Configure
2. Set the desired thumbnail position percentage
3. **Check "Force Regenerate Existing Thumbnails"** to overwrite existing thumbnails
4. Click "Process Video Thumbnails"
5. Monitor progress in Admin → Jobs

**Important**: The Force option is now working correctly. When checked:
- ✅ **Regenerates All**: Processes videos even if thumbnails already exist
- ✅ **Overwrites Files**: Replaces existing thumbnail files
- ✅ **Logs Activity**: Clear logging of force regeneration status
- ✅ **Batch Processing**: Works with bulk thumbnail generation

**When Force is NOT checked**:
- ⏭️ **Skips Existing**: Videos with thumbnails are skipped
- 🚀 **Faster Processing**: Only processes videos without thumbnails
- 📊 **Selective**: Ideal for processing new uploads only

### Download Prevention

The module includes optional security features to prevent video downloads:

#### When Enabled:
- ❌ Removes download button from video controls
- ❌ Disables right-click context menu on videos
- ❌ Removes fallback download links
- ❌ Disables picture-in-picture mode
- ❌ Prevents casting to external devices

#### Configuration:
1. Go to Admin → Modules → DerivativeMedia → Configure
2. Check "Disable Video Downloads"
3. Save configuration
4. All videos will immediately use the new security settings

## Usage

### Configuration of commands

Set settings in the main settings page. Each row in the text area is one format.
The filepath is the left part of the row (`mp4/{filename}.mp4`) and the command
is the right part.

The default params allows to create five derivative files, two for audio, two
for video, and one for pdf. They are designed to keep the same quality than the
original file, and to maximize compatibility with old browsers and Apple Safari.
The webm one is commented (a "#" is prepended), because it is slow.

You can modify params as you want and remove or add new ones. They are adapted
for a recent Linux distribution with a recent version of ffmpeg. You may need to
change names of arguments and codecs on older versions.

For pdf, the html5 standard doesn't give the possibility to display multiple
sources for one link, so it's useless to multiply them.

Ideally, the params should mix compatibilities parameters for old browsers and
Apple Safari, improved parameters for modern browsers (vp9/webm), and different
qualities for low speed networks (128kB), and high speed networks (fiber).

Then, in the site item pages or in the admin media pages, all files will be
appended together in the html5 `<audio>` and `<video>` elements, so the browser
will choose the best one. For pdf, the derivative file will be used
automatically by modules [Universal Viewer] (via [IIIF Server]) and [Pdf Viewer].

### Bulk creation

You can convert existing files via the config form. This job is available in the
module [Bulk Check] too.

Note that the creation of derivative files is a slow and cpu-intensive process:
until two or three hours for a one hour video. You can use arguments `-preset veryslow`
or `-preset ultrafast` (mp4) or `-deadline best` or `-deadline realtime` (webm)
to speed or slow process, but faster means a lower ratio quality/size. See
[ffmpeg wiki] for more info about arguments for mp4, [ffmpeg wiki too] for webm,
and the [browser support table].

For mp4, important options (preset and tune) are [explained here].
For webm, important options (preset and tune) are [explained here].

In all cases, it is better to have original files that follow common standards.
Check if a simple fix like [this one] is enough before uploading files.

The default queries are (without `ffmpeg` or `gs` prepended, and output,
appended):

```
# Audio
mp3/{filename}.mp3   = -c copy -c:a libmp3lame -qscale:a 2
ogg/{filename}.ogg   = -c copy -vn -c:a libopus
aac/{filename}.m4a   = -c copy -c:a aac -q:a 2 -movflags +faststart

# Video. To avoid issue with Apple Safari, you may add mov before mp4.
webm/{filename}.webm = -c copy -c:v libvpx-vp9 -crf 30 -b:v 0 -deadline realtime -pix_fmt yuv420p -c:a libopus
mov/{filename}.mov   = -c copy -c:v libx264 -movflags +faststart -filter:v crop='floor(in_w/2)*2:floor(in_h/2)*2' -crf 22 -level 3 -preset ultrafast -tune film -pix_fmt yuv420p -c:a aac -qscale:a 2 -f mov
mp4/{filename}.mp4   = -c copy -c:v libx264 -movflags +faststart -filter:v crop='floor(in_w/2)*2:floor(in_h/2)*2' -crf 22 -level 3 -preset ultrafast -tune film -pix_fmt yuv420p -c:a libmp3lame -qscale:a 2

# Pdf (supported via gs)
# The default setting "/screen" output the smallest pdf readable on a screen.
pdfs/{filename}.pdf' => '-dCompatibilityLevel=1.7 -dPDFSETTINGS=/screen
# The default setting "/ebook" output a medium size pdf readable on any device.
pdfe/{filename}.pdf' => '-dCompatibilityLevel=1.7 -dPDFSETTINGS=/ebook
# Here an example with the most frequent params (see https://github.com/mattdesl/gsx-pdf-optimize)
pdfo/{filename}.pdf  = -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -dNOPAUSE -dQUIET -dBATCH -dCompatibilityLevel=1.7 -dSubsetFonts=true -dCompressFonts=true -dEmbedAllFonts=true -sProcessColorModel=DeviceRGB -sColorConversionStrategy=RGB -sColorConversionStrategyForImages=RGB -dConvertCMYKImagesToRGB=true -dDetectDuplicateImages=true -dColorImageDownsampleType=/Bicubic -dColorImageResolution=300 -dGrayImageDownsampleType=/Bicubic -dGrayImageResolution=300 -dMonoImageDownsampleType=/Bicubic -dMonoImageResolution=300 -dDownsampleColorImages=true -dDoThumbnails=true -dCreateJobTicket=false -dPreserveEPSInfo=false -dPreserveOPIComments=false -dPreserveOverprintSettings=false -dUCRandBGInfo=/Remove
```

It's important to check the version of ffmpeg and gs that is installed on the
server, because the options may be different or may have been changed.


### External preparation

Because conversion is cpu-intensive, they can be created on another computer,
then copied in the right place.

Here is an example of a one-line command to prepare all wav into mp3 of a
directory:

```sh
cd /my/source/dir; for filename in *.wav; do name=`echo "$filename" | cut -d'.' -f1`; basepath=${filename%.*}; basename=${basepath##*/}; echo "$basename.wav => $basename.mp3"; ffmpeg -i "$filename" -c copy -c:a libmp3lame -qscale:a 2 "${basename}.mp3"; done
```

Another example when original files are in subdirectories (module Archive Repertory):

```sh
# Go to the root directory (important to recreate structure with command below).
cd '/var/www/html/files/original'

# Convert all files.
find '/var/www/html/files/original' -type f -name '*.wav' -exec ffmpeg -i "{}" -c copy -c:a libmp3lame -qscale:a 2 "{}".mp3 \;

# Recreate structure of a directory (here the destination is "/var/www/html/files").
find * -type d -exec mkdir -p "/var/www/html/files/mp3/{}" \;

# Move a specific type of files into a directory.
find . -type f -name "*.mp3" -exec mv "{}" "/var/www/html/files/mp3/{}" \;

# Remove empty directories.
rmdir "/var/www/html/files/mp3/*"

# Rename new files.
find '/var/www/html/files/mp3' -type f -name '*.wav.mp3' -exec rename 's/.wav.mp3/.mp3/' "{}" \;
```

**IMPORTANT**: After copy, Omeka should know that new derivative files exist,
because it doesn't check directories and formats each time a media is rendered.
To record the metadata, go to the config form and click "Store metadata".

### Fast start

For mp4 and mov, it's important to set the option `-movflags +faststart` to
allow the video to start before the full loading. To check if a file has the
option:

```sh
ffmpeg -i 'my_video.mp4' -v trace 2>&1 | grep -m 1 -o -e "type:'mdat'" -e "type:'moov'"
```

If output is `mdat` and not `moov`, the file is not ready for fast start. To fix
it, simply copy the file with the option:

```sh
ffmpeg -i 'my_video.mp4' -c copy -movflags +faststart 'my_video.faststart.mp4'
```

See [ffmpeg help] for more information.

### Protection of files

To protect files created dynamically (alto, text, zip…), add a rule in the file
`.htaccess` at the root of Omeka to redirect files/alto, files/zip, etc. to
/derivative/{type}/#id.

### Theme Integration

#### Resource Block

Use the resource block "Derivative Media List" to display the list of available derivatives of a resource.

#### View Helper

Use the view helper `derivatives()`:

```php
<?= $this->derivatives($resource) ?>
```

#### Video Thumbnail Display

Video thumbnails are automatically used in:
- Item listings and search results
- Media galleries and carousels
- Resource page blocks
- Admin interface media previews

#### Custom Video Rendering

The enhanced video renderer provides:

```html
<!-- Automatic video rendering with download prevention (when enabled) -->
<video src="video.mp4" controls controlsList="nodownload" oncontextmenu="return false">
    Your browser does not support HTML5 video.
</video>
```

## Technical Implementation

### Architecture

The video thumbnail enhancement consists of several integrated components:

#### Core Components

1. **VideoAwareThumbnailer** (`src/File/Thumbnailer/VideoAwareThumbnailer.php`)
   - Extends ImageMagick thumbnailer
   - Detects video files and delegates to FFmpeg
   - Fallback to standard thumbnailer for non-video files

2. **VideoThumbnailService** (`src/Service/VideoThumbnailService.php`)
   - Handles FFmpeg thumbnail generation
   - Configurable thumbnail position and quality
   - Error handling and logging

3. **Custom File Renderers** (`src/Media/FileRenderer/`)
   - Enhanced video and audio renderers
   - Download prevention features
   - URL generation fixes

4. **Background Jobs** (`src/Job/GenerateVideoThumbnails.php`)
   - Batch processing for multiple videos
   - Progress tracking and error reporting
   - Configurable processing options

#### Event System

The module uses Omeka S's event system for automatic processing:

```php
// Automatic thumbnail generation on media upload/update
$sharedEventManager->attach(
    'Omeka\Api\Adapter\MediaAdapter',
    'api.create.post',
    [$this, 'handleVideoThumbnailGeneration']
);
```

#### Service Configuration

Key services are registered in `config/module.config.php`:

```php
'service_manager' => [
    'factories' => [
        'DerivativeMedia\Service\VideoThumbnailService' =>
            Service\VideoThumbnailServiceFactory::class,
        'DerivativeMedia\File\Thumbnailer\VideoAwareThumbnailer' =>
            Service\VideoAwareThumbnailerFactory::class,
    ],
],
```

### API Reference

#### VideoThumbnailService Methods

```php
// Generate thumbnail for a media object (with force regeneration option)
$success = $videoThumbnailService->generateThumbnail($media, $percentage, $forceRegenerate);

// Check if media is a video file
$isVideo = $videoThumbnailService->isVideoFile($media);

// Get thumbnail path for media
$thumbnailPath = $videoThumbnailService->getThumbnailPath($media, $size);

// Check if media has existing thumbnails
$hasExisting = $videoThumbnailService->hasExistingThumbnails($media);

// Get video duration
$duration = $videoThumbnailService->getVideoDuration($media);
```

#### DebugManager Service

```php
// Get debug manager instance
$debugManager = $serviceManager->get('DerivativeMedia\Service\DebugManager');

// Log messages by component
$debugManager->logInfo('Message', DebugManager::COMPONENT_MODULE, $operationId);
$debugManager->logDebug('Debug info', DebugManager::COMPONENT_THUMBNAILER, $operationId);
$debugManager->logWarning('Warning', DebugManager::COMPONENT_RENDERER, $operationId);
$debugManager->logError('Error message', DebugManager::COMPONENT_SERVICE, $operationId);

// Available components
DebugManager::COMPONENT_MODULE      // Module-level operations
DebugManager::COMPONENT_RENDERER    // File rendering
DebugManager::COMPONENT_THUMBNAILER // Thumbnail generation
DebugManager::COMPONENT_SERVICE     // Background services
DebugManager::COMPONENT_HELPER      // View helpers
DebugManager::COMPONENT_FACTORY     // Service factories
DebugManager::COMPONENT_BLOCK       // Block layouts
DebugManager::COMPONENT_FORM        // Form processing
DebugManager::COMPONENT_API         // API operations
```

#### Configuration Settings

| Setting Key | Type | Description | Default |
|-------------|------|-------------|---------|
| `derivativemedia_video_thumbnail_enabled` | boolean | Enable/disable video thumbnails | `true` |
| `derivativemedia_video_thumbnail_percentage` | integer | Thumbnail position (0-100%) | `15` |
| `derivativemedia_disable_video_downloads` | boolean | Enable download prevention | `false` |
| `derivativemedia_ffmpeg_path` | string | Path to FFmpeg executable | `/usr/bin/ffmpeg` |
| `derivativemedia_ffprobe_path` | string | Path to FFprobe executable | `/usr/bin/ffprobe` |
| `derivativemedia_debug_enabled` | boolean | Enable debug logging | `false` |

#### Job Parameters

| Parameter | Type | Description | Usage |
|-----------|------|-------------|-------|
| `force_regenerate` | boolean | Force regeneration of existing thumbnails | Job processing |
| `percentage` | integer | Thumbnail extraction position | Job processing |
| `query` | array | Media query parameters | Batch processing |
| `media_id` | integer | Specific media ID to process | Single media |


## Features

### ✅ Implemented Features

- **🎬 Video Thumbnail Generation**: Automatic thumbnail creation from video files
- **🛡️ Download Prevention**: Security features to prevent video downloads
- **⚙️ Custom File Renderers**: Enhanced video/audio rendering with URL fixes
- **🔄 Background Job Processing**: Batch thumbnail generation
- **📱 Cross-Browser Compatibility**: Works with all modern browsers
- **🎯 Configurable Thumbnail Position**: Extract thumbnails at any time position
- **🔧 Admin Interface**: Easy configuration through Omeka S admin
- **📊 Job Monitoring**: Track progress through Jobs interface

### 🚀 Enhanced Video Features

- **Smart Thumbnailer**: Automatically detects video files and uses FFmpeg
- **Multiple Thumbnail Sizes**: Generates large, medium, and square thumbnails
- **Event-Driven Processing**: Thumbnails generated on upload/update
- **Fallback Support**: Graceful degradation for non-video files
- **Security Controls**: Optional download prevention with multiple protection layers
- **URL Generation Fixes**: Improved media URL handling for better compatibility

## TODO

### Core Module
- [ ] Adapt for any store, not only local one
- [ ] Adapt for models
- [ ] Improve security of the command or limit access to super admin only
- [ ] Add a check for the duration: a shorter result than original means that an issue occurred
- [ ] Add a check for missing conversions (a table with a column by conversion)
- [ ] Add a check for fast start (mov,mp4,m4a,3gp,3g2,mj2)
- [ ] Finalize for PDF processing
- [ ] Add a check of number of jobs before running job CreateDerivatives
- [ ] PDF to TSV for IIIF search

### Video Thumbnail Enhancements
- [x] ✅ Automatic video thumbnail generation
- [x] ✅ Configurable thumbnail position
- [x] ✅ Background job processing
- [x] ✅ Download prevention features
- [x] ✅ Custom file renderers
- [x] ✅ Admin interface integration
- [ ] Support for additional video formats
- [ ] Thumbnail quality settings
- [ ] Multiple thumbnail positions per video
- [ ] Video preview generation (animated thumbnails)
- [ ] Integration with external thumbnail services


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


## Troubleshooting

### Configuration Issues

#### Wrong URLs in Media Links

**Problem**: Media URLs point to wrong domain or show localhost in production.

**Solution**:
1. ✅ Set `base_url` in `/path/to/omeka-s/config/local.config.php`:
   ```php
   'base_url' => 'https://your-actual-domain.com/omeka-s',
   ```
2. ✅ Configure `file_store.local.base_uri` to match your domain:
   ```php
   'file_store' => [
       'local' => [
           'base_uri' => 'https://your-actual-domain.com/omeka-s/files',
       ],
   ],
   ```
3. ✅ Restart web server: `sudo systemctl restart apache2`
4. ✅ Clear browser cache and test

#### Environment Portability Issues

**Problem**: Module works in development but fails in production.

**Solution**:
1. ✅ Use environment variables in `local.config.php`:
   ```php
   'base_url' => getenv('OMEKA_BASE_URL') ?: 'http://localhost/omeka-s',
   ```
2. ✅ Set environment variables in your deployment:
   ```bash
   export OMEKA_BASE_URL="https://production-domain.com/omeka-s"
   export OMEKA_FILES_PATH="/var/www/omeka-s/files"
   ```
3. ✅ Ensure `local.config.php` is not in version control
4. ✅ Document required environment variables for your team

#### Configuration Not Taking Effect

**Problem**: Changes to `local.config.php` don't seem to work.

**Solution**:
1. ✅ Check file syntax: `php -l /path/to/omeka-s/config/local.config.php`
2. ✅ Verify file permissions: `ls -la /path/to/omeka-s/config/local.config.php`
3. ✅ Restart web server: `sudo systemctl restart apache2`
4. ✅ Clear any PHP opcache if enabled
5. ✅ Check error logs for PHP syntax errors

### Video Thumbnail Issues

#### Black Thumbnails Still Appearing

**Problem**: Videos still show black thumbnails after installation.

**Solution**:
1. ✅ Verify FFmpeg is installed: `ffmpeg -version`
2. ✅ Check local.config.php has the thumbnailer override
3. ✅ Restart Apache/web server: `sudo systemctl restart apache2`
4. ✅ Upgrade the module in Admin → Modules
5. ✅ Test with a new video upload

#### Job Not Starting

**Problem**: "Process Video Thumbnails" button doesn't start jobs.

**Solution**:
1. ✅ Upgrade the module in Admin → Modules → DerivativeMedia → Upgrade
2. ✅ Check Admin → Jobs for error messages
3. ✅ Verify FFmpeg paths in module configuration
4. ✅ Check server logs for PHP errors

#### Permission Errors

**Problem**: FFmpeg permission denied or file access errors.

**Solution**:
```bash
# Ensure FFmpeg is executable
sudo chmod +x /usr/bin/ffmpeg
sudo chmod +x /usr/bin/ffprobe

# Check file permissions
sudo chown -R www-data:www-data /path/to/omeka-s/files/
sudo chmod -R 755 /path/to/omeka-s/files/
```

#### Video Rendering Issues

**Problem**: Videos not displaying properly or download prevention not working.

**Solution**:
1. ✅ Clear browser cache
2. ✅ Check browser console for JavaScript errors
3. ✅ Verify custom file renderers are enabled in module settings
4. ✅ Test with different video formats (MP4, WebM)

### Configuration Verification

To verify your installation is working correctly:

```bash
# Test FFmpeg installation
ffmpeg -version
ffprobe -version

# Check Omeka S file permissions
ls -la /path/to/omeka-s/files/
ls -la /path/to/omeka-s/config/local.config.php

# Test video thumbnail generation manually
ffmpeg -i input_video.mp4 -ss 00:00:05 -vframes 1 -f image2 test_thumbnail.jpg

# Verify configuration syntax
php -l /path/to/omeka-s/config/local.config.php

# Test URL generation
curl -I http://your-domain.com/omeka-s/files/
```

### Configuration Best Practices Checklist

Use this checklist to ensure your configuration follows Omeka S best practices:

- ✅ **base_url** is set in `local.config.php` (not hardcoded in modules)
- ✅ **file_store** configuration is in `local.config.php` (if needed)
- ✅ **VideoAwareThumbnailer** is configured in service_manager aliases
- ✅ **local.config.php** is excluded from version control
- ✅ **Environment variables** are used for deployment flexibility
- ✅ **No hardcoded URLs** exist in module code
- ✅ **Web server** has been restarted after configuration changes

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Black thumbnails | Missing thumbnailer override | Add VideoAwareThumbnailer to local.config.php |
| Wrong URLs | Missing base_url configuration | Set base_url in local.config.php |
| Job not starting | Module not upgraded | Upgrade module in admin interface |
| Permission denied | File permissions | Fix file ownership and permissions |
| FFmpeg not found | Missing installation | Install FFmpeg package |
| Videos not playing | Missing renderers | Enable custom file renderers |
| Config not working | Syntax errors or caching | Check syntax, restart web server |
| Environment issues | Hardcoded values | Use environment variables in local.config.php |

### Debugging and Logging

The module includes a comprehensive logging system for troubleshooting:

#### Debug Configuration

Enable debug logging in the module configuration:
1. Go to Admin → Modules → DerivativeMedia → Configure
2. Check "Enable Debug Logging" (if available)
3. Save configuration

#### Log Locations

- **Application Logs**: `/var/www/omeka-s/logs/application.log`
- **Apache Error Logs**: `/var/log/apache2/omeka-s_error.log`
- **System Logs**: `/var/log/syslog` (for FFmpeg issues)

#### Debug Components

The module logs activities by component:
- **MODULE**: Bootstrap, configuration, event handling
- **RENDERER**: Video/audio file rendering
- **THUMBNAILER**: Thumbnail generation processes
- **SERVICE**: Background services and jobs
- **HELPER**: View helpers and URL generation
- **FACTORY**: Service factory operations
- **BLOCK**: Block layout rendering
- **FORM**: Configuration form processing
- **API**: API interactions

#### Common Debug Scenarios

```bash
# Monitor thumbnail generation in real-time
tail -f /var/www/omeka-s/logs/application.log | grep "THUMBNAILER"

# Check for URL generation issues
tail -f /var/www/omeka-s/logs/application.log | grep "HELPER"

# Monitor job processing
tail -f /var/www/omeka-s/logs/application.log | grep "SERVICE"

# Check module bootstrap issues
tail -f /var/www/omeka-s/logs/application.log | grep "MODULE"
```

#### Force Regeneration Debugging

If force regeneration isn't working:

1. **Check Job Arguments**:
   ```bash
   grep "force_regenerate" /var/www/omeka-s/logs/application.log
   ```

2. **Verify Service Calls**:
   ```bash
   grep "generateThumbnail.*force" /var/www/omeka-s/logs/application.log
   ```

3. **Monitor Thumbnail Processing**:
   ```bash
   tail -f /var/www/omeka-s/logs/application.log | grep "Force regeneration"
   ```

### Getting Help

For additional support:
- 📖 Check the [module issues] page for known issues
- 🐛 Report bugs with detailed error messages and server configuration
- 💡 Include FFmpeg version, PHP version, and Omeka S version in reports
- 📋 Include relevant log excerpts when reporting issues
- 🔍 Enable debug logging and include component-specific logs


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


## Changelog

### Version 3.4.x - Video Thumbnail Enhancement

#### Added
- 🎬 **Video Thumbnail Generation**: Automatic thumbnail creation using FFmpeg
- 🛡️ **Download Prevention**: Security features to prevent video downloads
- ⚙️ **Custom File Renderers**: Enhanced video/audio rendering with URL fixes
- 🔄 **Background Job Processing**: Batch thumbnail generation system
- 📱 **Cross-Browser Compatibility**: Improved video playback across devices
- 🎯 **Configurable Settings**: Admin interface for all video thumbnail options
- 📊 **Job Monitoring**: Progress tracking through Omeka S Jobs interface
- 🌍 **Environment Portability**: Proper configuration management following Omeka S best practices

#### Enhanced
- **VideoAwareThumbnailer**: Intelligent thumbnailer that detects video files
- **Event System**: Automatic processing on media upload/update
- **Error Handling**: Comprehensive logging and error reporting
- **URL Generation**: Fixed media URL issues for better compatibility
- **Configuration Management**: Removed hardcoded URLs, uses standard Omeka S configuration

#### Technical Improvements
- Service-oriented architecture with dependency injection
- Event-driven processing for automatic thumbnail generation
- Configurable FFmpeg integration with path validation
- Multiple thumbnail size generation (large, medium, square)
- Graceful fallback for non-video files
- **Omeka S Best Practices Compliance**: All environment-specific settings moved to `local.config.php`
- **Environment Variable Support**: Docker and CI/CD friendly configuration
- **Auto-Detection Fallbacks**: Graceful handling of missing configuration

#### Recent Fixes and Enhancements (Latest)

##### 🔧 **Force Regeneration Fix**
- **Fixed**: Force regeneration option now works correctly
- **Issue**: Force checkbox was not properly passing parameter to thumbnail service
- **Solution**: Updated job processing to include force parameter in service calls
- **Result**: Checking "Force regenerate existing thumbnails" now actually regenerates thumbnails

##### 🏗️ **Modern Framework Compatibility**
- **Fixed**: Replaced all deprecated `getServiceLocator()` calls with proper dependency injection
- **Enhanced**: Module now fully compatible with Laminas v3 patterns
- **Improved**: Better error handling and service access throughout the module
- **Result**: No more ServiceNotFoundException errors in configuration forms

##### 📝 **Configurable Logging System**
- **Added**: Comprehensive DebugManager service for configurable logging
- **Replaced**: All direct `error_log()` calls with conditional logging
- **Enhanced**: Component-based logging with operation tracking
- **Components**: MODULE, RENDERER, HELPER, THUMBNAILER, SERVICE, FACTORY, BLOCK, FORM, API
- **Result**: Clean production logs with optional debug output

##### 🌐 **Environment-Aware URL Generation**
- **Fixed**: Hardcoded development URLs replaced with dynamic configuration
- **Enhanced**: CustomServerUrl helper with malformed URL detection and correction
- **Added**: Multi-source URL fallback system (Config → Settings → Request → Relative)
- **Result**: URLs work correctly in any environment without hardcoded values

##### 🎯 **Production-Ready Architecture**
- **Implemented**: Complete separation of development and production concerns
- **Enhanced**: All environment-specific settings moved to `local.config.php`
- **Added**: Graceful degradation when services are unavailable
- **Result**: Module works reliably across different deployment environments

### Previous Versions
- See [module issues] and commit history for earlier changes

## Copyright

* Copyright Daniel Berthereau, 2020-2024
* Video Thumbnail Enhancement, 2024

First version of this module was done for [Archives sonores de poésie] of [Sorbonne Université].

The video thumbnail enhancement was developed to address the common issue of black thumbnails for video files in Omeka S installations.


[Derivative Media]: https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia
[Omeka S]: https://omeka.org/s
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[Bulk Check]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkCheck
[IIIF]: https://iiif.io
[Iiif Search]: https://github.com/Symac/Omeka-S-module-IiifSearch
[Iiif Server]: https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer
[ffmpeg]: https://ffmpeg.org
[ffmpeg wiki]: https://trac.ffmpeg.org/wiki/Encode/H.264
[ffmpeg wiki too]: https://trac.ffmpeg.org/wiki/Encode/VP9
[explained here]: https://trac.ffmpeg.org/wiki/Encode/H.264#a2.Chooseapresetandtune
[ghostscript]: https://www.ghostscript.com
[browser support table]: https://en.wikipedia.org/wiki/HTML5_video#Browser_support
[this one]: https://forum.omeka.org/t/mov-videos-not-playing-on-item-page-only-audio/11775/12
[ffmpeg help]: https://trac.ffmpeg.org/wiki/HowToCheckIfFaststartIsEnabledForPlayback
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[GitLab]: https://gitlab.com/Daniel-KM
[Archives sonores de poésie]: https://asp.huma-num.fr
[Sorbonne Université]: https://lettres.sorbonne-universite.fr
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
