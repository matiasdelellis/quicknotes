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

use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Db\TaskMapper;

class NoteController extends Controller	{

	private $mapper;
	private $userId;

	public function __construct($AppName, IRequest $request, NoteMapper $mapper, $UserId) {
		parent::__construct($AppName, $request);
		$this->mapper = $mapper;
		$this->userId = $UserId;
	}

	/**
	 * @NoAdminRequired
	 */
	 public function index() {
		return new DataResponse($this->mapper->findAll($this->userId));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 */
	public function show($id) {
		try {
			return new DataResponse($this->mapper->find($id, $this->userId));
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $title
	 * @param string $content
	 */
	public function create($title, $content, $color = "#F7EB96") {
		$note = new Note();
		$note->setTitle($title);
		$note->setContent($content);
		$note->setColor($color);
		$note->setUserId($this->userId);
		return new DataResponse($this->mapper->insert($note));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param string $title
	 * @param string $content
	 */
	public function update($id, $title, $content, $color = "#F7EB96") {
		try {
			$note = $this->mapper->find($id, $this->userId);
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$note->setTitle($title);
		$note->setContent($content);
		$note->setColor($color);
		return new DataResponse($this->mapper->update($note));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 */
	public function destroy($id) {
		try {
			$note = $this->mapper->find($id, $this->userId);
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		/*$taskmapper = new TaskMapper($this->mapper->db);
		$tasks = $taskmapper->findAll($note);
		foreach ($tasks as $task) {
			$taskmapper->delete($task->id);
		}*/
		$this->mapper->delete($note);

		return new DataResponse($note);
	}
 }