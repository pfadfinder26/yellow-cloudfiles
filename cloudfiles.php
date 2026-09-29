<?php
// Cloudfiles extension, https://github.com/pfadfinder26/yellow-cloudfiles
// Based on Datenstrom Yellow, https://datenstrom.se/yellow/

class YellowCloudfiles {
    const VERSION = "0.5.0";
    const PRIORITY = 17;    // before the edit extension, it answers every request under /edit/
    public $yellow;         // access to API
    public $requests;       // number of requests to the cloud
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("cloudfilesUrl", "");
        $this->yellow->system->setDefault("cloudfilesServer", "");
        $this->yellow->system->setDefault("cloudfilesCacheTime", "3600");
        $this->yellow->system->setDefault("cloudfilesDepth", "2");
        $this->yellow->system->setDefault("cloudfilesLocation", "/cloudfile/");
        $this->yellow->system->setDefault("cloudfilesDownloadDirectory", "downloads/");
        $this->yellow->system->setDefault("cloudfilesImageDirectory", "cloud/");
        $this->yellow->system->setDefault("cloudfilesImageExtensions", "png, jpg, jpeg, gif, webp, svg");
        $this->yellow->system->setDefault("cloudfilesImageSettings", "image, banner, thumbnail");
        $this->yellow->system->setDefault("cloudfilesFileCacheTime", "86400");
        $this->yellow->system->setDefault("cloudfilesOpenExtensions", "pdf, png, jpg, jpeg, gif, webp, txt");
        $this->yellow->system->setDefault("cloudfilesFileSizeMax", "33554432");
        $this->yellow->system->setDefault("cloudfilesLabelOpen", "Open folder");
        $this->yellow->system->setDefault("cloudfilesLabelEmpty", "No files at the moment.");
        $this->yellow->system->setDefault("cloudfilesLabelSource", "Show in the cloud");
        $this->yellow->system->setDefault("cloudfilesLabelReload", "Fetch again from the cloud");
        $this->yellow->system->setDefault("cloudfilesLabelFetch", "Fetch to this website");
        $this->yellow->system->setDefault("cloudfilesLabelCloud", "In the cloud");
    }
    
    // Return where a file is asked for again. It lies below the editing location, because the
    // cookies that say who is asking are sent there and nowhere else
    public function getReloadLocation() {
        $editLocation = $this->yellow->system->isExisting("editLocation") ?
            $this->yellow->system->get("editLocation") : "/edit/";
        return $this->yellow->system->get("coreServerBase").$editLocation."cloudfiles-reload";
    }

    // Handle request, serve a file of the shared folder from the cache of this server,
    // or fetch a picture again for somebody who may edit this website
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        if ($base.$location==$this->getReloadLocation()) {
            return $this->processRequestReload($scheme, $address, $base, $location, $fileName);
        }
        $prefix = $this->yellow->system->get("cloudfilesLocation");
        if (substru($location, 0, strlenu($prefix))!=$prefix) return 0;
        if (!preg_match("#^".preg_quote($prefix, "#")."([0-9a-f]{12})/#", $location, $matches)) return 0;
        $meta = $this->getFileMeta($matches[1]);
        if (is_null($meta)) return $this->yellow->sendStatus(404);
        $fileData = $this->getFileData($meta);
        if (is_null($fileData)) return $this->yellow->sendStatus(404);
        $opening = $this->isOpening($meta["name"]);
        return $this->yellow->sendData(200, array(
            "Content-Type" => $this->getContentType($meta["name"]),
            "Content-Disposition" => ($opening ? "inline" : "attachment").
                "; filename=\"".addcslashes($this->getFileNameSafe($meta["name"]), "\"")."\"",
            "X-Content-Type-Options" => "nosniff",
            "Cache-Control" => "max-age=".intval($this->yellow->system->get("cloudfilesFileCacheTime"))), $fileData);
    }
    
    // Fetch a file, the copy on this server is thrown away and asked for anew
    public function processRequestReload($scheme, $address, $base, $location, $fileName) {
        if ($this->yellow->toolbox->getServer("REQUEST_METHOD")!="POST") return $this->yellow->sendStatus(405);
        if (!$this->isEditor($scheme, $address, $base, $location, $fileName)) return $this->yellow->sendStatus(403);
        $meta = $this->getFileMeta(trim($this->yellow->page->getRequest("cloudfiles-fetch")));
        if (is_null($meta)) return $this->yellow->sendStatus(404);
        $fileNameCached = $this->getFileNameCached($meta);
        if (is_file($fileNameCached)) @unlink($fileNameCached);
        if (is_null($this->getFileData($meta, $fileNameCached))) return $this->yellow->sendStatus(504);
        $back = trim($this->yellow->page->getRequest("cloudfiles-back"));
        if (substru($back, 0, strlenu($base)+1)!="$base/") $back = "$base/";
        return $this->yellow->sendStatus(303, $this->yellow->lookup->normaliseUrl($scheme, $address, "", $back));
    }

    // Check if the one who asks may change this website, the edit extension knows
    public function isEditor($scheme, $address, $base, $location, $fileName) {
        if (!$this->yellow->extension->isExisting("edit")) return false;
        $edit = $this->yellow->extension->get("edit");
        if (!$edit->checkUserAuth($scheme, $address, $base, $location, $fileName)) return false;
        if (!$edit->response->isUserAccess("upload", "/")) return false;
        $tokenExpected = $this->yellow->toolbox->getCookie("yellowcsrftoken");
        $tokenReceived = $this->yellow->page->getRequest("yellowcsrftoken");
        return !is_string_empty($tokenExpected) &&
            $this->yellow->toolbox->verifyToken($tokenExpected, $tokenReceived);
    }

    // Return what is known about a file, null if this server never saw it
    public function getFileMeta($hash) {
        $fileName = $this->getCacheFileName("file-$hash", "meta");
        if (!is_file($fileName)) return null;
        $meta = @json_decode($this->yellow->toolbox->readFile($fileName), true);
        return is_array($meta) ? $meta : null;
    }
    
    // Return a file, from the cache of this server if it is fresh enough
    public function getFileData($meta, $fileName = "") {
        if (is_string_empty($fileName)) $fileName = $this->getDownloadFileName($meta);
        $cacheTime = intval($this->yellow->system->get("cloudfilesFileCacheTime"));
        if (is_file($fileName) && filemtime($fileName)+$cacheTime>time()) {
            return $this->yellow->toolbox->readFile($fileName);
        }
        $context = stream_context_create(array("http" => array("timeout" => 15, "ignore_errors" => true,
            "header" => "Authorization: Basic ".base64_encode($meta["token"].":")."\r\n")));
        $url = $meta["server"]."/public.php/webdav".$this->getPathEncoded($meta["path"]);
        $sizeMax = intval($this->yellow->system->get("cloudfilesFileSizeMax"));
        $fileData = @file_get_contents($url, false, $context, 0, $sizeMax);
        if ($fileData===false || is_string_empty($fileData)) {
            return is_file($fileName) ? $this->yellow->toolbox->readFile($fileName) : null;
        }
        $this->yellow->toolbox->writeFile($fileName, $fileData, true);
        return $fileData;
    }
    
    // Remember where a file comes from, so it can be served later
    public function setFileMeta($hash, $server, $token, $path, $name) {
        $fileName = $this->getCacheFileName("file-$hash", "meta");
        $meta = array("hash" => $hash, "server" => $server, "token" => $token,
            "path" => $path, "name" => $name);
        $fileData = json_encode($meta);
        if (!is_file($fileName) || $this->yellow->toolbox->readFile($fileName)!=$fileData) {
            $this->yellow->toolbox->writeFile($fileName, $fileData, true);
        }
        return $meta;
    }
    
    // Return the name of a cache file, the notes about a file and the listings
    // hold the token of the share, so they stay out of the media directory
    public function getCacheFileName($name, $extension) {
        return $this->yellow->system->get("coreCacheDirectory")."cloudfiles-$name.$extension";
    }

    // Return the name of the cached file itself, in the downloads of this website
    public function getDownloadFileName($meta) {
        $name = $this->getFileNameSafe($meta["name"]);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $name = pathinfo($name, PATHINFO_FILENAME)."-".substru($meta["hash"], 0, 10);
        if (!is_string_empty($extension)) $name .= ".".$extension;
        return $this->yellow->system->get("coreMediaDirectory").
            $this->yellow->system->get("cloudfilesDownloadDirectory").$name;
    }
    
    // Return the type of a file, from its extension, never from the cloud
    public function getContentType($name) {
        $contentTypes = array("pdf" => "application/pdf", "png" => "image/png", "jpg" => "image/jpeg",
            "jpeg" => "image/jpeg", "gif" => "image/gif", "webp" => "image/webp", "txt" => "text/plain; charset=utf-8");
        $extension = strtoloweru(pathinfo($name, PATHINFO_EXTENSION));
        if (!$this->isOpening($name) || !isset($contentTypes[$extension])) return "application/octet-stream";
        return $contentTypes[$extension];
    }
    
    // Return a file name that is safe in a header
    public function getFileNameSafe($name) {
        return preg_replace("/[^\w \-\.\(\)äöüÄÖÜß]/u", "", $name);
    }
    
    // Check if a file is one a browser can show
    public function isOpening($name) {
        $extensions = preg_split("/\s*,\s*/", strtoloweru($this->yellow->system->get("cloudfilesOpenExtensions")));
        return in_array(strtoloweru(pathinfo($name, PATHINFO_EXTENSION)), $extensions);
    }
    
    // Handle page meta data, a picture of the cloud stands where a picture of this website stands
    public function onParseMetaData($page) {
        foreach (preg_split("/\s*,\s*/", $this->yellow->system->get("cloudfilesImageSettings")) as $key) {
            $value = $page->get($key);
            if (is_string_empty($value) || strposu($value, "/s/")===false) continue;
            $locations = array();
            foreach (preg_split("/\s*,\s*/", $value) as $url) {
                $location = $this->getImageLocation($url);
                $locations[] = is_string_empty($location) ? $url : $location;
            }
            $page->set($key, implode(", ", $locations));
        }
    }

    // Return the picture of a shared file, in the media directory of this website, empty for
    // anything that is not a picture of a cloud this website knows. The file is fetched once,
    // where it lands it is an ordinary picture of this website, for a banner or a gallery
    public function getImageLocation($url) {
        $url = trim($url);
        if (strposu($url, "/s/")===false) return "";
        $fileNameMeta = $this->getCacheFileName("image-".substru(md5($url), 0, 12), "meta");
        $meta = $this->getImageMetaFile($fileNameMeta);
        if (!is_null($meta) && is_file($meta["location"])) return $meta["location"];
        list($server, $token, $path) = $this->getShareFile($url);
        if (is_string_empty($token) || !in_array($server, $this->getServers())) return "";
        $fileId = "";
        if (is_string_empty($path)) {
            list($name, $fileId) = $this->getSharedFile($server, $token);
        } else {
            $name = basename($path);
        }
        if (is_string_empty($name) || !$this->isImage($name)) return "";
        $hash = $this->getFileHash($server, $token, $path, $fileId);
        $meta = $this->setFileMeta($hash, $server, $token, $path, $name);
        $location = $this->getImageFileName($meta);
        if (!is_file($location) && is_null($this->getFileData($meta, $location))) return "";
        $this->yellow->toolbox->writeFile($fileNameMeta,
            json_encode(array("url" => $url, "location" => $location)), true);
        return $location;
    }

    // Return the notes about a picture, null for a file this website read anywhere else
    public function getImageMetaFile($fileName) {
        if (!is_file($fileName)) return null;
        $meta = @json_decode($this->yellow->toolbox->readFile($fileName), true);
        return is_array($meta) && isset($meta["location"]) ? $meta : null;
    }

    // Return the files of the cloud that are not on this website yet, so an editor can see
    // what there is and fetch what is wanted, without waiting for the first visitor
    public function onEditrailMedia() {
        $url = $this->yellow->system->get("cloudfilesUrl");
        if (is_string_empty($url)) return "";
        list($server, $token) = $this->getShare($url);
        if (is_string_empty($token)) return "";
        $this->requests = 0;
        $files = $this->getFolder($server, $token, "", intval($this->yellow->system->get("cloudfilesDepth")), array());
        if (is_null($files)) return "";
        $output = "";
        foreach ($this->getFilesFlat($files) as $file) {
            $meta = $this->getFileMetaUrl($file["url"]);
            if (is_null($meta) || is_file($this->getFileNameCached($meta))) continue;
            $output .= $this->getMediaFileHtml($meta, "$server/s/$token?path=".rawurlencode(dirname($file["path"])));
        }
        if (is_string_empty($output)) return "";
        return "<span class=\"editrail-page editrail-folder\"><span class=\"editrail-title\">".
            htmlspecialchars($this->yellow->system->get("cloudfilesLabelCloud"))."</span></span>\n".
            "<ul>\n".$output."</ul>\n";
    }

    // Return the files of a listing, the ones inside a folder as well
    public function getFilesFlat($files) {
        $flat = array();
        foreach ($files as $file) {
            if ($file["directory"]) {
                $flat = array_merge($flat, $this->getFilesFlat($file["children"]));
            } else {
                $flat[] = $file;
            }
        }
        return $flat;
    }

    // Return what is known about the file a link of this server stands for
    public function getFileMetaUrl($url) {
        $prefix = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("cloudfilesLocation");
        if (substru($url, 0, strlenu($prefix))!=$prefix) return null;
        list($hash) = explode("/", substru($url, strlenu($prefix)));
        return $this->getFileMeta($hash);
    }

    // Return where a file of the cloud lies on this website, a picture with the pictures
    public function getFileNameCached($meta) {
        return $this->isImage($meta["name"]) ?
            $this->getImageFileName($meta) : $this->getDownloadFileName($meta);
    }

    // Return one file of the cloud that is not here yet, a row for the editing rail
    public function getMediaFileHtml($meta, $url) {
        $textFetch = htmlspecialchars($this->yellow->system->get("cloudfilesLabelFetch"));
        $textSource = htmlspecialchars($this->yellow->system->get("cloudfilesLabelSource"));
        $output = "<li class=\"editrail-file\">\n";
        $output .= "<span class=\"editrail-file-icon\" aria-hidden=\"true\"></span>";
        $output .= "<span class=\"editrail-file-name\" title=\"".htmlspecialchars($meta["path"])."\">".
            htmlspecialchars($meta["name"])."</span>";
        $output .= "<span class=\"editrail-tools\">";
        $output .= "<a class=\"editrail-tool editrail-tool-open\" href=\"".htmlspecialchars($url)."\"".
            " target=\"_blank\" rel=\"noreferrer\" title=\"".$textSource."\" aria-label=\"".$textSource."\"></a>";
        $output .= $this->getReloadFormHtml("cloudfiles-fetch", $meta["hash"], $textFetch);
        $output .= "</span>\n</li>\n";
        return $output;
    }

    // Return the form that asks this server to fetch a file, it needs nothing but a click
    public function getReloadFormHtml($key, $value, $text) {
        $token = $this->yellow->toolbox->getCookie("yellowcsrftoken");
        return "<form method=\"post\" action=\"".htmlspecialchars($this->getReloadLocation())."\">".
            "<input type=\"hidden\" name=\"".$key."\" value=\"".htmlspecialchars($value)."\" />".
            "<input type=\"hidden\" name=\"cloudfiles-back\" value=\"".
            htmlspecialchars($this->yellow->page->getBase().$this->yellow->page->location)."\" />".
            "<input type=\"hidden\" name=\"yellowcsrftoken\" value=\"".htmlspecialchars($token)."\" />".
            "<button type=\"submit\" class=\"editrail-tool editrail-tool-reload\"".
            " title=\"".$text."\" aria-label=\"".$text."\"></button></form>";
    }

    // Return what this extension has to say about a file of the editing rail: where it comes
    // from, and a button that fetches it again. A picture and a download are the same here
    public function onEditrailFile($location) {
        $meta = $this->getFileMetaLocation($location);
        if (is_null($meta)) return "";
        $textSource = htmlspecialchars($this->yellow->system->get("cloudfilesLabelSource"));
        $textReload = htmlspecialchars($this->yellow->system->get("cloudfilesLabelReload"));
        $output = "<a class=\"editrail-tool editrail-tool-open\" href=\"".
            htmlspecialchars($this->getSourceUrl($meta))."\"".
            " target=\"_blank\" rel=\"noreferrer\" title=\"".$textSource."\" aria-label=\"".$textSource."\"></a>";
        $output .= $this->getReloadFormHtml("cloudfiles-fetch", $meta["hash"], $textReload);
        return $output;
    }

    // Return what is known about a file of this website, null for a file of this website itself.
    // The name carries the beginning of the hash, which is what the notes are filed under
    public function getFileMetaLocation($location) {
        $base = $this->yellow->system->get("coreServerBase");
        if (substru($location, 0, strlenu($base))==$base) $location = substru($location, strlenu($base));
        $location = ltrim($location, "/");
        if (!preg_match("/-([0-9a-f]{10})(\.[^.]+)?$/", $location, $matches)) return null;
        foreach (glob($this->getCacheFileName("file-".$matches[1]."*", "meta")) as $fileName) {
            $meta = @json_decode($this->yellow->toolbox->readFile($fileName), true);
            if (!is_array($meta) || !isset($meta["hash"])) continue;
            if ($this->getFileNameCached($meta)==$location) return $meta;
        }
        return null;
    }

    // Return the link into the cloud a file comes from, the folder it lies in for a folder share
    public function getSourceUrl($meta) {
        $url = $meta["server"]."/s/".$meta["token"];
        $path = dirname($meta["path"]);
        if (!is_string_empty($path) && $path!="/" && $path!=".") $url .= "?path=".rawurlencode($path);
        return $url;
    }

    // Return where a picture of the cloud is kept, next to the pictures of this website
    public function getImageFileName($meta) {
        $name = $this->getFileNameSafe($meta["name"]);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $name = pathinfo($name, PATHINFO_FILENAME)."-".substru($meta["hash"], 0, 10);
        if (!is_string_empty($extension)) $name .= ".".$extension;
        return ltrim($this->yellow->system->get("coreImageLocation"), "/").
            $this->yellow->system->get("cloudfilesImageDirectory").$name;
    }

    // Check if a file is a picture, a picture is kept as a file and not served by this server
    public function isImage($name) {
        $extensions = preg_split("/\s*,\s*/", strtoloweru($this->yellow->system->get("cloudfilesImageExtensions")));
        return in_array(strtoloweru(pathinfo($name, PATHINFO_EXTENSION)), $extensions);
    }

    // Handle page content in HTML format, a link to a shared file is served by this server,
    // a picture of the cloud is fetched and stands here as a picture of this website
    public function onParseContentHtml($page, $text) {
        $servers = $this->getServers();
        if (is_array_empty($servers) || strposu($text, "/s/")===false) return null;
        $text = preg_replace_callback("/(<img[^>]*\ssrc=\")([^\"]+)(\")/i", function ($matches) {
            $location = $this->getImageLocation(html_entity_decode($matches[2], ENT_QUOTES, "UTF-8"));
            return is_string_empty($location) ? $matches[0] :
                $matches[1].htmlspecialchars($this->yellow->page->getBase()."/".$location).$matches[3];
        }, $text);
        return preg_replace_callback("/(<a[^>]*\shref=\")([^\"]+)(\")/i", function ($matches) use ($servers) {
            $url = $this->getFileUrlShared(html_entity_decode($matches[2], ENT_QUOTES, "UTF-8"), $servers);
            return is_string_empty($url) ? $matches[0] : $matches[1].htmlspecialchars($url).$matches[3];
        }, $text);
    }

    // Return the servers whose shares are served by this server
    public function getServers() {
        $servers = array();
        foreach (array($this->yellow->system->get("cloudfilesUrl"),
            $this->yellow->system->get("cloudfilesServer")) as $setting) {
            foreach (preg_split("/\s*,\s*/", $setting) as $url) {
                list($server) = $this->getShare($url);
                if (is_string_empty($server) && preg_match("#^(https?://[^/]+)#", trim($url), $matches)) {
                    $server = $matches[1];
                }
                if (!is_string_empty($server)) $servers[] = $server;
            }
        }
        return array_unique($servers);
    }

    // Return the link of this server for a shared file, empty for anything else
    public function getFileUrlShared($url, $servers) {
        list($server, $token) = $this->getShare($url);
        if (is_string_empty($token) || !in_array($server, $servers)) return "";
        list($dummy, $dummy, $path) = $this->getShareFile($url);
        $fileId = "";
        if (is_string_empty($path)) {
            list($name, $fileId) = $this->getSharedFile($server, $token);
        } else {
            $name = basename($path);
        }
        if (is_string_empty($name)) return "";
        return $this->getFileUrl($server, $token, $name, false, $path, 0, $fileId);
    }

    // Handle page content element
    public function onParseContentElement($page, $name, $text, $attributes, $type) {
        $output = null;
        if ($name=="files" && ($type=="block" || $type=="inline")) {
            $arguments = $this->yellow->toolbox->getTextArguments($text);
            list($url) = $arguments;
            $options = array_values(array_filter(array_slice($arguments, 1)));
            if (is_string_empty($url)) $url = $this->yellow->system->get("cloudfilesUrl");
            if (is_string_empty($url)) return $this->getErrorHtml("Please add a folder link!");
            list($server, $token) = $this->getShare($url);
            if (is_string_empty($token)) return $this->getErrorHtml("Can't understand folder link '$url'!");
            $this->requests = 0;
            $files = $this->getFolder($server, $token, "", intval($this->yellow->system->get("cloudfilesDepth")), $options);
            if (is_null($files)) return $this->getErrorHtml("Can't read folder '$url'!");
            $page->setLastModified(time());
            $output = $this->getFilesHtml($files, "$server/s/$token");
        }
        return $output;
    }

    // Return name and file id of a file that is shared by itself, empty for a shared folder
    public function getSharedFile($server, $token) {
        $fileData = $this->getFolderData($server, $token, "");
        if (is_null($fileData)) return array("", "");
        $xml = @simplexml_load_string($fileData, "SimpleXMLElement", LIBXML_NOCDATA, "DAV:");
        if ($xml===false) return array("", "");
        foreach ($xml->response as $response) {
            $href = rawurldecode(trim((string)$response->href));
            if (!$this->isFolderItself($href, "")) continue;
            $properties = $response->propstat[0]->prop;
            if (isset($properties->resourcetype->collection)) return array("", "");
            $name = trim((string)$properties->displayname);
            if (is_string_empty($name)) $name = basename(rtrim($href, "/"));
            return array($name, trim((string)$properties->children("http://owncloud.org/ns")->fileid));
        }
        return array("", "");
    }

    // Return the name a file is cached under, the same for every share of that file
    public function getFileHash($server, $token, $path, $fileId = "") {
        if (!is_string_empty($fileId)) return substru(md5("$server#$fileId"), 0, 12);
        return substru(md5("$server/$token$path"), 0, 12);
    }

    // Return server, token and path of one file, a path alone uses the folder of the website
    public function getShareFile($file) {
        list($server, $token) = $this->getShare($file);
        if (!is_string_empty($token)) {
            $path = "";
            if (preg_match("#^nextcloud://[^/]+/[^/?\#]+(/.*)$#", $file, $matches)) $path = $matches[1];
            if (preg_match("#^https?://[^/]+/(?:index\.php/)?s/[^/?\#]+(?:\?path=([^&\#]*))?#", $file, $matches)) {
                if (isset($matches[1])) $path = rawurldecode($matches[1]);
            }
            return array($server, $token, $path);
        }
        list($server, $token) = $this->getShare($this->yellow->system->get("cloudfilesUrl"));
        return array($server, $token, "/".ltrim($file, "/"));
    }
    
    // Return server and token of a Nextcloud folder share, as a short link or as the link from the app
    public function getShare($url) {
        if (preg_match("#^nextcloud://([^/]+)/([^/?\#]+)#", $url, $matches)) {
            return array("https://".$matches[1], $matches[2]);
        }
        if (preg_match("#^(https?://[^/]+)/(?:index\.php/)?s/([^/?\#]+)#", $url, $matches)) {
            return array($matches[1], $matches[2]);
        }
        return array("", "");
    }
    
    // Return the entries of a folder, with the entries of the folders inside it
    public function getFolder($server, $token, $path, $depth, $options) {
        $fileData = $this->getFolderData($server, $token, $path);
        if (is_null($fileData)) return null;
        $files = $this->getFilesSelected($this->getFiles($fileData, $server, $token, $path), $options);
        foreach ($files as $number=>$file) {
            if (!$file["directory"] || $depth<=1 || $this->requests>=20) continue;
            $children = $this->getFolder($server, $token, $file["path"], $depth-1, $options);
            if (!is_null($children)) $files[$number]["children"] = $children;
        }
        return $files;
    }
    
    // Return folder listing, from cache if it is fresh enough
    public function getFolderData($server, $token, $path = "") {
        $fileName = $this->yellow->system->get("coreCacheDirectory")."cloudfiles-".
            substru(md5("$server/$token/$path"), 0, 8).".cache";
        $cacheTime = intval($this->yellow->system->get("cloudfilesCacheTime"));
        if (is_file($fileName) && filemtime($fileName)+$cacheTime>time()) {
            return $this->yellow->toolbox->readFile($fileName);
        }
        $request = "<?xml version=\"1.0\"?>\n".
            "<d:propfind xmlns:d=\"DAV:\" xmlns:oc=\"http://owncloud.org/ns\"><d:prop><d:displayname/>".
            "<d:getcontentlength/><d:getcontenttype/><d:getlastmodified/><d:resourcetype/>".
            "<oc:fileid/></d:prop></d:propfind>";
        $context = stream_context_create(array("http" => array(
            "method" => "PROPFIND",
            "timeout" => 5,
            "ignore_errors" => true,
            "header" => "Authorization: Basic ".base64_encode("$token:")."\r\n".
                "Depth: 1\r\nContent-Type: application/xml\r\n",
            "content" => $request)));
        ++$this->requests;
        $fileData = @file_get_contents("$server/public.php/webdav".$this->getPathEncoded($path)."/", false, $context);
        if ($fileData===false || strposu($fileData, "<d:multistatus")===false) {
            return is_file($fileName) ? $this->yellow->toolbox->readFile($fileName) : null;
        }
        $this->yellow->toolbox->writeFile($fileName, $fileData, true);
        return $fileData;
    }
    
    // Return a path with every part encoded for a URL
    public function getPathEncoded($path) {
        $parts = array();
        foreach (explode("/", trim($path, "/")) as $part) {
            if (!is_string_empty($part)) $parts[] = rawurlencode($part);
        }
        return is_array_empty($parts) ? "" : "/".implode("/", $parts);
    }
    
    // Return the files of a folder
    public function getFiles($fileData, $server, $token, $path = "") {
        $files = array();
        $xml = @simplexml_load_string($fileData, "SimpleXMLElement", LIBXML_NOCDATA, "DAV:");
        if ($xml===false) return $files;
        foreach ($xml->response as $response) {
            $href = rawurldecode(trim((string)$response->href));
            $properties = $response->propstat[0]->prop;
            $name = trim((string)$properties->displayname);
            if (is_string_empty($name)) $name = basename(rtrim($href, "/"));
            $directory = isset($properties->resourcetype->collection);
            if (is_string_empty($name) || $this->isFolderItself($href, $path)) continue;
            $fileId = trim((string)$properties->children("http://owncloud.org/ns")->fileid);
            $files[] = array(
                "name" => $name,
                "title" => $this->getTitle($name, $directory),
                "path" => "$path/$name",
                "children" => array(),
                "directory" => $directory,
                "size" => intval((string)$properties->getcontentlength),
                "modified" => strtotime((string)$properties->getlastmodified),
                "url" => $this->getFileUrl($server, $token, $name, $directory, "$path/$name",
                    intval((string)$properties->getcontentlength), $fileId));
        }
        return $files;
    }
    
    // Return the link of an entry, a file is served by this server so the browser can show it
    public function getFileUrl($server, $token, $name, $directory, $path, $size, $fileId = "") {
        if ($directory) return "$server/s/$token?path=".rawurlencode($path);
        if ($size>intval($this->yellow->system->get("cloudfilesFileSizeMax"))) {
            return "$server/s/$token/download?path=%2F&files=".rawurlencode($name);
        }
        $hash = $this->getFileHash($server, $token, $path, $fileId);
        $this->setFileMeta($hash, $server, $token, $path, $name);
        return $this->yellow->system->get("coreServerBase").$this->yellow->system->get("cloudfilesLocation").
            "$hash/".rawurlencode($name);
    }
    
    // Return the name of an entry, without the extension and easier to read
    public function getTitle($name, $directory) {
        $title = $directory ? $name : pathinfo($name, PATHINFO_FILENAME);
        $title = trim(preg_replace("/\s+/", " ", str_replace("_", " ", $title)));
        return strtoupperu(substru($title, 0, 1)).substru($title, 1);
    }
    
    // Check if an entry is the folder that was asked for, not something in it
    public function isFolderItself($href, $path) {
        $location = rtrim(preg_replace("#/+#", "/", $href), "/");
        $folder = rtrim(preg_replace("#/+#", "/", "/public.php/webdav".$path), "/");
        return substru($location, -strlenu($folder)-1)=="/".ltrim($folder, "/") || $location==$folder;
    }
    
    // Return the files to show, sorted and limited
    public function getFilesSelected($files, $options) {
        $sort = "name";
        $limit = 0;
        foreach ($options as $option) {
            list($key, $value) = $this->yellow->toolbox->getTextList($option, ":", 2);
            if ($key=="sort" && !is_string_empty($value)) $sort = $value;
            if (is_numeric($option)) $limit = intval($option);
        }
        usort($files, function ($a, $b) use ($sort) {
            if ($a["directory"]!=$b["directory"]) return $a["directory"] ? -1 : 1;
            if ($sort=="date") return $b["modified"]<=>$a["modified"];
            if ($sort=="size") return $b["size"]<=>$a["size"];
            return strnatcasecmp($a["title"], $b["title"]);
        });
        return $limit>0 ? array_slice($files, 0, $limit) : $files;
    }
    
    // Return files HTML
    public function getFilesHtml($files, $link) {
        $output = "<div class=\"files\">\n";
        if (is_array_empty($files)) {
            $output .= "<p class=\"files-empty\">".htmlspecialchars($this->yellow->system->get("cloudfilesLabelEmpty"))."</p>\n";
        } else {
            $output .= $this->getEntriesHtml($files);
        }
        $output .= "<p class=\"files-link\"><a href=\"".htmlspecialchars($link)."\">".
            htmlspecialchars($this->yellow->system->get("cloudfilesLabelOpen"))."</a></p>\n";
        $output .= "</div>\n";
        return $output;
    }
    
    // Return the entries of a folder, folders as sections that fold open
    public function getEntriesHtml($files) {
        $output = "";
        $list = array_filter($files, function ($file) { return !$file["directory"]; });
        $folders = array_filter($files, function ($file) { return $file["directory"]; });
        if (!is_array_empty($list)) {
            $output .= "<ul>\n";
            foreach ($list as $file) $output .= $this->getEntryHtml($file);
            $output .= "</ul>\n";
        }
        foreach ($folders as $folder) {
            if (is_array_empty($folder["children"])) {
                $output .= "<ul>\n".$this->getEntryHtml($folder)."</ul>\n";
            } else {
                $output .= "<details class=\"files-folder\" open>\n";
                $output .= "<summary>".htmlspecialchars($folder["title"])."</summary>\n";
                $output .= $this->getEntriesHtml($folder["children"]);
                $output .= "</details>\n";
            }
        }
        return $output;
    }
    
    // Return one entry
    public function getEntryHtml($file) {
        $output = "<li".($file["directory"] ? " class=\"files-directory\"" : "").">\n";
        $output .= "<a class=\"files-name\" href=\"".htmlspecialchars($file["url"])."\"";
        $output .= " title=\"".htmlspecialchars($file["name"])."\">".htmlspecialchars($file["title"])."</a>\n";
        $output .= "<span class=\"files-type\">".htmlspecialchars($this->getTypeText($file))."</span>\n";
        $output .= "<span class=\"files-size\">".htmlspecialchars($this->getSizeText($file))."</span>\n";
        if ($file["modified"]) {
            $output .= "<time class=\"files-date\" datetime=\"".htmlspecialchars(date("c", $file["modified"]))."\">".
                htmlspecialchars($this->yellow->language->getDateFormatted($file["modified"],
                    $this->yellow->language->getText("coreDateFormatMedium")))."</time>\n";
        }
        $output .= "</li>\n";
        return $output;
    }
    
    // Return the type of a file, its extension
    public function getTypeText($file) {
        if ($file["directory"]) return "";
        $extension = strtoupperu(pathinfo($file["name"], PATHINFO_EXTENSION));
        return $extension;
    }
    
    // Return the size of a file, in units people read
    public function getSizeText($file) {
        if ($file["directory"] || !$file["size"]) return "";
        $units = array("bytes", "KB", "MB", "GB");
        $size = $file["size"];
        for ($number = 0; $size>=1024 && $number<count($units)-1; ++$number) $size /= 1024;
        return ($size<10 && $number>0 ? round($size, 1) : round($size))." ".$units[$number];
    }
    
    // Return error message for authors
    public function getErrorHtml($text) {
        return "<p class=\"error\">Cloudfiles: ".htmlspecialchars($text)."</p>\n";
    }
}
