# Cloudfiles 0.1.1

Zeigt die Dateien eines geteilten Cloud-Ordners. Entwickelt von Liam Perlaki.

Eine Downloadseite, die niemand pflegen muss: die Dateien kommen aus einem Ordner, der in Nextcloud
geteilt ist, wer dort ein Dokument ablegt, hat es veröffentlicht. Der Webserver liest den Ordner
über WebDAV und behält eine Kopie der Liste, damit ein Seitenaufruf nicht auf die Cloud wartet.

## Wie man eine Erweiterung installiert

[ZIP-Datei herunterladen](https://github.com/pfadfinder26/yellow-cloudfiles/archive/refs/heads/main.zip) und in den Ordner `system/extensions` kopieren. [Mehr über Erweiterungen](https://github.com/annaesvensson/yellow-update).

## Wie man Dateien zeigt

Einen Ordner in Nextcloud teilen, „Link kopieren“, und in eine Seite schreiben:

    [files https://cloud.example.org/s/TOKEN]

Die Kurzform sagt dasselbe:

    [files nextcloud://cloud.example.org/TOKEN]

Optionen stehen hinter dem Link: eine Zahl begrenzt, wie viele Einträge gezeigt werden, `sort:name`
ist voreingestellt, `sort:date` stellt die neuesten nach vorne, `sort:size` die größten:

    [files nextcloud://cloud.example.org/TOKEN 10 sort:date]

Ordner im geteilten Ordner werden zu Abschnitten, die sich auf- und zuklappen lassen, zwei Ebenen
tief, der Ordnername ist die Überschrift. Unter der Liste steht ein Link, der den Ordner in
Nextcloud öffnet.

Dateinamen stehen ohne Endung da, Unterstriche als Leerzeichen, erster Buchstabe groß, der echte
Name bleibt im Tooltip. Typ und Größe stehen in eigenen Spalten.

**Die Dateien liefert der eigene Webserver aus**, sie werden nicht in die Cloud verlinkt: die
Erweiterung holt eine Datei einmal, legt sie in `system/extensions` ab und schickt sie mit ihrem
Typ. Ein PDF oder ein Bild öffnet sich im Browser wie gewohnt, alles andere wird zum Download
angeboten. So wird die Cloud auch nicht für jeden Besuch gefragt.

Der geteilte Ordner muss ohne Passwort lesbar sein. Ein Link mit Passwort oder ein reiner
Upload-Link lässt sich nicht auflisten.

## Einstellungen

`CloudfilesUrl` ein Link, der gilt, wenn `[files]` keinen hat  
`CloudfilesCacheTime` wie lange eine Liste behalten wird, in Sekunden, `3600`  
`CloudfilesFileCacheTime` wie lange eine Datei behalten wird, in Sekunden, `86400`  
`CloudfilesDepth` wie viele Ordnerebenen gelesen werden, `2`  
`CloudfilesOpenExtensions` was im Browser aufgeht, `pdf, png, jpg, jpeg, gif, webp, txt`  
`CloudfilesFileSizeMax` größte Datei, die dieser Server selbst ausliefert, in Bytes, `33554432`  
`CloudfilesLocation` wo die Dateien ausgeliefert werden, `/cloudfile/`  
`CloudfilesLabelOpen`, `CloudfilesLabelEmpty` die Wörter auf der Seite

Die Listen liegen in `system/extensions/cloudfiles-*.cache`. Ist ein Ordner nicht erreichbar, gilt
die letzte Kopie.

**Datenschutz:** den Ordner und die Dateien liest der eigene Webserver, die Besucher*innen reden
nie mit der Cloud.

**Vertrauen und Sicherheit:** der Link in einer Seite sagt dem Webserver, was er holen soll, Seiten
sollten also nur Leute bearbeiten, denen man den Server anvertraut. Dateien gehen mit dem Typ ihrer
Endung raus, nie mit dem, den die Cloud behauptet, und nur eine kurze Liste von Typen wird im
Browser gezeigt, so kann über den geteilten Ordner niemand ein Skript veröffentlichen. Dateien über
`CloudfilesFileSizeMax` werden in die Cloud verlinkt statt ausgeliefert.

Hast du Fragen? [Hier gibt's Hilfe](https://datenstrom.se/yellow/help/).
