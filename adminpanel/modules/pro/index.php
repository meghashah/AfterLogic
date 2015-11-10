<?php

//$bDisabled = true;
$iSortIndex = 80;
$sCurrentModule = 'CProModule';
class CProModule extends ap_Module
{
	/**
	* @var CApiDomainsManager
	*/
	protected $oDomainsApi;

	/**
	 * @var CApiWebmailManager
	 */
	protected $oWebmailApi;

	/**
	 * @var bool
	 */
	private $bHasWebmail;

	/**
	 * @var CApiLicensingManager
	 */
	protected $oLicApi;

	/**
	 * @var CApiTenantsManager
	 */
	protected $oTenantsApi;

	/**
	 * @var CApiChannelsManager
	 */
	protected $oChannelsApi;

	/**
	 * @var CApiUsersManager
	 */
	protected $oUsersApi;

	/**
	 * @var CApiCapabilityManager
	 */
	protected $oCapabilityApi;

	/**
	 * @param CAdminPanel $oAdminPanel
	 * @param string $sPath
	 * @return CProModule
	 */
	public function __construct(CAdminPanel &$oAdminPanel, $sPath)
	{
		parent::__construct($oAdminPanel, $sPath);

		$this->oDomainsApi = CApi::Manager('domains');
		$this->oLicApi = CApi::Manager('licensing');
		$this->oUsersApi = CApi::Manager('users');
		$this->oWebmailApi = CApi::Manager('webmail');
		$this->oCapabilityApi = CApi::Manager('capability');
		$this->oTenantsApi = CApi::Manager('tenants');

		$this->oChannelsApi = null;

		$this->bHasWebmail = false;

		$this->aTabs[] = AP_TAB_COMMON;
		$this->aTabs[] = AP_TAB_SYSTEM;

		if ($oAdminPanel->RType)
		{
			$this->aTabs[] = AP_TAB_TENANTS;
			$this->aTabs[] = AP_TAB_CHANNELS;

			$this->oChannelsApi = CApi::Manager('channels');
		}

		$this->aTabs[] = AP_TAB_DOMAINS;
		$this->aTabs[] = AP_TAB_USERS;

		$this->aQueryActions[] = 'new';
		$this->aQueryActions[] = 'edit';
		$this->aQueryActions[] = 'invite';

		$this->oPopulateData = new CProPopulateData($this);
		$this->oStandardPostAction = new CProPostAction($this);
		$this->oStandardPopAction = new CProPopAction($this);
		$this->oTableAjaxAction = new CProAjaxAction($this);

		$aTabs =& $oAdminPanel->GetTabs();
		array_push($aTabs,
			array(CApi::I18N('ADMIN_PANEL/TABNAME_COMMON'), AP_TAB_COMMON),
			array(CApi::I18N('ADMIN_PANEL/TABNAME_USERS'), AP_TAB_USERS),
			array(CApi::I18N('ADMIN_PANEL/TABNAME_TENANTS'), AP_TAB_TENANTS),
			array(CApi::I18N('ADMIN_PANEL/TABNAME_CHANNELS'), AP_TAB_CHANNELS)
		);
	}

	/**
	 * @param CAdminPanel $oAdminPanel
	 */
	public function InitAdminPanel(CAdminPanel &$oAdminPanel)
	{
		$oAdminPanel->PType = true;
		$oAdminPanel->LType = $this->oLicApi && $this->oLicApi->IsValidKey();

		$this->bHasWebmail = $oAdminPanel->IsModuleInit('CWebMailModule');

		if (!$oAdminPanel->LType)
		{
			$oAdminPanel->RemoveTabs(
				array(AP_TAB_COMMON, AP_TAB_DOMAINS, AP_TAB_USERS, AP_TAB_TENANTS, AP_TAB_CHANNELS)
			);
		}

		if (!$oAdminPanel->RType)
		{
			$oAdminPanel->RemoveTabs(
				array(AP_TAB_TENANTS, AP_TAB_CHANNELS)
			);
		}
	}

	/**
	 * @param string $sTab
	 * @param ap_Screen $oScreen
	 */
	protected function initStandardMenuByTab($sTab, ap_Screen &$oScreen)
	{
		switch ($sTab)
		{
			case AP_TAB_SYSTEM:
				$oScreen->AddMenuItem(PRO_MODE_LICESING, PRO_MODE_LICESING_NAME,
					$this->sPath.'/templates/licensing.php',
					array(),
					'db');
				break;

			case AP_TAB_COMMON:

				if ($this->oAdminPanel->IsTenantAuthType())
				{
					$oScreen->AddMenuItem(CM_MODE_TENANT, CM_MODE_TENANT_NAME,
						$this->sPath.'/templates/tenant.php');
				}

				if ($this->oAdminPanel->IsTenantAuthType())
				{
					$oScreen->AddMenuItem(CM_MODE_RESOURCE_USAGE, CM_MODE_RESOURCE_USAGE_NAME,
						$this->sPath.'/templates/resource-usage.php');
				}

				$oTenant = $this->GetTenantAdminObject();

				$oApiCapa = CApi::Manager('capability');
				/* @var $oApiCapa CApiCapabilityManager */

				if ($oApiCapa && $oApiCapa->isHelpdeskSupported() && $oTenant && (
					($this->oAdminPanel->IsTenantAuthType() && $oTenant->isHelpdeskSupported()) ||
					($this->oAdminPanel->IsSuperAdminAuthType() && !$oApiCapa->isTenantsSupported())
				))
				{
					$oScreen->AddMenuItem(CM_MODE_HELPDESK, CM_MODE_HELPDESK_NAME,
						$this->sPath.'/templates/helpdesk.php');
				}

				if ($oApiCapa && $oApiCapa->isTwilioSupported() && $oTenant)
				{
					if ($oTenant->IsDefault)
					{
						$oScreen->AddMenuItem(CM_MODE_TWILIO, CM_MODE_TWILIO_NAME,
							$this->sPath.'/templates/twilio.php');
					}
					else
					{
						if ($oTenant->TwilioAllowConfiguration && $oTenant->isTwilioSupported())
						{
							$oScreen->AddMenuItem(CM_MODE_TWILIO, CM_MODE_TWILIO_NAME,
								$this->sPath.'/templates/twilio.php');
						}
					}
				}

				if ($oApiCapa && $oApiCapa->isSipSupported() && $oTenant)
				{
					if ($oTenant->IsDefault)
					{
						$oScreen->AddMenuItem(CM_MODE_SIP, CM_MODE_SIP_NAME,
							$this->sPath.'/templates/sip.php');
					}
					else
					{
						if ($oTenant->SipAllowConfiguration && $oTenant->isSipSupported())
						{
							$oScreen->AddMenuItem(CM_MODE_SIP, CM_MODE_SIP_NAME,
								$this->sPath.'/templates/sip.php');
						}
					}
				}

				if ($this->oAdminPanel->IsSuperAdminAuthType() || $this->oAdminPanel->IsOnlyReadAuthType())
				{
					$oScreen->AddMenuItem(WM_MODE_DAV, WM_MODE_DAV_NAME,
						$this->sPath.'/templates/dav.php');
				}

				if (!$oApiCapa->isTenantsSupported())
				{
					$oScreen->AddMenuItem(CM_MODE_BRANDING, CM_MODE_BRANDING_NAME,
						$this->sPath.'/templates/branding-both.php');

					if ($this->oAdminPanel->AType)
					{
						$oScreen->AddMenuItem(CM_MODE_INVITATIONS, CM_MODE_INVITATIONS_NAME,
							$this->sPath.'/templates/invitations.php');
					}
				}
				else if (!$this->oAdminPanel->IsTenantAuthType() &&
					($this->oAdminPanel->IsSuperAdminAuthType() || $this->oAdminPanel->IsOnlyReadAuthType()))
				{
					$oScreen->AddMenuItem(CM_MODE_BRANDING, CM_MODE_BRANDING_NAME,
						$this->sPath.'/templates/branding-tenant-admin.php');
		
					$oScreen->AddMenuItem(CM_MODE_INVITATIONS, CM_MODE_INVITATIONS_NAME,
						$this->sPath.'/templates/invitations.php');
				}
				else if ($this->oAdminPanel->IsTenantAuthType() && $oTenant)
				{
					$oScreen->AddMenuItem(CM_MODE_BRANDING, CM_MODE_BRANDING_NAME,
						$this->sPath.'/templates/branding-tenant-user.php');
				}
				
				break;
		}
	}

	/**
	 * @param string $sTab
	 * @param ap_Screen $oScreen
	 */
	protected function initTableTopMenu($sTab, ap_Screen &$oScreen)
	{
		switch ($sTab)
		{
			case AP_TAB_DOMAINS:
				$this->JsAddFile('domains.js');
				
				$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_NEW_DOMAIN'), 'new-domain.png', 'IdDomainsNewDomainButton');
				$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_DELETE'), 'delete.gif', 'IdDomainsDeleteButton');
				break;

			case AP_TAB_TENANTS:
				$this->JsAddFile('tenants.js');

				$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_NEW_TENANT'), 'new_contact.gif', 'IdTenantsNewTenantButton');
				$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_DELETE'), 'delete.gif', 'IdTenantsDeleteButton');
				break;

			case AP_TAB_CHANNELS:
				$this->JsAddFile('channels.js');

				$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_NEW_CHANNEL'), 'new_contact.gif', 'IdChannelsNewChannelButton');
				$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_DELETE'), 'delete.gif', 'IdChannelsDeleteButton');
				break;

			case AP_TAB_USERS:
				
				$mDomainIndex = $oScreen->GetFilterIndex();
				$oDomain = $this->getDomain((int) $mDomainIndex);
				if ($oDomain)
				{
					$this->JsAddVar('AllowWebMail', $oDomain->AllowWebMail);
					$this->JsAddVar('IsAType', $this->oAdminPanel->AType ? '1' : '0');
				}
				$this->JsAddFile('users.js');

				$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_NEW_USER'), 'user_new.png', 'IdUsersNewUserButton');
				
				if ($oDomain && ($oDomain->IsDefaultTenantDomain || $oDomain->IsDefaultDomain) && $this->oAdminPanel->AType)
				{
					$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_INVITE_USER'), 'user_invite.png', 'IdUsersInviteUserButton');
				}
//				$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_ENABLE'), 'user_enable.png', 'IdUsersEnableUserButton');
				$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_DISABLE'), 'user_disable.png', 'IdUsersDisableUserButton');
				$oScreen->AddTopMenuButton(CApi::I18N('ADMIN_PANEL/TOOLBAR_BUTTON_DELETE'), 'delete.gif', 'IdUsersDeleteButton');
				break;
		}
	}

	/**
	 * @param string $sTab
	 * @param ap_Screen $oScreen
	 */
	protected function initTableListHeaders($sTab, ap_Screen &$oScreen)
	{
		$oScreen->SetEmptySearch(AP_LANG_RESULTEMPTY);
		switch ($sTab)
		{
			case AP_TAB_TENANTS:
				$oScreen->ClearHeaders();
				$oScreen->SetEmptyList(PRO_LANG_NOTENANTS);
				$oScreen->SetEmptySearch(PRO_LANG_TENANTS_RESULTEMPTY);

				$oScreen->AddHeader('Name', 120);
				$oScreen->AddHeader('Description', 120, true);
				break;

			case AP_TAB_CHANNELS:
				$oScreen->ClearHeaders();
				$oScreen->SetEmptyList(PRO_LANG_NOCHANNELS);
				$oScreen->SetEmptySearch(PRO_LANG_CHANNELS_RESULTEMPTY);

				$oScreen->AddHeader('Name', 120);
				$oScreen->AddHeader('Description', 120, true);
				break;

			case AP_TAB_USERS:
				$oScreen->ClearHeaders();
				$oScreen->AddHeader('Type', 42);
				$oScreen->AddHeader('Email', 150, true);
				$oScreen->AddHeader('Friendly name', 120);
				$oScreen->AddHeader('Last login', 100);
				$oScreen->SetEmptyList(CM_LANG_NOUSERSINDOMAIN);
				$oScreen->SetEmptySearch(CM_LANG_USERS_RESULTEMPTY);
				break;
		}

	}

	/**
	 * @param string $sTab
	 * @param ap_Screen $oScreen
	 */
	protected function initTableList($sTab, ap_Screen &$oScreen)
	{
		if (AP_TAB_TENANTS === $sTab && $this->oTenantsApi)
		{
			$sSearch = $oScreen->GetSearchDesc();

			$iCount = $this->oTenantsApi->getTenantCount($sSearch);
			$oScreen->SetAllListCount($iCount);

			$oScreen->SetLowToolBar(CApi::I18N('ADMIN_PANEL/TOOLBAR_COUNT_TENANTS').(empty($sSearch) ? $iCount : $this->oTenantsApi->getTenantCount()));

			$sOrderBy = $oScreen->GetOrderBy();
			$sOrderBy = 'Name' === $sOrderBy ? 'Login' : 'Description';

			$aTenantList = $this->oTenantsApi->getTenantList(
				$oScreen->GetPage(), $oScreen->GetLinesPerPage(),
				$sOrderBy, $oScreen->GetOrderType(), $sSearch);

			if (is_array($aTenantList) && 0 < count($aTenantList))
			{
				foreach ($aTenantList as $iTenantId => $aTenantArray)
				{
					$oScreen->AddListItem($iTenantId, array(
						'Name' => $aTenantArray[0],
						'Description' => $aTenantArray[1]
					));
				}
			}
		}
		else if (AP_TAB_CHANNELS === $sTab && $this->oChannelsApi)
		{
			$sSearch = $oScreen->GetSearchDesc();

			$iCount = $this->oChannelsApi->getChannelCount($sSearch);
			$oScreen->SetAllListCount($iCount);

			$oScreen->SetLowToolBar(CApi::I18N('ADMIN_PANEL/TOOLBAR_COUNT_CHANNELS').(empty($sSearch) ? $iCount : $this->oChannelsApi->getChannelCount()));

			$sOrderBy = $oScreen->GetOrderBy();
			$sOrderBy = 'Name' === $sOrderBy ? 'Login' : 'Description';

			$aChannelList = $this->oChannelsApi->getChannelList(
				$oScreen->GetPage(), $oScreen->GetLinesPerPage(),
				$sOrderBy, $oScreen->GetOrderType(), $sSearch);

			if (is_array($aChannelList) && 0 < count($aChannelList))
			{
				foreach ($aChannelList as $iChannelId => $aChannelArray)
				{
					$oScreen->AddListItem($iChannelId, array(
						'Name' => $aChannelArray[0],
						'Description' => $aChannelArray[1]
					));
				}
			}
		}
		else if (AP_TAB_USERS === $sTab)
		{
			$mDomainIndex = $oScreen->GetFilterIndex();
			if ($this->oAdminPanel->HasAccessDomain($mDomainIndex))
			{
				$sSearchDesc = $oScreen->GetSearchDesc();
				$iSearchCount = $this->oUsersApi->getUsersCountForDomain($mDomainIndex, $sSearchDesc);
				$oScreen->SetAllListCount($iSearchCount);

				$oScreen->SetLowToolBar(CApi::I18N('ADMIN_PANEL/TOOLBAR_COUNT_USERS').((empty($sSearchDesc))
					? $iSearchCount : $this->oUsersApi->getUsersCountForDomain($mDomainIndex)));

				$aUsersList = $this->oUsersApi->getUserList(
					$mDomainIndex, $oScreen->GetPage(), $oScreen->GetLinesPerPage(),
					$oScreen->GetOrderBy(), $oScreen->GetOrderType(), $oScreen->GetSearchDesc());

				if (is_array($aUsersList) && 0 < count($aUsersList))
				{
					foreach ($aUsersList as $iUserId => $aUserArray)
					{
						$sD = isset($aUserArray[6]) ? (string) $aUserArray[6] : '';
						if ('1970' === substr($sD, 0, 4))
						{
							$sD = '';
						}

						$oScreen->AddListItem($iUserId, array(
							'Type' => ($aUserArray[0])
								? '<img src="static/images/icons/M.gif">'
								: (($aUserArray[3])
									? '<img src="static/images/icons/U_disable.gif">'
									: '<img src="static/images/icons/U.gif">'
							),
							'Email' => $aUserArray[1],
							'Friendly name' => $aUserArray[2],
							'Last login' => $sD
						));
					}
				}
			}
		}
	}

	/**
	* @param string $sTab
	* @param ap_Screen $oScreen
	*/
	protected function initTableListFilter($sTab, ap_Screen &$oScreen)
	{
		if (AP_TAB_USERS === $sTab)
		{
			$iTenantId = $this->oAdminPanel->RType() ? $this->oAdminPanel->TenantId() : 0;

			$oScreen->InitFilter(CApi::I18N('ADMIN_PANEL/INFO_DOMAIN_FILTER'));
			if ($this->oAdminPanel->HasAccessDomain(0) && /*!$this->oAdminPanel->XType &&*/ 0 === $iTenantId)
			{
				$oScreen->AddFilter('0', AP_WITHOUT_DOMAIN_NAME, 'domWebMail');
			}

			$aFilters = $this->oDomainsApi->GetFilterList($iTenantId);
			if (is_array($aFilters))
			{
				foreach ($aFilters as $mIndex => $aValues)
				{
					$oTenant = null;
					$sName = isset($aValues[1]) ? $aValues[1] : '';
					$oDomain = $this->oDomainsApi->GetDomainByName($sName);
					if ($oDomain)
					{
						$oTenant = $this->oTenantsApi->getTenantById($oDomain->IdTenant);
						if ($oDomain->IsDefaultTenantDomain)
						{
							if ($iTenantId === 0)
							{
								if ($oTenant)
								{
									$sName = CApi::I18N('ADMIN_PANEL/INFO_NOT_IN_DOMAIN') . ' (' . $oTenant->Login . ')';
								}
							}
							else
							{
								$sName = CApi::I18N('ADMIN_PANEL/INFO_NOT_IN_DOMAIN');
							}
						}
						else
						{
							if ($iTenantId === 0 &&  $this->oAdminPanel->RType())
							{
								if ($oTenant)
								{
									$sName = $sName . ' (' . $oTenant->Login . ')';
								}
								else
								{
									$sName = $sName . ' (' . CApi::I18N('ADMIN_PANEL/NOT_IN_TENANT') . ')';
								}
							}
						}
					}
					
					if (is_array($aValues) && 1 < count($aValues) &&
						$this->oAdminPanel->HasAccessDomain($mIndex))
					{
						$oScreen->AddFilter($mIndex, $sName,
							$aValues[0] ? 'domWebMail' : 'domWebMail'); // domMailSuite
					}
				}
			}
		}
	}

	/**
	 * @param string $sTab
	 * @param ap_Screen $oScreen
	 */
	protected function initTableMainSwitchers($sTab, ap_Screen &$oScreen)
	{
		$sMainAction = $this->getQueryAction();
		if (AP_TAB_TENANTS === $sTab)
		{
			switch ($sMainAction)
			{
				case 'new':
					$oScreen->Main->AddTopSwitcher($this->sPath.'/templates/main-top-new-tenant.php');

					$oScreen->Main->AddSwitcher(
						PRO_SWITCHER_MODE_TENANT, PRO_SWITCHER_MODE_TENANT_NAME,
						$this->sPath.'/templates/main-tenant.php');
					break;

				case 'edit':
					$iTenantId = isset($_GET['uid']) ? (int) $_GET['uid'] : null;

					$oTenant =& $this->oAdminPanel->GetMainObject('tenant_edit');
					if (!$oTenant && null !== $iTenantId && 0 < $iTenantId)
					{
						$oTenant = $this->getTenantById($iTenantId);
						if ($oTenant)
						{
							$this->oAdminPanel->SetMainObject('tenant_edit', $oTenant);
						}
					}

					if ($oTenant)
					{
						$oScreen->Main->AddSwitcher(
							PRO_SWITCHER_MODE_TENANT, PRO_SWITCHER_MODE_TENANT_NAME,
							$this->sPath.'/templates/main-tenant.php');
					}
					break;
			}
		}
		else if (AP_TAB_CHANNELS === $sTab)
		{
			switch ($sMainAction)
			{
				case 'new':
					$oScreen->Main->AddTopSwitcher($this->sPath.'/templates/main-top-new-channel.php');

					$oScreen->Main->AddSwitcher(
						PRO_SWITCHER_MODE_CHANNEL, PRO_SWITCHER_MODE_CHANNEL_NAME,
						$this->sPath.'/templates/main-channel.php');
					break;

				case 'edit':
					$iChannelId = isset($_GET['uid']) ? (int) $_GET['uid'] : null;

					$oChannel =& $this->oAdminPanel->GetMainObject('channel_edit');
					if (!$oChannel && null !== $iChannelId && 0 < $iChannelId)
					{
						$oChannel = $this->getChannelById($iChannelId);
						if ($oChannel)
						{
							$this->oAdminPanel->SetMainObject('channel_edit', $oChannel);
						}
					}

					if ($oChannel)
					{
						$oScreen->Main->AddSwitcher(
							PRO_SWITCHER_MODE_CHANNEL, PRO_SWITCHER_MODE_CHANNEL_NAME,
							$this->sPath.'/templates/main-channel.php');
					}
					break;
			}
		}
		else if (AP_TAB_DOMAINS === $sTab && $this->bHasWebmail)
		{
			switch ($sMainAction)
			{
				case 'new':
					$oScreen->Main->AddTopSwitcher($this->sPath.'/templates/main-top-new-domain.php');

					if (!$this->oAdminPanel->RType() || 0 < $this->oAdminPanel->TenantId())
					{
						$oScreen->Data->SetValue('classHideTenantName', 'wm_hide');
					}

					$oScreen->Main->AddSwitcher(
						CM_SWITCHER_MODE_NEW_DOMAIN, CM_SWITCHER_MODE_NEW_DOMAIN_NAME,
						$this->sPath.'/templates/main-new-domain.php');
					break;

				case 'edit':
					$oDomain =& $this->oAdminPanel->GetMainObject('domain_edit');
					/* @var $oDomain CDomain */
					if ($oDomain)
					{
						$oScreen->Main->AddSwitcher(
							PRO_SWITCHER_MODE_EDIT_CALENDAR, PRO_SWITCHER_MODE_EDIT_CALENDAR_NAME,
							$this->sPath.'/templates/main-edit-domain-calendar.php');

						$oScreen->Main->AddSwitcher(
							WM_SWITCHER_MODE_EDIT_DOMAIN_WEBMAIL, WM_SWITCHER_MODE_EDIT_DOMAIN_WEBMAIL_NAME,
							$this->sPath.'/templates/main-edit-domain-webmail.php');

						$oTenant = null;
						if (0 < $oDomain->IdTenant)
						{
							$oTenant =& $this->oAdminPanel->GetMainObject('domain_edit_tenant');
						}

						$oCapabylity = CApi::Manager('capability');
						/* @var $oCapabylity CApiCapabilityManager */
						if ($oCapabylity)
						{
							if ($oCapabylity->isGlobalContactsSupported())
							{
								$oScreen->Main->AddSwitcher(
									WM_SWITCHER_MODE_EDIT_DOMAIN_ADDRESS_BOOK, WM_SWITCHER_MODE_EDIT_DOMAIN_ADDRESS_BOOK_NAME,
									$this->sPath.'/templates/main-edit-domain-address-book.php');
							}

							if ($oCapabylity->isFilesSupported() && (!$oTenant || $oTenant->isFilesSupported()))
							{
								$oScreen->Main->AddSwitcher(
									WM_SWITCHER_MODE_EDIT_DOMAIN_FILES, WM_SWITCHER_MODE_EDIT_DOMAIN_FILES_NAME,
									$this->sPath.'/templates/main-edit-domain-files.php');
							}

							if ($oCapabylity->isHelpdeskSupported() && (!$oTenant || $oTenant->isHelpdeskSupported()))
							{
								$oScreen->Main->AddSwitcher(
									WM_SWITCHER_MODE_EDIT_DOMAIN_HELPDESK, WM_SWITCHER_MODE_EDIT_DOMAIN_HELPDESK_NAME,
									$this->sPath.'/templates/main-edit-domain-helpdesk.php');
							}
						}
					}
					break;
			}
		}
		else if (AP_TAB_USERS === $sTab)
		{
			switch ($sMainAction)
			{
				case 'new':
					$mDomainIndex = $oScreen->GetFilterIndex();
					if ($this->oAdminPanel->HasAccessDomain($mDomainIndex))
					{
						$oScreen->Main->AddTopSwitcher($this->sPath.'/templates/main-top-new-user.php');

						$oDomain = $this->getDomain((int) $mDomainIndex);
						if ($oDomain)
						{
							$this->oAdminPanel->SetMainObject('domain_filter', $oDomain);

							if ($oDomain->IsDefaultDomain)
							{
								$oScreen->Main->AddSwitcher(
									CM_SWITCHER_MODE_NEW_USER, CM_SWITCHER_MODE_NEW_USER_NAME,
									$this->sPath.'/templates/main-new-user-external.php');

								$oScreen->Main->AddSwitcher(
									CM_SWITCHER_MODE_NEW_USER, CM_SWITCHER_MODE_NEW_USER_NAME,
									$this->sPath.'/templates/main-edit-user-webmail-inc-server.php');

								$oScreen->Main->AddSwitcher(
									CM_SWITCHER_MODE_NEW_USER, CM_SWITCHER_MODE_NEW_USER_NAME,
									$this->sPath.'/templates/main-edit-user-webmail-out-server.php');
							}
							else
							{
								$oScreen->Main->AddSwitcher(
									CM_SWITCHER_MODE_NEW_USER, CM_SWITCHER_MODE_NEW_USER_NAME,
									$this->sPath.'/templates/main-new-user.php');
							}
						}
					}
					break;
					
				case 'invite':
					$mDomainIndex = $oScreen->GetFilterIndex();
					if ($this->oAdminPanel->HasAccessDomain($mDomainIndex))
					{
						$oScreen->Main->AddTopSwitcher($this->sPath.'/templates/main-top-invite-user.php');

						$oDomain = $this->getDomain((int) $mDomainIndex);
						if ($oDomain)
						{
							$this->oAdminPanel->SetMainObject('domain_filter', $oDomain);

							if ($oDomain->IsDefaultTenantDomain || $oDomain->IsDefaultDomain)
							{
								$oScreen->Main->AddSwitcher(
									CM_SWITCHER_MODE_INVITE_USER, CM_SWITCHER_MODE_INVITE_USER_NAME,
									$this->sPath.'/templates/main-invite-user.php');
							}
						}
					}
					break;					

				case 'edit':
					$iAccountId = isset($_GET['uid']) ? (int) $_GET['uid'] : null;

					$oAccount =& $this->oAdminPanel->GetMainObject('account_edit');
					if (!$oAccount && null !== $iAccountId && 0 < $iAccountId)
					{
						$oAccount = $this->getAccount($iAccountId);
						if ($oAccount && $this->oAdminPanel->HasAccessDomain($oAccount->Domain->IdDomain))
						{
							$this->oAdminPanel->SetMainObject('account_edit', $oAccount);
						}
						else
						{
							$oAccount = null;
						}
					}

					if ($oAccount)
					{
						$oScreen->Main->AddSwitcher(
							CM_SWITCHER_MODE_EDIT_USER_GENERAL, CM_SWITCHER_MODE_EDIT_USER_GENERAL_NAME,
							$this->sPath.'/templates/main-edit-user-general.php');

						$oCapabylity = CApi::Manager('capability');
						/* @var $oCapabylity CApiCapabilityManager */
						if ($oCapabylity)
						{
							if ($oCapabylity->isSipSupported($oAccount))
							{
								$oScreen->Main->AddSwitcher(
									CM_SWITCHER_MODE_EDIT_USER_SIP, CM_SWITCHER_MODE_EDIT_USER_SIP_NAME,
									$this->sPath.'/templates/main-edit-user-sip.php');
							}
							
							if ($oCapabylity->isTwilioSupported($oAccount))
							{
								$oScreen->Main->AddSwitcher(
									CM_SWITCHER_MODE_EDIT_USER_TWILIO, CM_SWITCHER_MODE_EDIT_USER_TWILIO_NAME,
									$this->sPath.'/templates/main-edit-user-twilio.php');
							}
						}
					}
					break;
			}
		}
	}


	/**
	 * @param string $sTab
	 * @param ap_Screen $oScreen
	 */
	protected function initTableMainSwitchersPost($sTab, ap_Screen &$oScreen)
	{
		$sMainAction = $this->getQueryAction();
		if (AP_TAB_DOMAINS === $sTab)
		{
			switch ($sMainAction)
			{
				case 'new':

					if (in_array($this->oAdminPanel->AuthType(), array(
						AP_SESS_AUTH_TYPE_SUPER_ADMIN, AP_SESS_AUTH_TYPE_SUPER_ADMIN_ONLYREAD
					)))
					{
						$oScreen->Main->AddSwitcher(
							CM_SWITCHER_MODE_NEW_DOMAIN, CM_SWITCHER_MODE_NEW_DOMAIN_NAME,
							$this->sPath.'/templates/main-new-domain-post.php');
					}
					break;
			}
		}
		else if (AP_TAB_USERS === $sTab)
		{
			switch ($sMainAction)
			{
				case 'edit':
					$oAccount =& $this->oAdminPanel->GetMainObject('account_edit');
					if ($oAccount)
					{
						$oScreen->Main->AddSwitcher(
							WM_SWITCHER_MODE_EDIT_USERS_GENERAL, WM_SWITCHER_MODE_EDIT_USERS_GENERAL_NAME,
							$this->sPath.'/templates/main-edit-user-general-webmail.php');
					}
					break;
			}
		}

	}

	/**
	 * @return void
	 */
	protected function initInclude()
	{
		include $this->sPath.'/inc/constants.php';
		include $this->sPath.'/inc/populate.php';
		include $this->sPath.'/inc/post.php';
		include $this->sPath.'/inc/pop.php';
		include $this->sPath.'/inc/ajax.php';
	}

	/**
	 * @return string
	 */
	public function GetLicenseKey()
	{
		return $this->oLicApi ? $this->oLicApi->GetLicenseKey() : '';
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function UpdateLicenseKey($sKey)
	{
		return $this->oLicApi ? $this->oLicApi->UpdateLicenseKey($sKey) : false;
	}

	/**
	 * @return int
	 */
	public function GetCurrentNumberOfUsers()
	{
		$iCnt = $this->oLicApi ? $this->oLicApi->GetCurrentNumberOfUsers() : 0;
		return null === $iCnt ? 0 : $iCnt;
	}

	/**
	 * @return int
	 */
	public function GetUserNumberLimit()
	{
		return $this->oLicApi ? $this->oLicApi->GetUserNumberLimitAsString() : 0;
	}

	/**
	 * @return bool
	 */
	public function IsTrial()
	{
		return in_array($this->oLicApi ? $this->oLicApi->GetLicenseType() : 0, array(10, 11));
	}

	/**
	 * @param array $aTenantsIds
	 * @return bool
	 */
	public function deleteTenants($aTenantsIds)
	{
		$iResult = 1;
		if ($this->oTenantsApi)
		{
			foreach ($aTenantsIds as $iTenantId)
			{
				$oTenant = $this->oTenantsApi->getTenantById($iTenantId);
				if ($oTenant instanceof CTenant)
				{
					$iResult &= $this->deleteTenant($oTenant);
				}
				unset($oTenant);
			}
		}

		return (bool) $iResult;
	}

	/**
	 * @param array $aChannelsIds
	 * @return bool
	 */
	public function deleteChannels($aChannelsIds)
	{
		$iResult = 1;
		if ($this->oChannelsApi)
		{
			foreach ($aChannelsIds as $iChannelId)
			{
				$oChannel = $this->oChannelsApi->getChannelById($iChannelId);
				if ($oChannel instanceof CChannel)
				{
					$iResult &= $this->deleteChannel($oChannel);
				}
				unset($oChannel);
			}
		}
		return (bool) $iResult;
	}

	/**
	 * @param CTenant $oTenant
	 * @return bool
	 */
	public function deleteTenant($oTenant)
	{
		return $this->oTenantsApi ? $this->oTenantsApi->deleteTenant($oTenant) : false;
	}

	/**
	 * @param CChannel $oChannel
	 * @return bool
	 */
	public function deleteChannel($oChannel)
	{
		return $this->oChannelsApi ? $this->oChannelsApi->deleteChannel($oChannel) : false;
	}

	/**
	 * @param CTenant $oTenant
	 * @return bool
	 */
	public function createTenant(CTenant $oTenant)
	{
		if ($this->oTenantsApi && !$this->oTenantsApi->createTenant($oTenant))
		{
			$this->lastErrorCode = $this->oTenantsApi->getLastErrorCode();
			$this->lastErrorMessage = $this->oTenantsApi->GetLastErrorMessage();
			return false;
		}
		return true;
	}

	/**
	 * @param CChannel $oChannel
	 * @return bool
	 */
	public function createChannel(CChannel $oChannel)
	{
		if ($this->oChannelsApi && !$this->oChannelsApi->createChannel($oChannel))
		{
			$this->lastErrorCode = $this->oChannelsApi->getLastErrorCode();
			$this->lastErrorMessage = $this->oChannelsApi->GetLastErrorMessage();
			return false;
		}
		return true;
	}

	/**
	 * @param int $iTenantId
	 *
	 * @return array | false
	 */
	public function getTenantDomains($iTenantId)
	{
		return $this->oTenantsApi ? $this->oTenantsApi->getTenantDomains($iTenantId) : false;
	}

	/**
	 * @param int $iTenantId
	 * @return CTenant
	 */
	public function getTenantById($iTenantId)
	{
		$oTenant = null;
		if ($this->oTenantsApi)
		{
			$oTenant = ($iTenantId > 0) ? $this->oTenantsApi->getTenantById($iTenantId) : $this->oTenantsApi->getDefaultGlobalTenant();
		}
		
		return $oTenant;
	}
	
	/**
	 * @param string $sEmail
	 * @return CTenant
	 */
	public function GetAccountByEmail($sEmail)
	{
		return $this->oUsersApi ? $this->oUsersApi->getAccountByEmail($sEmail) : null;
	}


	/**
	 * @param int $iChannelId
	 * @return CCannel
	 */
	public function getChannelById($iChannelId)
	{
		return $this->oChannelsApi ? $this->oChannelsApi->getChannelById($iChannelId) : null;
	}

	/**
	 * @param CTenant $oTenant
	 * @return bool
	 */
	public function updateTenant(CTenant $oTenant)
	{
		if ($this->oTenantsApi && !$this->oTenantsApi->updateTenant($oTenant))
		{
			$this->lastErrorCode = $this->oTenantsApi->getLastErrorCode();
			$this->lastErrorMessage = $this->oTenantsApi->GetLastErrorMessage();
			return false;
		}
		return true;
	}

	/**
	 * @param string $sEmail
	 * @return bool
	 */
	public function ValidateHelpdeskEmail($sEmail)
	{
		$oAccount = $this->oUsersApi->getAccountByEmail($sEmail);
		return $oAccount instanceof CAccount;
	}

	/**
	 * @return CTenant
	 */
	public function GetTenantAdminObject()
	{
		$oTenant = null;
		$iTenantId = $this->oAdminPanel->TenantId();
		
		if ($this->oTenantsApi)
		{
			if (0 < $iTenantId)
			{
				$oTenant = $this->oTenantsApi->getTenantById($iTenantId);
			}
			else
			{
				$oTenant = $this->oTenantsApi->getDefaultGlobalTenant();
			}
		}

		return $oTenant;
	}

	/**
	 * @param CTenant $oTenant
	 */
	public function UpdateTenantAdminObject($oTenant)
	{
		$bResult = false;
		$iTenantId = $this->oAdminPanel->TenantId();
		
		if ($this->oTenantsApi && $oTenant && (0 === $iTenantId ||0 < $iTenantId && $iTenantId === $oTenant->IdTenant))
		{
			$bResult = $this->oTenantsApi->updateTenant($oTenant);
		}

		return $bResult;
	}

	/**
	 * @param CChannel $oChannel
	 * @return bool
	 */
	public function updateChannel(CChannel $oChannel)
	{
		if ($this->oChannelsApi && !$this->oChannelsApi->updateChannel($oChannel))
		{
			$this->lastErrorCode = $this->oChannelsApi->getLastErrorCode();
			$this->lastErrorMessage = $this->oChannelsApi->GetLastErrorMessage();
			return false;
		}
		return true;
	}

	/**
	* @param CDomain &$oAccount
	* @return bool
	*/
	public function CreateAccount(CAccount &$oAccount)
	{
		$bWithMailConnection =  !$oAccount->IsInternal && $oAccount->Domain->AllowWebMail && $oAccount->AllowMail;
		
		if (!$this->oUsersApi->createAccount($oAccount, $bWithMailConnection))
		{
			$this->lastErrorCode = $this->oUsersApi->getLastErrorCode();
			$this->lastErrorMessage = $this->oUsersApi->GetLastErrorMessage();
			return false;
		}
		return true;
	}

	/**
	 * @param array $aAccountsIds
	 * @param bool $bIsEnabled
	 * @return bool
	 */
	public function EnableAccounts($aAccountsIds, $bIsEnabled)
	{
		if (!$this->oUsersApi->enableAccounts($aAccountsIds, $bIsEnabled))
		{
			$this->lastErrorCode = $this->oUsersApi->getLastErrorCode();
			$this->lastErrorMessage = $this->oUsersApi->GetLastErrorMessage();
			return false;
		}
		return true;
	}

	/**
	 * @param CDomain &$oAccount
	 * @return bool
	 */
	public function UpdateAccount(CAccount &$oAccount)
	{
		if (!$this->oUsersApi->updateAccount($oAccount))
		{
			$this->lastErrorCode = $this->oUsersApi->getLastErrorCode();
			$this->lastErrorMessage = $this->oUsersApi->GetLastErrorMessage();
			return false;
		}
		return true;
	}

	/**
	 * @param CAccount $oAccount
	 * @return bool
	 */
	public function AccountExists(CAccount $oAccount)
	{
		return $this->oUsersApi->accountExists($oAccount);
	}

	/**
	 * @param array $aAccountsIds
	 * @return bool
	 */
	public function DeleteAccounts($aAccountsIds)
	{
		$iResult = 1;
		foreach ($aAccountsIds as $iAccountId)
		{
			$iResult &= $this->oUsersApi->deleteAccountById($iAccountId);
		}
		return (bool) $iResult;
	}

	/**
	 * @param int $iAccountId
	 * @return CAccount
	 */
	public function getAccount($iAccountId)
	{
		if (is_numeric($iAccountId) && 0 < $iAccountId)
		{
			return $this->oUsersApi->getAccountById($iAccountId);
		}
		return null;
	}

	/**
	 * @param int $iAccountId
	 * @return CAccount
	 */
	public function GetAccountById($iAccountId)
	{
		if (is_numeric($iAccountId) && 0 < $iAccountId)
		{
			return $this->oUsersApi->getAccountById($iAccountId);
		}
		return null;
	}
	
	/**
	* @param int $iDomainId
	* @return CDomain
	*/
	public function getDomain($iDomainId)
	{
		if (0 === $iDomainId)
		{
			return $this->oDomainsApi->GetDefaultDomain();
		}
		return $this->oDomainsApi->GetDomainById($iDomainId);
	}

	/**
	* @param CDomain &$oDomain
	* @return bool
	*/
	public function CreateDomain(CDomain &$oDomain)
	{
		if (!$this->oDomainsApi->CreateDomain($oDomain))
		{
			$this->lastErrorCode = $this->oDomainsApi->getLastErrorCode();
			$this->lastErrorMessage = $this->oDomainsApi->GetLastErrorMessage();
			return false;
		}

		CSession::Set(AP_SESS_DOMAIN_NEXT_EDIT_ID, $oDomain->IdDomain);
		return true;
	}

	/**
	 * @param string $sTenantName
	 *
	 * @return int
	 */
	public function GetTenantIdByName($sTenantName)
	{
		return $this->oTenantsApi ? $this->oTenantsApi->getTenantIdByLogin($sTenantName) : 0;
	}

	/**
	* @param array $aDomainsIds
	* @return bool
	*/
	public function DeleteDomains($aDomainsIds)
	{
		if (!$this->oDomainsApi->DeleteDomains($aDomainsIds))
		{
			$this->lastErrorCode = $this->oDomainsApi->getLastErrorCode();
			$this->lastErrorMessage = $this->oDomainsApi->GetLastErrorMessage();
			return false;
		}

		return true;
	}

	/**
	* @param string $sLogin
	* @param string $sPassword
	 *
	* @return int
	*/
	public function GetTenantIdByLoginPassword($sLogin, $sPassword)
	{
		return $this->oTenantsApi ? $this->oTenantsApi->getTenantIdByLogin($sLogin, $sPassword) : 0;
	}
}
