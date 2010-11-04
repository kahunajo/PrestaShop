<?php

class AdminPerformance extends AdminTab
{
	public function postProcess()
	{
		global $currentIndex;

		if (Tools::isSubmit('submitCiphering') AND Configuration::get('PS_CIPHER_ALGORITHM') != (int)Tools::getValue('PS_CIPHER_ALGORITHM'))
		{
			$algo = (int)Tools::getValue('PS_CIPHER_ALGORITHM');
			$settings = file_get_contents(dirname(__FILE__).'/../../config/settings.inc.php');
			if ($algo)
			{
				if (!function_exists('mcrypt_encrypt'))
					$this->_errors[] = Tools::displayError('mcrypt is not activated on this server');
				else
				{
					if (!strstr($settings, '_RIJNDAEL_KEY_'))
					{
						$key_size = mcrypt_get_key_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
						$key = Tools::passwdGen($key_size);
						$settings = preg_replace('/define\(\'_COOKIE_KEY_\', \'([a-z0-9=\/+-_]+)\'\);/i', 'define(\'_COOKIE_KEY_\', \'\1\');'."\n".'define(\'_RIJNDAEL_KEY_\', \''.$key.'\');', $settings);
					}
					if (!strstr($settings, '_RIJNDAEL_IV_'))
					{
						$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
						$iv = base64_encode(mcrypt_create_iv($iv_size, MCRYPT_RAND));
						$settings = preg_replace('/define\(\'_COOKIE_IV_\', \'([a-z0-9=\/+-_]+)\'\);/i', 'define(\'_COOKIE_IV_\', \'\1\');'."\n".'define(\'_RIJNDAEL_IV_\', \''.$iv.'\');', $settings);
					}
				}
			}
			if (!count($this->_errors))
			{
				if (file_put_contents(dirname(__FILE__).'/../../config/settings.inc.php', $settings))
				{
					Configuration::updateValue('PS_CIPHER_ALGORITHM', $algo);
					Tools::redirectAdmin($currentIndex.'&token='.Tools::getValue('token').'&conf=4');
				}
				else
					$this->_errors[] = Tools::displayError('Cannot overwrite settings file');
			}
		}
		
		if (Tools::isSubmit('submitCCC'))
		{
			if (
				!Configuration::updateValue('PS_CSS_THEME_CACHE', (int)Tools::getValue('PS_CSS_THEME_CACHE')) OR
				!Configuration::updateValue('PS_JS_THEME_CACHE', (int)Tools::getValue('PS_JS_THEME_CACHE')) OR
				!Configuration::updateValue('PS_HTML_THEME_COMPRESSION', (int)Tools::getValue('PS_HTML_THEME_COMPRESSION')) OR
				!Configuration::updateValue('PS_JS_HTML_THEME_COMPRESSION', (int)Tools::getValue('PS_JS_HTML_THEME_COMPRESSION')) OR
				!Configuration::updateValue('PS_HIGH_HTML_THEME_COMPRESSION', (int)Tools::getValue('PS_HIGH_HTML_THEME_COMPRESSION'))
			)
				$this->_errors[] = Tools::displayError('Unknown error.');
			else
				Tools::redirectAdmin($currentIndex.'&token='.Tools::getValue('token').'&conf=4');
		}
		if (Tools::isSubmit('submitMediaServers'))
		{
			if (Tools::getValue('_MEDIA_SERVER_1_') != NULL AND !Validate::isFileName(Tools::getValue('_MEDIA_SERVER_1_')))
				$this->_errors[] = Tools::displayError('Media server #1 is invalid');
			if (Tools::getValue('_MEDIA_SERVER_2_') != NULL AND !Validate::isFileName(Tools::getValue('_MEDIA_SERVER_2_')))
				$this->_errors[] = Tools::displayError('Media server #2 is invalid');
			if (Tools::getValue('_MEDIA_SERVER_3_') != NULL AND !Validate::isFileName(Tools::getValue('_MEDIA_SERVER_3_')))
				$this->_errors[] = Tools::displayError('Media server #3 is invalid');
			if (!sizeof($this->_errors))
			{
				$baseUrls = array();
				$baseUrls['_MEDIA_SERVER_1_'] = Tools::getValue('_MEDIA_SERVER_1_');
				$baseUrls['_MEDIA_SERVER_2_'] = Tools::getValue('_MEDIA_SERVER_2_');
				$baseUrls['_MEDIA_SERVER_3_'] = Tools::getValue('_MEDIA_SERVER_3_');
				rewriteSettingsFile($baseUrls, NULL, NULL);
				unset($this->_fieldsGeneral['_MEDIA_SERVER_1_']);
				unset($this->_fieldsGeneral['_MEDIA_SERVER_2_']);
				unset($this->_fieldsGeneral['_MEDIA_SERVER_3_']);
				Tools::redirectAdmin($currentIndex.'&token='.Tools::getValue('token').'&conf=4');
			}
		}
		if (Tools::isSubmit('submitSmartyConfig'))
		{
			Configuration::updateValue('PS_SMARTY_FORCE_COMPILE', Tools::getValue('smarty_force_compile', 0));
			Tools::redirectAdmin($currentIndex.'&token='.Tools::getValue('token').'&conf=4');
		}
		
		return parent::postProcess();
	}

	public function display()
	{
		global $currentIndex;

		echo '
		<form action="'.$currentIndex.'&token='.Tools::getValue('token').'" method="post">
			<fieldset>
				<legend><img src="../img/admin/subdomain.gif" /> '.$this->l('Media servers').'</legend>
				<p>'.$this->l('You have to put another domain or subdomain in order to use cookieless static content.').'</p>
				<label for="_MEDIA_SERVER_1_">'.$this->l('Media server #1').'</label>
				<div class="margin-form">
					<input type="text" name="_MEDIA_SERVER_1_" id="_MEDIA_SERVER_1_" value="'.htmlentities(Tools::getValue('_MEDIA_SERVER_1_', _MEDIA_SERVER_1_), ENT_QUOTES, 'UTF-8').'" size="30" />
					<p>'.$this->l('Name of the second domain of your shop, (e.g., myshop-media-server-1.com). If you do not have another domain, leave this field blank').'</p>
				</div>
				<label for="_MEDIA_SERVER_2_">'.$this->l('Media server #2').'</label>
				<div class="margin-form">
					<input type="text" name="_MEDIA_SERVER_2_" id="_MEDIA_SERVER_2_" value="'.htmlentities(Tools::getValue('_MEDIA_SERVER_2_', _MEDIA_SERVER_2_), ENT_QUOTES, 'UTF-8').'" size="30" />
					<p>'.$this->l('Name of the third domain of your shop, (e.g., myshop-media-server-2.com). If you do not have another domain, leave this field blank').'</p>
				</div>
				<label for="_MEDIA_SERVER_3_">'.$this->l('Media server #3').'</label>
				<div class="margin-form">
					<input type="text" name="_MEDIA_SERVER_3_" id="_MEDIA_SERVER_3_" value="'.htmlentities(Tools::getValue('_MEDIA_SERVER_3_', _MEDIA_SERVER_3_), ENT_QUOTES, 'UTF-8').'" size="30" />
					<p>'.$this->l('Name of the fourth domain of your shop, (e.g., myshop-media-server-3.com). If you do not have another domain, leave this field blank').'</p>
				</div>
				<div class="margin-form">
					<input type="submit" value="'.$this->l('   Save   ').'" name="submitMediaServers" class="button" />
				</div>
			</fieldset>
		</form>';
		
		echo '
		<form action="'.$currentIndex.'&token='.Tools::getValue('token').'" method="post" style="margin-top:10px;">
			<fieldset>
				<legend><img src="../img/admin/computer_key.png" /> '.$this->l('Ciphering').'</legend>
				<p>'.$this->l('Mcrypt is faster than our custom BlowFish class, but require PHP extension "mcrypt". If you change this configuration, every cookies will be reset.').'</p>
				<label>'.$this->l('Algorithm').' </label>
				<div class="margin-form">
					<input type="radio" value="1" name="PS_CIPHER_ALGORITHM" id="PS_CIPHER_ALGORITHM_1" '.(Configuration::get('PS_CIPHER_ALGORITHM') ? 'checked="checked"' : '').' />
					<label class="t" for="PS_CIPHER_ALGORITHM_1">'.$this->l('Use Rijndael with mcrypt lib.').'</label>
					<br />
					<input type="radio" value="0" name="PS_CIPHER_ALGORITHM" id="PS_CIPHER_ALGORITHM_0" '.(Configuration::get('PS_CIPHER_ALGORITHM') ? '' : 'checked="checked"').' />
					<label class="t" for="PS_CIPHER_ALGORITHM_0">'.$this->l('Keep the custom BlowFish class.').'</label>
				</div>
				<div class="margin-form">
					<input type="submit" value="'.$this->l('   Save   ').'" name="submitCiphering" class="button" />
				</div>
			</fieldset>
		</form>';
		
		echo '
		<form action="'.$currentIndex.'&token='.Tools::getValue('token').'" method="post" style="margin-top:10px;">
			<fieldset>
				<legend><img src="../img/admin/arrow_in.png" /> '.$this->l('CCC (Combine, Compress and Cache)').'</legend>
				<p>'.$this->l('CCC allows you to reduce the loading time of your page, browser-side. With these settings you will gain performance without touching the code of your theme. Caution, however, that your theme is compatible PrestaShop 1.4+, CCC otherwise cause problems.').'</p>
				<label>'.$this->l('Smart cache for CSS').' </label>
				<div class="margin-form">
					<input type="radio" value="1" name="PS_CSS_THEME_CACHE" id="PS_CSS_THEME_CACHE_1" '.(Configuration::get('PS_CSS_THEME_CACHE') ? 'checked="checked"' : '').' />
					<label class="t" for="PS_CSS_THEME_CACHE_1">'.$this->l('Use CCC for CSS.').'</label>
					<br />
					<input type="radio" value="0" name="PS_CSS_THEME_CACHE" id="PS_CSS_THEME_CACHE_0" '.(Configuration::get('PS_CSS_THEME_CACHE') ? '' : 'checked="checked"').' />
					<label class="t" for="PS_CSS_THEME_CACHE_0">'.$this->l('Keep CSS as original').'</label>
				</div>
				
				<label>'.$this->l('Smart cache for JavaScript').' </label>
				<div class="margin-form">
					<input type="radio" value="1" name="PS_JS_THEME_CACHE" id="PS_JS_THEME_CACHE_1" '.(Configuration::get('PS_JS_THEME_CACHE') ? 'checked="checked"' : '').' />
					<label class="t" for="PS_JS_THEME_CACHE_1">'.$this->l('Use CCC for JavaScript.').'</label>
					<br />
					<input type="radio" value="0" name="PS_JS_THEME_CACHE" id="PS_JS_THEME_CACHE_0" '.(Configuration::get('PS_JS_THEME_CACHE') ? '' : 'checked="checked"').' />
					<label class="t" for="PS_JS_THEME_CACHE_0">'.$this->l('Keep JavaScript as original').'</label>
				</div>
				
				<label>'.$this->l('Minify HTML').' </label>
				<div class="margin-form">
					<input type="radio" value="1" name="PS_HTML_THEME_COMPRESSION" id="PS_HTML_THEME_COMPRESSION_1" '.(Configuration::get('PS_HTML_THEME_COMPRESSION') ? 'checked="checked"' : '').' />
					<label class="t" for="PS_HTML_THEME_COMPRESSION_1">'.$this->l('Minify HTML after "smarty compile" execution.').'</label>
					<br />
					<input type="radio" value="0" name="PS_HTML_THEME_COMPRESSION" id="PS_HTML_THEME_COMPRESSION_0" '.(Configuration::get('PS_HTML_THEME_COMPRESSION') ? '' : 'checked="checked"').' />
					<label class="t" for="PS_HTML_THEME_COMPRESSION_0">'.$this->l('Keep HTML as original').'</label>
				</div>
				
				<label>'.$this->l('Compress inline JavaScript in HTML').' </label>
				<div class="margin-form">
					<input type="radio" value="1" name="PS_JS_HTML_THEME_COMPRESSION" id="PS_JS_HTML_THEME_COMPRESSION_1" '.(Configuration::get('PS_JS_HTML_THEME_COMPRESSION') ? 'checked="checked"' : '').' />
					<label class="t" for="PS_JS_HTML_THEME_COMPRESSION_1">'.$this->l('Compress inline JavaScript in HTML after "smarty compile" execution').'</label>
					<br />
					<input type="radio" value="0" name="PS_JS_HTML_THEME_COMPRESSION" id="PS_JS_HTML_THEME_COMPRESSION_0" '.(Configuration::get('PS_JS_HTML_THEME_COMPRESSION') ? '' : 'checked="checked"').' />
					<label class="t" for="PS_JS_HTML_THEME_COMPRESSION_0">'.$this->l('Keep inline JavaScript in HTML as original').'</label>
				</div>
				
				<label>'.$this->l('High and dangerous HTML compression').' </label>
				<div class="margin-form">
					<input type="radio" value="1" name="PS_HIGH_HTML_THEME_COMPRESSION" id="PS_HIGH_HTML_THEME_COMPRESSION_1" '.(Configuration::get('PS_HIGH_HTML_THEME_COMPRESSION') ? 'checked="checked"' : '').' />
					<label class="t" for="PS_HIGH_HTML_THEME_COMPRESSION_1">'.$this->l('HTML compress up but cancels the W3C validation (only when "Minify HTML" is enabled)').'</label>
					<br />
					<input type="radio" value="0" name="PS_HIGH_HTML_THEME_COMPRESSION" id="PS_HIGH_HTML_THEME_COMPRESSION_0" '.(Configuration::get('PS_HIGH_HTML_THEME_COMPRESSION') ? '' : 'checked="checked"').' />
					<label class="t" for="PS_HIGH_HTML_THEME_COMPRESSION_0">'.$this->l('Keep W3C validation').'</label>
				</div>
				
				<div class="margin-form">
					<input type="submit" value="'.$this->l('   Save   ').'" name="submitCCC" class="button" />
				</div>
			</fieldset>
		</form>';
		
		echo '
		<form action="'.$currentIndex.'&token='.Tools::getValue('token').'" method="post" style="margin-top:10px;">
			<fieldset>
				<legend><img src="../img/admin/prefs.gif" /> '.$this->l('Smarty').'</legend>
				
				<label>'.$this->l('Force compile:').'</label>
				<div class="margin-form">
					<input type="radio" name="smarty_force_compile" id="smarty_force_compile_1" value="1" '.(Configuration::get('PS_SMARTY_FORCE_COMPILE') ? 'checked="checked"' : '').' /> <label class="t"><img src="../img/admin/enabled.gif" alt="" /> '.$this->l('Yes').'</label>
					<input type="radio" name="smarty_force_compile" id="smarty_force_compile_0" value="0" '.(!Configuration::get('PS_SMARTY_FORCE_COMPILE') ? 'checked="checked"' : '').' /> <label class="t"><img src="../img/admin/disabled.gif" alt="" /> '.$this->l('No').'</label>
					<p>'.$this->l('This forces Smarty to (re)compile templates on every invocation. This is handy for development and debugging. It should never be used in a production environment.').'</p>
				</div>
				
				<div class="margin-form">
					<input type="submit" value="'.$this->l('   Save   ').'" name="submitSmartyConfig" class="button" />
				</div>
			</fieldset>
		</form>';
	}
}

?>
