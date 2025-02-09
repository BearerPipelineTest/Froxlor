<?php
namespace Froxlor\Api\Commands;

use Froxlor\Database\Database;
use Froxlor\Settings;

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * For the full copyright and license information, please view the COPYING
 * file that was distributed with this source code. You can also view the
 * COPYING file online at http://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright (c) the authors
 * @author Froxlor team <team@froxlor.org> (2010-)
 * @license GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @package API
 * @since 0.10.0
 *       
 */
class EmailForwarders extends \Froxlor\Api\ApiCommand implements \Froxlor\Api\ResourceEntity
{

	/**
	 * add new email-forwarder entry for given email-address by either id or email-address
	 *
	 * @param int $id
	 *        	optional, the email-address-id
	 * @param string $emailaddr
	 *        	optional, the email-address to add the forwarder for
	 * @param int $customerid
	 *        	optional, required when called as admin (if $loginname is not specified)
	 * @param string $loginname
	 *        	optional, required when called as admin (if $customerid is not specified)
	 * @param string $destination
	 *        	email-address to add as forwarder
	 *        	
	 * @access admin,customer
	 * @throws \Exception
	 * @return string json-encoded array
	 */
	public function add()
	{
		if ($this->isAdmin() == false && Settings::IsInList('panel.customer_hide_options', 'email')) {
			throw new \Exception("You cannot access this resource", 405);
		}

		if ($this->getUserDetail('email_forwarders_used') < $this->getUserDetail('email_forwarders') || $this->getUserDetail('email_forwarders') == '-1') {

			// parameter
			$id = $this->getParam('id', true, 0);
			$ea_optional = ($id <= 0 ? false : true);
			$emailaddr = $this->getParam('emailaddr', $ea_optional, '');
			$destination = $this->getParam('destination');

			// validation
			$idna_convert = new \Froxlor\Idna\IdnaWrapper();
			$destination = $idna_convert->encode($destination);

			$result = $this->apiCall('Emails.get', array(
				'id' => $id,
				'emailaddr' => $emailaddr
			));
			$id = $result['id'];

			// current destination array
			$result['destination_array'] = explode(' ', ($result['destination'] ?? ''));

			// prepare destination
			$destination = trim($destination);

			if (! \Froxlor\Validate\Validate::validateEmail($destination)) {
				\Froxlor\UI\Response::standard_error('destinationiswrong', $destination, true);
			} elseif ($destination == $result['email']) {
				\Froxlor\UI\Response::standard_error('destinationalreadyexistasmail', $destination, true);
			} elseif (in_array($destination, $result['destination_array'])) {
				\Froxlor\UI\Response::standard_error('destinationalreadyexist', $destination, true);
			}

			// get needed customer info to reduce the email-forwarder-counter by one
			$customer = $this->getCustomerData('email_forwarders');

			// add destination to address
			$result['destination'] .= ' ' . $destination;
			$stmt = Database::prepare("
				UPDATE `" . TABLE_MAIL_VIRTUAL . "` SET `destination` = :dest
				WHERE `customerid`= :cid AND `id`= :id
			");
			$params = array(
				"dest" => \Froxlor\FileDir::makeCorrectDestination($result['destination']),
				"cid" => $customer['customerid'],
				"id" => $id
			);
			Database::pexecute($stmt, $params, true, true);

			// update customer usage
			Customers::increaseUsage($customer['customerid'], 'email_forwarders_used');

			$this->logger()->logAction($this->isAdmin() ? \Froxlor\FroxlorLogger::ADM_ACTION : \Froxlor\FroxlorLogger::USR_ACTION, LOG_INFO, "[API] added email forwarder for '" . $result['email_full'] . "'");

			$result = $this->apiCall('Emails.get', array(
				'emailaddr' => $result['email_full']
			));
			return $this->response(200, "successful", $result);
		}
		throw new \Exception("No more resources available", 406);
	}

	/**
	 * You cannot directly get an email forwarder.
	 * Try EmailForwarders.listing()
	 */
	public function get()
	{
		throw new \Exception('You cannot directly get an email forwarder. Try EmailForwarders.listing()', 303);
	}

	/**
	 * You cannot update an email forwarder.
	 * You need to delete the entry and create a new one.
	 */
	public function update()
	{
		throw new \Exception('You cannot update an email forwarder. You need to delete the entry and create a new one.', 303);
	}

	/**
	 * List email forwarders for a given email address
	 *
	 * @param int $id
	 *        	optional, the email-address-id
	 * @param string $emailaddr
	 *        	optional, the email-address to delete the forwarder from
	 * @param int $customerid
	 *        	optional, admin-only, the customer-id
	 * @param string $loginname
	 *        	optional, admin-only, the loginname
	 *        	
	 * @access admin,customer
	 * @throws \Exception
	 * @return string json-encoded array count|list
	 */
	public function listing()
	{
		if ($this->isAdmin() == false && Settings::IsInList('panel.customer_hide_options', 'email')) {
			throw new \Exception("You cannot access this resource", 405);
		}

		// parameter
		$id = $this->getParam('id', true, 0);
		$ea_optional = ($id <= 0 ? false : true);
		$emailaddr = $this->getParam('emailaddr', $ea_optional, '');

		// validation
		$result = $this->apiCall('Emails.get', array(
			'id' => $id,
			'emailaddr' => $emailaddr
		));
		$id = $result['id'];

		$result['destination'] = explode(' ', $result['destination']);
		$destination = array();
		foreach ($result['destination'] as $index => $address) {
			$destination[] = [
				'id' => $index,
				'address' => $address
			];
		}

		return $this->response(200, "successful", [
			'count' => count($destination),
			'list' => $destination
		]);
	}

	/**
	 * count email forwarders for a given email address
	 *
	 * @param int $id
	 *        	optional, the email-address-id
	 * @param string $emailaddr
	 *        	optional, the email-address to delete the forwarder from
	 * @param int $customerid
	 *        	optional, admin-only, the customer-id
	 * @param string $loginname
	 *        	optional, admin-only, the loginname
	 *        	
	 * @access admin,customer
	 * @throws \Exception
	 * @return string json-encoded array
	 */
	public function listingCount()
	{
		if ($this->isAdmin() == false && Settings::IsInList('panel.customer_hide_options', 'email')) {
			throw new \Exception("You cannot access this resource", 405);
		}

		// parameter
		$id = $this->getParam('id', true, 0);
		$ea_optional = ($id <= 0 ? false : true);
		$emailaddr = $this->getParam('emailaddr', $ea_optional, '');

		// validation
		$result = $this->apiCall('Emails.get', array(
			'id' => $id,
			'emailaddr' => $emailaddr
		));
		$id = $result['id'];

		$result['destination'] = explode(' ', $result['destination']);

		return $this->response(200, "successful", count($result['destination']));
	}

	/**
	 * delete email-forwarder entry for given email-address by either id or email-address and forwarder-id
	 *
	 * @param int $id
	 *        	optional, the email-address-id
	 * @param string $emailaddr
	 *        	optional, the email-address to delete the forwarder from
	 * @param int $customerid
	 *        	optional, required when called as admin (if $loginname is not specified)
	 * @param string $loginname
	 *        	optional, required when called as admin (if $customerid is not specified)
	 * @param int $forwarderid
	 *        	id of the forwarder to delete
	 *        	
	 * @access admin,customer
	 * @throws \Exception
	 * @return string json-encoded array
	 */
	public function delete()
	{
		if ($this->isAdmin() == false && Settings::IsInList('panel.customer_hide_options', 'email')) {
			throw new \Exception("You cannot access this resource", 405);
		}

		// parameter
		$id = $this->getParam('id', true, 0);
		$ea_optional = ($id <= 0 ? false : true);
		$emailaddr = $this->getParam('emailaddr', $ea_optional, '');
		$forwarderid = $this->getParam('forwarderid');

		// validation
		$result = $this->apiCall('Emails.get', array(
			'id' => $id,
			'emailaddr' => $emailaddr
		));
		$id = $result['id'];

		$result['destination'] = explode(' ', $result['destination']);
		if (isset($result['destination'][$forwarderid]) && $result['email'] != $result['destination'][$forwarderid]) {

			// get needed customer info to reduce the email-forwarder-counter by one
			$customer = $this->getCustomerData();

			// unset it from array
			unset($result['destination'][$forwarderid]);
			// rebuild destination-string
			$result['destination'] = implode(' ', $result['destination']);
			// update in DB
			$stmt = Database::prepare("
				UPDATE `" . TABLE_MAIL_VIRTUAL . "` SET `destination` = :dest
				WHERE `customerid`= :cid AND `id`= :id
			");
			$params = array(
				"dest" => \Froxlor\FileDir::makeCorrectDestination($result['destination']),
				"cid" => $customer['customerid'],
				"id" => $id
			);
			Database::pexecute($stmt, $params, true, true);

			// update customer usage
			Customers::decreaseUsage($customer['customerid'], 'email_forwarders_used');

			$this->logger()->logAction($this->isAdmin() ? \Froxlor\FroxlorLogger::ADM_ACTION : \Froxlor\FroxlorLogger::USR_ACTION, LOG_INFO, "[API] deleted email forwarder for '" . $result['email_full'] . "'");

			$result = $this->apiCall('Emails.get', array(
				'emailaddr' => $result['email_full']
			));
			return $this->response(200, "successful", $result);
		}
		throw new \Exception("Unknown forwarder id", 404);
	}
}
