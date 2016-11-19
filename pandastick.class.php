<?php class pandastick {

  ///   CSS Minifier
  public static function miniCSS($a) {
    $b = array(
      /// Block de commentaires
      '/\/\*.*\*\//Us' => '',
      /// Clean (1ere passe)
      '/\s{2,}/s' => ' ',
      /// Zéros non nécessaires
      '/([^0-9])0\./s' => '$1.',
      /// Espaces non nécessaires
      '/\s?([,:;{}>])\s?/s' => '$1',
      /// Clean (2eme passe)
      '/\s{2,}/s' => ' '
    );not now
    return trim(preg_replace(array_keys($b),array_values($b),$a));
  }

  ///   JS Minifier
  public static function miniJS($a) {
    $b = array(
      /// Block de commentaires
      '/\/\*.*\*\//Us' => '',
      /// Commentaires linéaires
      // -
      /// Clean
      '/\t+/s' => "\n",
      '/\n{2,}/s' => "\n"
    );
    return trim(preg_replace(array_keys($b),array_values($b),$a));
  }

  ///   URL Generator
  public static function urlMagik($a) {
    $b = Transliterator::createFromRules("::Latin-ASCII; ::Lower; [^[:L:][:N:]]+ > '-';");
    $a = '-'.$b->transliterate(mb_strtolower(trim($a),'UTF-8')).'-';
    $c = array(
      '/-(des|les|sur|dans|the|que|ses|mes|leurs?)-/' => '-',
      '/-([A-z]{1,2})-/' => '-',
      '/(-{2,})/' => '-'
    );
    while ($r = preg_replace(array_keys($c),array_values($c),$a) AND $a != $r) $a = $r;
    return trim($a,'-');
  }

  ///   EXIF/IPTC/XMP image extractor
  public static function fullImageMeta($filename) {
		$mdata = array();

		/* EXIF */
		$mdata['EXIF'] = @exif_read_data($filename,'EXIF',0);
		unset($mdata['EXIF']['MakerNote']);

		/* IPTC */
		$size = getimagesize($filename, $info);
		if (isset($info['APP13'])) {
			$iptc = iptcparse($info['APP13']);
			$iptc_s = array(
				'2#005' => 'Title/Job Name',
				'2#007' => 'Edit Status',
				'2#010' => 'Urgency',
				'2#015' => 'Category',
				'2#020' => 'Supp Category',
				'2#025' => 'Keywords',
				'2#040' => 'Special Instructions',
				'2#055' => 'Date',
				'2#060' => 'Hour',
				'2#080' => 'Author',
				'2#085' => 'Author Position',
				'2#090' => 'City',
				'2#092' => 'Sublocation',
				'2#095' => 'State',
				'2#100' => 'Country Code',
				'2#101' => 'Country',
				'2#103' => 'Jod ID/Trans Ref',
				'2#105' => 'Headline',
				'2#110' => 'Credit',
				'2#115' => 'Source',
				'2#120' => 'Description',
				'2#122' => 'Description Writers',
				'2#116' => 'Copyright Notice',
				'2#221' => 'photomechanic:Prefs'
			);
			foreach($iptc as $key => $d) {
				if (isset($iptc_s[$key])) {
					if (count($d) > 1) $mdata['IPTC'][$iptc_s[$key]] = $iptc[$key];
					$mdata['IPTC'][$iptc_s[$key]] = $d[0];
				}
				else $mdata['IPTC'][$key] = $d[0];
			}
		}

		/* XMP */
    $file = fopen($filename, 'r');
    $source = fread($file, filesize($filename));
    $xmpdata_start = strpos($source,"<x:xmpmeta");
    $xmpdata_end = strpos($source,"</x:xmpmeta>");
    $xmplenght = $xmpdata_end-$xmpdata_start;
    $xmpdata = substr($source,$xmpdata_start,$xmplenght+12);
    fclose($file);
		if ($xmpdata) {
			$xmp = xml2array($xmpdata);

			$mdata['XMP'] = self::rdfDesc($xmp['x:xmpmeta']['rdf:RDF']['rdf:Description']);

			if (isset($xmp['x:xmpmeta']['rdf:RDF']['rdf:Description_attr']) AND is_array($xmp['x:xmpmeta']['rdf:RDF']['rdf:Description_attr'])) foreach ($xmp['x:xmpmeta']['rdf:RDF']['rdf:Description_attr'] as $key => $value) {
				if (preg_match('/^(.+):(.+)$/',$key,$k)) $key = $k[1];
				if ($value) $mdata['XMP'][$k[1]][$k[2]] = $value;
			}
			ksort($mdata['XMP']);
		}
		return $mdata;
	}

  ///   Help Extract Datas in RDF format
  public static function rdfDesc($desc) {
  	$ret = array();
  	if (is_array($desc)) foreach ($desc as $key => $value) {
  		if (preg_match('/^(.+):(.+)$/',$key,$k)) {
  			if ($key == 'Iptc4xmpCore:CreatorContactInfo_attr') {
  				$k[2] = 'CreatorContactInfo';
  				$val = array();
  				foreach ($value as $ka => $va) {
  					if (preg_match('/:(.+)$/', $ka,$ki)) $val[$ki[1]] = $va;
  				}
  			}
  			else if (is_array($value)) foreach ($value as $kv => $vv) {
  				if ($kv == 'rdf:Alt') {
  					if (isset($vv['rdf:li'])) $val = $vv['rdf:li'];
  				}
  				else if ($kv == 'rdf:Bag') {
  					if (isset($vv['rdf:li_attr'])) {
  						$val = array();
  						foreach ($vv['rdf:li_attr'] as $kc => $vc) {
  							if (preg_match('/^.+:(.+)$/',$kc,$i)) $val[$i[1]] = $vc;
  							else $val[$kc] = $vc;
  						}
  					}
  					else $val = $vv['rdf:li'];
  				}
  				else if ($kv == 'rdf:Seq') {
  					$val = array();
  					if (is_array($vv['rdf:li']) AND count($vv['rdf:li']) > 0) foreach ($vv['rdf:li'] as $uli => $vli) {
  						if (preg_match('/^(\d+)_attr$/',$uli,$u)) {
  							foreach ($vli as $kc => $vc) {
  								if (preg_match('/^.+:(.+)$/',$kc,$i)) $val[$u[1]][$i[1]] = $vc;
  								else $val[$kc] = $vc;
  							}
  						}
  					}
  					else if (isset($vv['rdf:li_attr'])) foreach ($vv['rdf:li_attr'] as $uli => $vli) {
  						if (preg_match('/^.+:(.+)$/',$uli,$i)) $val[0][$i[1]] = $vli;
  					}
  					else $val = $vv['rdf:li'];
  				}
  				else if ($kv == 'rdf:Description') {
  					$val = self::rdfDesc($vv);
  				}
  			}
  			if (isset($val)) $value = $val;
  			$ret[$k[1]][$k[2]] = $value;
  		}
  	}
  	return $ret;
  }

}
