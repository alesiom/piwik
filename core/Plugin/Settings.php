<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */
namespace Piwik\Plugin;

use Piwik\Common;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\SystemSetting;
use Piwik\Settings\UserSetting;

class Settings
{
    const TYPE_INT    = 'integer';
    const TYPE_FLOAT  = 'float';
    const TYPE_STRING = 'string';
    const TYPE_BOOL   = 'boolean';
    const TYPE_ARRAY  = 'array';

    const FIELD_TEXT     = 'text';
    const FIELD_TEXTAREA = 'textarea';
    const FIELD_CHECKBOX = 'checkbox';
    const FIELD_PASSWORD = 'password';
    const FIELD_MULTI_SELECT  = 'multiselect';
    const FIELD_SINGLE_SELECT = 'select';

    /**
     * An array containing all available settings: Array ( [setting-name] => [setting] )
     *
     * @var Settings[]
     */
    private $settings = array();

    /**
     * Array containing all plugin settings values: Array( [setting-key] => [setting-value] ).
     *
     * @var array
     */
    private $settingsValues = array();

    private $introduction;
    private $pluginName;

    public function __construct($pluginName)
    {
        $this->pluginName = $pluginName;

        $this->init();
        $this->loadSettings();
    }

    protected function init()
    {
        // Define your settings and introduction here.
    }

    /**
     * Sets (overwrites) the plugin settings introduction.
     *
     * @param string $introduction
     */
    protected function setIntroduction($introduction)
    {
        $this->introduction = $introduction;
    }

    public function getIntroduction()
    {
        return $this->introduction;
    }

    /**
     * Returns only settings that can be displayed for current user. For instance a regular user won't see get
     * any settings that require super user permissions.
     *
     * @return Setting[]
     */
    public function getSettingsForCurrentUser()
    {
        return array_values(array_filter($this->getSettings(), function (Setting $setting) {
            return $setting->canBeDisplayedForCurrentUser();
        }));
    }

    /**
     * Get all available settings without checking any permissions.
     *
     * @return Setting[]
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Saves (persists) the current setting values in the database.
     */
    public function save()
    {
        Option::set($this->getOptionKey(), serialize($this->settingsValues));
    }

    /**
     * Removes all settings for this plugin. Useful for instance while uninstalling the plugin.
     */
    public function removeAllPluginSettings()
    {
        Option::delete($this->getOptionKey());
    }

    /**
     * Gets the current value for this setting. If no value is specified, the default value will be returned.
     *
     * @param Setting $setting
     *
     * @return mixed
     *
     * @throws \Exception In case the setting does not exist or if the current user is not allowed to change the value
     *                    of this setting.
     */
    public function getSettingValue(Setting $setting)
    {
        $this->checkIsValidSetting($setting->getName());

        if (array_key_exists($setting->getKey(), $this->settingsValues)) {

            return $this->settingsValues[$setting->getKey()];
        }

        return $setting->defaultValue;
    }

    /**
     * Sets (overwrites) the value for the given setting. Make sure to call `save()` afterwards, otherwise the change
     * has no effect. Before the value is saved a possibly define `validate` closure and `filter` closure will be
     * called. Alternatively the value will be casted to the specfied setting type.
     *
     * @param Setting $setting
     * @param string $value
     *
     * @throws \Exception In case the setting does not exist or if the current user is not allowed to change the value
     *                    of this setting.
     */
    public function setSettingValue(Setting $setting, $value)
    {
        $this->checkIsValidSetting($setting->getName());

        if ($setting->validate && $setting->validate instanceof \Closure) {
            call_user_func($setting->validate, $value, $setting);
        }

        if ($setting->filter && $setting->filter instanceof \Closure) {
            $value = call_user_func($setting->filter, $value, $setting);
        } else {
            settype($value, $setting->type);
        }

        $this->settingsValues[$setting->getKey()] = $value;
    }

    /**
     * Removes the value for the given setting. Make sure to call `save()` afterwards, otherwise the removal has no
     * effect.
     *
     * @param Setting $setting
     */
    public function removeSettingValue(Setting $setting)
    {
        $key = $setting->getKey();

        if (array_key_exists($key, $this->settingsValues)) {
            unset($this->settingsValues[$key]);
        }
    }

    /**
     * Adds a new setting.
     *
     * @param Setting $setting
     * @throws \Exception       In case a setting having the same name already exists.
     *                          In case the name contains non-alnum characters.
     */
    protected function addSetting(Setting $setting)
    {
        if (!ctype_alnum($setting->getName())) {
            $msg = sprintf('The setting name "%s" in plugin "%s" is not valid. Only alpha and numerical characters are allowed', $setting->getName(), $this->pluginName);
            throw new \Exception($msg);
        }

        if (array_key_exists($setting->getName(), $this->settings)) {
            throw new \Exception(sprintf('A setting with name "%s" does already exist for plugin "%s"', $setting->getName(), $this->pluginName));
        }

        if (!is_null($setting->field) && is_null($setting->type)) {
            $setting->type = $setting->getDefaultType($setting->field);
        } elseif (!is_null($setting->type) && is_null($setting->field)) {
            $setting->field = $setting->getDefaultField($setting->type);
        }

        if (is_null($setting->validate) && !is_null($setting->fieldOptions)) {
            $pluginName = $this->pluginName;
            $setting->validate = function ($value) use ($setting, $pluginName) {

                $errorMsg = Piwik::translate('CoreAdminHome_PluginSettingsValueNotAllowed', array($setting->title, $pluginName));

                if (is_array($value) && $setting->type == Settings::TYPE_ARRAY) {
                    foreach ($value as $val) {
                        if (!array_key_exists($val, $setting->fieldOptions)) {
                            throw new \Exception($errorMsg);
                        }
                    }
                } else {
                    if (!array_key_exists($value, $setting->fieldOptions)) {
                        throw new \Exception($errorMsg);
                    }
                }
            };
        }

        $this->settings[$setting->getName()] = $setting;
    }

    private function getOptionKey()
    {
        return 'Plugin_' . $this->pluginName . '_Settings';
    }

    private function loadSettings()
    {
        $values = Option::get($this->getOptionKey());

        if (!empty($values)) {
            $this->settingsValues = unserialize($values);
        }
    }

    private function checkIsValidSetting($name)
    {
        $setting = $this->getSetting($name);

        if (empty($setting)) {
            throw new \Exception(sprintf('The setting %s does not exist', $name));
        }

        if (!$setting->canBeDisplayedForCurrentUser()) {
            $errorMsg = Piwik::translate('PluginSettingChangeNotAllowed', array($name, $this->pluginName));
            throw new \Exception($errorMsg);
        }
    }

    /**
     * @param  $name
     * @return Setting|null
     */
    private function getSetting($name)
    {
        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        }
    }

}
