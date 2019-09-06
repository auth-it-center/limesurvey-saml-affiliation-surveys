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
            'label' => 'SAML Affiliation Attribute',
            'default' => 'eduPersonPrimaryAffiliation'
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
                    'current' => $this->get('SAML_affiliation_permit_enabled', 'Survey', $event->get('survey'), false),
                ],
                'allowed_affiliation' => [
                    'type' => 'string',
                    'label' => 'Allowed Affiliations',
                    'help' => 'Comma separated, without spaces',
                    'default' => 'faculty,student',
                    'current' => $this->get('allowed_affiliation', 'Survey', $event->get('survey'), 'faculty,student'),
                ],
                'allowed_status_per_affiliation' => [
                    'type' => 'string',
                    'label' => 'Allowed Status per Affiliation',
                    'help' => 'Comma separated, without spaces. One status rule per affiliation. Possible values (whatever, active, inactive, missing)',
                    'default' => 'whatever|active,whatever|active',
                    'current' => $this->get('allowed_status_per_affiliation', 'Survey', $event->get('survey'), 'active,whatever'),
                ],
                'affiliation_mapping_survey' => [
                    'type' => 'string',
                    'label' => 'SAML Affiliation Attribute',
                    'default' => $this->get('affiliation_mapping', null, null, $this->settings['affiliation_mapping']['default']),
                    'current' => $this->get('affiliation_mapping_survey', 'Survey', $event->get('survey'), $this->settings['affiliation_mapping']['default']),
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

        $ssp->requireAuth();

        $attributes = $ssp->getAttributes();

        $affiliationField = $this->get('affiliation_mapping_survey', 'Survey', $this->event->get('surveyId'));

        return $attributes[$affiliationField][0];
    }

    public function getSurveyAllowedAffiliations()
    {
        $id = $this->getEvent()->get('surveyId');
        $affiliations = $this->get('allowed_affiliation', 'Survey', $id);
        $affiliations = explode(',', $affiliations);
        return $affiliations;
    }

    public function getSurveyAllowedPersonStatuses()
    {
        $id = $this->getEvent()->get('surveyId');
        $personStatus = $this->get('allowed_status_per_affiliation', 'Survey', $id);
        $personStatus = explode(',', $personStatus);
        return $personStatus;
    }

    public function beforeSurveyPage()
    {
        $this->pluginGuard();
    }

    public function afterSurveyComplete()
    {
        $this->pluginGuard();
    }

    public function pluginGuard() {
        $plugin_enabled = $this->get('SAML_affiliation_permit_enabled', 'Survey', $this->event->get('surveyId'));
        if ($plugin_enabled) {
            $person_affiliation = $this->getAffiliation();
            $affiliations = $this->getSurveyAllowedAffiliations();
            if (!in_array($person_affiliation, $affiliations)) {
                throw new CHttpException(403, gT("We are sorry but you are not allowed to participate in this survey."));
            }

            $PersonStatus = $this->pluginManager->loadPlugin('SAMLPersonStatus');
            if ($PersonStatus !== null) {
                $person_status = $PersonStatus->getPersonStatus();

                $person_statuses = $this->getSurveyAllowedPersonStatuses();

                if (count($person_statuses) !== count($affiliations)) {
                    throw new CHttpException(500, gT("Affiliation count does not match Status per Affiliation count."));
                }

                array_map(function ($affiliation, $status) use ($PersonStatus, $person_affiliation, $person_status) {
                    if ($affiliation === $person_affiliation && !$PersonStatus->checkPersonStatus($person_status, $status)) {
                        throw new CHttpException(403, gT("We are sorry but you do not meet the required affiliation person status to participate in this survey."));
                    }
                }, $affiliations, $person_statuses);
            }
        }
    }
}
