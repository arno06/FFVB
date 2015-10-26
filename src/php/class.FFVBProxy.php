<?php
class FFVBProxy
{
	const URL = "http://www.ffvbbeach.org/ffvbapp/resu/vbspo_calendrier.php?saison=2015/2016&codent=LIIDF&poule=RME";

    private $folder = "../data/";
	private $file = "data";
    private $data;

	public function __construct()
	{
		$this->parse();
	}

	public function getRanking()
	{
        return $this->data["ranking"];
	}

	public function getAgenda()
	{
        return $this->data["agenda"];
	}

	protected function pullFromCache($pStoreDuration = 1)
	{
		$pFileName = $this->stripFileName($this->file);
		$folder = $this->folder;
		$pFileName = $folder.$pFileName.".json";
		if(!file_exists($pFileName))
			return false;
		try
		{
			$fmtime = filemtime($pFileName);
			$current = time();
			$diff = ($current - $fmtime) / (60*$pStoreDuration);
			if($diff>60)
				return false;
			$content = file_get_contents($pFileName);
			$jsonParsed = json_decode($content);
		}
		catch(Exception $e)
		{
			return false;
		}
		$jsonParsed = $this->fromNumericEntities($jsonParsed);
		return $jsonParsed;
	}

	private function fromNumericEntities($pValue)
	{
		$convmap = array(0x80, 0xff, 0, 0xff);
		if(!is_array($pValue))
		{
			$specialChars = array("&#8221;"=>'"',
				"&#8220;"=>'"',
				"&#8222;"=>'"',
				"&#8211;"=>'-',
				"&#8212;"=>'_',
				"&#8216"=>"'",
				"&#8217"=>"'",
				"&#8218"=>"'");
			foreach($specialChars as $k=>$v)
				$pValue = preg_replace("/".$k."/",$v,$pValue);
			return mb_decode_numericentity($pValue, $convmap, "UTF-8");
		}
		foreach($pValue as &$value)
			$value = $this->fromNumericEntities($value);
		return $pValue;
	}

	private function storeInCache($pContent)
	{
		$pFileName = $this->stripFileName($this->file);
		$folder = $this->folder;
		$pFileName = $folder.$pFileName.".json";
		if(file_exists($pFileName))
			chmod($pFileName, 0777);
		else
			mkdir($pFileName, 0777, true);
		unlink($pFileName);
		$pContent = json_encode($pContent);
		file_put_contents($pFileName, $pContent);
	}

	private function stripFileName($pFileName)
	{
		$chars = array(
			"/"=>"_sl_",
			"."=>"_po_"
		);
		foreach($chars as $key=>$change)
		{
			$pFileName = str_replace($key, $change, $pFileName);
		}
		return $pFileName;
	}


	private function parse()
	{
        if($data = $this->pullFromCache(5))
        {
            return;
        }

		$html = file_get_contents(self::URL);

		$this->re_escape('/(\r|\n|\t)/', $html);

		$body = $this->re_extract('/<body[^>]*>(.+)<\/body>/i', $html);


		/**
		 * Cleaning script and noscript tags
		 */
		$this->re_escape('/(<script[^>]*>[^<]*|<[^\/]*<\/script>)/i', $body);
		$this->re_escape('/(<\/script>)/i', $body);
		$this->re_escape('/(<noscript[^>]*>.*<\/noscript>)/i', $body);
		/**
		 * Deleting useless table
		 */
		$first_table = strpos($body, '</TABLE>');
		$content = substr($body, $first_table + 8, strlen($body));

		/**
		 * More Cleaning
		 */
		$content = $this->re_extract('/(<table[^>]*>.*<\/table>)/i', $content);

		/**
		 * Isolation
		 */
		$last_table = strrpos($content, "<TABLE");
		$first_stable = strpos($content, "</TABLE>");

		/**
		 * First extraction
		 */
		$table_ranking = substr($content, 0, $first_stable + 8);
		$table_agenda = substr($content, $last_table, strlen($content) - strlen($table_ranking));

		/**
		 * Escaping attributes
		 */
		$re_attributes = '/(<[a-z0-9]+)([^>]*)>/i';
		$table_ranking = preg_replace($re_attributes, '$1>', $table_ranking);
		$table_agenda = preg_replace($re_attributes, '$1>', $table_agenda);

		/**
		 * Escaping form and input tags
		 */
		$re_tags = '/(<form>|<input>|<\/form>|<\/input>|<p>|<\/p>|<img>|<img\/>)/i';
		$this->re_escape($re_tags, $table_ranking);
		$this->re_escape($re_tags, $table_agenda);

		/**
		 * Final XML parsing to SimpleXMLElement
		 */
		$ranking_p = simplexml_load_string(utf8_encode($table_ranking));
		$agenda_p = simplexml_load_string(utf8_encode($table_agenda));
		$ranking = array();
		for($i = 1, $max = count($ranking_p->tr); $i<$max; $i++)
		{
			$team = $ranking_p->tr[$i]->td;
			$ranking[] = array(
				"name"=>strval($team[1]),
				"championship_points"=>$this->str($team[2]),
				"matches"=>array(
					"played"=>$this->num($team[3]),
					"won"=>$this->num($team[4]),
					"lost"=>$this->num($team[5]),
					"F"=>$this->num($team[6]),
					"3_0"=>$this->num($team[7]),
					"3_1"=>$this->num($team[8]),
					"3_2"=>$this->num($team[9]),
					"2_3"=>$this->num($team[10]),
					"1_3"=>$this->num($team[11]),
					"0_3"=>$this->num($team[12])
				),
				"sets"=>array(
					"played"=>$this->num($team[13])+$this->num($team[14]),
					"won"=>$this->num($team[13]),
					"lost"=>$this->num($team[14])
				),
				"points"=>array(
					"played"=>$this->num($team[16])+$this->num($team[17]),
					"won"=>$this->num($team[16]),
					"lost"=>$this->num($team[17])
				)
			);
		}

		$agenda = array();

		for($i = 0, $max = count($agenda_p->tr); $i<$max; $i++)
		{
			$day = $agenda_p->tr[$i]->td;
			if(count($day)==1)
			{
				$agenda[] = array("label"=>$this->str($day), "matches"=>array());
			}
			else
			{
				$points = explode(":", $this->str($day[9]));
				if(!isset($points[0]))
					$points = array("", "");
				if(!isset($points[1]))
					$points[1] = "";
				$agenda[count($agenda)-1]["matches"][] = array(
					"date"=>$this->str($day[1]),
					"hour"=>$this->str($day[2]),
					"home"=>array(
						"name"=>$this->str($day[3]),
						"set"=>$this->str($day[6]),
						"points"=>$points[0]
					),
					"guest"=>array(
						"name"=>$this->str($day[5]),
						"set"=>$this->str($day[7]),
						"points"=>$points[1]
					)
				);
			}
		}

		$this->data = array(
			"ranking"=>$ranking,
			"agenda"=>$agenda
		);
		$this->storeInCache($this->data);
	}

	private function str($pString)
	{
		return mb_encode_numericentity((String) utf8_decode($pString), array(0x80, 0xff, 0, 0xff), "ISO-8859-1");
	}

	private function num($pValue)
	{
		return (int) utf8_decode($pValue);
	}

	private function re_escape($pRegExp, &$pString)
	{
		$pString = preg_replace($pRegExp, "", $pString);
	}

	private function re_extract($pRegExp, $pString)
	{
		preg_match($pRegExp, $pString, $matches);
		if(!isset($matches[1]))
			return false;
		return $matches[1];
	}
}
