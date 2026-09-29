# Cloudfiles 0.5.0

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
Erweiterung holt eine Datei einmal, legt sie unter ihrem Namen samt Anfang ihrer Prüfsumme in
`media/downloads` ab und schickt sie mit ihrem Typ. Eine Datei liegt einmal da, egal wie viele
Seiten auf sie verweisen, weil der Name der Kopie der Datei in der Cloud folgt und nicht der
Seite, die nach ihr fragt. Ein PDF oder ein Bild öffnet sich im Browser wie gewohnt, alles andere wird zum Download
angeboten. So wird die Cloud auch nicht für jeden Besuch gefragt.

## Eine einzelne Datei verlinken

Die Datei in Nextcloud teilen, „Link kopieren“, und einen ganz normalen Link damit schreiben:

    [Packliste für Kurzlager](https://cloud.example.org/s/TOKEN)

Ein Link auf eine Datei einer bekannten Cloud wird zu einem Link auf diesen Server, die Datei wird
also zwischengelegt und genauso ausgeliefert wie eine aus einer Liste. `CloudfilesUrl` sagt, welche
Cloud das ist, `CloudfilesServer` nennt weitere, mit Komma getrennt. Ein Link auf einen geteilten
Ordner bleibt, was er ist, und jeder andere Link auch.

Der geteilte Ordner muss ohne Passwort lesbar sein. Ein Link mit Passwort oder ein reiner
Upload-Link lässt sich nicht auflisten.

## Ein Bild verwenden

Ein Bild liefert dieser Server nicht aus, es wird zu einem Bild dieser Website: die Erweiterung
holt es einmal nach `media/images/cloud`, unter seinem Namen und dem Anfang seiner Prüfsumme, und
in der Seite steht von da an diese Datei. Ein Freigabelink darf also überall stehen, wo ein Bild
dieser Website steht:

    Banner: https://cloud.example.org/s/TOKEN
    Image: https://cloud.example.org/s/TOKEN?path=%2FLager%2Ffoto.jpg

    ![Ein Bild](https://cloud.example.org/s/TOKEN)

`CloudfilesImageSettings` sagt, welche Einstellungen einer Seite so gelesen werden, zunächst
`Image`, `Banner` und `Thumbnail`, und eine Einstellung darf mehrere Links mit Komma getrennt
halten. `CloudfilesImageExtensions` sagt, was als Bild gilt. Ein Bild im Text einer Seite wird
gefunden, wo immer es steht. Weil die Datei bei den anderen Bildern liegt, funktioniert alles
weitere wie gewohnt, eine Galerie über `cloud/` zum Beispiel, samt der Vorschaubilder dazu.

## In der Bearbeitungsleiste

Gibt es die [Editrail-Erweiterung](https://github.com/pfadfinder26/yellow-editrail), sagt jede
Datei, die diese Erweiterung geholt hat, das bei den Dateien der Website: ein Knopf öffnet, woher
sie kommt, ein zweiter holt sie erneut, wenn sie sich in der Cloud geändert hat. Darunter stehen
die Dateien aus `CloudfilesUrl`, die noch nicht auf dieser Website sind, jede mit einem Knopf, der
sie holt, damit man sieht, was es gibt, statt auf den ersten Besuch zu warten. Holen darf nur, wer
angemeldet ist, wen also die Edit-Erweiterung kennt, und die Anfrage muss den Token tragen, der
sagt, dass sie von dieser Website kam. Ohne die Leiste entsteht nichts davon, und die Leiste weiß
umgekehrt nichts von dieser Erweiterung.

## Einstellungen

`CloudfilesUrl` ein Link, der gilt, wenn `[files]` keinen hat  
`CloudfilesCacheTime` wie lange eine Liste behalten wird, in Sekunden, `3600`  
`CloudfilesFileCacheTime` wie lange eine Datei behalten wird, in Sekunden, `86400`  
`CloudfilesDepth` wie viele Ordnerebenen gelesen werden, `2`  
`CloudfilesOpenExtensions` was im Browser aufgeht, `pdf, png, jpg, jpeg, gif, webp, txt`  
`CloudfilesFileSizeMax` größte Datei, die dieser Server selbst ausliefert, in Bytes, `33554432`  
`CloudfilesLocation` wo die Dateien ausgeliefert werden, `/cloudfile/`  
`CloudfilesImageDirectory` wo die Bilder liegen, unterhalb der Bilder, `cloud/`  
`CloudfilesImageExtensions` was als Bild gilt, `png, jpg, jpeg, gif, webp, svg`  
`CloudfilesImageSettings` welche Einstellungen einer Seite ein Bild halten, `image, banner, thumbnail`  
`CloudfilesLabelOpen`, `CloudfilesLabelEmpty` die Wörter auf der Seite

Die Listen liegen in `system/cache/cloudfiles-*.cache`, die Notizen zu einer einzelnen Datei
in `system/cache/cloudfiles-file-*.meta`: beide enthalten den Token des Links und bleiben
deshalb aus dem Medienordner heraus. Nur die Dateien selbst liegen in `media/downloads`. Ist ein
Ordner nicht erreichbar, gilt die letzte Kopie.

**Datenschutz:** den Ordner und die Dateien liest der eigene Webserver, die Besucher*innen reden
nie mit der Cloud.

**Vertrauen und Sicherheit:** der Link in einer Seite sagt dem Webserver, was er holen soll, Seiten
sollten also nur Leute bearbeiten, denen man den Server anvertraut. Dateien gehen mit dem Typ ihrer
Endung raus, nie mit dem, den die Cloud behauptet, und nur eine kurze Liste von Typen wird im
Browser gezeigt, so kann über den geteilten Ordner niemand ein Skript veröffentlichen. Dateien über
`CloudfilesFileSizeMax` werden in die Cloud verlinkt statt ausgeliefert.

Hast du Fragen? [Hier gibt's Hilfe](https://datenstrom.se/yellow/help/).
