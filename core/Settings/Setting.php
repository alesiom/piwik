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

namespace Piwik\Settings;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\Settings;

/**
 * Base setting class. Extend this class to define your own type of setting.
 *
 * @package Piwik
 * @subpackage Settings
 */
abstract class Setting
{
    /**
     * Defines the PHP type of the setting. Before the value is saved it will be cased depending on this setting.
     *
     * @var string
     */
    public $type = Settings::TYPE_STRING;

    /**
     * Defines which field type should be displayed on the setting page.
     *
     * @var string
     */
    public $field = Settings::FIELD_TEXT;

    /**
     * An array of field attributes that will be added as HTML attributes to the HTML form field.
     * Example: `array('size' => 3)`. Please note the attributes will be escaped for security.
     * @var array
     */
    public $fieldAttributes = array();

    /**
     * Defines available options in case you want to give the user the possibility to select a value (in a select
     * field). For instance `array('nb_visits' => 'Visits', 'nb_actions' => 'Actions')`.
     * In case field options are set and you do not specify a validator, a validator will be automatically added
     * to check the set value is one of the defined array keys. An error will be triggered in case a user tries to
     * save a value that is not allowed.
     *
     * @var null|array
     */
    public $fieldOptions = null;

    /**
     * Defines an introduction that will be displayed as a text block above the setting.
     * @var null|string
     */
    public $introduction    = null;

    /**
     * Defines a description that will be displayed underneath the setting title. It should be just a short description
     * what the setting is about.
     * @var null|string
     */
    public $description     = null;

    /**
     * The inline help will be displayed in a separate help box next to the setting and can contain some further
     * explanations about the setting. For instance if only some specific characters are allowed to can explain them
     * here.
     * @var null|string
     */
    public $inlineHelp      = null;

    /**
     * If a closure is set, the closure will be executed before a new value is saved. In case a user tries to save an
     * invalid value just throw an exception containing a useful message. Example:
     * ```
     * function ($value, Setting $setting) {
     *     if ($value > 60) {
     *         throw new \Exception('Value has to be at <= 60 as an hour has only 60 minutes');
     *     }
     * }
     * ```
     *
     * @var null|\Closure
     */
    public $validate        = null;

    /**
     * You can define a filter closure that is executed after a value is validated. In case you define a value, the
     * property `$type` has no effect. That means the value won't be casted to the specified type. It is on your own to
     * cast or format the value on your needs. Make sure to return a value at the end of the function as this value
     * will be saved. Example:
     * ```
     * function ($value, Setting $setting) {
     *     if ($value > 30) {
     *         $value = 30;
     *     }
     *
     *     return (int) $value;
     * }
     * ```
     *
     * @var null|\Closure
     */
    public $filter          = null;

    /**
     * Defines the default value for this setting that will be used in case the user has not specified a value so far.
     * The default value won't be casted so make sure to define an appropriate value.
     *
     * @var mixed
     */
    public $defaultValue    = null;

    /**
     * Defines the title of the setting which will be visible to the user. For instance `Refresh Interval`
     *
     * @var string
     */
    public $title           = '';

    protected $key;
    protected $name;
    protected $displayedForCurrentUser = false;

    /**
     * Creates a new setting.
     *
     * @param string $name    The name of the setting, only alnum characters are allowed. For instance `refreshInterval`
     * @param string $title   The title of the setting which will be visible to the user. For instance `Refresh Interval`
     */
    public function __construct($name, $title)
    {
        $this->key   = $name;
        $this->name  = $name;
        $this->title = $title;
    }

    public function getName()
    {
        return $this->name;
    }

    public function canBeDisplayedForCurrentUser()
    {
        return $this->displayedForCurrentUser;
    }

    /**
     * Returns the key under which property name the setting will be stored.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    public function getDefaultType($field)
    {
        $defaultTypes = array(
            Settings::FIELD_TEXT          => Settings::TYPE_STRING,
            Settings::FIELD_TEXTAREA      => Settings::TYPE_STRING,
            Settings::FIELD_PASSWORD      => Settings::TYPE_STRING,
            Settings::FIELD_CHECKBOX      => Settings::TYPE_BOOL,
            Settings::FIELD_MULTI_SELECT  => Settings::TYPE_ARRAY,
            Settings::FIELD_SINGLE_SELECT => Settings::TYPE_STRING,
        );

        return $defaultTypes[$field];
    }

    public function getDefaultField($type)
    {
        $defaultFields = array(
            Settings::TYPE_INT    => Settings::FIELD_TEXT,
            Settings::TYPE_FLOAT  => Settings::FIELD_TEXT,
            Settings::TYPE_STRING => Settings::FIELD_TEXT,
            Settings::TYPE_BOOL   => Settings::FIELD_CHECKBOX,
            Settings::TYPE_ARRAY  => Settings::FIELD_MULTI_SELECT,
        );

        return $defaultFields[$type];
    }
}
