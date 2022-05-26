<?php

declare(strict_types=1);

namespace OCA\QuickNotes\Dashboard;

use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\IURLGenerator;

class NotesWidget implements IWidget {

	private IURLGenerator $url;
	private IL10N $l10n;

	public function __construct(IURLGenerator $url,
	                            IL10N         $l10n)
	{
		$this->url = $url;
		$this->l10n = $l10n;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'quicknotes';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Quick notes');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 30;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'icon-quicknotes';
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->url->linkToRouteAbsolute('quicknotes.page.index');
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		\OCP\Util::addScript('quicknotes', 'quicknotes-dashboard');
	}
}
