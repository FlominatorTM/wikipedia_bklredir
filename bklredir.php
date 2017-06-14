<?php header('Content-Type: text/html; charset=utf-8');  
while (@ob_end_flush());/* no output buffering (via http://stackoverflow.com/a/15319311/4609258)*/ ?>
<html>
 <head></head>
 <body>
 <!-- checks if an array of given articles does match certain criteria like disambig, first names, etc. -->
<?php 

//@Todo: {{Anker}}
require_once("shared_inc/wiki_functions.inc.php");

$server= $lang.".".$project.".org";

$articles = explode(';', $_REQUEST['articles']);
$articles = array_unique($articles);
$ISBNs = explode(';', $_REQUEST['isbns']);
$ISBNs = array_unique($ISBNs);

//var_dump($articles);

foreach($articles As $article)
{
	$article = urldecode($article);
	$article = str_replace("rraauuttttee", '#', $article);
	
	//rauten entfernen
	//echo $art_text;
	//echo "$article in Arbeit";
	check_target_pages($article);
}

foreach($ISBNs As $isbn)
{
    if(strlen($isbn) > 10)
    {
        check_isbn_10_and_13($isbn);
    }
}

check_notes($_REQUEST['article']);

function check_notes($article)
{
    global $server;
    if($server != "de.wikipedia.org")
    {
        echo "return";
        return;
    }
    $note_pages[] = array('Benutzer:Flominator/gelesen', $article.']]', 'gelesen');
    $note_pages[] = array('Benutzer_Diskussion:Flominator/AufGeFallen', $article.']]' , 'AufGeFallen');
    $note_pages[] = array('Benutzer_Diskussion:Flominator/Diskussionen', $article, 'Diskussionen');
    foreach($note_pages As $page)
    {
        $src_note = get_source_code($page[0]);
        if(needle_in_cached_page($page[1], $src_note))
        {
            echo make_link($page[0], $page[2]).'<br>';
        }
    }
}

function clean_isbn($isbn)
{
    return str_replace(',', '', 
                str_replace('ISBN ', '', 
                    str_replace('-', '', $isbn)));
}
function check_isbn_10_and_13($isbn)
{
    $template = check_isbn($isbn);
    if($template)
    {
        echo link_isbn($isbn) . ' gibt es als ' . $template.'<br>';
        return;
    }
    switch(strlen($isbn))
    {
        case 10:
        {
            $template = check_isbn(isbn10_to_13($isbn));
            break;
        }
        case 13:
        {
            $template = check_isbn(ISBN13toISBN10($isbn));
            break;
        }
        default: return;
    }
    if($template)
    {
       echo link_isbn($isbn) . ' gibt es als ' . $template.'<br>';
    }
}

function link_isbn($isbn)
{
    global $server;
    return '<a href="https://'.$server.'/wiki/Special:Booksources/' . clean_isbn($isbn) . '">'.$isbn.'</a>';
 }
function check_isbn($isbn)
{
    $isbn = clean_isbn($isbn);
    $template = 'Vorlage:BibISBN/' . $isbn;
    $src = get_source_code($template);
    if(strlen($src) > 0)
    {
        return '{{BibISBN|'.$isbn.'}}';
    }
    return false;
}

function check_target_pages($article)
{
	$begin_anchor = strpos($article, '#');
	//echo $article." <br>";
	
	//echo "Anker bei $begin_anchor in $article gefunden";
	if($begin_anchor)
	{
		$anchor = substr($article, $begin_anchor+1);
		$article = substr($article, 0, $begin_anchor);
	}
	
    $art_text = get_source_code($article);
	$is_redir = needle_in_cached_page("#redirect", $art_text);
	if(!$is_redir)
	{
		$is_redir = needle_in_cached_page("#weiterleitung", $art_text);
	}

	if($is_redir)
	{
		$redir_target = extract_link($art_text);
		echo make_link($article) ." ist ein Redirect zu [[" . $redir_target. "|]]<br>\n";
		echo "<i>";
		check_target_pages($redir_target);
		echo "</i>";
	}
	else
	{
		$is_wrong_spelled = needle_in_cached_page("{{Falschschreibung", $art_text);
		
		if($is_wrong_spelled)
		{
			echo make_link($article) ." ist eine Falschschreibung von [[";
			echo extract_first_parameter($art_text) ."]]<br>";
		}
		else
		{
			if((!is_bkl2($art_text)) && (is_bkl1($art_text)))
			{
				echo make_link($article) ." ist eine BKL<br>";
			}
			else
			{
				if($begin_anchor)
				{
					$headline = '='. decode_anchor($anchor).'=';
					$hl_anchor = needle_in_cached_page($headline, str_replace(' ', '', $art_text));
					$cd_anchor = needle_in_cached_page("{{anker|". $anchor, $art_text);
					
					if(!$hl_anchor and !$cd_anchor)
					{
						echo make_link($article)." hat keinen Abschnitt ";
						echo "und keinen Anker ";
						echo $anchor.'<br>';
					}
				}
				else
				{
					if(is_name($art_text))
					{
						echo make_link($article)." ist eine Namensseite<br>";
					}
					
					if(($article=="Weltausstellung") 
					|| ($article=="Glyptothek") 
					|| ($article=="Postbus") 
					|| ($article=="Dollar") 
					|| ($article=="Bundesgartenschau") 
					|| ($article=="Landesregierung") 
					|| ($article=="Landesregierung (Deutschland)") 
					|| ($article=="Einkommensteuer") 
					|| ($article=="Erbschaftsteuer") 
					|| ($article=="Liste von Hochschulen für Bildende Kunst") 
					|| ($article=="Olympische Spiele") 
					|| ($article=="Musikhochschule") 
					|| ($article=="Verjährung") 
					)
					{
						echo make_link($article)." könnte präziser sein<br>";
					}
				}
			}
		}
	
	}
}

function get_source_code($article)
{
	$articleenc = name_in_url($article);
	global $server;
	$url = "https://".$server."/w/index.php?title=".$articleenc."&action=raw";
	//echo $url.'<br>';
	
	//echo "<br><br>Suche nach $needle in $article";
	if(!$article_text = file_get_contents($url))
	{
		//echo "retrieving $url didn't work";
		//var_dump($http_response_header[0]);
		//die("klappt nicht");
	}
	return $article_text;
}

function needle_in_cached_page($needle, $articletext)
{
	//echo "suche $needle in <small>$articletext</small>";
	if(stristr(strtolower($articletext), strtolower($needle)))
	{
		return true;
	}
	else
	{
		return false;
	}
}

function make_link($article, $alias="")
{
	global $server;
    $linkAlias = $article;
    if($alias != "")
    {
        $linkAlias = $alias;
    }
	return "<a href=\"https://$server/wiki/$article\" target=\"_blank\">$linkAlias</a>";
}

function extract_link($haystack)
{
	$link_begin = strpos($haystack, "[[") + 2;
	$link_end = strpos($haystack, "]]");
	$link = substr($haystack, $link_begin, $link_end-$link_begin);
	return str_replace('_', ' ', $link);
}

function is_bkl2($art_text)
{
	if((needle_in_cached_page("rungshinweis}}", $art_text)) OR (needle_in_cached_page("rungshinweis|", $art_text)) OR (needle_in_cached_page("{{Dieser Artikel|", $art_text)) OR (needle_in_cached_page("Schweizer Kanton|", $art_text)))
	{
		return true;
	}
	else
	{
		return false;
	}
}

function is_bkl1($art_text)
{
	//echo "durchsuche <small>$art_text</small>";
	if(needle_in_cached_page("{{Begriffskl", $art_text)==true)
	{
		return true;
	}
	else
	{
		return needle_in_cached_page("{{BKL}}", $art_text);
	}
}

function extract_first_parameter($haystack)
{
	$link_begin = strpos($haystack, "|") + 1;
	$link_end = strpos($haystack, "}}");
	$link = substr($haystack, $link_begin, $link_end-$link_begin);
	return str_replace('_', ' ', $link);
}

function decode_anchor($anchor)
{
	$anchor = str_replace('.', '%', $anchor);
	$anchor = urldecode($anchor);
	$anchor = str_replace(' ', '', $anchor);
	$anchor = str_replace('_', '', $anchor);
	return $anchor;
}
function is_name($art_text)
{
	if(needle_in_cached_page("[[Kategorie:Familienname]]", $art_text))
	{
		return true;
	}
	else
	{
		if(needle_in_cached_page("[[Kategorie:Weiblicher Vorname]]", $art_text))
		{
			return true;
		}
		else
		{
			if(needle_in_cached_page("nnlicher Vorname]]", $art_text))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
	}
	
}

//via http://xorlogic.blogspot.de/2007/04/converting-isbn13-to-isbn10-in-php.html 
function ISBN13toISBN10($isbn) {
    if (preg_match('/^\d{3}(\d{9})\d$/', $isbn, $m)) {
        $sequence = $m[1];
        $sum = 0;
        $mul = 10;
        for ($i = 0; $i < 9; $i++) {
            $sum = $sum + ($mul * (int) $sequence{$i});
            $mul--;
        }
        $mod = 11 - ($sum%11);
        if ($mod == 10) {
            $mod = "X";
        }
        else if ($mod == 11) {
            $mod = 0;
        }
        $isbn = $sequence.$mod;
    }
    return $isbn;
}
// via https://johnveldboom.com/posts/convert-isbn10-to-isbn13-with-php/ 
function genchksum13($isbn)
{
   $isbn = trim($isbn);
   $tb = 0;
   for ($i = 0; $i <= 12; $i++)
   {
      $tc = substr($isbn, -1, 1);
      $isbn = substr($isbn, 0, -1);
      $ta = ($tc*3);
      $tci = substr($isbn, -1, 1);
      $isbn = substr($isbn, 0, -1);
      $tb = $tb + $ta + $tci;
   }
   
   $tg = ($tb / 10);
   $tint = intval($tg);
   if ($tint == $tg) { return 0; }
   $ts = substr($tg, -1, 1);
   $tsum = (10 - $ts);
   return $tsum;
}
// via https://johnveldboom.com/posts/convert-isbn10-to-isbn13-with-php/ 
function isbn10_to_13($isbn)
{
   $isbn = trim($isbn);
   if(strlen($isbn) == 12){ // if number is UPC just add zero
      $isbn13 = '0'.$isbn;}
   else
   {
      $isbn2 = substr("978" . trim($isbn), 0, -1);
      $sum13 = genchksum13($isbn2);
      $isbn13 = "$isbn2$sum13";
   }
   return ($isbn13);
}
?>
</body>
</html>