# Cloudfiles 0.1.1

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
once, keeps it in `system/extensions`, and sends it with the type it has. A PDF or an image opens in
the browser as usual, everything else is offered as a download. That also means the cloud is not
asked again for every visitor.

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
`CloudfilesLabelOpen`, `CloudfilesLabelEmpty` the words on the page

The listings are kept in `system/extensions/cloudfiles-*.cache`. A folder that cannot be reached
falls back to the last copy.

**Data protection:** the folder and the files are read by your web server, your visitors never talk
to the cloud.

**Trust and safety:** the link in a page tells the web server what to fetch, so only people you
trust with the server should be able to edit pages. Files are sent with the type of their extension,
never with the one the cloud claims, and only a short list of types is shown in the browser, so
nobody can publish a script through the shared folder. Files above `CloudfilesFileSizeMax` are
linked to the cloud instead of served.

Do you have questions? [Get help](https://datenstrom.se/yellow/help/).
