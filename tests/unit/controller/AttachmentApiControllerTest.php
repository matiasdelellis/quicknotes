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

use OCA\QuickNotes\Service\FileService;


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
	private FakeRequest $request;
	private $userId = 'john';

	protected function setUp(): void {
		$this->request = new FakeRequest();
		$this->fileService = $this->createMock(FileService::class);

		$this->controller = new AttachmentApiController(
			'quicknotes', $this->request, $this->fileService, $this->userId
		);
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
			'preview_url'   => 'https://example/preview/123',
			'redirect_url'  => 'https://example/redirect/123',
			'deep_link_url' => 'https://example/deep/123',
		], $response->getData());
	}

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
			'preview_url'   => 'https://example/preview/456',
			'redirect_url'  => 'https://example/redirect/456',
			'deep_link_url' => 'https://example/deep/456',
		], $response->getData());
	}

}
