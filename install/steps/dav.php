<?php

// api
include_once WM_INSTALLER_PATH.'../libraries/afterlogic/api.php';

class CDavStep extends AInstallerStep
{
	/**
	 * @var api_Settings
	 */
	protected $oSettings;

	public function __construct()
	{
		$this->oSettings =& CApi::GetSettings();
	}

	public function DoPost()
	{
		$bResult = false;
		if (CPost::Has('next_btn'))
		{
			/* @var $oApiDavManager CApiDavManager */
			$oApiDavManager = CApi::Manager('dav');
			
			if ($oApiDavManager)
			{
				$this->oSettings->SetConf('WebMail/ExternalHostNameOfDAVServer',
					CPost::get('txtCalDAVUrl', $oApiDavManager->getServerUrl()));

				$this->oSettings->SetConf('WebMail/ExternalHostNameOfLocalImap',
					CPost::get('txtIMAPHostName', $this->oSettings->GetConf('WebMail/ExternalHostNameOfLocalImap')));

				$this->oSettings->SetConf('WebMail/ExternalHostNameOfLocalSmtp',
					CPost::get('txtSMTPHostName', $this->oSettings->GetConf('WebMail/ExternalHostNameOfLocalSmtp')));

				$bResult =
					$oApiDavManager->setMobileSyncEnable((bool) CPost::get('chEnableMobileSync', false)) &&
					$this->oSettings->SaveToXml()
				;
			}
		}

		return $bResult;
	}

	public function TemplateValues()
	{
		$sDavError = '';
		if (isset($_SESSION['wm_install_sync_error']))
		{
			$sDavError = $_SESSION['wm_install_sync_error'];
			$sDavError = empty($sDavError) ? '' : $sDavError.'<br />';
			unset($_SESSION['wm_install_sync_error']);
		}

		$sAuto = false;

		/* @var $oApiDavManager CApiDavManager */
		$oApiDavManager = CApi::Manager('dav');

		$bEnableMobileSync = $oApiDavManager->isMobileSyncEnabled();

		$sDAVUrl = $oApiDavManager->getServerUrl();
		if (empty($sDAVUrl))
		{
			$sAuto = true;
			$sRequestUri = api_Utils::RequestUri();
			$iPos = strpos($sRequestUri, '/install/');
			if (false !== $iPos)
			{
				$oHttp = new api_Http();
				$sDAVUrl = $oHttp->GetScheme().'://'.$oHttp->GetHost().
					substr($sRequestUri, 0, $iPos).'/dav/server.php/';
			}
		}

		return array(
			'MobyleSyncChecked' => ($bEnableMobileSync) ? ' checked="checked" ' : '',
			'AutoClass' => $sAuto ? '' : 'wm_hide',
			'CalDAVUrl' => $sDAVUrl,
			'DavError' => $sDavError,
			'IMAPHostName' => $this->oSettings->GetConf('WebMail/ExternalHostNameOfLocalImap'),
			'SMTPHostName' => $this->oSettings->GetConf('WebMail/ExternalHostNameOfLocalSmtp')
		);
	}
}