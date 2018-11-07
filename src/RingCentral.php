<?php

namespace Coxy121\RingCentralLaravel;

use Coxy121\RingCentralLaravel\Exceptions\CouldNotAuthenticate;
use Coxy121\RingCentralLaravel\Exceptions\CouldNotSendMessage;
use RingCentral\SDK\SDK;

class RingCentral
{
	/** @var string */
	protected $ringCentral;

	/** @var string */
    protected $serverUrl;

    /** @var string */
    protected $clientId;

    /** @var string */
    protected $clientSecret;

    /** @var string */
    protected $username;

    /** @var string */
    protected $operatorExtension;

    /** @var string */
    protected $operatorPassword;

    /** @var string */
    protected $adminExtension;

    /** @var string */
    protected $adminPassword;

    /** @var string */
    protected $loggedInExtension;

    /** @var string */
    protected $loggedInExtensionId;

	public function setClientId(string $clientId)
    {
        $this->clientId = $clientId;
        return $this;
    }

	public function setClientSecret(string $clientSecret)
    {
        $this->clientSecret = $clientSecret;
        return $this;
    }

    public function setServerUrl(string $serverUrl)
    {
        $this->serverUrl = $serverUrl;
        return $this;
    }

	public function setUsername(string $username)
    {
        $this->username = $username;
        return $this;
    }

    public function setOperatorExtension(string $operatorExtension)
    {
        $this->operatorExtension = $operatorExtension;
        return $this;
    }

    public function setOperatorPassword(string $operatorPassword)
    {
        $this->operatorPassword = $operatorPassword;
        return $this;
    }

	public function setAdminExtension(string $adminExtension)
    {
        $this->adminExtension = $adminExtension;
        return $this;
    }

    public function setAdminPassword(string $adminPassword)
    {
        $this->adminPassword = $adminPassword;
        return $this;
    }

	public function clientId()
	{
		return $this->clientId;
	}

	public function clientSecret()
	{
		return $this->clientSecret;
	}

	public function serverUrl()
	{
		return $this->serverUrl;
	}

	public function username()
	{
		return $this->username;
	}

	public function operatorExtension()
	{
		return $this->operatorExtension;
	}

	public function operatorPassword()
	{
		return $this->operatorPassword;
	}

	public function adminExtension()
	{
		return $this->adminExtension ? : $this->operatorExtension;
	}

	public function adminPassword()
	{
		return $this->adminPassword ?: $this->operatorPassword;
	}

	public function connect()
	{
		$this->ringCentral = (new SDK($this->clientId(), $this->clientSecret(), $this->serverUrl()))->platform();
	}

	public function loginOperator()
	{
		$this->login($this->username(), $this->operatorExtension(), $this->operatorPassword());
	}

	public function loginAdmin()
	{
		$this->login($this->username(), $this->adminExtension(), $this->adminPassword());
	}

	public function login(string $username, string $extension, string $password)
	{
		$this->ringCentral->login($username, $extension, $password);

		$this->setLoggedInExtension();

	}

	public function setLoggedInExtension()
	{
		$extension = $this->ringCentral->get('/account/~/extension/~/')->json();
		$this->loggedInExtensionId = $extension->id;
		$this->loggedInExtension = $extension->extensionNumber;
	}

	public function loggedInExtensionId()
	{
		return $this->loggedInExtensionId;
	}

	public function loggedInExtension()
	{
		return $this->loggedInExtension;
	}

	public function authenticateOperator()
	{
		if(!$this->ringCentral) {
			$this->connect();
		}

		if(!$this->operatorLoggedIn()) {
			$this->loginOperator();
		}

		if(!$this->ringCentral->loggedIn()) {
            throw CouldNotAuthenticate::operatorLoginFailed();
        }

		return true;
	}

	public function operatorLoggedIn()
	{
		if($this->ringCentral->loggedIn()) {
			return $this->loggedInExtension() === $this->operatorExtension();
		}

		return false;
	}

	public function authenticateAdmin()
	{
		if(!$this->ringCentral) {
			$this->connect();
		}

		if(!$this->adminLoggedIn()) {
			$this->loginAdmin();
		}

        if(!$this->ringCentral->loggedIn()) {
            throw CouldNotAuthenticate::adminLoginFailed();
        }

        return true;
	}

	public function adminLoggedIn()
	{
		if($this->ringCentral->loggedIn()) {
			return $this->ringCentral->get('/account/~/extension/~/')->json()->extensionNumber === $this->adminExtension();
		}

		return false;
	}

    public function sendMessage(array $message)
	{
	    if(empty($message['to'])) {
            throw CouldNotSendMessage::toNumberNotProvided();
        }

        if(empty($message['text'])) {
            throw CouldNotSendMessage::textNotProvided();
        }

		$this->authenticateOperator();

        return $this->ringCentral->post('/account/~/extension/~/sms', [
                'from' => ['phoneNumber' => $this->username()],
                'to' => [
                    ['phoneNumber' => $message['to']],
                ],
                'text' => $message['text']
            ]);

	}

	public function getExtensions()
	{
		$this->authenticateAdmin();

		$r = $this->ringCentral->get('/account/~/extension');

		return $r->json()->records;
	}

	public function getMessages(string $extensionId = '~', ?object $fromDate = NULL)
	{
        $r = $this->ringCentral->get('/account/~/extension/'.$extensionId.'/message-store', [
			 	 'dateFrom' => $fromDate ? $fromDate->format("Y-m-d\TH:i:s.z\Z") : null,
               ]);

        return $r->json()->records;
	}

	public function getOperatorMessages(?object $fromDate = NULL)
	{
		$this->authenticateOperator();

		return $this->getMessages('~', $fromDate);
	}

	public function getMessagesForExtensionId(string $extensionId, ?object $fromDate = NULL)
	{
		$this->authenticateAdmin();

		return $this->getMessages($extensionId, $fromDate);
	}

    public function getMessageAttachmentById(string $extensionId, string $messageId, string $attachementId)
    {
        $this->authenticateAdmin();

        return $this->ringCentral->get('/account/~/extension/'.$extensionId.'/message-store/' . $messageId . '/content/' . $attachementId);
	}
}