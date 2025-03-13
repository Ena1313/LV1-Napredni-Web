<?php

require_once "iRadovi.php";

class DiplomskiRadovi implements iRadovi {
    private $db;  // instanca SQLite3 bp

    public function __construct() {
        // otvaranje/stvaranje baze
        $this->db = new SQLite3('radovi.db');
        // kreiramo tablicu za pohranu podataka o diplomskim radovima
        $this->db->exec("CREATE TABLE IF NOT EXISTS diplomski_radovi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            naziv_rada TEXT,
            tekst_rada TEXT,
            link_rada TEXT,
            oib_tvrtke TEXT
        )");
    }

    // funkcija za dohvaćanje HTML sadržaja
    private function fetch_html($url) {
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url);  // postavljamo URL za dohvat
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // postavljamo da želimo odgovor kao string
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

        $html = curl_exec($ch); 
        curl_close($ch);  // zatvaramo cURL sesiju
        return $html; 
    }

    // funkcija za izdvajanje potrebnih podataka iz HTML sadržaja
    private function extract_data($html) {
        $dom = new DOMDocument();  
        @$dom->loadHTML($html); 
        $xpath = new DOMXPath($dom);  // kreiramo XPath objekt za selektiranje elemenata

        $data = [];
        $entries = $xpath->query("//article");  // tražimo sve članke na stranici

        // prolazimo kroz sve pronađene članke
        foreach ($entries as $entry) {
            
            $titleNode = $xpath->query(".//h2/a", $entry);
            $linkNode = $xpath->query(".//h2/a/@href", $entry);
            $imageNode = $xpath->query(".//img/@src", $entry);

            // ako su podaci dostupni, čitamo ih; inače postavljamo zadane vrijednosti
            $title = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : "Nepoznato";
            $link = $linkNode->length > 0 ? trim($linkNode->item(0)->nodeValue) : "Nepoznato";

            // ako je prisutna slika, tražimo OIB tvrtke iz URL-a slike
            $oib = "Nepoznato";
            if ($imageNode->length > 0) {
                preg_match('/(\d{9,10})/', $imageNode->item(0)->nodeValue, $matches);
                if (!empty($matches[1])) {
                    $oib = $matches[1];  
                }
            }

            $work_text = $this->fetch_work_text($link);

            // spremamo sve podatke u polje
            $data[] = [
                'naziv_rada' => $title,
                'tekst_rada' => $work_text,
                'link_rada' => $link,
                'oib_tvrtke' => $oib
            ];
        }

        return $data;
    }

    // funkcija za dohvaćanje teksta diplomskog rada
    private function fetch_work_text($url) {
        $html = $this->fetch_html($url);  
        $dom = new DOMDocument();  
        @$dom->loadHTML($html);  
        $xpath = new DOMXPath($dom); 
        $textNode = $xpath->query("//div[contains(@class, 'entry-content')]");

        return $textNode->length > 0 ? trim($textNode->item(0)->textContent) : "Nema opisa";
    }

    // funkcija za dohvaćanje i obrada podataka s više stranica
    public function create() {
        $final_data = []; 

        // petlja za dohvaćanje podataka sa stranica 2 do 6
        for ($i = 2; $i <= 6; $i++) {
            $url = "https://stup.ferit.hr/index.php/zavrsni-radovi/page/$i/";  
            echo "Dohvaćanje podataka sa: $url\n";

            $html = $this->fetch_html($url); 
            $parsed_data = $this->extract_data($html);  // parsiramo podatke

            // ako su podaci pronađeni, spajamo ih s postojećim podacima
            if (!empty($parsed_data)) {
                $final_data = array_merge($final_data, $parsed_data);
            }
        }

        return $final_data;
    }

    // spremanje podataka u bazu
    public function save() {
        $data = $this->create();

        if (empty($data)) {
            echo "Nema podataka za spremanje!\n";
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO diplomski_radovi (naziv_rada, tekst_rada, link_rada, oib_tvrtke) VALUES (:naziv_rada, :tekst_rada, :link_rada, :oib_tvrtke)");

        // iteriramo kroz sve podatke i spremamo ih u bazu
        foreach ($data as $entry) {
            $stmt->bindValue(':naziv_rada', $entry['naziv_rada'], SQLITE3_TEXT);
            $stmt->bindValue(':tekst_rada', $entry['tekst_rada'], SQLITE3_TEXT);
            $stmt->bindValue(':link_rada', $entry['link_rada'], SQLITE3_TEXT);
            $stmt->bindValue(':oib_tvrtke', $entry['oib_tvrtke'], SQLITE3_TEXT);
            $stmt->execute();
        }

        echo "Podaci su spremljeni u SQLite bazu.\n";
    }

    // funkcija za čitanje svih podataka iz baze
    public function read() {
        $results = $this->db->query("SELECT * FROM diplomski_radovi");
        $data = []; 

        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }
}

?>
