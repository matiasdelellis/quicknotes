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

use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

use OCA\QuickNotes\Db\NoteShareMapper;


class ShareControllerTest extends TestCase {

	private $controller;
	private $noteShareMapper;
	private $userId = 'john';

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->noteShareMapper = $this->createMock(NoteShareMapper::class);

		$this->controller = new ShareController(
			'quicknotes', $request, $this->noteShareMapper, $this->userId
		);
	}

	public function testForget(): void {
		$this->noteShareMapper->expects($this->once())
			->method('forgetShareByNoteIdAndSharedUser')
			->with(7, $this->userId);

		$response = $this->controller->forget(7);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame([], $response->getData());
	}

}
