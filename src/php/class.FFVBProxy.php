<?php
class FFVBProxy
{
	const URL = "http://www.ffvbbeach.org/ffvbapp/resu/vbspo_calendrier.php?saison=2015/2016&codent=LIIDF&poule=";

    private $folder = "../data/";
	private $file = "data";
    private $data;
    private $url;

	public function __construct($pWho)
	{
        $this->url = self::URL.$pWho;
        $this->file = $this->file."_".$pWho;
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
			$jsonParsed = json_decode($content, true);
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
        {
            unlink($pFileName);
        }
        fclose(fopen($pFileName,'x'));
        chmod($pFileName, 0777);
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
        if($this->data = $this->pullFromCache(5))
        {
            return;
        }

		$html = file_get_contents($this->url);

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
		$overall = array();
        $points = array();
        $set_points = array();
        $match_points = array();
        $lost_set_points = array();
        $lost_match_points = array();
		for($i = 1, $max = count($ranking_p->tr); $i<$max; $i++)
		{
			$team = $ranking_p->tr[$i]->td;
            $overall[] = array(
                "position"=>$i,
				"name"=>strval($team[1]),
                "coef"=>round((($this->num($team[2]))/($this->num($team[3])*3))*100,2),
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
				)
			);
            $points[] = array(
                "position"=>$i,
                "name"=>strval($team[1]),
                "coef"=>round(($this->num($team[16]))/($this->num($team[17])),2),
                "played"=>$this->num($team[16])+$this->num($team[17]),
                "won"=>$this->num($team[16]),
                "lost"=>$this->num($team[17])
            );
            $set_points[] = array(
                "position"=>$i,
                "name"=>strval($team[1]),
                "value"=>round(($this->num($team[16]))/($this->num($team[13])+$this->num($team[14])),2),
            );
            $match_points[] = array(
                "position"=>$i,
                "name"=>strval($team[1]),
                "value"=>round(($this->num($team[16]))/($this->num($team[3])),2),
            );
            $lost_set_points[] = array(
                "position"=>$i,
                "name"=>strval($team[1]),
                "value"=>round(($this->num($team[17]))/($this->num($team[13])+$this->num($team[14])),2),
            );
            $lost_match_points[] = array(
                "position"=>$i,
                "name"=>strval($team[1]),
                "value"=>round(($this->num($team[17]))/($this->num($team[3])),2),
            );
		}

        usort($points, function($pA, $pB){
            if($pA['coef'] == $pB['coef'])
                return 0;
            return $pA['coef']>$pB['coef']?-1:1;
        });

        usort($set_points, function($pA, $pB){
            if($pA['value'] == $pB['value'])
                return 0;
            return $pA['value']>$pB['value']?-1:1;
        });

        usort($match_points, function($pA, $pB){
            if($pA['value'] == $pB['value'])
                return 0;
            return $pA['value']>$pB['value']?-1:1;
        });

        usort($lost_set_points, function($pA, $pB){
            if($pA['value'] == $pB['value'])
                return 0;
            return $pA['value']<$pB['value']?-1:1;
        });

        usort($lost_match_points, function($pA, $pB){
            if($pA['value'] == $pB['value'])
                return 0;
            return $pA['value']<$pB['value']?-1:1;
        });

		$agenda = array();

        $winning_distance = array();
        $losing_distance = array();

		for($i = 0, $max = count($agenda_p->tr); $i<$max; $i++)
		{
			$day = $agenda_p->tr[$i]->td;
            $home_name = $this->str($day[3]);
            $guest_name = $this->str($day[5]);

            if(!$winning_distance[$home_name])
                $winning_distance[$home_name] = array();
            if(!$winning_distance[$guest_name])
                $winning_distance[$guest_name] = array();
            if(!$losing_distance[$home_name])
                $losing_distance[$home_name] = array();
            if(!$losing_distance[$guest_name])
                $losing_distance[$guest_name] = array();

			if(count($day)==1)
			{
				$agenda[] = array("label"=>$this->str($day), "matches"=>array());
			}
			else
			{
                $sets = explode(", ", $this->str($day[8]));
                if(count($sets)>1)
                {
                    foreach($sets as $s)
                    {
                        $p = explode(":", $s);
                        if($p[0] > $p[1])
                        {
                            $winning_distance[$home_name][] = $p[0] - $p[1];
                            $losing_distance[$guest_name][] = $p[0] - $p[1];
                        }
                        else
                        {
                            $winning_distance[$guest_name][] = $p[1] - $p[0];
                            $losing_distance[$home_name][] = $p[1] - $p[0];
                        }
                    }
                }
				$m_points = explode(":", $this->str($day[9]));
				if(!isset($m_points[0]))
                    $m_points = array("", "");
				if(!isset($m_points[1]))
                    $m_points[1] = "";
				$sets_home = $this->str($day[6]);
				$points_home = $m_points[0];
				$sets_guest = $this->str($day[7]);
				$points_guest = $m_points[1];

				$played = preg_match('/^([0-9])$/', $sets_home, $m)||preg_match('/^([0-9])$/', $sets_guest, $m);

				if($played)
				{
					$sets_home = intval($sets_home);
					$points_home = intval($points_home);
					$sets_guest = intval($sets_guest);
					$points_guest = intval($points_guest);
				}

				$agenda[count($agenda)-1]["matches"][] = array(
					"date"=>$this->str($day[1]),
					"hour"=>$this->str($day[2]),
					"played"=>$played,
					"home"=>array(
						"name"=>$home_name,
						"set"=>$sets_home,
						"points"=>$points_home
					),
					"guest"=>array(
						"name"=>$guest_name,
						"set"=>$sets_guest,
						"points"=>$points_guest
					)
				);
			}
		}

        $wd = array();
        $ld = array();
        foreach($overall as $e)
        {
            $l = isset($losing_distance[$e['name']])?$losing_distance[$e['name']]:array(0);
            $w = isset($winning_distance[$e['name']])?$winning_distance[$e['name']]:array(0);
            $lt = 0;
            $wt = 0;
            foreach($l as $v)
                $lt += $v;
            $lt = round($lt/count($l), 2);
            foreach($w as $v)
                $wt += $v;
            $wt = round($wt/count($w), 2);
            $wd[] = array(
                'name'=>$e['name'],
                'position'=>$e['position'],
                'value'=>$wt
            );
            $ld[] = array(
                'name'=>$e['name'],
                'position'=>$e['position'],
                'value'=>$lt
            );
        }

        usort($ld, function($pA, $pB){
            if($pA['value'] == $pB['value'])
                return 0;
            return $pA['value']<$pB['value']?-1:1;
        });
        usort($wd, function($pA, $pB){
            if($pA['value'] == $pB['value'])
                return 0;
            return $pA['value']>$pB['value']?-1:1;
        });


		$this->data = array(
			"ranking"=>array('overall'=>$overall,
                            'points'=>$points,
                            'points_per_set'=>$set_points,
                            'points_per_match'=>$match_points,
                            'lost_points_per_set'=>$lost_set_points,
                            'lost_points_per_match'=>$lost_match_points,
                            'average_losing_distance'=>$ld,
                            'average_winning_distance'=>$wd),
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
