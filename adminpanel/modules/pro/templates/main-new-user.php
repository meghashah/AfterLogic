<table class="wm_contacts_view">
	<tr>
		<td class="wm_field_title">
		<?php if ($this->Data->GetValueAsBool('domainIsInternal')) {
			echo CApi::I18N('ADMIN_PANEL/USERS_USERNAME');
		} else {
			echo CApi::I18N('ADMIN_PANEL/USERS_LOGIN');
		} ?> *
		</td>
		<td class="wm_field_value">
			<input name="hiddenDomainId" type="hidden" id="hiddenDomainId" value="<?php $this->Data->PrintInputValue('hiddenDomainId') ?>" />
			<input name="txtNewLogin" type="text" id="txtNewLogin" class="wm_input"
				style="width: 150px" maxlength="100" value="" />
			<?php if ($this->Data->GetValueAsBool('isDefaultTenantDomain'))
			{
				echo ' (' . CApi::I18N('ADMIN_PANEL/SOCIAL_USER_CREATE_DESC') . ')';
			} ?>
		</td>
	</tr>
	<tr>
		<td class="wm_field_title">
			<?php echo CApi::I18N('ADMIN_PANEL/USERS_PASSWORD'); ?> *
		</td>
		<td class="wm_field_value">
			<input name="txtNewPassword" type="password" id="txtNewPassword" class="wm_input"
				style="width: 150px" maxlength="100" value="" />
		</td>
	</tr>
	<?php if ($this->Data->GetValueAsBool('domainIsInternal')) { ?>
	<tr>
		<td align="left" width="100">
			<nobr><?php echo CApi::I18N('ADMIN_PANEL/USERS_QUOTA'); ?></nobr>
		</td>
		<td align="left">
			<input name="txtEditStorageQuota" type="text" id="txtEditStorageQuota" class="wm_input"
				style="width: 150px" maxlength="9" value="<?php $this->Data->PrintInputValue('txtEditStorageQuota') ?>" />
			<?php echo CApi::I18N('ADMIN_PANEL/USERS_QUOTA_MB'); ?>
		</td>
	</tr>
	<?php } ?>
	<?php if ($this->Data->GetValueAsBool('subscriptionsSupported')) { ?>
	<tr>
		<td align="left" width="100">
			<span id="selSubscribtions_label">
				<nobr><?php echo CApi::I18N('ADMIN_PANEL/USERS_SUBSCRIPTION'); ?></nobr>
			</span>
		</td>
		<td align="left">
			 <?php $this->Data->PrintValue('selSubscribtionsOptions'); ?>
		</td>
	</tr>
	<?php } ?>
	<tr data-bind="visible: allowWebMail">
		<td align="left"></td>
		<td align="left">
			<input type="checkbox" class="wm_checkbox" name="chAllowMail" id="chAllowMail" value="1"
				data-bind="checked: chAllowMail"/>
			<label id="txtOutgoingMailLogin_label" for="chAllowMail">
				<?php echo 'Enable mail'; ?>
			</label>
		</td>
	</tr>
	
</table>