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
use OCA\QuickNotes\Db\Color;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Db\ColorMapper;

class NoteController extends Controller {

	private $notemapper;
	private $colormapper;
	private $userId;

	public function __construct($AppName, IRequest $request, NoteMapper $notemapper, ColorMapper $colormapper, $UserId) {
		parent::__construct($AppName, $request);
		$this->notemapper = $notemapper;
		$this->colormapper = $colormapper;
		$this->userId = $UserId;
	}

	/**
	 * @NoAdminRequired
	 */
	 public function index() {
		$notes = $this->notemapper->findAll($this->userId);
		// Insert true color to response
		foreach ($notes as $note) {
			$note->setColor($this->colormapper->find($note->getColorId())->getColor());
		}
		return new DataResponse($notes);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 */
	public function show($id) {
		// TODO: Implement.
		try {
			return new DataResponse($this->notemapper->find($id, $this->userId));
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
		// Get color or append it
		if ($this->colormapper->colorExists($color)) {
			$hcolor = $this->colormapper->findByColor($color);
		} else {
			$hcolor = new Color();
			$hcolor->setColor($color);
			$hcolor = $this->colormapper->insert($hcolor);
		}

		// Create note and insert it
		$note = new Note();
		$note->setTitle($title);
		$note->setContent($content);
		$note->setTimestamp(time());
		$note->setColorId($hcolor->id);
		$note->setUserId($this->userId);

		// Insert true color to response
		$note->setColor($hcolor->getColor());

		return new DataResponse($this->notemapper->insert($note));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param string $title
	 * @param string $content
	 * @param string $color
	 */
	public function update($id, $title, $content, $color = "#F7EB96") {
		// Get Note
		try {
			$note = $this->notemapper->find($id, $this->userId);
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// Get color or append it
		if ($this->colormapper->colorExists($color)) {
			$hcolor = $this->colormapper->findByColor($color);
		} else {
			$hcolor = new Color();
			$hcolor->setColor($color);
			$hcolor = $this->colormapper->insert($hcolor);
		}

		// TODO: Remove old color if necessary

		/* Update note */
		$note->setTitle($title);
		$note->setContent($content);
		$note->setTimestamp(time());
		$note->setColorId($hcolor->id);

		// Insert true color to response
		$note->setColor($hcolor->getColor());

		return new DataResponse($this->notemapper->update($note));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 */
	public function destroy($id) {
		try {
			$note = $this->notemapper->find($id, $this->userId);
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// TODO: Remove old color if necessary.

		return new DataResponse($note);
	}
 }