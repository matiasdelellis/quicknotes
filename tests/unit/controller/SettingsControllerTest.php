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

use OCA\QuickNotes\Service\SettingsService;


class SettingsControllerTest extends TestCase {

	private $controller;
	private $settingsService;
	private $userId = 'john';

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->settingsService = $this->createMock(SettingsService::class);

		$this->controller = new SettingsController(
			'quicknotes', $request, $this->settingsService, $this->userId
		);
	}

	public function testSetUserValueColor(): void {
		$this->settingsService->expects($this->once())
			->method('setColorForNewNotes')
			->with('#abcdef');

		$response = $this->controller->setUserValue(
			SettingsService::COLOR_FOR_NEW_NOTES_KEY,
			'#abcdef'
		);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame([
			'status' => SettingsController::STATE_SUCCESS,
			'value'  => '#abcdef',
		], $response->getData());
	}

	public function testSetUserValueUnknownKey(): void {
		$this->settingsService->expects($this->never())
			->method('setColorForNewNotes');

		$response = $this->controller->setUserValue('not_a_real_key', 'x');

		$this->assertSame([
			'status' => SettingsController::STATE_ERROR,
			'value'  => 'x',
		], $response->getData());
	}

	public function testGetUserValueColor(): void {
		$this->settingsService->expects($this->once())
			->method('getColorForNewNotes')
			->willReturn('#123456');

		$response = $this->controller->getUserValue(
			SettingsService::COLOR_FOR_NEW_NOTES_KEY
		);

		$this->assertSame([
			'status' => SettingsController::STATE_OK,
			'value'  => '#123456',
		], $response->getData());
	}

	public function testGetUserValueUnknownKey(): void {
		$this->settingsService->expects($this->never())
			->method('getColorForNewNotes');

		$response = $this->controller->getUserValue('not_a_real_key');

		$this->assertSame([
			'status' => SettingsController::STATE_FALSE,
			'value'  => 'nodata',
		], $response->getData());
	}

}
