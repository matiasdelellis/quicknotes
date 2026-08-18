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

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\File;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IPreview;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

use OCA\QuickNotes\Db\Attach;
use OCA\QuickNotes\Db\AttachMapper;
use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Service\FileService;
use OCA\QuickNotes\Service\NoteService;
use OCA\QuickNotes\Service\ShareService;

use Psr\Log\LoggerInterface;


/**
 * Minimal in-test stub of IRequest that exposes the legacy `$files`
 * public property the AttachmentApiController reads. The upstream
 * IRequest interface no longer declares it, so PHPUnit's mock of the
 * interface would emit a "dynamic property" deprecation when we set
 * `$request->files = [...]`. All other IRequest methods return the
 * defaults the controller does not look at.
 */
class FakeRequest implements IRequest {
	public array $files = [];

	public function getHeader(string $name): string { return ''; }
	public function getParam(string $key, $default = null) { return $default; }
	public function getParams(): array { return []; }
	public function getMethod(): string { return 'POST'; }
	public function getUploadedFile(string $key) { return null; }
	public function getEnv(string $key) { return null; }
	public function getCookie(string $key) { return null; }
	public function passesCSRFCheck(): bool { return true; }
	public function passesStrictCookieCheck(): bool { return true; }
	public function passesLaxCookieCheck(): bool { return true; }
	public function getId(): string { return ''; }
	public function getRemoteAddress(): string { return ''; }
	public function getServerProtocol(): string { return ''; }
	public function getHttpProtocol(): string { return ''; }
	public function getRequestUri(): string { return ''; }
	public function getRawPathInfo(): string { return ''; }
	public function getPathInfo() { return ''; }
	public function getScriptName(): string { return ''; }
	public function isUserAgent(array $agent): bool { return false; }
	public function getInsecureServerHost(): string { return ''; }
	public function getServerHost(): string { return ''; }
	public function throwDecodingExceptionIfAny(): void {}
	public function getFormat(): ?string { return null; }
}


class AttachmentApiControllerTest extends TestCase {

	private $controller;
	private $fileService;
	private $noteService;
	private $attachMapper;
	private $shareService;
	private $previewManager;
	private $mimeTypeDetector;
	private FakeRequest $request;
	private $userId = 'john';

	protected function setUp(): void {
		$this->request = new FakeRequest();
		$this->fileService = $this->createMock(FileService::class);
		$this->noteService = $this->createMock(NoteService::class);
		$this->attachMapper = $this->createMock(AttachMapper::class);
		$this->shareService = $this->createMock(ShareService::class);
		$this->previewManager = $this->createMock(IPreview::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->mimeTypeDetector->method('mimeTypeIcon')->willReturn('/core/img/filetypes/x.svg');

		$this->controller = new AttachmentApiController(
			'quicknotes',
			$this->request,
			$this->fileService,
			$this->noteService,
			$this->attachMapper,
			$this->shareService,
			$this->previewManager,
			$this->mimeTypeDetector,
			$this->createMock(LoggerInterface::class),
			$this->userId
		);
	}

	private function makeNote(int $id = 7): Note {
		$note = new Note();
		$note->setId($id);
		$note->setUserId('alice');
		$note->setTitle('A title');
		$note->setContent('A content');
		return $note;
	}

	private function makeAttach(int $noteId = 7, int $fileId = 123, string $userId = 'alice'): Attach {
		$attach = new Attach();
		$attach->setId(1);
		$attach->setNoteId($noteId);
		$attach->setFileId($fileId);
		$attach->setUserId($userId);
		return $attach;
	}

	public function testUploadNoFiles(): void {
		$this->request->files = [];

		$response = $this->controller->upload();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testUploadMultipleFiles(): void {
		$this->request->files = [
			'file1' => ['name' => 'a.png', 'tmp_name' => '/tmp/a', 'error' => UPLOAD_ERR_OK],
			'file2' => ['name' => 'b.png', 'tmp_name' => '/tmp/b', 'error' => UPLOAD_ERR_OK],
		];

		$response = $this->controller->upload();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testUploadWithError(): void {
		$this->request->files = [
			'file' => [
				'name'     => 'a.png',
				'tmp_name' => '/tmp/a',
				'error'    => UPLOAD_ERR_NO_FILE,
			],
		];

		$response = $this->controller->upload();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testUploadSuccess(): void {
		$tmpFile = tempnam(sys_get_temp_dir(), 'qn');
		file_put_contents($tmpFile, 'hello');

		$this->request->files = [
			'file' => [
				'name'     => 'a.png',
				'tmp_name' => $tmpFile,
				'error'    => UPLOAD_ERR_OK,
			],
		];

		$this->fileService->expects($this->once())
			->method('upload')
			->with('a.png', 'hello')
			->willReturn(123);
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('a.png');
		$file->method('getMimetype')->willReturn('image/png');
		$this->fileService->method('getFileOf')->with('john', 123)->willReturn($file);
		$this->previewManager->method('isAvailable')->with($file)->willReturn(true);
		$this->fileService->method('getPreviewUrl')
			->with(123, 512)
			->willReturn('https://example/preview/123');
		$this->fileService->method('getRedirectToFileUrl')
			->with(123)
			->willReturn('https://example/redirect/123');
		$this->fileService->method('getDeepLinkUrl')
			->with(123)
			->willReturn('https://example/deep/123');

		$response = $this->controller->upload();

		@unlink($tmpFile);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'file_id'       => 123,
			'basename'      => 'a.png',
			'mime'          => 'image/png',
			'has_preview'   => true,
			'preview_url'   => 'https://example/preview/123',
			'redirect_url'  => 'https://example/redirect/123',
			'deep_link_url' => 'https://example/deep/123',
		], $response->getData());
	}

	// preview / download ----------------------------------------------------

	/**
	 * The note is what grants access, so a note this user cannot see is a 404
	 * — and the attachment is never even looked up.
	 */
	public function testPreviewOfANoteTheUserCannotSee(): void {
		$this->noteService->expects($this->once())
			->method('get')
			->with($this->userId, 7)
			->willReturn(null);

		$this->attachMapper->expects($this->never())->method('findByNoteAndFileId');

		$response = $this->controller->preview(7, 123);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	/**
	 * Seeing a note does not make every file id in the instance readable: it
	 * has to be attached to *that* note.
	 */
	public function testPreviewOfAFileThatIsNotAttachedToTheNote(): void {
		$this->noteService->method('get')->willReturn($this->makeNote());
		$this->attachMapper->method('findByNoteAndFileId')
			->with(7, 999)
			->willThrowException(new DoesNotExistException('nope'));

		$this->fileService->expects($this->never())->method('getFileOf');

		$response = $this->controller->preview(7, 999);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	/** The file is read from the storage of whoever attached it. */
	public function testPreviewReadsTheFileOfTheAttacher(): void {
		$file = $this->createMock(File::class);
		$preview = $this->createMock(ISimpleFile::class);
		$preview->method('getMimeType')->willReturn('image/png');

		$this->noteService->method('get')->willReturn($this->makeNote());
		$this->attachMapper->method('findByNoteAndFileId')
			->willReturn($this->makeAttach(7, 123, 'alice'));

		$this->fileService->expects($this->once())
			->method('getFileOf')
			->with('alice', 123)
			->willReturn($file);

		$this->previewManager->method('isAvailable')->with($file)->willReturn(true);
		$this->previewManager->expects($this->once())
			->method('getPreview')
			->with($file, 512, 512, true)
			->willReturn($preview);

		$response = $this->controller->preview(7, 123);

		$this->assertInstanceOf(FileDisplayResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	/**
	 * An attachment of somebody who has since been dropped from the note is
	 * not served to its audience any more.
	 */
	public function testPreviewOfAnAttachmentOfSomebodyWhoIsOutOfTheNote(): void {
		$this->noteService->method('get')->willReturn($this->makeNote());
		$this->attachMapper->method('findByNoteAndFileId')
			->willReturn($this->makeAttach(7, 123, 'carol'));
		$this->shareService->method('canSee')->with('carol', $this->anything())->willReturn(false);

		$this->fileService->expects($this->never())->method('getFileOf');

		$response = $this->controller->preview(7, 123);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	/** One of somebody who is still part of it, on the other hand, is. */
	public function testPreviewOfAnAttachmentOfACollaborator(): void {
		$file = $this->createMock(File::class);
		$preview = $this->createMock(ISimpleFile::class);

		$this->noteService->method('get')->willReturn($this->makeNote());
		$this->attachMapper->method('findByNoteAndFileId')
			->willReturn($this->makeAttach(7, 123, 'carol'));
		$this->shareService->method('canSee')->willReturn(true);

		$this->fileService->expects($this->once())
			->method('getFileOf')
			->with('carol', 123)
			->willReturn($file);
		$this->previewManager->method('isAvailable')->willReturn(true);
		$this->previewManager->method('getPreview')->willReturn($preview);

		$response = $this->controller->preview(7, 123);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	/** A file the person who attached it has since deleted. */
	public function testPreviewOfAFileThatIsGone(): void {
		$this->noteService->method('get')->willReturn($this->makeNote());
		$this->attachMapper->method('findByNoteAndFileId')->willReturn($this->makeAttach());
		$this->fileService->method('getFileOf')->willReturn(null);

		$response = $this->controller->preview(7, 123);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	/**
	 * A pdf or a zip has no preview, and `/core/preview` answers those with the
	 * icon of the file type. Answering 404 instead would leave the tile of the
	 * grid blank, which is what it looked like before the app served its own.
	 */
	public function testPreviewOfSomethingWithoutAPreviewFallsBackToTheMimeIcon(): void {
		$file = $this->createMock(File::class);
		$file->method('getMimetype')->willReturn('application/pdf');

		$this->noteService->method('get')->willReturn($this->makeNote());
		$this->attachMapper->method('findByNoteAndFileId')->willReturn($this->makeAttach());
		$this->fileService->method('getFileOf')->willReturn($file);
		$this->previewManager->method('isAvailable')->willReturn(false);

		$this->previewManager->expects($this->never())->method('getPreview');
		$this->mimeTypeDetector->expects($this->once())
			->method('mimeTypeIcon')
			->with('application/pdf');

		$response = $this->controller->preview(7, 123);

		$this->assertInstanceOf(RedirectResponse::class, $response);
		$this->assertSame('/core/img/filetypes/x.svg', $response->getRedirectUrl());
	}

	/** And the same when the preview manager tries and fails. */
	public function testPreviewFallsBackToTheMimeIconWhenGenerationFails(): void {
		$file = $this->createMock(File::class);
		$file->method('getMimetype')->willReturn('application/pdf');

		$this->noteService->method('get')->willReturn($this->makeNote());
		$this->attachMapper->method('findByNoteAndFileId')->willReturn($this->makeAttach());
		$this->fileService->method('getFileOf')->willReturn($file);
		$this->previewManager->method('isAvailable')->willReturn(true);
		$this->previewManager->method('getPreview')
			->willThrowException(new \RuntimeException('no provider handled it'));

		$response = $this->controller->preview(7, 123);

		$this->assertInstanceOf(RedirectResponse::class, $response);
	}

	/** The size is what the caller asked for, within reason. */
	public function testPreviewSizeIsClamped(): void {
		$file = $this->createMock(File::class);
		$preview = $this->createMock(ISimpleFile::class);

		$this->noteService->method('get')->willReturn($this->makeNote());
		$this->attachMapper->method('findByNoteAndFileId')->willReturn($this->makeAttach());
		$this->fileService->method('getFileOf')->willReturn($file);
		$this->previewManager->method('isAvailable')->willReturn(true);

		$this->previewManager->expects($this->once())
			->method('getPreview')
			->with($file, 1024, 32, true)
			->willReturn($preview);

		$this->controller->preview(7, 123, 40000, 1);
	}

	public function testDownloadServesTheFile(): void {
		$file = $this->createMock(File::class);
		$file->method('getMimeType')->willReturn('application/pdf');
		$file->method('getName')->willReturn('the report.pdf');
		// FileDisplayResponse reads both to set its caching headers, and a
		// mock that answers null to getMTime() makes DateTime complain.
		$file->method('getEtag')->willReturn('abc123');
		$file->method('getMTime')->willReturn(1700000000);

		$this->noteService->method('get')->willReturn($this->makeNote());
		$this->attachMapper->method('findByNoteAndFileId')->willReturn($this->makeAttach());
		$this->fileService->method('getFileOf')->willReturn($file);

		$response = $this->controller->download(7, 123);

		$this->assertInstanceOf(FileDisplayResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$headers = $response->getHeaders();
		$this->assertSame('application/pdf', $headers['Content-Type']);
		$this->assertStringContainsString('the%20report.pdf', $headers['Content-Disposition']);
	}

	public function testDownloadOfANoteTheUserCannotSee(): void {
		$this->noteService->method('get')->willReturn(null);

		$response = $this->controller->download(7, 123);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	// info -------------------------------------------------------------------

	public function testInfoUnknownPath(): void {
		$this->fileService->expects($this->once())
			->method('getFileIdByPath')
			->with('/Photos/gone.png')
			->willReturn(null);

		$response = $this->controller->info('/Photos/gone.png');

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testInfoSuccess(): void {
		$this->fileService->expects($this->once())
			->method('getFileIdByPath')
			->with('/Photos/birdie.jpg')
			->willReturn(456);
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('birdie.jpg');
		$file->method('getMimetype')->willReturn('image/jpeg');
		$this->fileService->method('getFileOf')->with('john', 456)->willReturn($file);
		$this->previewManager->method('isAvailable')->with($file)->willReturn(true);
		$this->fileService->method('getPreviewUrl')
			->with(456, 512)
			->willReturn('https://example/preview/456');
		$this->fileService->method('getRedirectToFileUrl')
			->with(456)
			->willReturn('https://example/redirect/456');
		$this->fileService->method('getDeepLinkUrl')
			->with(456)
			->willReturn('https://example/deep/456');

		$response = $this->controller->info('/Photos/birdie.jpg');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'file_id'       => 456,
			'basename'      => 'birdie.jpg',
			'mime'          => 'image/jpeg',
			'has_preview'   => true,
			'preview_url'   => 'https://example/preview/456',
			'redirect_url'  => 'https://example/redirect/456',
			'deep_link_url' => 'https://example/deep/456',
		], $response->getData());
	}

}
