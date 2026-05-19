<?php

class KlubCestovatelu extends LunchMenuSource
{
	public $title = 'Klub cestovatelů';
	public $link = 'https://www.klubcestovatelubrno.cz/denni-menu/';
	public $icon = 'klubcestovatelu';

	public function getTodaysMenu($todayDate, $cacheSourceExpires)
	{
		$cached = $this->downloadHtml($cacheSourceExpires);
		$result = new LunchMenuResult($cached['stored']);

		$content = $cached['html']->find("div.entry-content", 0);
		if (!$content) {
			throw new ScrapingFailedException("div.entry-content not found");
		}

		$iframe = $content->find("iframe#menu-frame", 0);
		if (!$iframe) {
			throw new ScrapingFailedException("iframe#menu-frame not found");
		}

		$iframeUrl = html_entity_decode($iframe->src, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		if (strpos($iframeUrl, '//') === 0) {
			$iframeUrl = 'https:' . $iframeUrl;
		} elseif (strpos($iframeUrl, 'http') !== 0) {
			$iframeUrl = rtrim($this->link, '/') . '/' . ltrim($iframeUrl, '/');
		}

		$menu = cache_get_html($this->title . ' iframe', $iframeUrl, $cacheSourceExpires);
		if (!$menu['html']) {
			throw new ScrapingFailedException("iframe menu not loaded");
		}

		$this->processEmbeddedMenu($result, $menu['html'], $todayDate);
		return $result;
	}

	protected function processEmbeddedMenu(&$result, $html, $todayDate)
	{
		$dayName = get_czech_day(date('w', $todayDate));
		$dayBlock = null;
		foreach ($html->find('div.day-block') as $block) {
			$name = $this->normalizeText($block->find('span.day-name', 0)->plaintext);
			if (mb_strtolower($name) == $dayName) {
				$dayBlock = $block;
				break;
			}
		}

		if (!$dayBlock) {
			throw new ScrapingFailedException("today's day-block not found");
		}

		$prices = array();
		foreach ($html->find('span.price-item strong') as $priceItem) {
			$text = $this->normalizeText($priceItem->plaintext);
			if (preg_match('/Menu\s+č\.\s*([0-9]+)\s*[–-]\s*(.+)$/ui', $text, $m)) {
				$prices[(int)$m[1]] = trim($m[2]);
			}
		}

		$soup = $dayBlock->find('span.soup-name', 0);
		if ($soup) {
			$result->dishes[] = new Dish($this->normalizeText($soup->plaintext));
		}

		foreach ($dayBlock->find('li.dish-item') as $dish) {
			$number = $this->normalizeText($dish->find('span.dish-num', 0)->plaintext);
			$number = trim($number, '.');
			$what = $this->normalizeText($dish->find('span.dish-name', 0)->plaintext);
			$price = isset($prices[(int)$number])? $prices[(int)$number] : NULL;
			$result->dishes[] = new Dish($what, $price, NULL, NULL, $number);
		}
	}

	protected function normalizeText($text)
	{
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/\s+/u', ' ', $text);
		return trim($text);
	}
}
