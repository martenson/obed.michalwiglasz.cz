<?php

class Menza extends LunchMenuSource {

	public $title = 'Menza';
	public $link = 'http://www.kam.vut.cz/?p=menu&provoz=5';
	public $icon = 'ambulance';

	public function getTodaysMenu($todayDate, $cacheSourceExpires)
	{
		$cached = $this->downloadRaw($cacheSourceExpires);
		$result = new LunchMenuResult($cached['stored']);
		$group = null;

		if (!preg_match('/<table\b[^>]*\bid=["\']m5["\'][^>]*>.*?<\/table>/is', $cached['contents'], $m)) {
			throw new ScrapingFailedException("table#m5 was not found");
		}

		$tableHtml = str_get_html($m[0]);
		if (!$tableHtml) {
			throw new ScrapingFailedException("table#m5 could not be parsed");
		}

		$table = $tableHtml->find("table#m5", 0);

		$mapping = array(
			"td.levy" => "type",
			"td.levyjid" => "name",
			"td.slcen1" => "priceStudent",
			"td.slcen2" => "priceEmployee",
			"td.slcen3" => "priceExternal",
		);

		foreach ($table->find("tr") as $i => $row) {

			$values = array(
				"type" => "",
				"name" => "",
				"priceStudent" => "",
				"priceEmployee" => "",
				"priceExternal" => "",
			);
			foreach ($mapping as $selector => $key) {
				if ($key == 'name') {
					$element = $this->findCzechName($row);
				} else {
					$element = $row->find($selector, 0);
				}
				if ($element) {
					$text = $element->plaintext;
					if ($key == 'name') {
						$text = preg_replace('/<small\b[^>]*>.*?<\/small>/is', '', $element->innertext);
					}
					$values[$key] = $this->normalizeText($text);
				}
			}

			if (!$values["name"]) {
				continue;
			}

			$type = substr($values["type"], 0, 1);
			if ($type === "H") {
				$group = "Hlavní jídlo";
			} elseif ($type === "P") {
				$group = "Polévka";
			} else {
				$group = "Ostatní";
			}

			$price = array(
				"Student" => $values["priceStudent"],
				"Zaměstnanec" => $values["priceEmployee"],
				"Externí stravník" => $values["priceExternal"],
			);

			$result->dishes[] = new Dish($values["name"], $price, '', $group, '');
		}

		return $result;

	}

	protected function findCzechName($row)
	{
		$fallback = null;
		foreach ($row->find('td.levyjid') as $element) {
			if (!$fallback) {
				$fallback = $element;
			}
			if (strpos($element->class, 'jjjaz1jjj') !== FALSE) {
				return $element;
			}
		}
		return $fallback;
	}

	protected function normalizeText($text)
	{
		$text = strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		$text = preg_replace('/\s+/u', ' ', $text);
		return trim($text);
	}
}
