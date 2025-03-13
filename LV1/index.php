<?php

require_once "DiplomskiRadovi.php";

//instanca klase DiplomskiRadovi
$radovi = new DiplomskiRadovi();

// metoda 'save' dohvaća podatke o diplomskim radovima i sprema ih u bazu
$radovi->save();

// metoda 'read' čita podatke o diplomskim radovima iz baze i pohranjuje ih u varijablu $data
$data = $radovi->read();

// Ispisujemo sadržaj varijable $data da vidimo sve spremljene radove
print_r($data);

?>
