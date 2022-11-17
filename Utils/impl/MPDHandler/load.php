<?php

if (!$this->url) {
    return;
}

///\Todo: Check if this works with http basic auth

$this->mpd = file_get_contents($this->url);
if (!$this->mpd) {
    return;
}

$simpleXML = simplexml_load_string($this->mpd);
if (!$simpleXML) {
    return;
}

$domSxe = dom_import_simplexml($simpleXML);
if (!$domSxe) {
    return;
}

$dom = new \DOMDocument('1.0');
$domSxe = $dom->importNode($domSxe, true);
if (!$domSxe) {
    return;
}

$dom->appendChild($domSxe);
$main_element_nodes = $dom->getElementsByTagName('MPD');
if ($main_element_nodes->length == 0) {
    $this->dom = null;
    return;
}

$this->dom = $main_element_nodes->item(0);
