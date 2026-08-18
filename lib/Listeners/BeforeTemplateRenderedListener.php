<?php

declare(strict_types=1);

namespace OCA\QuickNotes\Listeners;

use OCA\Viewer\Event\LoadViewer;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\Util;

class BeforeTemplateRenderedListener implements IEventListener {

	private $request;

	/** @var IEventDispatcher */
	private $eventDispatcher;

	public function __construct(IRequest $request, IEventDispatcher $eventDispatcher) {
		$this->request         = $request;
		$this->eventDispatcher = $eventDispatcher;
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		if (!$event->isLoggedIn()) {
			return;
		}

		Util::addStyle('quicknotes', 'icons');

		$pathInfo = $this->request->getPathInfo();
		if (strpos($pathInfo, '/call/') === 0 || strpos($pathInfo, '/apps/spreed') === 0) {
			Util::addScript('quicknotes', 'quicknotes-talk');
		}

		if (strpos($pathInfo, '/apps/quicknotes') === 0) {
			$this->loadViewer();
		}
	}

	/**
	 * Ask the Viewer app to put itself on our page, so `js/script.js` can open
	 * an attachment in it.
	 *
	 * Dispatching `LoadViewer` is how an app that is not Files gets the viewer
	 * — the same event Deck and Talk use. It is not free: the listener on the
	 * other side pulls in the viewer bundle *and* declares a dependency on the
	 * scripts of the Files app, so it is done only on our own page and not on
	 * every render of every app.
	 *
	 * The viewer can be disabled, in which case the class is not there to
	 * dispatch and `OCA.Viewer` never shows up in the browser either; the click
	 * handler falls back to the plain link for exactly that case.
	 */
	private function loadViewer(): void {
		if (!class_exists(LoadViewer::class)) {
			return;
		}

		$this->eventDispatcher->dispatchTyped(new LoadViewer());
	}

}