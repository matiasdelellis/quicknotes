<?php
/**
 * ownCloud - quicknotes
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 * @copyright Matias De lellis 2016
 */

namespace OCA\QuickNotes\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Exception\ConflictException;
use OCA\QuickNotes\Exception\ForbiddenException;
use OCA\QuickNotes\Service\NoteService;


class NoteApiControllerTest extends TestCase {

	private $controller;
	private $noteService;
	private $request;
	private $userId = 'john';

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->noteService = $this->createMock(NoteService::class);

		$this->controller = new NoteApiController(
			'quicknotes', $this->request, $this->noteService, $this->userId
		);
	}

	private function makeNote(int $id = 1,
	                         string $title = 'A title',
	                         string $content = 'A content'): Note {
		$note = new Note();
		$note->setId($id);
		$note->setUserId($this->userId);
		$note->setTitle($title);
		$note->setContent($content);
		$note->setTimestamp(1700000000);
		$note->setColorId(1);
		$note->setColor('#F7EB96');
		$note->setIsPinned(false);
		return $note;
	}

	// index -----------------------------------------------------------------

	public function testIndexEmpty(): void {
		$this->noteService->expects($this->once())
			->method('getAll')
			->with($this->userId)
			->willReturn([]);

		$response = $this->controller->index();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testIndexWithNotes(): void {
		$note = $this->makeNote();
		$this->noteService->expects($this->once())
			->method('getAll')
			->with($this->userId)
			->willReturn([$note]);

		$response = $this->controller->index();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertCount(1, $response->getData());
		$this->assertNotEmpty($response->getETag());
		$this->assertNotNull($response->getLastModified());
	}

	// show ------------------------------------------------------------------

	public function testShowFound(): void {
		$note = $this->makeNote(42);
		$this->noteService->expects($this->once())
			->method('get')
			->with($this->userId, 42)
			->willReturn($note);

		$response = $this->controller->show(42);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertNotEmpty($response->getETag());
		$this->assertSame($note, $response->getData());
	}

	public function testShowNotFound(): void {
		$this->noteService->expects($this->once())
			->method('get')
			->with($this->userId, 99)
			->willReturn(null);

		$response = $this->controller->show(99);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	// create ----------------------------------------------------------------

	public function testCreateMinimal(): void {
		$note = $this->makeNote(7, 'hello', 'world');
		$this->noteService->expects($this->once())
			->method('create')
			->with($this->userId, 'hello', 'world', null, false, [], [], [])
			->willReturn($note);

		$response = $this->controller->create('hello', 'world');

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertNotEmpty($response->getETag());
	}

	public function testCreateFull(): void {
		$note = $this->makeNote(7);
		$sharedWith = [['id' => 'alice']];
		$tags = [['name' => 'urgent']];
		$attachments = [['file_id' => 1]];

		$this->noteService->expects($this->once())
			->method('create')
			->with(
				$this->userId,
				'hello',
				'world',
				'#abcdef',
				true,
				$sharedWith,
				$tags,
				$attachments
			)
			->willReturn($note);

		$response = $this->controller->create(
			'hello', 'world', '#abcdef', true,
			$sharedWith, $tags, $attachments
		);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertNotEmpty($response->getETag());
	}

	// update ----------------------------------------------------------------

	public function testUpdateFound(): void {
		$note = $this->makeNote(5);
		$this->noteService->expects($this->once())
			->method('update')
			->with($this->userId, 5, 't', 'c', '#fff', false, [], [], [], null)
			->willReturn($note);

		$response = $this->controller->update(5, 't', 'c', '#fff', false, [], [], []);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($note->getEtag(), trim($response->getETag(), '"'));
	}

	/**
	 * Everything but the title and the content is optional, so that a client
	 * that only means to change one thing does not have to resend the rest.
	 */
	public function testUpdateLeavesOmittedFieldsAlone(): void {
		$note = $this->makeNote(5);
		$this->noteService->expects($this->once())
			->method('update')
			->with($this->userId, 5, 't', 'c', null, null, null, null, null, null)
			->willReturn($note);

		$response = $this->controller->update(5, 't', 'c');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateNotFound(): void {
		$this->noteService->expects($this->once())
			->method('update')
			->willReturn(null);

		$response = $this->controller->update(99, 't', 'c', '#fff', false, [], [], []);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	/** A note shared read only is a 403, not a 404: the user can see it. */
	public function testUpdateForbidden(): void {
		$this->noteService->expects($this->once())
			->method('update')
			->willThrowException(new ForbiddenException('read only'));

		$response = $this->controller->update(5, 't', 'c');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['message' => 'read only'], $response->getData());
	}

	/**
	 * An `If-Match` that no longer matches comes back as 412 with the note as
	 * it now stands, so the client can show what it was about to overwrite.
	 */
	public function testUpdateConflictCarriesTheCurrentNote(): void {
		$current = $this->makeNote(5, 'theirs', 'their content');

		$this->request->method('getHeader')
			->with('If-Match')
			->willReturn('"stale-etag"');

		$this->noteService->expects($this->once())
			->method('update')
			->with($this->userId, 5, 't', 'c', null, null, null, null, null, 'stale-etag')
			->willThrowException(new ConflictException($current));

		$response = $this->controller->update(5, 't', 'c');

		$this->assertSame(Http::STATUS_PRECONDITION_FAILED, $response->getStatus());
		$this->assertSame($current, $response->getData()['note']);
	}

	/** `If-Match: *` is "as long as it exists", i.e. no condition at all. */
	public function testUpdateIgnoresWildcardIfMatch(): void {
		$note = $this->makeNote(5);

		$this->request->method('getHeader')
			->with('If-Match')
			->willReturn('*');

		$this->noteService->expects($this->once())
			->method('update')
			->with($this->userId, 5, 't', 'c', null, null, null, null, null, null)
			->willReturn($note);

		$response = $this->controller->update(5, 't', 'c');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	// destroy ---------------------------------------------------------------

	public function testDestroy(): void {
		$this->noteService->expects($this->once())
			->method('destroy')
			->with($this->userId, 5)
			->willReturn(true);

		$response = $this->controller->destroy(5);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	/**
	 * Destroying is the owner's alone, and a note that is not theirs used to
	 * be answered with a cheerful 200 after doing nothing at all.
	 */
	public function testDestroyOfSomebodyElsesNote(): void {
		$this->noteService->expects($this->once())
			->method('destroy')
			->willReturn(false);

		$response = $this->controller->destroy(5);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	// empty trash -----------------------------------------------------------

	public function testEmptyTrash(): void {
		$this->noteService->expects($this->once())
			->method('emptyTrash')
			->with($this->userId)
			->willReturn(3);

		$response = $this->controller->emptyTrash();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['destroyed' => 3], $response->getData());
	}

	/** Asking for an empty trash to be emptied is not an error. */
	public function testEmptyTrashWithNothingInIt(): void {
		$this->noteService->method('emptyTrash')->willReturn(0);

		$response = $this->controller->emptyTrash();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['destroyed' => 0], $response->getData());
	}

	// archive / unarchive / trash / restore --------------------------------

	public function testArchive(): void {
		$note = $this->makeNote(5);
		$this->noteService->expects($this->once())
			->method('archive')
			->with($this->userId, 5)
			->willReturn($note);

		$response = $this->controller->archive(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertNotEmpty($response->getETag());
	}

	public function testArchiveNotFound(): void {
		$this->noteService->expects($this->once())
			->method('archive')
			->willReturn(null);

		$response = $this->controller->archive(99);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUnarchive(): void {
		$note = $this->makeNote(5);
		$this->noteService->expects($this->once())
			->method('unarchive')
			->with($this->userId, 5)
			->willReturn($note);

		$response = $this->controller->unarchive(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUnarchiveNotFound(): void {
		$this->noteService->expects($this->once())
			->method('unarchive')
			->willReturn(null);

		$response = $this->controller->unarchive(99);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testTrash(): void {
		$note = $this->makeNote(5);
		$this->noteService->expects($this->once())
			->method('trash')
			->with($this->userId, 5)
			->willReturn($note);

		$response = $this->controller->trash(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testTrashNotFound(): void {
		$this->noteService->expects($this->once())
			->method('trash')
			->willReturn(null);

		$response = $this->controller->trash(99);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testRestore(): void {
		$note = $this->makeNote(5);
		$this->noteService->expects($this->once())
			->method('restore')
			->with($this->userId, 5)
			->willReturn($note);

		$response = $this->controller->restore(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testRestoreNotFound(): void {
		$this->noteService->expects($this->once())
			->method('restore')
			->willReturn(null);

		$response = $this->controller->restore(99);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	// reminder --------------------------------------------------------------

	public function testReminderSet(): void {
		$note = $this->makeNote(5);
		$this->noteService->expects($this->once())
			->method('setReminder')
			->with($this->userId, 5, '2026-08-01 09:00:00')
			->willReturn($note);

		$response = $this->controller->reminder(5, '2026-08-01 09:00:00');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testReminderCancelledWhenOmitted(): void {
		$note = $this->makeNote(5);
		$this->noteService->expects($this->once())
			->method('setReminder')
			->with($this->userId, 5, null)
			->willReturn($note);

		$response = $this->controller->reminder(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testReminderMalformedDate(): void {
		$this->noteService->expects($this->once())
			->method('setReminder')
			->willThrowException(new \InvalidArgumentException('nope'));

		$response = $this->controller->reminder(5, 'tomorrow-ish');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'nope'], $response->getData());
	}

	public function testReminderNotFound(): void {
		$this->noteService->expects($this->once())
			->method('setReminder')
			->willReturn(null);

		$response = $this->controller->reminder(99, '2026-08-01 09:00:00');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

}
