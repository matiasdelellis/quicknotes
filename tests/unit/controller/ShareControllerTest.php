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
use OCA\QuickNotes\Db\NoteShare;
use OCA\QuickNotes\Exception\ForbiddenException;
use OCA\QuickNotes\Service\NoteService;
use OCA\QuickNotes\Service\ShareService;


class ShareControllerTest extends TestCase {

	private $controller;
	private $noteService;
	private $shareService;
	private $userId = 'john';

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->noteService = $this->createMock(NoteService::class);
		$this->shareService = $this->createMock(ShareService::class);

		$this->controller = new ShareController(
			'quicknotes', $request, $this->noteService, $this->shareService, $this->userId
		);
	}

	private function makeNote(int $id, bool $isOwner = true, int $permissions = NoteShare::PERMISSIONS_ALL): Note {
		$note = new Note();
		$note->setId($id);
		$note->setUserId($isOwner ? $this->userId : 'alice');
		$note->setTitle('A title');
		$note->setContent('A content');
		$note->setTimestamp(1700000000);
		$note->setColorId(1);
		$note->setIsOwner($isOwner);
		$note->setPermissions($permissions);
		return $note;
	}

	private function makeShare(int $id = 1): NoteShare {
		$share = new NoteShare();
		$share->setId($id);
		$share->setNoteId(7);
		$share->setShareType(NoteShare::TYPE_USER);
		$share->setShareWith('alice');
		$share->setPermissions(NoteShare::PERMISSION_READ);
		return $share;
	}

	// index -----------------------------------------------------------------

	public function testIndexListsTheSharesOfTheNote(): void {
		$share = $this->makeShare();
		$this->noteService->method('get')->with($this->userId, 7)->willReturn($this->makeNote(7));
		$this->shareService->expects($this->once())
			->method('getSharesForNote')
			->with(7)
			->willReturn([$share]);

		$response = $this->controller->index(7);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([$share], $response->getData());
	}

	public function testIndexNotFound(): void {
		$this->noteService->method('get')->willReturn(null);

		$response = $this->controller->index(7);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	/**
	 * Somebody who was given a note to read has no business knowing who else
	 * it was shared with.
	 */
	public function testIndexForbiddenForAPlainRecipient(): void {
		$this->noteService->method('get')
			->willReturn($this->makeNote(7, false, NoteShare::PERMISSION_READ | NoteShare::PERMISSION_UPDATE));

		$response = $this->controller->index(7);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	// create ----------------------------------------------------------------

	public function testCreateSharesWithAUser(): void {
		$note = $this->makeNote(7);
		$share = $this->makeShare();

		$this->noteService->method('get')->with($this->userId, 7)->willReturn($note);
		$this->shareService->expects($this->once())
			->method('create')
			->with($this->userId, $note, NoteShare::TYPE_USER, 'alice', NoteShare::PERMISSION_READ)
			->willReturn($share);

		$response = $this->controller->create(7, NoteShare::TYPE_USER, 'alice', NoteShare::PERMISSION_READ);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($share, $response->getData());
	}

	public function testCreateForbidden(): void {
		$this->noteService->method('get')->willReturn($this->makeNote(7));
		$this->shareService->method('create')
			->willThrowException(new ForbiddenException('not yours to share'));

		$response = $this->controller->create(7, NoteShare::TYPE_USER, 'alice');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testCreateWithANonsenseRecipient(): void {
		$this->noteService->method('get')->willReturn($this->makeNote(7));
		$this->shareService->method('create')
			->willThrowException(new \InvalidArgumentException('No such user'));

		$response = $this->controller->create(7, NoteShare::TYPE_USER, 'nobody');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'No such user'], $response->getData());
	}

	// update / destroy ------------------------------------------------------

	public function testUpdatePermissions(): void {
		$share = $this->makeShare();
		$this->shareService->expects($this->once())
			->method('updatePermissions')
			->with($this->userId, 1, NoteShare::PERMISSION_READ | NoteShare::PERMISSION_UPDATE)
			->willReturn($share);

		$response = $this->controller->update(1, NoteShare::PERMISSION_READ | NoteShare::PERMISSION_UPDATE);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testDestroy(): void {
		$this->shareService->expects($this->once())
			->method('delete')
			->with($this->userId, 1)
			->willReturn($this->makeShare());

		$response = $this->controller->destroy(1);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame([], $response->getData());
	}

	// leave -----------------------------------------------------------------

	public function testLeave(): void {
		$this->shareService->expects($this->once())
			->method('leave')
			->with($this->userId, 7)
			->willReturn(true);

		$response = $this->controller->leave(7);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	/**
	 * A note that reaches the user through a group is not theirs to leave, so
	 * there is no personal share to drop and the answer is a 404.
	 */
	public function testLeaveWithoutAPersonalShare(): void {
		$this->shareService->method('leave')->willReturn(false);

		$response = $this->controller->leave(7);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	// sharees ---------------------------------------------------------------

	public function testShareesSearch(): void {
		$note = $this->makeNote(7);
		$sharees = [['shareType' => 0, 'shareWith' => 'alice', 'label' => 'Alice', 'subline' => '']];

		$this->noteService->method('get')->willReturn($note);
		$this->shareService->expects($this->once())
			->method('searchSharees')
			->with($this->userId, $note, 'ali', 25)
			->willReturn($sharees);

		$response = $this->controller->sharees(7, 'ali');

		$this->assertSame($sharees, $response->getData());
	}

	public function testShareesLimitIsCapped(): void {
		$this->noteService->method('get')->willReturn($this->makeNote(7));
		$this->shareService->expects($this->once())
			->method('searchSharees')
			->with($this->userId, $this->anything(), 'a', 50)
			->willReturn([]);

		$this->controller->sharees(7, 'a', 500);
	}

}
