<?php
/**
 * Nextcloud - quicknotes
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 * @copyright Matias De lellis 2019
 */

namespace OCA\QuickNotes\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;

use OCA\QuickNotes\Db\Tag;
use OCA\QuickNotes\Db\TagMapper;

class TagController extends Controller {

	private $tagmapper;
	private $userId;

	public function __construct($AppName,
	                            IRequest  $request,
	                            TagMapper $tagmapper,
	                            $UserId)
	{
		parent::__construct($AppName, $request);
		$this->tagmapper = $tagmapper;
		$this->userId = $UserId;
	}

	/**
	 * @NoAdminRequired
	 */
	 public function index() {
		$notes = $this->tagmapper->findAll($this->userId);
		return new DataResponse($notes);
	}

}
