<?php
/**
 * ownCloud - quicknotes
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 * @copyright Matias De lellis 2026
 */

namespace OCA\QuickNotes\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Service\NoteService;

/**
 * The page controller. Its endpoints are the same as the api one — which has
 * its own test — except for the dashboard, which only exists here.
 */
class NoteControllerTest extends TestCase {

	private $controller;
	private $noteService;
	private $userId = 'john';

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->noteService = $this->createMock(NoteService::class);

		$this->controller = new NoteController(
			'quicknotes', $request, $this->noteService, $this->userId
		);
	}

	private function makeNote(int $id,
	                         string $title,
	                         ?string $archivedAt = null,
	                         ?string $deletedAt = null,
	                         bool $pinned = false,
	                         int $timestamp = 1700000000): Note {
		$note = new Note();
		$note->setId($id);
		$note->setUserId($this->userId);
		$note->setTitle($title);
		$note->setContent('A content');
		$note->setTimestamp($timestamp);
		$note->setColorId(1);
		$note->setIsPinned($pinned);
		$note->setArchivedAt($archivedAt);
		$note->setDeletedAt($deletedAt);
		return $note;
	}

	public function testDashboardEmpty(): void {
		$this->noteService->method('getAll')->willReturn([]);

		$response = $this->controller->dashboard();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(['notes' => []], $response->getData());
	}

	/**
	 * A note taken out of the grid — archived, or in the trash — has no
	 * business still being on the dashboard. It was until 0.9.5.
	 */
	public function testDashboardLeavesOutArchivedAndTrashedNotes(): void {
		$this->noteService->method('getAll')->willReturn([
			$this->makeNote(1, 'active'),
			$this->makeNote(2, 'archived', '2026-08-01 10:00:00'),
			$this->makeNote(3, 'trashed', null, '2026-08-02 10:00:00'),
			$this->makeNote(4, 'archived and trashed', '2026-08-01 10:00:00', '2026-08-02 10:00:00'),
		]);

		$response = $this->controller->dashboard();

		$titles = array_column($response->getData()['notes'], 'title');
		$this->assertSame(['active'], $titles);
	}

	/** Nothing left after filtering is the empty answer, not a broken one. */
	public function testDashboardWithOnlyArchivedNotes(): void {
		$this->noteService->method('getAll')->willReturn([
			$this->makeNote(1, 'archived', '2026-08-01 10:00:00'),
		]);

		$response = $this->controller->dashboard();

		$this->assertSame(['notes' => []], $response->getData());
	}

	/** Pinned first, then the most recently touched, and never more than seven. */
	public function testDashboardOrdersAndCaps(): void {
		$notes = [];
		for ($i = 1; $i <= 9; $i++) {
			$notes[] = $this->makeNote($i, 'note ' . $i, null, null, false, 1700000000 + $i);
		}
		$notes[] = $this->makeNote(10, 'pinned', null, null, true, 1);

		$this->noteService->method('getAll')->willReturn($notes);

		$response = $this->controller->dashboard();
		$titles = array_column($response->getData()['notes'], 'title');

		$this->assertCount(7, $titles);
		$this->assertSame('pinned', $titles[0]);
		$this->assertSame('note 9', $titles[1]);
	}

	/** The content reaches the widget as plain text. */
	public function testDashboardFlattensTheRichText(): void {
		$note = $this->makeNote(1, '<b>bold</b> title');
		$note->setContent('<p>with <i>markup</i></p>');

		$this->noteService->method('getAll')->willReturn([$note]);

		$item = $this->controller->dashboard()->getData()['notes'][0];

		$this->assertSame('bold title', $item['title']);
		$this->assertSame('with markup', $item['content']);
	}

}
