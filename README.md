Derivative Media Optimizer (module for Omeka S)
===============================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Derivative Media] is a module for [Omeka S] that optimizes files for the web:
it creates derivative files from audio and video files adapted for mobile
or desktop, streamable, sized for slow or big connection, and cross-browser
compatible,  including Safari. Multiple derivative files can be created for each
file. It works the same way Omeka does for images (large, medium and square
thumbnails).

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


Installation
------------

Some formats used by this module requires server packages:
- `ffmpeg` for audio and video
- `ghostscript` with command `gs` for pdf
- `pdftotext` from package poppler-utils for format pdf2xml

If you use them, they should be installed on the server and available in the
path.

First, install the required module [Common].

You can use the release zip to install it, or use and init the source.

* From the zip

Download the last release [DerivativeMedia.zip] from the list of releases and
uncompress it in the `modules` directory.

* From the source and for development:

If the module was installed from the source, rename the name of the folder of
the module to `DerivativeMedia`.

See general end user documentation for [installing a module] and follow the
config instructions.


Usage
-----

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

### Theme with resource block and view helper

Use the resource block "Derivative Media List" to display the list of available
derivative of a resource.

Or use the view helper `derivatives()`:

```php
<?= $this->derivatives($resource) ?>
```


TODO
----

- [ ] Adapt for any store, not only local one.
- [ ] Adapt for thumbnails.
- [ ] Adapt for models.
- [ ] Improve security of the command or limit access to super admin only (in main settings anyway).
- [ ] Add a check for the duration: a shorter result than original means that an issue occurred.
- [ ] Add a check for missing conversions (a table with a column by conversion).
- [ ] Add a check for fast start (mov,mp4,m4a,3gp,3g2,mj2).
- [ ] Finalize for pdf.
- [ ] Add a check of number of job before running job CreateDerivatives.
- [ ] Pdf to tsv for iiif search


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page.


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


Copyright
---------

* Copyright Daniel Berthereau, 2020-2024

First version of this module was done for [Archives sonores de poésie] of [Sorbonne Université].


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
