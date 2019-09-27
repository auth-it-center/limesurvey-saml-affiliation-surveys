<?php

/**
 * LimeSurvey SAMLAffiliationPermit
 *
 * This plugin forces only SAML users with specific affiliation
 * to participate on selected surveys
 *
 * Author: Panagiotis Karatakis <karatakis@it.auth.gr>
 * Licence: GPL3
 *
 * Sources:
 * https://manual.limesurvey.org/Plugins_-_advanced
 * https://manual.limesurvey.org/Plugin_events
 * https://medium.com/@evently/creating-limesurvey-plugins-adcdf8d7e334
 */

class SAMLAffiliationPermit extends Limesurvey\PluginManager\PluginBase
{
    protected $storage = 'DbStorage';
    static protected $description = 'This plugin forces only SAML users with specific affiliation to participate on selected surveys';
    static protected $name = 'SAMLAffiliationPermit';

    protected $settings = [];
    protected $surveySettings = [];
    protected $filterFields = [];

    public function init()
    {
        $this->addSurveySetting(
            'person_filtering_enabled',
            'checkbox',
            'Enable Plugin',
            'Enable users filtering based on SAML attributes',
            false
        );

        $this->addField('affiliation', 'affiliations', 'eduPersonPrimaryAffiliation', 'faculty,student');
        $this->addField('title', 'titles', 'title', 'whatever,Undergraduate Student');
        $this->addField('department', 'departments', 'authDepartmentId', 'physics,hist');
        $this->addField('status', 'statuses', 'urn:oid:1.3.6.1.4.1.7709.1.1.1.1.32', 'active|retired,whatever');

        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('beforeSurveyPage');
        $this->subscribe('afterSurveyComplete');
        $this->subscribe('getGlobalBasePermissions');
    }

    public function beforeSurveySettings()
    {
        $permission = Permission::model()->hasGlobalPermission('plugin_settings', 'update');
        if ($permission) {
            $event = $this->event;

            $settings = $this->prepareSurveySettings($event);

            $event->set('surveysettings.' . $this->id, [
                'name' => get_class($this),
                'settings' => $settings
            ]);
        }
    }

    public function newSurveySettings()
    {
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value)
        {
            $default = $event->get($name, null, null, isset($this->settings[$name]['default']));
            $this->set($name, $value, 'Survey', $event->get('survey'), $default);
        }
    }

    public function beforeSurveyPage()
    {
        $this->pluginGuard();
    }

    public function afterSurveyComplete()
    {
        $this->pluginGuard();
    }

    public function getGlobalBasePermissions() {
        $this->getEvent()->append('globalBasePermissions',array(
            'plugin_settings' => array(
                'create' => false,
                'update' => true, // allow only update permission to display
                'delete' => false,
                'import' => false,
                'export' => false,
                'read' => false,
                'title' => gT("Save Plugin Settings"),
                'description' => gT("Allow user to save plugin settings"),
                'img' => 'usergroup'
            ),
        ));
    }

    /*
     Helper Functions
    */

    public function addField($field, $plural_field, $attribute, $default)
    {
        $this->filterFields[$plural_field] = $field;
        $this->addGlobalSetting($field . '_mapping', 'string', 'SAML ' . ucfirst($field) . ' attribute', '', $attribute);
        $this->addSurveySetting('allowed_' . $plural_field, 'string', 'Allowed ' . ucfirst($plural_field), 'Comma separated, without spaces.', $default);
    }

    public function addGlobalSetting($field, $type, $label, $help, $default)
    {
        $this->settings[$field] = [
            'type' => $type,
            'label' => $label,
            'help' => $help,
            'default' => $default
        ];
    }

    public function addSurveySetting($field, $type, $label, $help, $default) {
        $this->surveySettings[$field] = [
            'type' => $type,
            'label' => $label,
            'help' => $help,
            'default' => $default
        ];
    }

    public function prepareSurveySettings($event)
    {
        return $this->array_map(function($key, $setting) use ($event) {
            $setting['current'] = $this->get(
                $key,
                'Survey',
                $event->get('survey'),
                $this->surveySettings[$key]['default']
            );
            return $setting;
        }, $this->surveySettings);
    }

    public function pluginGuard() {
        $plugin_enabled = $this->get('person_filtering_enabled', 'Survey', $this->event->get('surveyId'), false);

        if ($plugin_enabled) {
            // If user is admin skip guard
            $AuthSurvey = $this->pluginManager->loadPlugin('SAMLProtect');
            if ($AuthSurvey) {
                $flag = $AuthSurvey->guardBypass($this->event->get('surveyId'));
                if ($flag) {
                    return;
                }
            }

            $allowed_titles = $this->get('allowed_titles', 'Survey', $this->event->get('surveyId'), $this->surveySettings['allowed_titles']['default']);
            $count = count(explode(',', $allowed_titles));

            for ($index = 0; $index < $count; $index++) {
                $flag = false;
                foreach ($this->filterFields as $plural_field => $field) {
                    $flag = $this->checkField($field, $plural_field, $index);
                    if (!$flag) {
                        continue 2;
                    }
                }

                if ($flag) {
                    return;
                }
            }
            throw new CHttpException(403, gT("We are sorry but you do not meet the required affiliation person status to participate in this survey."));
        }
    }

    public function getSAMLValue($field)
    {
        $AuthSAML = $this->pluginManager->loadPlugin('AuthSAML');

        $ssp = $AuthSAML->get_saml_instance();

        $ssp->requireAuth();

        $attributes = $ssp->getAttributes();

        $attribute = $this->get($field . '_mapping', null, null, $this->settings[$field . '_mapping']['default']);

        if (isset($attributes[$attribute][0])) {
            return $attributes[$attribute][0];
        }
        return 'missing';
    }

    public function checkField($field, $field_plural, $index)
    {
        $rules = $this->get('allowed_' . $field_plural, 'Survey', $this->event->get('surveyId'), $this->surveySettings['allowed_' . $field_plural]['default']);
        $rules = explode(',', $rules);

        if (!isset($rules[$index])) {
            throw new CHttpException(500, gT('Rules count does not match.'));
        }

        $value = $this->getSAMLValue($field);

        return $this->checkRules($value, $rules[$index]);
    }

    public function checkRules($value, $rules)
    {
        $rules = explode('|', $rules);

        if (in_array('whatever', $rules) || in_array($value, $rules)) {
            return true;
        }

        return false;
    }

    private function array_map($fn, $array) {
        $keys = array_keys($array);
        $valuesMapped = array_map($fn, $keys, $array);
        return array_combine($keys, $valuesMapped);
    }
}
