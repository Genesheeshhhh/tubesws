<?php

$xml = new DOMDocument();

if ($xml->load(__DIR__ . '/mobil-bekas-medan-owl.xml')) {
    echo "RDF/XML VALID";
} else {
    echo "RDF/XML INVALID";
}

