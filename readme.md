# Cloudfiles 0.5.0

List the files of a shared cloud folder. Developed by Liam Perlaki.

A downloads page that nobody has to maintain: the files come from a folder you share in Nextcloud,
whoever puts a document there has published it. The web server reads the folder over WebDAV and
keeps a copy of the listing, so a visit does not wait for the cloud.

## How to install an extension

[Download ZIP file](https://github.com/pfadfinder26/yellow-cloudfiles/archive/refs/heads/main.zip) and copy it into your `system/extensions` folder. [Learn more about extensions](https://github.com/annaesvensson/yellow-update).

## How to list files

Share a folder in Nextcloud, "Copy link", and paste it into a page:

    [files https://cloud.example.org/s/TOKEN]

The short form says the same thing:

    [files nextcloud://cloud.example.org/TOKEN]

Options follow the link: a number limits how many entries are shown, `sort:name` is the default,
`sort:date` puts the newest first, `sort:size` the biggest:

    [files nextcloud://cloud.example.org/TOKEN 10 sort:date]

Folders inside the shared folder become sections that fold open, two levels deep by default, and
their name is the summary. Below the list there is a link that opens the folder in Nextcloud.

File names are shown without the extension, underscores as spaces, first letter capital, the real
name stays in the tooltip. The type and the size are their own columns.

**Files are served by your own web server**, not linked into the cloud: the extension fetches a file
once, keeps it in `media/downloads` under its own name and the beginning of its hash, and sends it
with the type it has. A file is kept once, however many pages link to it, because the name of the
copy follows the file in the cloud and not the page that asks for it. A PDF or an image opens in
the browser as usual, everything else is offered as a download. That also means the cloud is not
asked again for every visitor.

## How to link one file

Share the file itself in Nextcloud, "Copy link", and write an ordinary link with it:

    [Packing list](https://cloud.example.org/s/TOKEN)

A link to a file of a shared cloud is turned into a link to this server, so the file is cached and
sent the same way as one from a list. `CloudfilesUrl` says which cloud that is, `CloudfilesServer`
names further ones, separated by commas. A link to a shared folder stays what it is, and so does
every other link.

## How to use a picture

A picture is not sent by this server, it is kept as a picture of this website: the extension
fetches it once into `media/images/cloud`, under its own name and the beginning of its hash, and
what stands in the page from then on is that file. So a share link can go wherever a picture of
this website goes:

    Banner: https://cloud.example.org/s/TOKEN
    Image: https://cloud.example.org/s/TOKEN?path=%2FLager%2Ffoto.jpg

    ![A picture](https://cloud.example.org/s/TOKEN)

`CloudfilesImageSettings` says which settings of a page are read that way, `Image`, `Banner` and
`Thumbnail` to begin with, and a setting may hold several links separated by commas.
`CloudfilesImageExtensions` says what counts as a picture. A picture in the content of a page is
found wherever it stands. Because the file lies with the other pictures, everything else works on
it as usual, a gallery of `cloud/` for instance, and the thumbnails that go with it.

## In the editing rail

If the [editrail extension](https://github.com/pfadfinder26/yellow-editrail) is there, every file
this extension fetched says so among the files of the website: a button opens where it comes from
in the cloud, another fetches it again, for a file that was changed there. Below them stand the
files of `CloudfilesUrl` that are not on this website yet, each with a button that fetches it, so
an editor can see what there is instead of waiting for the first visitor to ask for it. Fetching
needs somebody who is logged in, whom the edit extension knows, and the token that says the
request came from this website. Without the rail nothing of this is built, and the rail does not
know about this extension either.

The share must be readable without a password. A share with a password, or an upload-only share,
cannot be listed.

## Settings

`CloudfilesUrl` a link used when `[files]` has none  
`CloudfilesCacheTime` how long a listing is kept, in seconds, `3600`  
`CloudfilesFileCacheTime` how long a file is kept, in seconds, `86400`  
`CloudfilesDepth` how many levels of folders are read, `2`  
`CloudfilesOpenExtensions` what opens in the browser, `pdf, png, jpg, jpeg, gif, webp, txt`  
`CloudfilesFileSizeMax` biggest file this server sends itself, in bytes, `33554432`  
`CloudfilesLocation` where the files are served, `/cloudfile/`  
`CloudfilesImageDirectory` where the pictures are kept, below the pictures, `cloud/`  
`CloudfilesImageExtensions` what counts as a picture, `png, jpg, jpeg, gif, webp, svg`  
`CloudfilesImageSettings` which settings of a page hold a picture, `image, banner, thumbnail`  
`CloudfilesLabelOpen`, `CloudfilesLabelEmpty` the words on the page

The listings are kept in `system/cache/cloudfiles-*.cache`, the notes about a single file in
`system/cache/cloudfiles-file-*.meta`: both carry the token of the share, so they stay out of
the media directory. Only the files themselves go to `media/downloads`. A folder that cannot be
reached falls back to the last copy.

**Data protection:** the folder and the files are read by your web server, your visitors never talk
to the cloud.

**Trust and safety:** the link in a page tells the web server what to fetch, so only people you
trust with the server should be able to edit pages. Files are sent with the type of their extension,
never with the one the cloud claims, and only a short list of types is shown in the browser, so
nobody can publish a script through the shared folder. Files above `CloudfilesFileSizeMax` are
linked to the cloud instead of served.

Do you have questions? [Get help](https://datenstrom.se/yellow/help/).
