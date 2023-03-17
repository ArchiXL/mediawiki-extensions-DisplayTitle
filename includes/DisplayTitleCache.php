<?php

class DisplayTitleCache {

	/**
	 * @var Title
	 */
	private $title;

	/**
	 * @param Title $title
	 */
	public function __construct( Title $title ) {
		$this->title = $title;
	}

	/**
	 * @return mixed
	 */
	public function get() {
		return $this->getObjectCache()->get(
			$this->getCacheKey()
		);
	}

	/**
	 * @param $value
	 * @param int $expiry
	 * @return void
	 */
	public function set( $value, int $expiry = 60 * 60 * 7 ): bool {
		wfDebugLog(
			'DisplayTitle',
			sprintf('[CACHE][NEW] Setting %s with key %s', $this->title->getFullText(), $this->getCacheKey() )
		);

		return $this->getObjectCache()->set(
			$this->getCacheKey(),
			$value,
			$expiry
		);
	}

	/**
	 * @param int|null $revId
	 * @return bool
	 */
	public function delete( int $revId = null ): bool {
		wfDebugLog(
			'DisplayTitle',
			sprintf('[CACHE][DELETE] Removing %s with key %s', $this->title->getFullText(), $this->getCacheKey() )
		);

		return $this->getObjectCache()->delete(
			$this->getCacheKey( $revId )
		);
	}

	/**
	 * @param int|null $revId
	 * @return string
	 */
	public function getCacheKey( int $revId = null ): string  {
		return $this->getObjectCache()->makeKey(
			'ext',
			'displaytitle',
			$this->title->getArticleID(),
			$revId ?? $this->title->getLatestRevID()
		);
	}

	/**
	 * @return BagOStuff
	 */
	private function getObjectCache(): BagOStuff {
		global $wgDisplayTitleCacheType;
		return $wgDisplayTitleCacheType
			? ObjectCache::getInstance( $wgDisplayTitleCacheType )
			: ObjectCache::getLocalClusterInstance();
	}

}