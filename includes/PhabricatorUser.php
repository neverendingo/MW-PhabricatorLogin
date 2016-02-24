<?php

class PhabricatorUser
{
	private $userId;
	
	private $userName;
	
	private $remoteId;
	
	private $accessToken = null;
	
	private $timestamp = null;
	
	private $resourceOwner;
	
	public function __construct( $remoteId, $userId, $userName, $token, $timestamp = null ) {
		$this->remoteId = $remoteId;
		$this->userId = $userId;
		$this->userName = $userName;
		$this->timestamp = $timestamp;
		$this->accessToken = $token;
	}
	
	public static function newFromRemoteId( $remoteId, $userName, $token, $db ) {
		$row = $db->selectRow(
				'phab_user',
				array( 'eu_external_id', 'eu_local_id', 'eu_username', 'eu_token', 'eu_timestamp' ),
				array( 'eu_external_id' => $remoteId ),
				__METHOD__
				);
		
		if ( !$row ) {
			return new self( $remoteId, 0, $userName, $token );
		} else {
			return new self( $remoteId, $row->eu_local_id, $row->eu_username, $token, $row->eu_timestamp );
		}
	}
	
	public static function newFromName( \User $user, $remoteId, $name, $token, $db ) {
		$row = $db->selectRow(
				'phab_user',
				array( 'eu_external_id', 'eu_local_id', 'eu_username', 'eu_token', 'eu_timestamp' ),
				array( 'eu_username' => $name ),
				__METHOD__
				);
		
		if ( !$row ) {
			return false;
		} else {
			return new self( $remoteId, $row->eu_local_id, $row->eu_username, $token, $eu_timestamp );
		}
	}
	
	public function addToDatabase( $db ) {
		$row = array(
			'eu_external_id' => $this->remoteId,
			'eu_local_id' => $this->userId,
			'eu_username' => $this->userName,
		);
		
		if ( $this->accessToken ) {
			$row += array(
				'eu_token' => $this->accessToken
			);
		}
		
		if ( $this->timestamp ) {
			$row += array(
				'eu_timestamp' => $db->timestampOrNull( (string)$this->timestamp ),
			);
		}
		
		$db->insert(
			'phab_user',
			$row,
			__METHOD__
		);
	}
	
	public function updateInDatabase( $db ) {
		if ( !$this->userId > 0 ) {
			throw new Exception( 'Error, this user does already exist in DB' );
		}
		$row = array(
			'eu_external_id' => $this->remoteId,
			'eu_username' => $this->userName,
		);
		
		if ( $this->accessToken ) {
			$row += array(
					'eu_token' => $this->accessToken
			);
		}
		
		if ( $this->timestamp ) {
			$row += array(
				'eu_timestamp' => $db->timestampOrNull( (string)$this->timestamp ),
			);
		}
		
		$db->update(
			'phab_user',
			/* SET */ $row,
			/* WHERE */ array( 'eu_local_id' => $this->userId ),
			__METHOD__
		);
	}
	
	public function getName() {
		return $this->userName;
	}
	
	public function getLocalId() {
		return $this->userId;
	}
	
	public function getRemoteId() {
		return $this->remoteId;
	}
	
	public function setLocalId( $id ) {
		$this->userId = $id;
	}
	
	public function setAccessToken( $accessToken ) {
		$this->accessToken = $accessToken;
	}
	
	public function setTimestamp( \MWTimestamp $ts ) {
		$this->timestamp = $ts;
	}
}