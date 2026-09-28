# Cloudfiles 0.2.2

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

A single file of the shared folder, linked in a sentence, is cached the same way:

    [cloudfile Packliste_Kurzlager.pdf Packing list]

The first argument is the link, the rest is the text, the name of the file without its extension
when there is no text. Share the file itself in Nextcloud, "Copy link", and paste that link:

    [cloudfile https://cloud.example.org/s/TOKEN Packing list]

A file that lies in the shared folder of `CloudfilesUrl` can be named by its path instead, and a
folder share plus a path says the same in one line:

    [cloudfile Forms/Packliste.pdf Packing list]
    [cloudfile nextcloud://cloud.example.org/TOKEN/Forms/Packliste.pdf Packing list]

A link of the file itself asks the cloud once for its name, that answer is kept like a listing. The
file is fetched when somebody asks for it, and a file that is shared twice is kept once per share.

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

The listings are kept in `system/extensions/cloudfiles-*.cache`, the notes about a single file in
`system/extensions/cloudfiles-file-*.meta`: both carry the token of the share, so they stay out of
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
