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

    protected $settings = [
        'affiliation_mapping' => [
            'type' => 'string',
            'label' => 'SAML attribute used as affiliation',
            'default' => 'eduPersonPrimaryAffiliation',
        ]
    ];

    public function init()
    {
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('beforeSurveyPage');
        $this->subscribe('afterSurveyComplete');
    }

    public function beforeSurveySettings()
    {
        $event = $this->event;

        $event->set('surveysettings.' . $this->id, [
            'name' => get_class($this),
            'settings' => [
                'SAML_affiliation_permit_enabled' => [
                    'type' => 'checkbox',
                    'label' => 'Enabled',
                    'help' => 'Enable the plugin for this survey',
                    'default' => false,
                    'current' => $this->get('SAML_affiliation_permit_enabled', 'Survey', $event->get('survey')),
                ],
                'allowed_affiliation' => [
                    'type' => 'string',
                    'label' => 'Allowed Affiliations',
                    'help' => 'Comma seperated, without spaces',
                    'default' => 'faculty,student',
                    'current' => $this->get('allowed_affiliation', 'Survey', $event->get('survey')),
                ]
            ]
        ]);
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

    public function getAffiliation()
    {
        $AuthSAML = $this->pluginManager->loadPlugin('AuthSAML');

        $ssp = $AuthSAML->get_saml_instance();

        if (!$ssp->isAuthenticated()) {
            throw new CHttpException(401, gT("We are sorry but you have to login in order to do this."));
        }

        $attributes = $ssp->getAttributes();

        $affiliationField = $this->get('affiliation_mapping', null, null, 'eduPersonPrimaryAffiliation');

        return $attributes[$affiliationField][0];
    }

    public function getSurveyAllowedAffilations()
    {
        $id = $this->getEvent()->get('surveyId');
        $affiliations = $this->get('allowed_affiliation', 'Survey', $id);
        $affiliations = explode(',', $affiliations);
        return $affiliations;
    }

    public function beforeSurveyPage()
    {
        $plugin_enabled = $this->get('SAML_affiliation_permit_enabled', 'Survey', $this->event->get('surveyId'));
        if ($plugin_enabled) {
            $affiliation = $this->getAffiliation();
            $affiliations = $this->getSurveyAllowedAffilations();
            if (!in_array($affiliation, $affiliations)) {
                throw new CHttpException(403, gT("We are sorry but your affiliation is not allowed to participate in this survey."));
            }
        }
    }
}
